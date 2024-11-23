<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationController extends Controller
{
    public function reserve(Request $request)
    {
        $request->validate([
            'seat_id' => 'required|int',
            'begin_time' => 'required|date',
            'end_time' => 'required|date',
        ]);

        $seatId = $request->input('seat_id');
        $beginTime = Carbon::parse($request->input('begin_time'));
        $endTime = Carbon::parse($request->input('end_time'));

        $now = Carbon::now();

        // 假設營業時間
        $openingTime = Carbon::createFromTime(9, 0, 0);
        $closingTime = Carbon::createFromTime(21, 0, 0);

        // 1. 檢查預約時間是否在營業時間內
        if ($beginTime->lt($openingTime) || $endTime->gt($closingTime)) {
            return response()->json(['error' => 'Reservation time is outside business hours'], 400);
        }

        // 2. 檢查預約時間是否大於現在時間
        if ($beginTime->lte($now) || $endTime->lte($now)) {
            return response()->json(['error' => 'Reservation time must be in the future'], 400);
        }

        // 3. 檢查開始和結束時間是否在同一天
        if (!$beginTime->isSameDay($endTime)) {
            return response()->json(['error' => 'Begin and end time must be on the same day'], 400);
        }

        // 4. 檢查該座位是否在該時間段已被預約
        $conflictingReservations = DB::table('reservations')
            ->where('seat_id', $seatId)
            ->where(function ($query) use ($beginTime, $endTime) {
                $query->whereBetween('begin_time', [$beginTime, $endTime])
                      ->orWhereBetween('end_time', [$beginTime, $endTime])
                      ->orWhere(function ($query) use ($beginTime, $endTime) {
                          $query->where('begin_time', '<=', $beginTime)
                                ->where('end_time', '>=', $endTime);
                      });
            })
            ->exists();

        if ($conflictingReservations) {
            return response()->json(['error' => 'Seat is already reserved during this time'], 400);
        }

        // 5. 檢查用戶當天是否已有預約
        $userEmail = $request->input('user.email');
        $userReservations = DB::table('reservations')
            ->where('user_email', $userEmail)
            ->whereDate('begin_time', $beginTime->toDateString())
            ->exists();

        if ($userReservations) {
            return response()->json(['error' => 'You can only make one reservation per day'], 400);
        }

        // 創建預約
        $reservationId = DB::table('reservations')->insertGetId([
            'seat_id' => $seatId,
            'begin_time' => $beginTime,
            'end_time' => $endTime,
            'user_email' => $userEmail,
        ]);

        return response()->json(['message' => 'Reservation successful', 'id' => $reservationId], 201);
    }
}
