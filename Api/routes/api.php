<?php

use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ElementController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\SeatMapController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
|
| Seatmap System API Routes
| Prefix: /api/v1
|
*/

Route::prefix('v1')->group(function () {

    // ── Venues ───────────────────────────────────────────────────────────────

    Route::apiResource('venues', VenueController::class);

    // Venue Templates
    Route::get('venues/{venue}/templates', [TemplateController::class, 'index']);
    Route::post('venues/{venue}/templates', [TemplateController::class, 'store']);

    // ── Templates ────────────────────────────────────────────────────────────

    Route::get('templates/{template}', [TemplateController::class, 'show']);
    Route::put('templates/{template}', [TemplateController::class, 'update']);
    Route::delete('templates/{template}', [TemplateController::class, 'destroy']);
    Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate']);

    // Template Elements
    Route::get('templates/{template}/elements', [ElementController::class, 'index']);
    Route::post('templates/{template}/elements', [ElementController::class, 'store']);
    Route::post('templates/{template}/elements/bulk', [ElementController::class, 'bulkStore']);
    Route::post('templates/{template}/elements/generate-seats', [ElementController::class, 'generateSeats']);

    // Template Zones
    Route::get('templates/{template}/zones', [ZoneController::class, 'index']);
    Route::post('templates/{template}/zones', [ZoneController::class, 'store']);
    Route::post('templates/{template}/zones/create-defaults', [ZoneController::class, 'createDefaults']);

    // ── Elements ──────────────────────────────────────────────────────────────

    Route::get('elements/{element}', [ElementController::class, 'show']);
    Route::put('elements/{element}', [ElementController::class, 'update']);
    Route::delete('elements/{element}', [ElementController::class, 'destroy']);
    Route::put('elements/bulk-update', [ElementController::class, 'bulkUpdate']);
    Route::post('elements/bulk-delete', [ElementController::class, 'bulkDestroy']);

    // ── Zones ─────────────────────────────────────────────────────────────────

    Route::get('zones/{zone}', [ZoneController::class, 'show']);
    Route::put('zones/{zone}', [ZoneController::class, 'update']);
    Route::delete('zones/{zone}', [ZoneController::class, 'destroy']);
    Route::get('zones/{zone}/elements', [ZoneController::class, 'elements']);
    Route::post('zones/{zone}/assign-elements', [ZoneController::class, 'assignElements']);
    Route::post('zones/{zone}/remove-elements', [ZoneController::class, 'removeElements']);

    // ── Events ────────────────────────────────────────────────────────────────

    Route::apiResource('events', EventController::class);
    Route::post('events/{event}/publish', [EventController::class, 'publish']);

    // ── Seatmap ───────────────────────────────────────────────────────────────

    Route::get('events/{event}/seatmap', [SeatMapController::class, 'show']);
    Route::get('events/{event}/available', [SeatMapController::class, 'available']);

    // ── Bookings ──────────────────────────────────────────────────────────────

    Route::post('bookings/lock', [BookingController::class, 'lock']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::get('bookings/{booking}', [BookingController::class, 'show']);
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm']);
    Route::delete('bookings/{booking}', [BookingController::class, 'destroy']);

});
