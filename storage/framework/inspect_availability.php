<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$company = App\Models\Company::find(1);
$user = App\Models\User::find(1);
$override = App\Models\ProfessionalDayOverride::with('intervals')->where('user_id', 1)->whereDate('date', '2026-04-29')->latest('id')->first();
$appointments = App\Models\Appointment::where('company_id', 1)->where('user_id', 1)->whereDate('start_time', '2026-04-29')->get(['id','start_time','end_time','status'])->toArray();
$slots = app(App\Services\AvailabilityService::class)->availableSlotsForDuration($company, $user, 30, '2026-04-29', false);
var_export([
    'now' => now(config('app.timezone'))->format('Y-m-d H:i:s'),
    'user' => $user?->only(['id','name','company_id']),
    'override' => $override?->toArray(),
    'appointments' => $appointments,
    'slots' => $slots,
]);
