<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (App\Models\User::query()->select('id','name','email','role','company_id')->orderByDesc('id')->limit(10)->get() as $user) {
    echo json_encode($user->only(['id','name','email','role','company_id']), JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
