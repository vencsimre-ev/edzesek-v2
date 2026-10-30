<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('location');
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6);
            $table->unsignedBigInteger('price_huf');
            $table->string('status')->default('scheduled');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['starts_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
