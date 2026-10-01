<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoRenderController;
use App\Http\Controllers\TemplateController;

Route::post(
    '/templates/sync',
    [TemplateController::class, 'sync']
);
Route::post('/render-video', [
    VideoRenderController::class,
    'render'
]);
Route::get('/render-jobs/{id}', [
    VideoRenderController::class,
    'status'
]);
Route::get('/videos/{filename}', [
    VideoRenderController::class,
    'serveVideo'
]);