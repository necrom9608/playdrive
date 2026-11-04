<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Display\DisplaySkin;
use App\Http\Controllers\Controller;
use App\Jobs\TranscodeDisplayVideo;
use App\Models\DisplayVideo;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backoffice → Klantenscherm: skin, teksten en filmpjes voor de standby.
 *
 *   GET    /display-skin                         instellingen + filmpjes
 *   PUT    /display-skin                         {skin, title_line_1, title_line_2, info_text, standby_ticker[], order_ticker[], show_hours, crt}
 *   POST   /display-skin/videos                  {title, zone, original_name, size}   → nieuw filmpje (status uploading)
 *   POST   /display-skin/videos/{id}/chunk       multipart {offset, chunk}            → stuk toevoegen
 *   POST   /display-skin/videos/{id}/complete                                        → verwerken (ffmpeg)
 *   POST   /display-skin/videos/{id}/retry                                           → opnieuw verwerken
 *   PUT    /display-skin/videos/{id}             {title, zone, is_active}
 *   DELETE /display-skin/videos/{id}
 *   POST   /display-skin/videos/reorder          {ids: [...]}
 *   GET    /display-skin/videos/{id}/file                                            → voorbeeld in de backoffice
 *
 * Uploaden gebeurt in stukken van < 1 MB, zodat de standaardlimieten van nginx/PHP volstaan.
 */
class DisplaySkinController extends Controller
{
    private const MAX_BYTES = 600 * 1024 * 1024;
    private const MAX_CHUNK = 4 * 1024 * 1024;

    public function show(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        return response()->json($this->state($currentTenant->id()));
    }

