<?php

use App\Http\Controllers\Slack\InteractionController;
use Illuminate\Support\Facades\Route;

Route::name('slack.')->prefix('slack')
->group(function (){
        Route::any('interaction', [InteractionController::class, '__invoke'])->name('interaction');
});
