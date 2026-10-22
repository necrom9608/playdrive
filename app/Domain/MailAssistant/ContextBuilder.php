<?php

namespace App\Domain\MailAssistant;

use App\Models\Account;
use App\Models\CateringOption;
use App\Models\EventType;
use App\Models\GiftVoucher;
use App\Models\MailAssistantFaq;
use App\Models\MailAssistantMessage;
use App\Models\MailAssistantSetting;
use App\Models\OpeningException;
use App\Models\OpeningHour;
use App\Models\Product;
use App\Models\RegionSeason;
use App\Models\StayOption;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Carbon\CarbonImmutable;

/**
 * Verzamelt de Playdrive-gegevens die de assistent mag gebruiken om een mail te beantwoorden.
 * Alles wordt als compacte tekst/JSON aan het model gegeven; het model mag niets anders gebruiken.
 */
class ContextBuilder
{
    private const OPENING_DAYS_AHEAD = 75;
    private const MAX_PRODUCTS = 200;

    private const WEEKDAYS = [1 => 'ma', 2 => 'di', 3 => 'wo', 4 => 'do', 5 => 'vr', 6 => 'za', 7 => 'zo'];

    /**
     * @return array{context: array, account_id: ?int}
     */
    public function build(Tenant $tenant, MailAssistantSetting $settings, MailAssistantMessage $message): array
    {
        $today = CarbonImmutable::now('Europe/Brussels')->startOfDay();

        $context = [
            'vandaag' => $today->format('Y-m-d') . ' (' . self::WEEKDAYS[$today->isoWeekday()] . ')',
            'zaak' => $this->tenantInfo($tenant),
        ];

        if ($settings->usesSource('opening_hours')) {
            $context['openingsuren'] = $this->openingHours($tenant, $today);
        }

        if ($settings->usesSource('products')) {
            $context['producten_en_prijzen'] = $this->products($tenant);
        }

        if ($settings->usesSource('options')) {
            $context['formules'] = $this->options($tenant);
        }

        $faqs = MailAssistantFaq::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['question', 'answer']);

        if ($faqs->isNotEmpty()) {
            $context['kennisbank'] = $faqs->map(fn ($f) => ['vraag' => $f->question, 'antwoord' => $f->answer])->all();
        }

        $accountId = null;
        if ($settings->usesSource('accounts')) {
            [$accountInfo, $accountId] = $this->account($tenant, $message->replyAddress(), $message->from_email);
            $context['klant'] = $accountInfo;
        }

        if ($settings->usesSource('vouchers')) {
            $vouchers = $this->vouchers($tenant, ($message->subject ?? '') . "\n" . ($message->body_text ?? ''));
            if ($vouchers !== []) {
                $context['vouchers_in_mail'] = $vouchers;
            }
        }

        if ($settings->usesSource('history')) {
            $history = $this->history($tenant, $message);
            if ($history !== []) {
                $context['eerdere_mails_van_deze_afzender'] = $history;
            }
        }

        $context['niet_beschikbaar'] = [
            'Reservatiesysteem: beschikbaarheid op een datum kan NIET gecontroleerd worden.',
        ];

