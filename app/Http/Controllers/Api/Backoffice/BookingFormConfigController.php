<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BookingFormConfig;
use App\Models\BookingFormEventTypeConfig;
use App\Models\BookingFormStayOptionConfig;
use App\Models\CateringOption;
use App\Models\EventType;
use App\Models\StayOption;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingFormConfigController extends Controller
{
    /**
     * Geeft de volledige formulier-configuratie terug voor de backoffice.
     * Bevat de globale config, alle event-types met hun form-config,
     * alle stay-opties met hun form-config, en de beschikbare catering-opties.
     */
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $tenant = $currentTenant->tenant;

        $config = BookingFormConfig::query()
            ->firstOrNew(['tenant_id' => $tenant->id]);

        // Alle event-types van de tenant, met hun form-config indien aanwezig
        $eventTypes = EventType::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $eventTypeConfigs = BookingFormEventTypeConfig::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('event_type_id');

        // Alle stay-opties van de tenant, met hun form-config indien aanwezig
        $stayOptions = StayOption::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $stayOptionConfigs = BookingFormStayOptionConfig::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('stay_option_id');

        // Catering-opties beschikbaar als keuze in audience_options
        $cateringOptions = CateringOption::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'emoji' => $c->emoji]);

        return response()->json([
            'config' => $this->mapConfig($config),
            'event_types' => $eventTypes->map(fn ($et) => $this->mapEventType(
                $et,
                $eventTypeConfigs->get($et->id)
            )),
            'stay_options' => $stayOptions->map(fn ($so) => $this->mapStayOption(
                $so,
                $stayOptionConfigs->get($so->id)
            )),
            'catering_options' => $cateringOptions,
        ]);
    }

    /**
     * Slaat de volledige configuratie op in één transactie.
     * Verwacht: config (globaal), event_type_configs (array), stay_option_configs (array).
     */
    public function save(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $tenant = $currentTenant->tenant;

        $data = $request->validate([
            // Globale config
            'config'                                    => ['required', 'array'],
            'config.is_active'                          => ['required', 'boolean'],
            'config.show_participant_children'          => ['required', 'boolean'],
            'config.show_participant_adults'            => ['required', 'boolean'],
            'config.show_participant_supervisors'       => ['required', 'boolean'],
            'config.outside_hours_warning_enabled'      => ['required', 'boolean'],
            'config.cancellation_hours_before'          => ['required', 'integer', 'min:0'],

            // Event-type configs
            'event_type_configs'                        => ['required', 'array'],
            'event_type_configs.*.event_type_id'        => [
                'required', 'integer',
                Rule::exists('event_types', 'id')->where('tenant_id', $tenant->id),
            ],
            'event_type_configs.*.show_in_form'         => ['required', 'boolean'],
            'event_type_configs.*.audience_mode'        => ['required', Rule::in([
                BookingFormEventTypeConfig::AUDIENCE_MODE_NONE,
                BookingFormEventTypeConfig::AUDIENCE_MODE_CHILDREN_ADULTS,
                BookingFormEventTypeConfig::AUDIENCE_MODE_ADULTS_ONLY,
            ])],
            'event_type_configs.*.audience_options'     => ['nullable', 'array'],

            // Stay-option configs
            'stay_option_configs'                       => ['required', 'array'],
            'stay_option_configs.*.stay_option_id'      => [
                'required', 'integer',
                Rule::exists('stay_options', 'id')->where('tenant_id', $tenant->id),
            ],
            'stay_option_configs.*.show_in_form'        => ['required', 'boolean'],
            'stay_option_configs.*.min_revenue_outside_hours_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($tenant, $data) {
            // Globale config
            BookingFormConfig::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'is_active'                     => $data['config']['is_active'],
                    'show_participant_children'     => $data['config']['show_participant_children'],
                    'show_participant_adults'       => $data['config']['show_participant_adults'],
                    'show_participant_supervisors'  => $data['config']['show_participant_supervisors'],
                    'outside_hours_warning_enabled' => $data['config']['outside_hours_warning_enabled'],
                    'cancellation_hours_before'     => $data['config']['cancellation_hours_before'],
                ]
            );

            // Event-type configs
            foreach ($data['event_type_configs'] as $row) {
                BookingFormEventTypeConfig::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'event_type_id' => $row['event_type_id']],
                    [
                        'show_in_form'     => $row['show_in_form'],
                        'audience_mode'    => $row['audience_mode'],
                        'audience_options' => $row['audience_options'] ?? null,
                    ]
                );
            }

            // Stay-option configs
            foreach ($data['stay_option_configs'] as $row) {
                BookingFormStayOptionConfig::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'stay_option_id' => $row['stay_option_id']],
                    [
                        'show_in_form'                    => $row['show_in_form'],
                        'min_revenue_outside_hours_cents' => $row['min_revenue_outside_hours_cents'] ?? null,
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Mapping helpers
    // ------------------------------------------------------------------

    private function mapConfig(BookingFormConfig $config): array
    {
        return [
            'is_active'                     => (bool) $config->is_active,
            'show_participant_children'     => (bool) ($config->show_participant_children ?? true),
            'show_participant_adults'       => (bool) ($config->show_participant_adults ?? true),
            'show_participant_supervisors'  => (bool) ($config->show_participant_supervisors ?? false),
            'outside_hours_warning_enabled' => (bool) ($config->outside_hours_warning_enabled ?? true),
            'cancellation_hours_before'     => (int) ($config->cancellation_hours_before ?? 24),
        ];
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
}
