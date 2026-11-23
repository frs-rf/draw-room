<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = ['name', 'role', 'sort', 'active', 'separated'];

    protected $casts = ['active' => 'boolean', 'separated' => 'boolean'];
}
