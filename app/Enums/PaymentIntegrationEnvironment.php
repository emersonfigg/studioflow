<?php

namespace App\Enums;

enum PaymentIntegrationEnvironment: string
{
    case Sandbox = 'sandbox';

    case Production = 'production';
}
