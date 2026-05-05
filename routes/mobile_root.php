<?php

use App\Http\Controllers\Api\MobileApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile Root-Level Routes (no /Webservice prefix)
|--------------------------------------------------------------------------
| The mobile app has TWO Retrofit instances:
|   - instance  → BASE_URL  (.../Webservice/) — most endpoints
|   - instance2 → BASE_URL_UNDER (root)       — only underMaintenance
|
| The maintenance check is called on app startup. If response.code == 1,
| the app shows the UnderMaintenanceActivity.
|--------------------------------------------------------------------------
*/

Route::get('underMaintenance', [MobileApiController::class, 'underMaintenance']);