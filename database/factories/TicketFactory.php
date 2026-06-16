<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'owner_id' => User::factory(),
            'responsible_id' => User::factory(),
            'project_id' => Project::factory(),
            'status_id' => TicketStatus::factory(),
            'type_id' => TicketType::factory(),
            'priority_id' => TicketPriority::factory(),
            'estimation' => fake()->randomFloat(1, 0.5, 8),
        ];
    }
}
