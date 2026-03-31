<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'sku',
        'quantity',
        'total_cents',
        'status',
        'pipeline_stage',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
