<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/reservations', [ReservationController::class, 'reserve'])->middleware('parse.user.info');
Route::get('/reservations', [ReservationController::class, 'getReservations']);
Route::get('/reservations/me', [ReservationController::class, 'getMyReservations'])->middleware('parse.user.info');
Route::delete('/reservations/{id}', [ReservationController::class, 'deleteReservation'])->middleware('parse.user.info');
