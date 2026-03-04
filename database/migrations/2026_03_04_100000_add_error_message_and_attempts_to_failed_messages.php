<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('failed_messages', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('payload');
            $table->unsignedInteger('attempts')->default(1)->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('failed_messages', function (Blueprint $table) {
            $table->dropColumn(['error_message', 'attempts']);
        });
    }
};
