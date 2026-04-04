<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BadgeTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'template_type',
        'description',
        'is_default',
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'config_json' => 'array',
        ];
    }
}
