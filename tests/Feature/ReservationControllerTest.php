<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class ReservationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testReserveSuccess()
    {
        $this->withHeaders([
            'X-User-Info' => json_encode(['email' => 'test@example.com']),
        ]);

        $response = $this->postJson('/api/reservations', [
            'seat_id' => 1,
            'begin_time' => Carbon::now()->addHours(2)->toISOString(),
            'end_time' => Carbon::now()->addHours(3)->toISOString(),
        ]);

        $response->assertStatus(201)
                 ->assertJson(['message' => 'Reservation successful']);
    }

    public function testReserveOutsideBusinessHours()
    {
        $this->withHeaders([
            'X-User-Info' => json_encode(['email' => 'test@example.com']),
        ]);

        $response = $this->postJson('/api/reservations', [
            'seat_id' => 1,
            'begin_time' => Carbon::createFromTime(8, 0, 0)->toISOString(),
            'end_time' => Carbon::createFromTime(9, 0, 0)->toISOString(),
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'Reservation time is outside business hours']);
    }

    public function testReserveInPast()
    {
        $this->withHeaders([
            'X-User-Info' => json_encode(['email' => 'test@example.com']),
        ]);

        $response = $this->postJson('/api/reservations', [
            'seat_id' => 1,
            'begin_time' => Carbon::now()->subHours(2)->toISOString(),
            'end_time' => Carbon::now()->subHours(1)->toISOString(),
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'Reservation time must be in the future']);
    }

    public function testReserveSameDayConstraint()
    {
        $this->withHeaders([
            'X-User-Info' => json_encode(['email' => 'test@example.com']),
        ]);

        $beginTime = Carbon::now()->addHours(2);
        $endTime = $beginTime->copy()->addHour();

        DB::table('reservations')->insert([
            'seat_id' => 1,
            'begin_time' => $beginTime,
            'end_time' => $endTime,
            'user_email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/reservations', [
            'seat_id' => 1,
            'begin_time' => Carbon::now()->addHours(4)->toISOString(),
            'end_time' => Carbon::now()->addHours(5)->toISOString(),
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'You can only make one reservation per day']);
    }

    public function testReserveSeatConflict()
    {
        $this->withHeaders([
            'X-User-Info' => json_encode(['email' => 'test@example.com']),
        ]);

        $beginTime = Carbon::now()->addHours(2);
        $endTime = $beginTime->copy()->addHour();

        // 插入一個與目標時間段衝突的預約
        DB::table('reservations')->insert([
            'seat_id' => 1,
            'begin_time' => $beginTime,
            'end_time' => $endTime,
            'user_email' => 'other@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 發送衝突預約請求
        $response = $this->postJson('/api/reservations', [
            'seat_id' => 1,
            'begin_time' => $beginTime->copy()->addMinutes(30)->toISOString(),
            'end_time' => $endTime->copy()->addMinutes(30)->toISOString(),
        ]);

        $response->assertStatus(400)
                 ->assertJson(['error' => 'Seat is already reserved during this time']);
    }
}
