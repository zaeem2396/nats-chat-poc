<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('failed_messages', function (Blueprint $table) {
            $table->string('source_subject', 255)->nullable()->after('subject')->index();
        });
    }

    public function down(): void
    {
        Schema::table('failed_messages', function (Blueprint $table) {
            $table->dropColumn('source_subject');
        });
    }
};
