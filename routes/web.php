<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/docs');

// Swagger UI for public/openapi.yaml
Route::view('/docs', 'docs')->name('docs');
