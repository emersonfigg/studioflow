<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

// All endpoints here are fixed local test endpoints; production URLs are never read.
$driver = getenv('FINANCE_TEST_DRIVER') ?: 'sqlite';
if (! in_array($driver, ['sqlite', 'pgsql'], true)) {
    throw new RuntimeException('Unsupported isolated test driver.');
}
$database = $driver === 'pgsql' ? 'studioflow_finance_test_'.getmypid().'_'.bin2hex(random_bytes(3)) : ':memory:';
if ($driver === 'pgsql') {
    $admin = new PDO('pgsql:host=127.0.0.1;port=55438;dbname=postgres', 'studioflow_test', '');
    $version = $admin->query('SELECT version()')->fetchColumn();
    if (! str_contains($version, 'PostgreSQL 18.')) {
        throw new RuntimeException('Not the isolated PostgreSQL 18 instance.');
    }
    $admin->exec('CREATE DATABASE "'.$database.'"');
    fwrite(STDERR, $version.PHP_EOL.'Isolated database: '.$database.PHP_EOL);
}
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)), 'DB_CONNECTION' => $driver,
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55438', 'DB_DATABASE' => $database, 'DB_USERNAME' => 'studioflow_test', 'DB_PASSWORD' => '', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'database', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/studioflow-finance-'.getmypid().'-no-cache.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Http::preventStrayRequests();
Artisan::call('migrate', ['--force' => true]);
// Existing RefreshDatabase tests reuse the pre-migrated test database. Never migrate:fresh.
RefreshDatabaseState::$migrated = true;
if ($driver === 'sqlite') {
    RefreshDatabaseState::$inMemoryConnections['sqlite'] = DB::connection()->getPdo();
}
HandleExceptions::flushState();
$app->flush();
Facade::clearResolvedInstances();
