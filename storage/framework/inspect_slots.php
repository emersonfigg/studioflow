<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$timezone = config('app.timezone');
$day = Carbon\CarbonImmutable::parse('2026-04-29', $timezone)->startOfDay();
$start = $day->setTimeFromTimeString('13:00');
$end = $day->setTimeFromTimeString('20:00');
$now = Carbon\CarbonImmutable::now($timezone);
$roundedNow = $now->setSecond(0);
$remainder = $roundedNow->minute % 30;
$safe = $remainder === 0 ? $roundedNow : $roundedNow->addMinutes(30 - $remainder);
$latest = $end->setSecond(0);
$remainderEnd = $latest->minute % 30;
$latest = $remainderEnd === 0 ? $latest : $latest->addMinutes(30 - $remainderEnd);
$slots = [];
for ($slot = $safe; $slot->lte($latest); $slot = $slot->addMinutes(30)) {
    $slots[] = $slot->format('H:i');
}
var_export([
    'now' => $now->format('Y-m-d H:i:s'),
    'safe' => $safe->format('H:i:s'),
    'start' => $start->format('H:i:s'),
    'end' => $end->format('H:i:s'),
    'latest' => $latest->format('H:i:s'),
    'slots' => $slots,
]);
