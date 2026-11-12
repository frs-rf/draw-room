<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcludedPerson extends Model
{
    protected $table = 'excluded_people';

    protected $fillable = ['group', 'name', 'sort'];
}
