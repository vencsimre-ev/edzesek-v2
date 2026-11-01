<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('attendances')->whereNull('created_by')->exists()) {
            throw new RuntimeException('Guest attendance exists; created_by cannot be made required.');
        }
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};
