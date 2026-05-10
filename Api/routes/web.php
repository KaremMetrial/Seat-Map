<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Seatmap Test Interface
Route::get('/seatmap-test', function () {
    return view('seatmap-test');
});
