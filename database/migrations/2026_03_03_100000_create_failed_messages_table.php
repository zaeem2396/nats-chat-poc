<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_messages', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 255)->nullable()->index();
            $table->json('payload');
            $table->text('error_reason')->nullable();
            $table->string('original_queue', 128)->nullable();
            $table->string('original_connection', 64)->nullable();
            $table->timestamp('failed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_messages');
    }
};
