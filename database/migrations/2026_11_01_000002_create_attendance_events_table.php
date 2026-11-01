<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Earlier withdrawals have a known time, but no reliably recorded actor.
        DB::table('attendances')->whereNotNull('withdrawn_at')->select(['id', 'withdrawn_at'])
            ->chunkById(100, function ($attendances) {
                DB::table('attendance_events')->insert($attendances->map(fn ($attendance) => [
                    'attendance_id' => $attendance->id,
                    'type' => 'withdrawn',
                    'performed_by' => null,
                    'created_at' => $attendance->withdrawn_at,
                    'updated_at' => $attendance->withdrawn_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
