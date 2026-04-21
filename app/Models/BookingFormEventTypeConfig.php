<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFormEventTypeConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_type_id',
        'show_in_form',
        'audience_mode',
        'audience_options',
    ];

    protected $casts = [
        'show_in_form'     => 'boolean',
        'audience_options' => 'array',
    ];

    /**
     * Geldige waarden voor audience_mode.
     * none            — geen doelgroepvraag tonen
     * children_adults — keuze tussen kinderen en volwassenen
     * adults_only     — altijd volwassenen, geen keuze (maar eventueel catering-opties)
     */
    public const AUDIENCE_MODE_NONE            = 'none';
    public const AUDIENCE_MODE_CHILDREN_ADULTS = 'children_adults';
    public const AUDIENCE_MODE_ADULTS_ONLY     = 'adults_only';

    public static function audienceModes(): array
    {
        return [
            self::AUDIENCE_MODE_NONE            => 'Geen doelgroepvraag',
            self::AUDIENCE_MODE_CHILDREN_ADULTS => 'Kinderen / Volwassenen',
            self::AUDIENCE_MODE_ADULTS_ONLY     => 'Altijd volwassenen',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }
}
