<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'country',
        'postal_code',
        'city',
    ];

    protected $casts = [
        'country' => 'string',
        'postal_code' => 'string',
        'city' => 'string',
    ];
}