        return ['context' => $context, 'account_id' => $accountId];
    }

    private function tenantInfo(Tenant $tenant): array
    {
        return array_filter([
            'naam' => $tenant->display_name,
            'omschrijving' => $tenant->public_description ? mb_substr(strip_tags($tenant->public_description), 0, 1200) : null,
            'adres' => $tenant->full_address,
            'telefoon' => $tenant->phone,
            'email' => $tenant->email,
            'website' => $tenant->website_url,
        ]);
    }

    /**
     * Openingsuren per dag voor de komende weken (uitzondering > vakantieperiode > regulier),
     * zelfde logica als de reservatiekalender op de website.
     */
    private function openingHours(Tenant $tenant, CarbonImmutable $today): array
    {
        $end = $today->addDays(self::OPENING_DAYS_AHEAD);

        $hours = OpeningHour::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->groupBy('season_key')
            ->map(fn ($rows) => $rows->keyBy('weekday'));

        $seasons = RegionSeason::query()
            ->where('region_code', $tenant->region_code)
            ->whereDate('date_until', '>=', $today->toDateString())
            ->whereDate('date_from', '<=', $end->toDateString())
            ->orderByDesc('priority')
            ->orderBy('date_from')
            ->get();

        $exceptions = OpeningException::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('date', [$today->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($e) => $e->date->format('Y-m-d'));

        $days = [];
        for ($date = $today; $date->lte($end); $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $label = $key . ' ' . self::WEEKDAYS[$date->isoWeekday()];

            if ($exception = $exceptions->get($key)) {
                $days[] = $label . ': ' . $this->formatHours($exception->is_open, $exception->open_from, $exception->open_until)
                    . ($exception->label ? " ({$exception->label})" : ' (uitzondering)');
                continue;
            }

            $season = $seasons->first(fn ($s) => $key >= $s->date_from->format('Y-m-d') && $key <= $s->date_until->format('Y-m-d'));
            $seasonKey = $season?->season_key ?? 'regular';
            $hour = $hours->get($seasonKey)?->get($date->isoWeekday());

            $days[] = $label . ': ' . ($hour ? $this->formatHours($hour->is_open, $hour->open_from, $hour->open_until) : 'gesloten')
                . ($season ? " ({$season->season_name})" : '');
        }

        $regular = [];
        foreach (self::WEEKDAYS as $weekday => $name) {
            $hour = $hours->get('regular')?->get($weekday);
            $regular[] = $name . ': ' . ($hour ? $this->formatHours($hour->is_open, $hour->open_from, $hour->open_until) : 'gesloten');
        }

        return [
            'regulier_per_weekdag' => $regular,
            'per_dag_komende_weken' => $days,
            'opmerking' => 'Voor datums na ' . $end->format('Y-m-d') . ' zijn de uren niet opgenomen.',
        ];
    }

    private function formatHours(bool $isOpen, ?string $from, ?string $until): string
    {
        if (! $isOpen) {
            return 'gesloten';
        }

        $from = $from ? substr($from, 0, 5) : '?';
        $until = $until ? substr($until, 0, 5) : '?';

        return "open {$from}–{$until}";
    }

    private function products(Tenant $tenant): array
    {
        return Product::query()
            ->with('category:id,name,is_active')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('product_category_id')
            ->orderBy('sort_order')
            ->limit(self::MAX_PRODUCTS)
            ->get()
            ->filter(fn (Product $p) => $p->category === null || $p->category->is_active)
            ->groupBy(fn (Product $p) => $p->category?->name ?? 'Overige')
            ->map(fn ($items) => $items->map(fn (Product $p) => array_filter([
                'naam' => $p->name,
                'prijs_incl_btw' => $p->price_incl_vat !== null ? '€ ' . number_format((float) $p->price_incl_vat, 2, ',', '.') : null,
                'omschrijving' => $p->description ? mb_substr(strip_tags($p->description), 0, 300) : null,
            ]))->values()->all())
            ->all();
    }

    private function options(Tenant $tenant): array
    {
        $names = fn ($query) => $query->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order');

        return array_filter([
            'event_types' => $names(EventType::query())->pluck('name')->all(),
            'verblijfsopties' => $names(StayOption::query())->get()->map(fn (StayOption $o) => $o->name
                . ($o->duration_minutes ? ' (' . $o->duration_minutes . ' min)' : ''))->all(),
            'cateringopties' => $names(CateringOption::query())->pluck('name')->all(),
        ]);
    }

    /** @return array{0: array, 1: ?int} */
    private function account(Tenant $tenant, string $replyAddress, string $fromEmail): array
    {
        $emails = array_values(array_unique(array_filter([strtolower($replyAddress), strtolower($fromEmail)])));
        $account = Account::query()->whereIn('email', $emails)->first();

        if (! $account) {
            return [['gekend' => false, 'opmerking' => 'Geen account gevonden voor dit e-mailadres.'], null];
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('account_id', $account->id)
            ->latest('id')
            ->first();

        $info = array_filter([
            'gekend' => true,
            'voornaam' => $account->first_name,
            'naam' => $account->full_name,
            'lid_bij_deze_zaak' => $membership ? 'ja' : 'nee',
            'lidmaatschap' => $membership?->membership_type,
            'lidmaatschap_actief' => $membership ? ($membership->is_active ? 'ja' : 'nee') : null,
            'lidmaatschap_tot' => $membership?->membership_ends_at?->format('Y-m-d'),
        ], fn ($v) => $v !== null && $v !== '');

        return [$info, $account->id];
    }

    /** Vouchers waarvan de code letterlijk in de mail staat. */
    private function vouchers(Tenant $tenant, string $text): array
    {
        preg_match_all('/\b[A-Z0-9][A-Z0-9-]{4,24}[A-Z0-9]\b/i', $text, $matches);

        $candidates = collect($matches[0] ?? [])
            ->filter(fn ($c) => preg_match('/\d/', $c))
            ->map(fn ($c) => strtoupper($c))
            ->unique()
            ->take(10)
            ->values();

        if ($candidates->isEmpty()) {
            return [];
        }

        $statuses = GiftVoucher::statusOptions();

        return GiftVoucher::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('code', $candidates->all())
            ->limit(5)
            ->get()
            ->map(fn (GiftVoucher $v) => array_filter([
                'code' => $v->code,
                'status' => $statuses[$v->status] ?? $v->status,
                'resterend_bedrag' => $v->amount_remaining !== null ? '€ ' . number_format((float) $v->amount_remaining, 2, ',', '.') : null,
                'geldig_tot' => $v->expires_at?->format('Y-m-d'),
                'vervallen' => $v->expires_at && $v->expires_at->isPast() ? 'ja' : null,
            ]))
            ->values()
            ->all();
    }

    private function history(Tenant $tenant, MailAssistantMessage $message): array
    {
        return MailAssistantMessage::query()
            ->where('tenant_id', $tenant->id)
            ->where('from_email', $message->from_email)
            ->where('id', '!=', $message->id)
            ->latest('received_at')
            ->limit(3)
            ->get()
            ->map(fn (MailAssistantMessage $m) => array_filter([
                'datum' => $m->received_at?->format('Y-m-d'),
                'onderwerp' => $m->subject,
                'samenvatting' => $m->summary,
                'ons_antwoord' => $m->sent_body ? mb_substr($m->sent_body, 0, 600) : null,
            ]))
            ->values()
            ->all();
    }
}
