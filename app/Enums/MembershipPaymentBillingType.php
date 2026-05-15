<?php

namespace App\Enums;

enum MembershipPaymentBillingType: string
{
    case Pix = 'pix';

    case CreditCard = 'credit_card';

    case Boleto = 'boleto';

    case Unknown = 'unknown';
}
