<?php

namespace App\Console\Commands;

use App\Services\BookingPaymentService;
use Illuminate\Console\Command;

class ExpireUnpaidBookingPaymentsCommand extends Command
{
    protected $signature = 'bookings:expire-unpaid-payments';

    protected $description = 'Expira pagamentos pendentes do agendamento publico e libera horarios vencidos.';

    public function handle(BookingPaymentService $bookingPaymentService): int
    {
        $expired = $bookingPaymentService->expireUnpaidPayments();

        $this->info("Pagamentos expirados: {$expired}");

        return self::SUCCESS;
    }
}
