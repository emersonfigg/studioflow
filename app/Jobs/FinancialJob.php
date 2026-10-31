<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

abstract class FinancialJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onConnection(config('financial.queue_connection'))->onQueue(config('financial.queue'))->afterCommit();
    }

    public function backoff(): array
    {
        return [15, 60, 300, 900];
    }
}
