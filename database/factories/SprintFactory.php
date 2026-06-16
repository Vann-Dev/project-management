<?php

namespace Database\Factories;

use App\Models\Sprint;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SprintFactory extends Factory
{
    protected $model = Sprint::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+1 week');
        $endsAt = fake()->dateTimeBetween($startsAt, '+2 weeks');

        return [
            'name' => fake()->words(3, true),
            'project_id' => Project::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(11),
        ]);
    }
}
