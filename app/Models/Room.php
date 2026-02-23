<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['name'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function analytic(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Analytic::class);
    }
}
