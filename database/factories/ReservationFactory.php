<?php

namespace Database\Factories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition()
    {
        return [
            'begin_time' => Carbon::now()->addHours(rand(1, 5)),
            'end_time' => Carbon::now()->addHours(rand(6, 10)),
            'user_email' => $this->faker->unique()->safeEmail,
            'seat_code' => $this->faker->numberBetween(1, 50),
        ];
    }
}
