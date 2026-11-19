<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transformer_id',
        'msg_id',
        'temp',
        'hum',
        'alarm_active',
        'created_at',
    ];

    protected $casts = [
        'temp'         => 'float',
        'hum'          => 'float',
        'alarm_active' => 'boolean',
        'created_at'   => 'datetime',
    ];

    public function transformer(): BelongsTo
    {
        return $this->belongsTo(Transformer::class);
    }
}