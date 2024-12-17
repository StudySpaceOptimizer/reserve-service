<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReservationController extends Controller
{
    private function checkReservationTime($beginTime, $endTime)
    {
        $now = Carbon::now();

        // TODO: Move these to config
        $openingTime = Carbon::createFromTime(0, 0);
        $closingTime = Carbon::createFromTime(12, 0);
        $beginTimeWithoutDate = Carbon::createFromTime($beginTime->hour, $beginTime->minute);
        $endTimeWithoutDate = Carbon::createFromTime($endTime->hour, $endTime->minute);

        if ($beginTimeWithoutDate->lt($openingTime) || $endTimeWithoutDate->gt($closingTime)) {
            return 'Reservation time is outside business hours';
        }

        if ($beginTime->lte($now) || $endTime->lte($now)) {
            return 'Reservation time must be in the future';
        }

        if (!$beginTime->isSameDay($endTime)) {
            return 'Begin and end time must be on the same day';
        }

        return null;
    }

    public function reserve(Request $request)
    {
        $request->validate([
            'seat_code' => 'required|string',
            'begin_time' => 'required|date',
            'end_time' => 'required|date',
        ]);

        $seatCode = $request->input('seat_code');
        $beginTime = Carbon::parse($request->input('begin_time'));
        $endTime = Carbon::parse($request->input('end_time'));

        $error = $this->checkReservationTime($beginTime, $endTime);
        if ($error) {
            return response()->json(['error' => $error], 400);
        }

        // TODO: Check if seat available and optimize this query
        // $seatId = DB::table('seats')->where('code', $seatCode)->value('id');
        if (preg_match('/^A(\d{1,3})$/', $seatCode, $matches)) {
            $seatId = 140 + intval($matches[1]) - 1;
        } elseif (preg_match('/^B(\d{1,3})$/', $seatCode, $matches)) {
            $seatId = intval($matches[1]);
        } else {
            return response()->json(['error' => 'Invalid seat code'], 400);
        }

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

        $userEmail = $request->input('user.email');
        $userReservations = DB::table('reservations')
            ->where('user_email', $userEmail)
            ->whereDate('begin_time', $beginTime->toDateString())
            ->exists();

        if ($userReservations) {
            return response()->json(['error' => 'You can only make one reservation per day'], 400);
        }

        $reservationId = DB::table('reservations')->insertGetId([
            'seat_id' => $seatId,
            'begin_time' => $beginTime,
            'end_time' => $endTime,
            'user_email' => $userEmail,
        ]);

        return response()->json(['message' => 'Reservation successful', 'id' => $reservationId], 201);
    }

    public function getReservations(Request $request)
    {
        $request->validate([
            'pageSize' => 'integer|min:1|max:100',
            'pageOffset' => 'integer|min:0',
        ]);

        $pageSize = $request->query('pageSize', 10);
        $pageOffset = $request->query('pageOffset', 0);

        $total = DB::table('reservations')->count();

        $reservations = DB::table('reservations')
            ->orderBy('begin_time', 'asc')
            ->offset($pageOffset)
            ->limit($pageSize)
            ->get([
                'id as reservationId',
                'seat_id as seatId',
                'begin_time as beginTime',
                'end_time as endTime',
                'user_email as userEmail'
            ]);

        return response()->json([
            'total' => $total,
            'data' => $reservations,
        ]);
    }

    public function getMyReservations(Request $request)
    {
        $userEmail = $request->input('user.email');

        $request->validate([
            'pageSize' => 'integer|min:1|max:100',
            'pageOffset' => 'integer|min:0',
        ]);

        $pageSize = $request->query('pageSize', 10);
        $pageOffset = $request->query('pageOffset', 0);

        $query = DB::table('reservations')
            ->where('user_email', $userEmail);

        $total = $query->count();

        $myReservations = $query->clone()
            ->orderBy('begin_time', 'asc')
            ->offset($pageOffset)
            ->limit($pageSize)
            ->get(['id as reservationId', 'seat_id as seatId', 'begin_time as beginTime', 'end_time as endTime']);

        return response()->json([
            'total' => $total,
            'data' => $myReservations,
        ]);
    }

    public function deleteReservation(Request $request, $id)
    {
        $userEmail = $request->input('user.email');
        $userRole = $request->input('user.role');
        $reservation = DB::table('reservations')->where('id', $id)->first();

        if (!$reservation) {
            return response()->json(['error' => 'Reservation not found'], 404);
        }

        if ($reservation->user_email !== $userEmail and $userRole !== 'admin') {
            return response()->json(['error' => 'You are not authorized to delete this reservation'], 403);
        }

        DB::table('reservations')->where('id', $id)->delete();
        return response()->json(['message' => 'Reservation deleted successfully'], 204);
    }
}
