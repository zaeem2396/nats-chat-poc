<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index(['room_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
