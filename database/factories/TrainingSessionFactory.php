<?php

namespace Database\Factories;

use App\Enums\TrainingSessionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Minta mászóedzés',
            'location' => 'Minta mászóterem',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => TrainingSessionStatus::Scheduled,
            'created_by' => User::factory()->admin(),
        ];
    }
}
