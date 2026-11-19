<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transformer extends Model
{
    protected $fillable = [
        'mac_address',
        'name',
        'neighborhood',
        'latitude',
        'longitude',
        'is_assigned',
        'last_temp',
        'last_hum',
        'alarm_active',
        'last_msg_id',
        'last_seen_at',
    ];

    protected $casts = [
        'is_assigned'  => 'boolean',
        'alarm_active' => 'boolean',
        'last_temp'    => 'float',
        'last_hum'     => 'float',
        'latitude'     => 'float',
        'longitude'    => 'float',
        'last_seen_at' => 'datetime',
    ];

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }
}