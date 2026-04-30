<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$timezone = config('app.timezone');
$day = Carbon\CarbonImmutable::parse('2026-04-29', $timezone)->startOfDay();
$safe = $day->setTimeFromTimeString('14:00');
$latest = $day->setTimeFromTimeString('20:00');
for ($slot = $safe, $i = 0; $i < 20; $i++, $slot = $slot->addMinutes(30)) {
    echo $slot->format('Y-m-d H:i:s') . ' <= ' . $latest->format('Y-m-d H:i:s') . ' ? ' . ($slot->lte($latest) ? 'yes' : 'no') . PHP_EOL;
    if (! $slot->lte($latest)) { break; }
}
