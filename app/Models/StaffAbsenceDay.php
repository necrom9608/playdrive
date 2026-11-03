<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Afwezigheidscode van een medewerker op één dag (Liantis-codes: F, V, KV, Z, ...).
 */
class StaffAbsenceDay extends Model
{
    /** Code => [label, kleur]. Volgorde = volgorde in de UI (zoals in Liantis). */
    public const CODES = [
        'F'   => ['label' => 'Feestdag',           'color' => '#67e8f9'],
        'V'   => ['label' => 'Vakantie',           'color' => '#818cf8'],
        'BIR' => ['label' => 'BIR',                'color' => '#d8b4fe'],
        'KV'  => ['label' => 'Klein verlet',       'color' => '#fdba74'],
        'IR'  => ['label' => 'Inhaalrust',         'color' => '#f9a8d4'],
        'TA'  => ['label' => 'TA',                 'color' => '#fde047'],
        'EW'  => ['label' => 'Economische werkl.', 'color' => '#fecaca'],
        'WV'  => ['label' => 'WV',                 'color' => '#fb7185'],
        'AO'  => ['label' => 'AO',                 'color' => '#bbf7d0'],
        'Z'   => ['label' => 'Ziekte',             'color' => '#a3e635'],
    ];

    protected $fillable = ['tenant_id', 'user_id', 'date', 'code', 'minutes', 'note'];

    protected $casts = [
        'date'    => 'date',
        'minutes' => 'integer',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