    public function update(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'skin'             => ['required', Rule::in(array_keys(DisplaySkin::SKINS))],
            'title_line_1'     => ['nullable', 'string', 'max:60'],
            'title_line_2'     => ['nullable', 'string', 'max:60'],
            'info_text'        => ['nullable', 'string', 'max:120'],
            'standby_ticker'   => ['nullable', 'array', 'max:20'],
            'standby_ticker.*' => ['nullable', 'string', 'max:60'],
            'order_ticker'     => ['nullable', 'array', 'max:20'],
            'order_ticker.*'   => ['nullable', 'string', 'max:60'],
            'show_hours'       => ['required', 'boolean'],
            'crt'              => ['required', Rule::in(DisplaySkin::CRT)],
        ]);

        $clean = fn (?array $list) => array_values(array_filter(array_map(fn ($v) => trim((string) $v), $list ?? []), 'strlen'));

        $settings = DisplaySkin::settingsFor($currentTenant->id());
        $settings->fill([
            'skin'           => $data['skin'],
            'title_line_1'   => $this->nullIfEmpty($data['title_line_1'] ?? null),
            'title_line_2'   => $this->nullIfEmpty($data['title_line_2'] ?? null),
            'info_text'      => $this->nullIfEmpty($data['info_text'] ?? null),
            'standby_ticker' => $clean($data['standby_ticker'] ?? null),
            'order_ticker'   => $clean($data['order_ticker'] ?? null),
            'show_hours'     => (bool) $data['show_hours'],
            'crt'            => $data['crt'],
        ])->save();

        return response()->json($this->state($currentTenant->id()));
    }

    public function storeVideo(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $data = $request->validate([
            'title'         => ['nullable', 'string', 'max:60'],
            'zone'          => ['nullable', Rule::in(DisplayVideo::ZONES)],
            'original_name' => ['required', 'string', 'max:255'],
            'size'          => ['required', 'integer', 'min:1', 'max:'.self::MAX_BYTES],
        ]);

        $ext = strtolower(pathinfo($data['original_name'], PATHINFO_EXTENSION));
        abort_unless(in_array($ext, ['mp4', 'mov', 'm4v', 'webm', 'mkv'], true), 422, 'Enkel videobestanden (mp4, mov, m4v, webm, mkv).');

        $video = DisplayVideo::query()->create([
            'tenant_id'     => $tenantId,
            'title'         => $this->nullIfEmpty($data['title'] ?? null),
            'zone'          => $data['zone'] ?? null,
            'status'        => DisplayVideo::STATUS_UPLOADING,
            'original_name' => $data['original_name'],
            'upload_size'   => (int) $data['size'],
            'is_active'     => true,
            'sort_order'    => (int) DisplayVideo::query()->where('tenant_id', $tenantId)->max('sort_order') + 1,
        ]);

        $video->update(['upload_path' => $video->folder().'/upload.'.$ext]);
        Storage::disk('local')->makeDirectory($video->folder());
        Storage::disk('local')->put($video->upload_path, '');

        return response()->json(['video' => $this->videoArray($video), 'chunk_size' => 768 * 1024], 201);
    }

    public function chunk(Request $request, CurrentTenant $currentTenant, int $video): JsonResponse
    {
        $model = $this->find($currentTenant, $video);
        abort_unless($model->status === DisplayVideo::STATUS_UPLOADING && $model->upload_path, 409, 'Dit filmpje wordt niet meer geüpload.');

        $request->validate([
            'offset' => ['required', 'integer', 'min:0'],
            'chunk'  => ['required', 'file', 'max:'.(int) (self::MAX_CHUNK / 1024)],
        ]);

        $path = Storage::disk('local')->path($model->upload_path);
        clearstatcache(true, $path);
        $current = is_file($path) ? filesize($path) : 0;
        $offset = (int) $request->input('offset');
        $file = $request->file('chunk');
        $length = (int) $file->getSize();

        // Stuk al ontvangen (opnieuw verstuurd na een time-out): gewoon bevestigen.
        if ($offset + $length <= $current) {
            return response()->json(['received' => $current]);
        }
        abort_unless($offset === $current, 409, "Verkeerd stuk: server heeft {$current} bytes, stuk begint op {$offset}.");
        abort_if($current + $length > $model->upload_size, 422, 'Bestand is groter dan aangekondigd.');

        $in = fopen($file->getRealPath(), 'rb');
        $out = fopen($path, 'ab');
        abort_unless($in && $out, 500, 'Kon het stuk niet wegschrijven.');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        clearstatcache(true, $path);

        return response()->json(['received' => filesize($path)]);
    }

    public function complete(CurrentTenant $currentTenant, int $video): JsonResponse
    {
        $model = $this->find($currentTenant, $video);
        abort_unless($model->status === DisplayVideo::STATUS_UPLOADING && $model->upload_path, 409, 'Dit filmpje wordt niet meer geüpload.');

        $size = (int) Storage::disk('local')->size($model->upload_path);
        abort_unless($size === (int) $model->upload_size, 422, "Upload onvolledig ({$size} van {$model->upload_size} bytes).");

        $model->update(['status' => DisplayVideo::STATUS_PROCESSING, 'note' => null]);
        TranscodeDisplayVideo::dispatchAfterResponse($model->id);

        return response()->json(['video' => $this->videoArray($model->fresh())]);
    }

    public function retry(CurrentTenant $currentTenant, int $video): JsonResponse
    {
        $model = $this->find($currentTenant, $video);
        abort_unless($model->upload_path && Storage::disk('local')->exists($model->upload_path), 409, 'Het originele bestand is er niet meer: upload het filmpje opnieuw.');
        abort_if($model->status === DisplayVideo::STATUS_UPLOADING, 409, 'Upload is nog bezig.');

        $model->update(['status' => DisplayVideo::STATUS_PROCESSING, 'note' => null]);
        TranscodeDisplayVideo::dispatchAfterResponse($model->id);

        return response()->json(['video' => $this->videoArray($model->fresh())]);
    }

    public function updateVideo(Request $request, CurrentTenant $currentTenant, int $video): JsonResponse
    {
        $model = $this->find($currentTenant, $video);

        $data = $request->validate([
            'title'     => ['nullable', 'string', 'max:60'],
            'zone'      => ['nullable', Rule::in(DisplayVideo::ZONES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model->update([
            'title'     => $this->nullIfEmpty($data['title'] ?? null),
            'zone'      => $data['zone'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $model->is_active,
        ]);

        return response()->json(['video' => $this->videoArray($model)]);
    }

    public function destroyVideo(CurrentTenant $currentTenant, int $video): JsonResponse
    {
        $model = $this->find($currentTenant, $video);
        Storage::disk('local')->deleteDirectory($model->folder());
        $model->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        DB::transaction(function () use ($data, $currentTenant) {
            foreach (array_values($data['ids']) as $i => $id) {
                DisplayVideo::query()->where('tenant_id', $currentTenant->id())->whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json($this->state($currentTenant->id()));
    }

    public function file(CurrentTenant $currentTenant, int $video): BinaryFileResponse
    {
        $model = $this->find($currentTenant, $video);
        abort_unless($model->isPlayable(), 404);

        return response()->file(Storage::disk('local')->path($model->path), [
            'Content-Type'  => 'video/mp4',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // ------------------------------------------------------------------

    private function state(int $tenantId): array
    {
        $settings = DisplaySkin::settingsFor($tenantId);

        return [
            'settings' => [
                'skin'           => $settings->skin ?: 'standaard',
                'title_line_1'   => $settings->title_line_1,
                'title_line_2'   => $settings->title_line_2,
                'info_text'      => $settings->info_text,
                'standby_ticker' => $settings->standby_ticker ?? [],
                'order_ticker'   => $settings->order_ticker ?? [],
                'show_hours'     => (bool) ($settings->show_hours ?? true),
                'crt'            => $settings->crt ?: 'zacht',
            ],
            'skins'    => collect(DisplaySkin::SKINS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'defaults' => DisplaySkin::defaults(),
            'zones'    => DisplayVideo::ZONES,
            'videos'   => DisplayVideo::query()->where('tenant_id', $tenantId)
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (DisplayVideo $v) => $this->videoArray($v))->values(),
        ];
    }

    private function videoArray(DisplayVideo $v): array
    {
        return [
            ...$v->toPublicArray(),
            'preview_url' => $v->status === DisplayVideo::STATUS_READY
                ? url("/api/backoffice/display-skin/videos/{$v->id}/file").'?v='.substr(md5((string) $v->path), 0, 10)
                : null,
        ];
    }

    private function find(CurrentTenant $currentTenant, int $id): DisplayVideo
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        return DisplayVideo::query()->where('tenant_id', $currentTenant->id())->findOrFail($id);
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
