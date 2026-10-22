<?php

namespace App\Domain\MailAssistant;

use App\Models\MailAssistantMessage;
use App\Models\MailAssistantSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Laat Claude een mail classificeren en een antwoord voorstellen op basis van de Playdrive-gegevens.
 * Het resultaat is altijd een concept: versturen gebeurt pas na goedkeuring in de backoffice.
 */
class ReplyDrafter
{
    private const MAX_MAIL_CHARS = 8000;

    public function __construct(
        private readonly ContextBuilder $contextBuilder,
    ) {
    }

    public function draft(Tenant $tenant, MailAssistantSetting $settings, MailAssistantMessage $message): MailAssistantMessage
    {
        $apiKey = (string) config('services.anthropic.key');
        if ($apiKey === '') {
            throw new RuntimeException('Geen Claude API-sleutel ingesteld (ANTHROPIC_API_KEY in .env).');
        }

        $built = $this->contextBuilder->build($tenant, $settings, $message);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(90)
            ->retry(2, 2000, throw: false)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('services.anthropic.model', 'claude-sonnet-4-5'),
                'max_tokens' => 2000,
                'system' => $this->systemPrompt($tenant, $settings),
                'messages' => [[
                    'role' => 'user',
                    'content' => $this->userPrompt($built['context'], $message),
                ]],
            ]);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? ('HTTP ' . $response->status());
            throw new RuntimeException('Claude API: ' . $error);
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        $result = $this->decode($text);

        $category = MailCategories::exists($result['category'] ?? null) ? $result['category'] : 'other';
        $needsHuman = (bool) ($result['needs_human'] ?? false) || MailCategories::isLocked($category);
        $reply = trim((string) ($result['reply'] ?? ''));

        if ($reply !== '' && filled($settings->signature)) {
            $reply .= "\n\n" . trim($settings->signature);
        }

        $warnings = array_values(array_filter(array_map('strval', (array) ($result['warnings'] ?? []))));
        $sources = collect((array) ($result['used_sources'] ?? []))
            ->filter(fn ($s) => is_array($s) && filled($s['label'] ?? null))
            ->map(fn ($s) => [
                'type' => (string) ($s['type'] ?? 'other'),
                'label' => (string) $s['label'],
                'detail' => isset($s['detail']) ? (string) $s['detail'] : null,
            ])
            ->values()
            ->all();

        $message->fill([
            'category' => $category,
            'confidence' => max(0, min(100, (int) ($result['confidence'] ?? 0))),
            'language' => substr((string) ($result['language'] ?? 'nl'), 0, 5),
            'summary' => isset($result['summary']) ? mb_substr((string) $result['summary'], 0, 500) : null,
            'needs_human' => $needsHuman,
            'needs_human_reason' => $result['needs_human_reason'] ?? (MailCategories::isLocked($category)
                ? MailCategories::label($category) . ' wordt altijd door een medewerker afgehandeld.'
                : null),
            'draft_body' => $reply !== '' ? $reply : null,
            'draft_original' => $reply !== '' ? $reply : null,
            'draft_sources' => $sources,
            'draft_warnings' => $warnings,
            'drafted_at' => now(),
            'draft_tokens' => (int) $response->json('usage.input_tokens', 0) + (int) $response->json('usage.output_tokens', 0),
            'account_id' => $built['account_id'],
            'status' => $needsHuman ? MailAssistantMessage::STATUS_MANUAL : MailAssistantMessage::STATUS_DRAFT,
            'error' => null,
        ]);

        if (($result['is_automated'] ?? false) === true) {
            $message->status = MailAssistantMessage::STATUS_IGNORED;
            $message->ignore_reason = 'Herkend als automatisch bericht';
        }

        $message->save();

        return $message;
    }

    private function systemPrompt(Tenant $tenant, MailAssistantSetting $settings): string
    {
        $tone = match ($settings->tone) {
            'formal' => 'Spreek de klant aan met "u" (beleefd en formeel).',
            'follow' => 'Volg de aanspreking van de klant: gebruikt de klant "u", dan ook "u"; anders "je/jij".',
            default => 'Spreek de klant aan met "je/jij" (vriendelijk en informeel, maar correct).',
        };

        $language = $settings->language === 'nl'
            ? 'Antwoord altijd in het Nederlands (Vlaams, standaardtaal).'
            : 'Antwoord in de taal waarin de klant schrijft (bij Nederlands: Vlaams, standaardtaal).';

        $categories = collect(MailCategories::ALL)
            ->map(fn ($c, $key) => "- {$key}: {$c['label']} ({$c['hint']})")
            ->implode("\n");

        $extra = filled($settings->instructions)
            ? "\n\nExtra richtlijnen van de zaak:\n" . trim($settings->instructions)
            : '';

        $name = $tenant->display_name;

        return <<<PROMPT
Je beantwoordt klantmails namens {$name}, een vrijetijdszaak. Je schrijft een CONCEPT dat een medewerker nakijkt voor het vertrekt.

Regels:
- Gebruik uitsluitend feiten uit het blok CONTEXT. Verzin nooit prijzen, uren, formules, regels of beloftes.
- Ontbreekt informatie, schrijf dan een duidelijke plaatshouder tussen vierkante haken (bv. [PRIJS VERJAARDAGSFORMULE]) en vermeld dat in "warnings".
- Bevestig nooit een reservatie of beschikbaarheid op een datum: dat kan niet gecontroleerd worden. Vraag wel de nodige gegevens (datum, uur, aantal personen) en zeg dat het team de beschikbaarheid bevestigt.
- Bij klachten, annuleringen en terugbetalingen: zet needs_human op true, wees empathisch, maak geen toezeggingen over geld of uitzonderingen.
- Schrijf platte tekst (geen markdown), kort en concreet, met een begroeting met de voornaam als die gekend is. Geen handtekening: die wordt automatisch toegevoegd.
- {$tone}
- {$language}
- Is de mail een automatisch bericht, nieuwsbrief, spam of factuur van een leverancier, zet dan is_automated op true en laat reply leeg.

Categorieën:
{$categories}

Antwoord met exact één JSON-object, zonder uitleg of markdown:
{
  "category": "<een van de categorie-sleutels>",
  "confidence": <0-100, hoe zeker je bent dat het antwoord klopt en volledig is>,
  "language": "<nl|fr|en|de>",
  "is_automated": <true|false>,
  "needs_human": <true|false>,
  "needs_human_reason": "<korte reden of null>",
  "summary": "<samenvatting van de vraag in max. 15 woorden>",
  "reply": "<het voorgestelde antwoord>",
  "used_sources": [{"type": "<opening_hours|products|options|knowledge|account|voucher|tenant|history>", "label": "<korte titel>", "detail": "<wat je gebruikte>"}],
  "warnings": ["<waar de medewerker op moet letten>"]
}{$extra}
PROMPT;
    }

    private function userPrompt(array $context, MailAssistantMessage $message): string
    {
        $body = MimeMessage::stripQuoted((string) $message->body_text);
        if (mb_strlen($body) > self::MAX_MAIL_CHARS) {
            $body = mb_substr($body, 0, self::MAX_MAIL_CHARS) . "\n[... ingekort]";
        }

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $from = trim(($message->from_name ? $message->from_name . ' ' : '') . '<' . $message->from_email . '>');
        $date = $message->received_at?->timezone('Europe/Brussels')->format('Y-m-d H:i') ?? '?';

        return <<<PROMPT
CONTEXT (gegevens uit Playdrive):
{$contextJson}

MAIL VAN DE KLANT
Van: {$from}
Ontvangen: {$date}
Onderwerp: {$message->subject}

{$body}
PROMPT;
    }

    private function decode(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) {
            throw new RuntimeException('Onverwacht antwoord van Claude (geen JSON).');
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            throw new RuntimeException('Onverwacht antwoord van Claude (ongeldige JSON).');
        }

        return $data;
    }
}
