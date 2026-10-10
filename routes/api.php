<?php

declare(strict_types=1);

use App\Http\Controllers\BibleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — clean-schema (no runtime string manipulation)
|--------------------------------------------------------------------------
| Prefix automatically /api via bootstrap/app.php
*/

// TASK 1: Chapter dict endpoint (clean-schema)
Route::get('/chapter/{book_abbr}/{chapter_num}', [BibleController::class, 'getChapter'])
    ->where(['book_abbr' => '[A-Za-z0-9\-]+', 'chapter_num' => '[0-9]+'])
    ->name('api.chapter');

// TASK 2: LXX occurrences per Strong — cached 24h
Route::get('/lxx/{strong_id}', [BibleController::class, 'getLxxOccurrences'])
    ->where('strong_id', '[HGhg][0-9]+')
    ->name('api.lxx');

// Optional alias: /api/bible/chapter/{book_abbr}/{chapter_num} for legacy clients
Route::get('/bible/chapter/{book_abbr}/{chapter_num}', [BibleController::class, 'getChapter'])
    ->where(['book_abbr' => '[A-Za-z0-9\-]+', 'chapter_num' => '[0-9]+']);
