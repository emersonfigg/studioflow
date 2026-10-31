<?php

// Explicit process-level isolation, independent of the developer's .env or shell.
foreach ([
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'DB_URL' => '', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/studioflow-foundation-no-config-cache.php',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
