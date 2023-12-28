<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OpenAIController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [OpenAIController::class, 'index']);
Route::post('/submit-message', [OpenAIController::class, 'submitMessage']);
Route::delete('/delete-thread', [OpenAIController::class, 'deleteThread']);
Route::delete('/delete-assistant', [OpenAIController::class, 'deleteAssistant']);
Route::post('/create-new-thread', [OpenAIController::class, 'createNewThread']);
Route::post('/create-new-assistant', [OpenAIController::class, 'createNewAssistant']);

// Add these two routes for starting the assistant run and checking its status
Route::post('/start-run', [OpenAIController::class, 'startRun']);
Route::post('/check-run-status', [OpenAIController::class, 'checkRunStatus']);
Route::get('/get-messages', [OpenAIController::class, 'getMessages']);
// Add a new route for downloading all files from a thread
Route::post('/download-files', [OpenAIController::class, 'downloadFiles']);



