<?php

namespace Modules\NsMultiStore\Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\NsMultiStore\Models\UserStoreRelation;

class UserStoreRelationFactory extends Factory
{
    protected $model = UserStoreRelation::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'store_id' => null,
            'role_namespace' => Role::STORECASHIER,
            'uuid' => (string) Str::uuid(),
        ];
    }

    /**
     * Defines the employee as the administrator of the store.
     */
    public function administrator(): static
    {
        return $this->state( fn ( array $attributes ) => [
            'role_namespace' => Role::STOREADMIN,
        ] );
    }
}
