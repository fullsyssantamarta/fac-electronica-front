<?php

namespace App\Models\Tenant;

class ConfigurationPos extends ModelTenant
{
    public $timestamps = false;

    protected $fillable = [
        'prefix',
        'resolution_number',
        'resolution_date',
        'date_from',
        'date_end',
        'from',
        'to',
        'electronic',
        'generated',
        'plate_number',
        'cash_type',
        'show_in_establishments',
        'establishment_ids',
    ];

    protected $casts = [
        'resolution_date' => 'date',
        'electronic' => 'boolean',
        'establishment_ids' => 'array',
    ];
}
