<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\BookingFormConfig;
use App\Models\BookingFormEventTypeConfig;
use App\Models\BookingFormStayOptionConfig;
use App\Models\CateringOption;
use App\Models\EventType;
use App\Models\OpeningException;
use App\Models\OpeningHour;
use App\Models\RegionSeason;
use App\Models\StayOption;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingFormSetupController extends Controller
{
    /**
     * Geeft alle data terug die het reservatieformulier nodig heeft om
     * zichzelf op te bouwen voor een specifieke tenant.
     *
     * Publiek toegankelijk — geen authenticatie vereist.
     * Tenant wordt geresolveerd via ?tenant=slug of X-Tenant header.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        abort_unless($tenant, 404, 'Venue niet gevonden.');

        // Formulier-config (of lege defaults als nog niet geconfigureerd)
        $config = BookingFormConfig::query()
            ->firstOrNew(['tenant_id' => $tenant->id]);

        abort_unless((bool) $config->is_active || ! $config->exists, 403, 'Reservatieformulier is niet actief.');

        // Event-types met hun form-config
        $eventTypes = EventType::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $etConfigs = BookingFormEventTypeConfig::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('event_type_id');

        // Stay-opties met hun form-config
        $stayOptions = StayOption::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $soConfigs = BookingFormStayOptionConfig::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('stay_option_id');

        // Catering-opties (voor naam/emoji lookup in het formulier)
        $cateringOptions = CateringOption::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'name'  => $c->name,
                'emoji' => $c->emoji,
            ]);

        // Alle openingsuren (alle seizoenen)
        $openingHours = OpeningHour::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('season_key')
            ->orderBy('weekday')
            ->get()
            ->map(fn ($h) => [
                'season_key' => $h->season_key,
                'weekday'    => $h->weekday,
                'is_open'    => $h->is_open,
                'open_from'  => $h->open_from,
                'open_until' => $h->open_until,
            ]);

        // Regio-seizoenen voor datum→seizoen mapping
        $seasons = RegionSeason::query()
            ->where('region_code', $tenant->region_code)
            ->orderByDesc('priority')
            ->orderBy('date_from')
            ->get()
            ->map(fn ($s) => [
                'season_key' => $s->season_key,
                'date_from'  => $s->date_from?->format('Y-m-d'),
                'date_until' => $s->date_until?->format('Y-m-d'),
                'priority'   => $s->priority,
            ]);

        // Uitzonderingen (komende 12 maanden)
        $exceptions = OpeningException::query()
            ->where('tenant_id', $tenant->id)
            ->where('date', '>=', now()->toDateString())
            ->where('date', '<=', now()->addMonths(12)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn ($e) => [
                'date'       => $e->date?->format('Y-m-d'),
                'is_open'    => $e->is_open,
                'open_from'  => $e->open_from,
                'open_until' => $e->open_until,
                'label'      => $e->label,
            ]);

        return response()->json([
            'config' => [
                'is_active'                     => true,
                'show_participant_children'     => (bool) ($config->show_participant_children ?? true),
                'show_participant_adults'       => (bool) ($config->show_participant_adults ?? true),
                'show_participant_supervisors'  => (bool) ($config->show_participant_supervisors ?? false),
                'outside_hours_warning_enabled' => (bool) ($config->outside_hours_warning_enabled ?? true),
            ],
            'event_types' => $eventTypes
                ->map(fn ($et) => $this->mapEventType($et, $etConfigs->get($et->id)))
                ->filter(fn ($et) => $et['show_in_form'])
                ->values(),
            'stay_options' => $stayOptions
                ->map(fn ($so) => $this->mapStayOption($so, $soConfigs->get($so->id)))
                ->filter(fn ($so) => $so['show_in_form'])
                ->values(),
            'catering_options' => $cateringOptions,
            'opening_hours' => $openingHours,
            'seasons'       => $seasons,
            'exceptions'    => $exceptions,
        ]);
    }

    private function mapEventType(EventType $et, ?BookingFormEventTypeConfig $cfg): array
    {
        return [
            'event_type_id'   => $et->id,
            'name'            => $et->name,
            'emoji'           => $et->emoji,
            'show_in_form'    => $cfg ? (bool) $cfg->show_in_form : true,
            'audience_mode'   => $cfg?->audience_mode ?? BookingFormEventTypeConfig::AUDIENCE_MODE_NONE,
            'audience_options' => $cfg?->audience_options ?? [],
        ];
    }

    private function mapStayOption(StayOption $so, ?BookingFormStayOptionConfig $cfg): array
    {
        return [
            'stay_option_id'                  => $so->id,
            'name'                            => $so->name,
            'duration_minutes'                => $so->duration_minutes,
            'show_in_form'                    => $cfg ? (bool) $cfg->show_in_form : true,
            'min_revenue_outside_hours_cents' => $cfg?->min_revenue_outside_hours_cents,
        ];
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $input = trim((string) (
            $request->query('tenant')
            ?? $request->header('X-Tenant')
            ?? ''
        ));

        if ($input === '') return null;

        $normalized = Str::lower($input);

        $byDomain = TenantDomain::query()
            ->with('tenant')
            ->whereRaw('lower(domain) = ?', [$normalized])
            ->first();

        if ($byDomain?->tenant?->is_active) {
            return $byDomain->tenant;
        }

        return Tenant::query()
            ->where('slug', $normalized)
            ->where('is_active', true)
            ->first();
    }
}
