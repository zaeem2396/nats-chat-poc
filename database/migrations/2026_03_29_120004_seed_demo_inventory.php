<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('inventory_items')->insert([
            'sku' => 'SKU-DEMO',
            'quantity' => 1000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('inventory_items')->where('sku', 'SKU-DEMO')->delete();
    }
};
