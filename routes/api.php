<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every public method on the application's service classes is exposed here
| as a JSON endpoint. Endpoints are grouped by the service that backs them
| and protected with the same authorization gates used by the web UI.
|
*/

// UserService::register / UserService::attempt — open to guests, but rate
// limited (email + IP) to blunt credential stuffing and account enumeration.
Route::middleware('throttle:auth')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/token', [AuthController::class, 'token']);
});

// Accepts either a bearer token (Sanctum personal access token) or the session cookie.
Route::middleware('auth:sanctum,web')->group(function () {
    Route::delete('auth/token', [AuthController::class, 'revokeToken']);

    // ---------------------------------------------------------------------
    // ProfileService actions (UserService, acting on the current user)
    // ---------------------------------------------------------------------
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);            // UserService::updateProfile
    Route::put('profile/password', [ProfileController::class, 'password']); // UserService::updatePassword
    Route::put('profile/preferences', [ProfileController::class, 'preferences']); // UserService::updatePreferences
    Route::delete('profile', [ProfileController::class, 'destroy']);        // UserService::deleteOwnAccount

    // ---------------------------------------------------------------------
    // Question bank: community submissions and reports
    // ---------------------------------------------------------------------
    Route::middleware('can:submit questions')->group(function () {
        Route::get('questions/submissions', [QuestionController::class, 'mySubmissions']); // SubmissionService::mine
        Route::post('questions/submissions', [QuestionController::class, 'submit'])->middleware('throttle:20,1'); // SubmissionService::submit
    });
    Route::post('questions/{question}/reports', [QuestionController::class, 'report'])
        ->middleware(['can:play games', 'throttle:20,1']); // ReportService::report

    // ---------------------------------------------------------------------
    // CategoryService
    // ---------------------------------------------------------------------
    Route::middleware('can:manage categories')->group(function () {
        Route::get('categories/options', [CategoryController::class, 'options']); // CategoryService::options
        Route::get('categories/stats', [CategoryController::class, 'stats']);     // CategoryService::stats
        Route::get('categories', [CategoryController::class, 'index']);           // CategoryService::searchPaginated
        Route::post('categories', [CategoryController::class, 'store']);          // CategoryService::create
        Route::put('categories/{category}', [CategoryController::class, 'update']); // CategoryService::update
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']); // CategoryService::delete
        Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->withTrashed();       // CategoryService::restore
        Route::delete('categories/{category}/force', [CategoryController::class, 'forceDestroy'])->withTrashed(); // CategoryService::forceDelete
    });

    // ---------------------------------------------------------------------
    // PostService
    // ---------------------------------------------------------------------
    Route::middleware('can:manage posts')->group(function () {
        Route::get('posts/stats', [PostController::class, 'stats']);   // PostService::stats
        Route::get('posts', [PostController::class, 'index']);         // PostService::searchPaginated
        Route::post('posts', [PostController::class, 'store']);        // PostService::create
        Route::put('posts/{post}', [PostController::class, 'update']); // PostService::update
        Route::delete('posts/{post}', [PostController::class, 'destroy']); // PostService::delete
        Route::post('posts/{post}/restore', [PostController::class, 'restore'])->withTrashed();       // PostService::restore
        Route::delete('posts/{post}/force', [PostController::class, 'forceDestroy'])->withTrashed(); // PostService::forceDelete
    });

    // ---------------------------------------------------------------------
    // UserService (admin management)
    // ---------------------------------------------------------------------
    Route::middleware('can:manage users')->group(function () {
        Route::get('users/roles', [UserController::class, 'roles']); // UserService::roleNames
        Route::get('users/stats', [UserController::class, 'stats']); // UserService::stats
        Route::get('users', [UserController::class, 'index']);       // UserService::searchPaginated
        Route::post('users', [UserController::class, 'store']);      // UserService::create
        Route::put('users/{user}', [UserController::class, 'update']); // UserService::updateUser
        Route::delete('users/{user}', [UserController::class, 'destroy']); // UserService::delete
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed();       // UserService::restore
        Route::delete('users/{user}/force', [UserController::class, 'forceDestroy'])->withTrashed(); // UserService::forceDelete
        Route::post('users/{user}/toggle-admin', [UserController::class, 'toggleAdmin']); // UserService::toggleAdminRole
        Route::post('users/{user}/suspend', [UserController::class, 'suspend']);       // UserService::suspend
        Route::post('users/{user}/reactivate', [UserController::class, 'reactivate']); // UserService::reactivate
    });

    // ---------------------------------------------------------------------
    // SettingService
    // ---------------------------------------------------------------------
    Route::middleware('can:manage settings')->group(function () {
        Route::get('settings', [SettingController::class, 'show']);   // SettingService::current
        Route::put('settings', [SettingController::class, 'update']); // SettingService::update
    });
});
