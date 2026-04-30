<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$company = App\Models\Company::find(1);
$user = App\Models\User::find(1);
$durationMinutes = 30;
$timezone = (string) config('app.timezone');
$day = Carbon\CarbonImmutable::parse('2026-04-29', $timezone)->startOfDay();
$today = Carbon\CarbonImmutable::now($timezone)->startOfDay();
$service = app(App\Services\AvailabilityService::class);
$ref = new ReflectionClass($service);
$intervalMethod = $ref->getMethod('workingIntervalsForDate');
$intervalMethod->setAccessible(true);
$roundMethod = $ref->getMethod('roundUpToSlot');
$roundMethod->setAccessible(true);
$intervals = $intervalMethod->invoke($service, $company, $user, $day);
$overallStart = $intervals->min(fn(array $interval) => $interval['start']);
$overallEnd = $intervals->max(fn(array $interval) => $interval['end']);
$conflictWindowEnd = $overallEnd->addMinutes($durationMinutes);
$appointments = App\Models\Appointment::query()->where('company_id', $company->id)->where('user_id', $user->id)->where('status', '!=', 'cancelled')->where('start_time', '<', $conflictWindowEnd)->where('end_time', '>', $overallStart)->get(['start_time','end_time']);
$safeNow = Carbon\CarbonImmutable::now($timezone);
$safeEarliestTime = $roundMethod->invoke($service, $safeNow);
$result = [];
foreach ($intervals as $interval) {
    $intervalStart = $roundMethod->invoke($service, $interval['start']);
    $intervalEnd = $interval['end'];
    $latestStartTime = $roundMethod->invoke($service, $intervalEnd);
    $earliestStartTime = $safeEarliestTime->gt($intervalStart) ? $safeEarliestTime : $intervalStart;
    $loop = [];
    for ($slotStart = $earliestStartTime; $slotStart->lte($latestStartTime); $slotStart = $slotStart->addMinutes(30)) {
        $slotEnd = $slotStart->addMinutes($durationMinutes);
        $hasConflict = $appointments->contains(fn (App\Models\Appointment $appointment): bool => $slotStart->lt($appointment->end_time) && $slotEnd->gt($appointment->start_time));
        $loop[] = ['slot' => $slotStart->format('H:i'), 'end' => $slotEnd->format('H:i'), 'conflict' => $hasConflict];
        if (! $hasConflict) {
            $result[] = $slotStart->format('H:i');
        }
    }
    var_export(['intervalStart'=>$intervalStart->format('H:i:s'),'latestStartTime'=>$latestStartTime->format('H:i:s'),'earliestStartTime'=>$earliestStartTime->format('H:i:s'),'loop'=>$loop]);
}
echo PHP_EOL . 'RESULT=';
var_export($result);
