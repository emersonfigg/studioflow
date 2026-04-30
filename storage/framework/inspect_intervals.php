<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$service = app(App\Services\AvailabilityService::class);
$ref = new ReflectionClass($service);
$method = $ref->getMethod('workingIntervalsForDate');
$method->setAccessible(true);
$company = App\Models\Company::find(1);
$user = App\Models\User::find(1);
$day = Carbon\CarbonImmutable::parse('2026-04-29', config('app.timezone'))->startOfDay();
$intervals = $method->invoke($service, $company, $user, $day);
var_export($intervals->toArray());
