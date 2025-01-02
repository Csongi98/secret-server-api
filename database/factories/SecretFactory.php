<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Secret;
use Illuminate\Support\Facades\Crypt;

class SecretFactory extends Factory
{
    protected $model = Secret::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'hash' => Str::random(32),
            'secret_text' => Crypt::encryptString($this->faker->sentence),
            'remaining_views' => $this->faker->numberBetween(1, 10),
            'expires_at' => Carbon::now()->addMinutes($this->faker->numberBetween(5, 60)),
            'tags' => ['personal', 'work']
        ];
    }
}
