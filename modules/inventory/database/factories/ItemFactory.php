<?php

namespace Tessera\Inventory\Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tessera\Inventory\Enums\ItemType;
use Tessera\Inventory\Models\Item;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'type' => ItemType::Item,
        ];
    }

    public function location(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ItemType::Location,
        ]);
    }

    public function bin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ItemType::Bin,
        ]);
    }

    /**
     * Place the item inside another, in the same organization.
     */
    public function childOf(Item $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $parent->team_id,
            'parent_id' => $parent->id,
        ]);
    }
}
