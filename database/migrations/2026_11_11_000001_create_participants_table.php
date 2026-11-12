<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('role', 8)->index(); // pm|sm|atm|prg|imp|bb
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('excluded_people', function (Blueprint $t) {
            $t->id();
            $t->string('group');
            $t->string('name');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excluded_people');
        Schema::dropIfExists('participants');
    }
};
