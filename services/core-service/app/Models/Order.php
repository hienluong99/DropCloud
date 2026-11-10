<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'reservation_token',
        'status',
        'version',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
