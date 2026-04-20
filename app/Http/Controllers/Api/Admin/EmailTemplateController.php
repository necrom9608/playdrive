<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    /**
     * The default (built-in) template definitions.
     * Each entry has: key, label, description, subject, body, variables.
     */
    private function defaults(): array
    {
        return [
            [
                'key'         => 'member-invite',
                'label'       => 'Liduitnodiging',
                'description' => 'Verstuurd wanneer een bestaand lid wordt uitgenodigd om zijn account te activeren.',
                'subject'     => 'Activeer je PlayDrive account',
                'variables'   => ['first_name', 'tenant_name', 'reset_url'],
                'body'        => $this->readBladeBody('emails.member-invite'),
            ],
            [
                'key'         => 'member-lifecycle-confirmation',
                'label'       => 'Lidmaatschap bevestiging',
                'description' => 'Verstuurd bij het starten of verlengen van een lidmaatschap.',
                'subject'     => 'Je abonnement is geregistreerd',
                'variables'   => ['first_name', 'last_name', 'membership_started_at', 'membership_expires_at', 'tenant_name'],
                'body'        => $this->readBladeBody('emails.member-lifecycle'),
            ],
            [
                'key'         => 'member-lifecycle-expiring',
                'label'       => 'Lidmaatschap vervalt binnenkort',
                'description' => 'Verstuurd wanneer een lidmaatschap op het punt staat te vervallen.',
                'subject'     => 'Je abonnement vervalt binnenkort',
                'variables'   => ['first_name', 'last_name', 'membership_expires_at', 'tenant_name'],
                'body'        => $this->readBladeBody('emails.member-lifecycle'),
            ],
            [
                'key'         => 'member-lifecycle-expired',
                'label'       => 'Lidmaatschap verlopen',
                'description' => 'Verstuurd wanneer een lidmaatschap is verlopen.',
                'subject'     => 'Je abonnement is verlopen',
                'variables'   => ['first_name', 'last_name', 'membership_expires_at', 'tenant_name'],
                'body'        => $this->readBladeBody('emails.member-lifecycle'),
            ],
            [
                'key'         => 'receipt',
                'label'       => 'Kassabon',
                'description' => 'Verstuurd na een betaling als digitale kassabon.',
                'subject'     => 'Je kassabon van {{tenant_name}}',
                'variables'   => ['tenant_name', 'receipt_date', 'total'],
                'body'        => '(Kassabon wordt automatisch gegenereerd vanuit de order-data.)',
            ],
        ];
    }

    public function index(): JsonResponse
    {
        $overrides = $this->loadOverrides();

        $templates = collect($this->defaults())->map(function (array $tpl) use ($overrides) {
            $override = $overrides[$tpl['key']] ?? null;

            return [
                'key'           => $tpl['key'],
                'label'         => $tpl['label'],
                'description'   => $tpl['description'],
                'variables'     => $tpl['variables'],
                'subject'       => $override['subject'] ?? $tpl['subject'],
                'body'          => $override['body'] ?? $tpl['body'],
                'is_customized' => $override !== null,
            ];
        })->values()->all();

        return response()->json(['templates' => $templates]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $this->findDefault($key);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:500'],
            'body'    => ['required', 'string'],
        ]);

        $overrides        = $this->loadOverrides();
        $overrides[$key]  = ['subject' => $data['subject'], 'body' => $data['body']];
        $this->saveOverrides($overrides);

        return response()->json(['ok' => true]);
    }

    public function reset(string $key): JsonResponse
    {
        $this->findDefault($key);

        $overrides = $this->loadOverrides();
        unset($overrides[$key]);
        $this->saveOverrides($overrides);

        return response()->json(['ok' => true]);
    }

    public function preview(Request $request, string $key): JsonResponse
    {
        $default  = $this->findDefault($key);
        $overrides = $this->loadOverrides();
        $current  = $overrides[$key] ?? $default;

        $data = $request->validate([
            'subject' => ['nullable', 'string'],
            'body'    => ['nullable', 'string'],
        ]);

        $subject = $data['subject'] ?? $current['subject'];
        $body    = $data['body']    ?? $current['body'];

        // Replace variable placeholders with sample values for the preview
        $sampleValues = [
            'first_name'            => 'Jan',
            'last_name'             => 'Peeters',
            'tenant_name'           => 'Game-Inn',
            'reset_url'             => 'https://example.com/reset?token=preview',
            'membership_started_at' => date('d/m/Y'),
            'membership_expires_at' => date('d/m/Y', strtotime('+1 year')),
            'receipt_date'          => date('d/m/Y'),
            'total'                 => '€ 25,00',
        ];

        $previewBody = $body;
        foreach ($sampleValues as $var => $val) {
            $previewBody = str_replace('{{' . $var . '}}', $val, $previewBody);
        }

        $html = $this->wrapInEmailLayout($subject, $previewBody);

        return response()->json(['html' => $html]);
    }

    // -------------------------------------------------------------------------

    private function findDefault(string $key): array
    {
        $found = collect($this->defaults())->firstWhere('key', $key);

        if (! $found) {
            abort(404, 'E-mailtemplate niet gevonden.');
        }

        return $found;
    }

    private function storagePath(): string
    {
        return storage_path('app/email-template-overrides.json');
    }

    private function loadOverrides(): array
    {
        $path = $this->storagePath();

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function saveOverrides(array $overrides): void
    {
        file_put_contents($this->storagePath(), json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function readBladeBody(string $view): string
    {
        $path = resource_path('views/' . str_replace('.', '/', $view) . '.blade.php');

        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return '';
    }

    private function wrapInEmailLayout(string $subject, string $body): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$subject}</title>
<style>
  body { margin: 0; padding: 0; background: #f1f5f9; font-family: ui-sans-serif, system-ui, sans-serif; }
  .wrapper { padding: 40px 16px; }
  .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); padding: 36px 40px; text-align: center; }
  .header-title { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.1em; margin: 0; }
  .header-sub { color: #94a3b8; font-size: 13px; margin: 6px 0 0; }
  .body { padding: 36px 40px; font-size: 15px; line-height: 1.7; color: #475569; }
  .footer { padding: 20px 40px 32px; text-align: center; font-size: 12px; color: #94a3b8; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <p class="header-title">PLAYDRIVE</p>
      <p class="header-sub">Voorbeeld e-mail</p>
    </div>
    <div class="body">{$body}</div>
    <div class="footer">© PlayDrive · Dit is een voorbeeld.</div>
  </div>
</div>
</body>
</html>
HTML;
    }
}
