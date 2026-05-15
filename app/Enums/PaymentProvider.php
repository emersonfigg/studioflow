<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Asaas = 'asaas';

    case GalaxyPay = 'galaxy_pay';

    case MercadoPago = 'mercado_pago';
}
