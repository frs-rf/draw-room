<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $t) {
            $t->id();
            $t->string('status', 12)->default('running')->index(); // running|finished|cancelled
            $t->unsignedTinyInteger('room_size');
            $t->json('caps');
            $t->json('lead_rooms');
            // RAHASIA sampai diungkap. Jangan pernah kirim utuh ke publik.
            $t->json('steps');
            $t->unsignedSmallInteger('current_index')->default(0);
            $t->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draws');
    }
};
