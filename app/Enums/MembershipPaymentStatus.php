<?php

namespace App\Enums;

enum MembershipPaymentStatus: string
{
    case Pending = 'pending';

    case Paid = 'paid';

    case Overdue = 'overdue';

    case Canceled = 'canceled';

    case Failed = 'failed';

    case Refunded = 'refunded';
}
