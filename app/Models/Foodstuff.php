<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An ingredient or item the kitchen regularly buys (pepper, noodles, tomatoes...). */
class Foodstuff extends Model
{
    protected $table = 'foodstuff';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
