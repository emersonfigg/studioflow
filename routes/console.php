<?php

use App\Console\Commands\CreateSuperAdminCommand;
use App\Console\Commands\ExpireUnpaidBookingPaymentsCommand;
use App\Console\Commands\MigrateLocalMediaToS3Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::addCommands([
    CreateSuperAdminCommand::class,
    ExpireUnpaidBookingPaymentsCommand::class,
    MigrateLocalMediaToS3Command::class,
]);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
