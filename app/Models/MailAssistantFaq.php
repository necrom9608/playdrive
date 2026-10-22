<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailAssistantFaq extends Model
{
    protected $fillable = [
        'tenant_id',
        'question',
        'answer',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
