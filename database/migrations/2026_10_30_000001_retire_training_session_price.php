<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('price_huf')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Compatibility with the old required field when explicitly rolling back this change.
        DB::table('training_sessions')->whereNull('price_huf')->update(['price_huf' => 0]);
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('price_huf')->change();
        });
    }
};
