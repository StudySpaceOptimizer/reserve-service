<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class ReservationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $fixedNow = Carbon::create(2024, 1, 1, 1, 0, 0);
        Carbon::setTestNow($fixedNow);

        DB::table('reservations')->insert([
            [
                'seat_code' => 'B01',
                'begin_time' => Carbon::create(2024, 11, 1, 9, 0, 0),
                'end_time' => Carbon::create(2024, 11, 1, 10, 0, 0),
                'user_email' => 'test1@example.com',
                'created_at' => Carbon::create(2024, 10, 31, 8, 0, 0),
                'updated_at' => Carbon::create(2024, 10, 31, 8, 0, 0),
            ],
            [
                'seat_code' => 'B02',
                'begin_time' => Carbon::create(2024, 11, 2, 11, 0, 0),
                'end_time' => Carbon::create(2024, 11, 2, 12, 0, 0),
                'user_email' => 'test2@example.com',
                'created_at' => Carbon::create(2024, 10, 31, 8, 0, 0),
                'updated_at' => Carbon::create(2024, 10, 31, 8, 0, 0),
            ],
            [
                'seat_code' => 'B03',
                'begin_time' => Carbon::create(2024, 11, 3, 11, 0, 0),
                'end_time' => Carbon::create(2024, 11, 3, 12, 0, 0),
                'user_email' => 'test1@example.com',
                'created_at' => Carbon::create(2024, 10, 31, 8, 0, 0),
                'updated_at' => Carbon::create(2024, 10, 31, 8, 0, 0),
            ],
        ]);
    }

    public function testReserveSuccess()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $beginTime = Carbon::create(2024, 1, 1, 3, 0, 0);
        $endTime = $beginTime->copy()->addHour();

        $response = $this->postJson('/api/reservations', [
            'seat_code' => 'B01',
            'begin_time' => $beginTime->toISOString(),
            'end_time' => $endTime->toISOString(),
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Reservation successful']);
    }

    public function testReserveOutsideBusinessHours()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $response = $this->postJson('/api/reservations', [
            'seat_code' => 'B01',
            'begin_time' => Carbon::createFromTime(13, 0, 0)->toISOString(),
            'end_time' => Carbon::createFromTime(14, 0, 0)->toISOString(),
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Reservation time is outside business hours']);
    }

    public function testReserveInPast()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $beginTime = Carbon::create(2024, 1, 1, 0, 0, 0);
        $endTime = $beginTime->copy()->addHour();

        $response = $this->postJson('/api/reservations', [
            'seat_code' => 'B01',
            'begin_time' => $beginTime->toISOString(),
            'end_time' => $endTime->toISOString(),
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Reservation time must be in the future']);
    }

    public function testReserveDifferentDay()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $beginTime = Carbon::create(2024, 1, 1, 11, 0, 0);
        $endTime = $beginTime->copy()->addDay();

        $response = $this->postJson('/api/reservations', [
            'seat_code' => 'B01',
            'begin_time' => $beginTime->toISOString(),
            'end_time' => $endTime->toISOString(),
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Begin and end time must be on the same day']);
    }

    public function testReserveSameDayConstraint()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $beginTime = Carbon::create(2024, 1, 1, 1, 0, 0);
        $endTime = $beginTime->copy()->addHours(5);

        DB::table('reservations')->insert([
            'seat_code' => 'B01',
            'begin_time' => $beginTime,
            'end_time' => $endTime,
            'user_email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/reservations', [
            'seat_code' => 'B02',
            'begin_time' => $beginTime->addHours(3)->toISOString(),
            'end_time' => $endTime->addHours(4)->toISOString(),
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'You can only make one reservation per day']);
    }

    public function testReserveSameDayConstraintSuccess()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $beginTime = Carbon::create(2024, 1, 1, 1, 0, 0);
        $endTime = $beginTime->copy()->addHour();

        DB::table('reservations')->insert([
            'seat_code' => 'B01',
            'begin_time' => $beginTime,
            'end_time' => $endTime,
            'user_email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/reservations', [
            'seat_code' => 'B01',
            'begin_time' => $beginTime->addHours(3)->toISOString(),
            'end_time' => $endTime->addHours(4)->toISOString(),
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Reservation successful']);
    }

    public function testReserveSeatConflict()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $beginTime = Carbon::now()->addHours(1);
        $endTime = $beginTime->copy()->addHour();

        // 插入一個與目標時間段衝突的預約
        DB::table('reservations')->insert([
            'seat_code' => 'B01',
            'begin_time' => $beginTime,
            'end_time' => $endTime,
            'user_email' => 'other@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 發送衝突預約請求
        $response = $this->postJson('/api/reservations', [
            'seat_code' => 'B01',
            'begin_time' => $beginTime->copy()->addMinutes(30)->toISOString(),
            'end_time' => $endTime->copy()->addMinutes(30)->toISOString(),
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Seat is already reserved during this time']);
    }

    public function testGetReservations()
    {
        $response = $this->getJson('/api/reservations?pageSize=1&pageOffset=0');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'data' => [
                    [
                        'reservationId',
                        'seatCode',
                        'beginTime',
                        'endTime',
                        'userEmail',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'reservationId' => 1,
                'seatCode' => 'B01',
                'beginTime' => '2024-11-01 09:00:00',
                'endTime' => '2024-11-01 10:00:00',
                'userEmail' => 'test1@example.com',
            ]);
    }

    public function testGetMyReservations()
    {
        // 模擬帶有 X-User-Info 的請求
        $this->withHeaders([
            'X-User-Email' => 'test1@example.com',
            'X-User-Role' => 'user',
        ]);

        $response = $this->getJson('/api/reservations/me?pageSize=10&pageOffset=0');
        $response->assertStatus(200)
            ->assertJsonFragment([
                'reservationId' => 1,
                'seatCode' => 'B01',
                'beginTime' => '2024-11-01 09:00:00',
                'endTime' => '2024-11-01 10:00:00',
            ])
            ->assertJsonMissing([
                'reservationId' => 2,
            ]);
    }

    public function testDeleteReservationSuccess()
    {
        $this->withHeaders([
            'X-User-Email' => 'admin@example.com',
            'X-User-Role' => 'admin',
        ]);

        // 建立測試資料
        $reservation = DB::table('reservations')->insertGetId([
            'begin_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'user_email' => 'test@example.com',
            'seat_code' => 'B01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->delete("/api/reservations/{$reservation}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('reservations', ['id' => $reservation]);
    }

    public function testDeleteReservationAdminSucess()
    {
        $this->withHeaders([
            'X-User-Email' => 'admin@example.com',
            'X-User-Role' => 'admin',
        ]);

        $reservation = DB::table('reservations')->insertGetId([
            'begin_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'user_email' => 'test@example.com',
            'seat_code' => 'B01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->delete("/api/reservations/{$reservation}");

        $this->assertDatabaseMissing('reservations', ['id' => $reservation]);
    }

    public function testDeleteReservationUnauthorized()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        // 建立測試資料
        $reservation = DB::table('reservations')->insertGetId([
            'begin_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'user_email' => 'otheruser@example.com',
            'seat_code' => 'B01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->delete("/api/reservations/{$reservation}");

        $response->assertStatus(403);
    }

    public function testDeleteReservationNotFound()
    {
        $this->withHeaders([
            'X-User-Email' => 'test@example.com',
            'X-User-Role' => 'user',
        ]);

        $response = $this->delete("/api/reservations/99999");

        $response->assertStatus(404);
    }
}
