<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PermissionFactory extends Factory
{
    public function definition(): array
    {
        $action = $this->faker->randomElement(['create', 'read', 'update', 'delete', 'approve']);
        $module = $this->faker->word;
        return [
            'name' => ucfirst($action) . ' ' . ucfirst($module),
            'slug' => strtolower($module) . '.' . $action,
            'module' => strtoupper($module),
            'action' => $action,
            'description' => $this->faker->sentence,
        ];
    }
}
