<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('message_count')->default(0);
            $table->timestamps();

            $table->unique('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics');
    }
};
