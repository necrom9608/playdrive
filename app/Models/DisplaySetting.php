<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Instellingen van het klantenscherm per tenant (skin, teksten, effecten).
 * Standaardwaarden per skin: App\Domain\Display\DisplaySkin.
 */
class DisplaySetting extends Model
{
    protected $fillable = [
        'tenant_id', 'skin', 'title_line_1', 'title_line_2', 'info_text',
        'standby_ticker', 'order_ticker', 'show_hours', 'crt', 'join_qr',
    ];

    protected function casts(): array
    {
        return [
            'standby_ticker' => 'array',
            'order_ticker'   => 'array',
            'show_hours'     => 'boolean',
            'join_qr'        => 'boolean',
        ];
    }
}
