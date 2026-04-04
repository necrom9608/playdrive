<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BadgeTemplate;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BadgeTemplateController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $templates = BadgeTemplate::query()
            ->where('tenant_id', $currentTenant->id())
            ->orderByDesc('is_default')
            ->orderBy('template_type')
            ->orderBy('name')
            ->get()
            ->map(fn (BadgeTemplate $template) => $this->mapTemplate($template))
            ->values();

        return response()->json($templates);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $this->validatedData($request);

        $template = DB::transaction(function () use ($data, $currentTenant) {
            if (! empty($data['is_default'])) {
                BadgeTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('template_type', $data['template_type'])
                    ->update(['is_default' => false]);
            }

            return BadgeTemplate::query()->create([
                'tenant_id' => $currentTenant->id(),
                'name' => trim($data['name']),
                'template_type' => $data['template_type'],
                'description' => $this->nullableString($data['description'] ?? null),
                'is_default' => (bool) ($data['is_default'] ?? false),
                'config_json' => $data['config_json'],
            ]);
        });

        return response()->json($this->mapTemplate($template), 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, BadgeTemplate $badgeTemplate): JsonResponse
    {
        abort_unless((int) $badgeTemplate->tenant_id === (int) $currentTenant->id(), 404);

        $data = $this->validatedData($request);
        $oldPaths = $this->extractManagedImagePaths($badgeTemplate->config_json ?? []);

        DB::transaction(function () use ($badgeTemplate, $data, $currentTenant) {
            if (! empty($data['is_default'])) {
                BadgeTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('template_type', $data['template_type'])
                    ->where('id', '!=', $badgeTemplate->id)
                    ->update(['is_default' => false]);
            }

            $badgeTemplate->fill([
                'name' => trim($data['name']),
                'template_type' => $data['template_type'],
                'description' => $this->nullableString($data['description'] ?? null),
                'is_default' => (bool) ($data['is_default'] ?? false),
                'config_json' => $data['config_json'],
            ]);

            $badgeTemplate->save();
        });

        $newPaths = $this->extractManagedImagePaths($badgeTemplate->fresh()->config_json ?? []);
        $this->deleteMissingManagedPaths($oldPaths, $newPaths);

        return response()->json($this->mapTemplate($badgeTemplate->fresh()));
    }

    public function destroy(CurrentTenant $currentTenant, BadgeTemplate $badgeTemplate): JsonResponse
    {
        abort_unless((int) $badgeTemplate->tenant_id === (int) $currentTenant->id(), 404);

        $paths = $this->extractManagedImagePaths($badgeTemplate->config_json ?? []);

        $badgeTemplate->delete();
        $this->deleteManagedPaths($paths);

        return response()->json([
            'success' => true,
        ]);
    }

    public function uploadMedia(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 500, 'No current tenant resolved.');

        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'kind' => ['nullable', 'string', Rule::in(['background', 'element'])],
        ]);

        $folder = ($data['kind'] ?? 'element') === 'background' ? 'backgrounds' : 'elements';
        $path = $request->file('image')->store("badge-templates/tenant-{$currentTenant->id()}/{$folder}", 'public');

        return response()->json([
            'path' => $path,
            'url' => $this->storagePathToPublicUrl($path),
        ], 201);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_type' => ['required', 'string', Rule::in(['staff', 'member', 'voucher'])],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
            'config_json' => ['required', 'array'],
            'config_json.width' => ['required', 'integer', 'min:600', 'max:3000'],
            'config_json.height' => ['required', 'integer', 'min:300', 'max:2000'],
            'config_json.backgroundColor' => ['nullable', 'string', 'max:32'],
            'config_json.backgroundImageUrl' => ['nullable', 'string', 'max:2048'],
            'config_json.backgroundImagePath' => ['nullable', 'string', 'max:2048'],
            'config_json.backgroundSize' => ['nullable', 'string', 'max:32'],
            'config_json.backgroundPosition' => ['nullable', 'string', 'max:32'],
            'config_json.elements' => ['required', 'array'],
            'config_json.elements.*.id' => ['required', 'string', 'max:120'],
            'config_json.elements.*.type' => ['required', 'string', Rule::in(['text', 'field', 'photo', 'image', 'logo', 'qr', 'shape'])],
            'config_json.elements.*.label' => ['nullable', 'string', 'max:120'],
            'config_json.elements.*.source' => ['nullable', 'string', 'max:120'],
            'config_json.elements.*.text' => ['nullable', 'string', 'max:500'],
            'config_json.elements.*.imageUrl' => ['nullable', 'string', 'max:2048'],
            'config_json.elements.*.imagePath' => ['nullable', 'string', 'max:2048'],
            'config_json.elements.*.fit' => ['nullable', 'string', Rule::in(['cover', 'contain'])],
            'config_json.elements.*.x' => ['required', 'numeric'],
            'config_json.elements.*.y' => ['required', 'numeric'],
            'config_json.elements.*.width' => ['required', 'numeric', 'min:1'],
            'config_json.elements.*.height' => ['required', 'numeric', 'min:1'],
            'config_json.elements.*.fontSize' => ['nullable', 'numeric', 'min:8', 'max:200'],
            'config_json.elements.*.fontWeight' => ['nullable', 'numeric', 'min:100', 'max:900'],
            'config_json.elements.*.color' => ['nullable', 'string', 'max:32'],
            'config_json.elements.*.backgroundColor' => ['nullable', 'string', 'max:32'],
            'config_json.elements.*.borderRadius' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'config_json.elements.*.textAlign' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'config_json.elements.*.opacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'config_json.elements.*.zIndex' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);
    }

    private function mapTemplate(BadgeTemplate $template): array
    {
        $config = $template->config_json ?? [];

        $config['backgroundImagePath'] = $config['backgroundImagePath'] ?? '';
        $config['backgroundImageUrl'] = $this->resolvePublicUrl(
            $config['backgroundImagePath'] ?? null,
            $config['backgroundImageUrl'] ?? null
        );

        $config['elements'] = collect($config['elements'] ?? [])
            ->map(function (array $element) {
                $element['imagePath'] = $element['imagePath'] ?? '';
                $element['imageUrl'] = $this->resolvePublicUrl(
                    $element['imagePath'] ?? null,
                    $element['imageUrl'] ?? null
                );
                $element['fit'] = $element['fit'] ?? (($element['type'] ?? null) === 'logo' ? 'contain' : 'cover');

                return $element;
            })
            ->values()
            ->all();

        return [
            'id' => $template->id,
            'name' => $template->name,
            'template_type' => $template->template_type,
            'description' => $template->description,
            'is_default' => (bool) $template->is_default,
            'config_json' => $config,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    private function resolvePublicUrl(?string $path, ?string $fallbackUrl = null): ?string
    {
        $path = $this->nullableString($path);

        if ($path !== null) {
            return $this->storagePathToPublicUrl($path);
        }

        $fallbackUrl = $this->nullableString($fallbackUrl);

        if ($fallbackUrl === null) {
            return null;
        }

        if (str_starts_with($fallbackUrl, 'http://') || str_starts_with($fallbackUrl, 'https://')) {
            $parsedPath = parse_url($fallbackUrl, PHP_URL_PATH);

            return is_string($parsedPath) && $parsedPath !== ''
                ? $parsedPath
                : $fallbackUrl;
        }

        if (str_starts_with($fallbackUrl, '/storage/')) {
            return $fallbackUrl;
        }

        if (str_starts_with($fallbackUrl, 'storage/')) {
            return '/' . ltrim($fallbackUrl, '/');
        }

        return $fallbackUrl;
    }

    private function storagePathToPublicUrl(string $path): string
    {
        return '/storage/' . ltrim($path, '/');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function extractManagedImagePaths(array $config): array
    {
        $paths = [];

        $backgroundPath = $config['backgroundImagePath'] ?? null;
        if ($this->isManagedBadgePath($backgroundPath)) {
            $paths[] = $backgroundPath;
        }

        foreach (($config['elements'] ?? []) as $element) {
            $imagePath = $element['imagePath'] ?? null;
            if ($this->isManagedBadgePath($imagePath)) {
                $paths[] = $imagePath;
            }
        }

        return array_values(array_unique($paths));
    }

    private function deleteMissingManagedPaths(array $oldPaths, array $newPaths): void
    {
        $pathsToDelete = array_values(array_diff($oldPaths, $newPaths));
        $this->deleteManagedPaths($pathsToDelete);
    }

    private function deleteManagedPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if ($this->isManagedBadgePath($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function isManagedBadgePath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, 'badge-templates/');
    }
}
