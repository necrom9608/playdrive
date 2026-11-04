<?php

namespace App\Jobs;

use App\Models\DisplayVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Maakt van een geüpload filmpje een licht bestand voor het klantenscherm:
 * 720×1280 (staand, bijgesneden indien nodig), H.264, geen geluid, faststart.
 * Een verticaal filmpje van 30 MB wordt zo ±3 MB en speelt overal (ook .mov/HEVC van een iPhone).
 *
 * Wordt na het antwoord van de upload uitgevoerd (dispatchAfterResponse), dus er is geen
 * queue-worker nodig. Zonder ffmpeg op de server blijft het origineel staan (met een notitie).
 * ffmpeg-pad aanpassen kan met FFMPEG_PATH in .env.
 */
class TranscodeDisplayVideo implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 1200;

    public function __construct(public int $videoId)
    {
    }

    public function handle(): void
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $video = DisplayVideo::query()->find($this->videoId);
        if (! $video || ! $video->upload_path) {
            return;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($video->upload_path)) {
            $video->update(['status' => DisplayVideo::STATUS_FAILED, 'note' => 'Geüpload bestand niet gevonden.']);
            return;
        }

        $video->update(['status' => DisplayVideo::STATUS_PROCESSING, 'note' => null]);

        $ffmpeg = $this->ffmpeg();
        $uploadPath = $video->upload_path;
        $input = $disk->path($uploadPath);

        if (! $ffmpeg) {
            // Geen ffmpeg: origineel gebruiken zoals het is.
            $video->update([
                'status'      => DisplayVideo::STATUS_READY,
                'path'        => $video->upload_path,
                'upload_path' => null,
                'size_bytes'  => (int) $disk->size($video->upload_path),
                'note'        => 'Niet verkleind: ffmpeg ontbreekt op de server.',
            ]);
            return;
        }

        $relative = $video->folder().'/'.Str::lower(Str::random(10)).'.mp4';
        $output = $disk->path($relative);
        @mkdir(dirname($output), 0775, true);

        try {
            $result = Process::timeout($this->timeout)->run([
                $ffmpeg, '-hide_banner', '-y', '-i', $input,
                '-an',
                '-vf', 'scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280,fps=30',
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '25', '-pix_fmt', 'yuv420p',
                '-movflags', '+faststart',
                $output,
            ]);
        } catch (Throwable $e) {
            $this->fail($video, $e->getMessage(), $output);
            return;
        }

        if (! $result->successful() || ! is_file($output) || filesize($output) < 1024) {
            $this->fail($video, $this->lastLine($result->errorOutput()), $output);
            return;
        }

        $old = $video->path;
        $video->update([
            'status'           => DisplayVideo::STATUS_READY,
            'path'             => $relative,
            'upload_path'      => null,
            'size_bytes'       => (int) filesize($output),
            'duration_seconds' => $this->duration($result->errorOutput()),
            'note'             => null,
        ]);

        $disk->delete($uploadPath);
        if ($old && $old !== $relative && $old !== $uploadPath) {
            $disk->delete($old);
        }
    }

    private function fail(DisplayVideo $video, string $reason, string $output): void
    {
        @unlink($output);
        Log::warning('Klantenscherm: filmpje verwerken mislukt', ['video' => $video->id, 'reason' => $reason]);
        $video->update([
            'status' => DisplayVideo::STATUS_FAILED,
            'note'   => Str::limit('Verwerken mislukt: '.($reason ?: 'onbekende fout'), 250),
        ]);
    }

    private function ffmpeg(): ?string
    {
        $candidates = array_filter([config('services.ffmpeg.path'), 'ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg']);
        foreach ($candidates as $bin) {
            try {
                if (Process::timeout(10)->run([$bin, '-version'])->successful()) {
                    return $bin;
                }
            } catch (Throwable) {
                // volgende proberen
            }
        }

        return null;
    }

    private function duration(string $log): ?float
    {
        // "Duration: 00:00:16.53" uit de ffmpeg-uitvoer
        if (preg_match('/Duration:\s*(\d+):(\d+):(\d+(?:\.\d+)?)/', $log, $m)) {
            return round(((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (float) $m[3], 2);
        }

        return null;
    }

    private function lastLine(string $text): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));

        return $lines ? end($lines) : '';
    }
}
