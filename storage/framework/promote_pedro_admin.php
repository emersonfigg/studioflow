<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::query()->where('email', 'pedro@teste.com')->first();
if ($user) {
    $user->update(['role' => 'admin']);
    echo json_encode($user->only(['id','name','email','role','company_id']), JSON_UNESCAPED_UNICODE);
}
