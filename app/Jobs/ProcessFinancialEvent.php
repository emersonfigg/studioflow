<?php

namespace App\Jobs;

use App\Services\FinancialEventProcessor;

class ProcessFinancialEvent extends FinancialJob
{
    public function __construct(public readonly int $eventId)
    {
        parent::__construct();
    }

    public function handle(FinancialEventProcessor $processor): void
    {
        $processor->process($this->eventId);
    }
}
