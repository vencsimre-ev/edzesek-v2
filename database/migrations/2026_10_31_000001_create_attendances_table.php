<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('participant_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            // Identifies a guest-form submission, not a person or a name.
            $table->uuid('submission_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['training_session_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
