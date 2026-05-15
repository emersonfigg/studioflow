<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentGatewayNotConfiguredException extends RuntimeException
{
    public static function forCompany(): self
    {
        return new self('Configure uma integração de pagamento antes de vender assinaturas online.');
    }
}
