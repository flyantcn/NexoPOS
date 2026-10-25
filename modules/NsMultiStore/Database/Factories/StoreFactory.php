<?php

namespace Modules\NsMultiStore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\NsMultiStore\Models\Store;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'code' => strtoupper( Str::slug( $name, '' ) ) . fake()->unique()->numberBetween( 100, 999 ),
            'type' => Store::TYPE_SINGLE,
            'parent_id' => null,
            'status' => Store::STATUS_ACTIVE,
            'description' => fake()->sentence(),
            'author_id' => null,
            'uuid' => (string) Str::uuid(),
        ];
    }

    /**
     * Defines the store as a chain store.
     */
    public function chain(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'type' => Store::TYPE_CHAIN,
        ] );
    }

    /**
     * Defines the store as a franchise store reporting to a headquarter.
     */
    public function franchise(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'type' => Store::TYPE_FRANCHISE,
        ] );
    }

    /**
     * Defines the store as disabled.
     */
    public function disabled(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'status' => Store::STATUS_DISABLED,
        ] );
    }
}
