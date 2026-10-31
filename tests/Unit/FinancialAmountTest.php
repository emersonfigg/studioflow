<?php

namespace Tests\Unit;

use App\Services\Payments\FinancialAmount;
use PHPUnit\Framework\TestCase;

class FinancialAmountTest extends TestCase
{
    public function test_exact_cents_and_invalid_financial_values(): void
    {
        $this->assertSame(5001, FinancialAmount::cents('50.01'));
        $this->assertSame(5010, FinancialAmount::cents('50.1'));
        $this->assertSame(999999999999, FinancialAmount::cents('9999999999.99'));
        $this->assertSame(0, FinancialAmount::cents('0.00'));
        foreach ([true, [], null, '-1', '1e3', '1.001', 'NaN', '10000000000'] as $value) {
            $this->assertNull(FinancialAmount::cents($value));
        }
    }
}
