<?php

use App\Http\Controllers\Api\MobileApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes — /Webservice/*
|--------------------------------------------------------------------------
| Hardcoded in mobile APK (com.calibehr.lms v5.4). DO NOT change this prefix.
| Mobile expects:
|   - response shape: { "data": <obj>, "code": <int>, "message": <str> }
|   - field names: snake_case for DB-bound, camelCase for derived
|   - codes: 0=error, 1=success, 2=disabled, 3=not_found, 5=no_data
|
| All endpoints are POST (mobile uses @FormUrlEncoded).
| Routes are PUBLIC (no Sanctum auth) — mobile sends userID per request.
|
| Total: 26 unique endpoints (loginWS/loginLdap mapped to similar handlers).
*/

// ─── AUTH (4) ──────────────────────────────────────────────────────
Route::post('login',          [MobileApiController::class, 'loginLdap']);  // alias
Route::post('loginLdap',      [MobileApiController::class, 'loginLdap']);
Route::post('loginWS',        [MobileApiController::class, 'loginWS']);
Route::post('getAppVersion',  [MobileApiController::class, 'getAppVersion']);
Route::post('addAppToken',    [MobileApiController::class, 'addAppToken']);

// ─── ACCOUNT (1) ───────────────────────────────────────────────────
Route::post('getAccountInfoWS', [MobileApiController::class, 'getAccountInfoWS']);

// ─── COURSES (8) ───────────────────────────────────────────────────
Route::post('getCourseCategory',     [MobileApiController::class, 'getCourseCategory']);
Route::post('getCatalogCourseList',  [MobileApiController::class, 'getCatalogCourseList']);
Route::post('getFeaturedCourseList', [MobileApiController::class, 'getFeaturedCourseList']);
Route::post('getAllCoursesList',     [MobileApiController::class, 'getAllCoursesList']);
Route::post('getCourseDetailsWS',    [MobileApiController::class, 'getCourseDetailsWS']);
Route::post('courseTopicDetailWS',   [MobileApiController::class, 'courseTopicDetailWS']);
Route::post('updateTopicTimeWS',     [MobileApiController::class, 'updateTopicTimeWS']);
Route::post('enrollSelf',            [MobileApiController::class, 'enrollSelf']);

// ─── QUIZ (2) ──────────────────────────────────────────────────────
Route::post('getQuestionsListWS',    [MobileApiController::class, 'getQuestionsListWS']);
Route::post('testSubmitMobileWS',    [MobileApiController::class, 'testSubmitMobileWS']);

// ─── FEEDBACK (5) ──────────────────────────────────────────────────
Route::post('getFeedbackQuestionsWS', [MobileApiController::class, 'getFeedbackQuestionsWS']);
Route::post('addFeedBackWS',          [MobileApiController::class, 'addFeedBackWS']);
Route::post('addFeedBackV2WS',        [MobileApiController::class, 'addFeedBackV2WS']);
Route::post('addFeedBackReplyWS',     [MobileApiController::class, 'addFeedBackReplyWS']);
Route::post('getAllFeedbackWS',       [MobileApiController::class, 'getAllFeedbackWS']);

// ─── WISHLIST (2) ──────────────────────────────────────────────────
Route::post('getWishlistWS',     [MobileApiController::class, 'getWishlistWS']);
Route::post('anrToWishlistWS',   [MobileApiController::class, 'anrToWishlistWS']);

// ─── LEADERBOARD (1) ───────────────────────────────────────────────
Route::post('getLeaderBoardWS',  [MobileApiController::class, 'getLeaderBoardWS']);

// ─── INTERVIEW (3) ─────────────────────────────────────────────────
Route::post('getInterviewWS',           [MobileApiController::class, 'getInterviewWS']);
Route::post('getInterviewQuestionWS',   [MobileApiController::class, 'getInterviewQuestionWS']);
Route::post('sendInterviewQuestionWS',  [MobileApiController::class, 'sendInterviewQuestionWS']);