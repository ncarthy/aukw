<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Services\PayrollDataProcessor;
use Models\Payslip;

/**
 * Unit tests for PayrollDataProcessor
 *
 * Tests:
 * - processPayslips: clean payslip, data passthrough, empty array
 * - processPayslips: negative employee pension → info
 * - processPayslips: negative employer pension → warning
 * - processPayslips: high deductions (>50%) → warning; at/below 50% → no warning
 * - processPayslips: deduction check guard (netPay must be > 0)
 * - processPayslips: zero total pay → info
 * - processPayslips: negative total pay → info
 * - processPayslips: multiple payslips processed independently
 * - validateJournalEntry: balanced, unbalanced, tolerance boundary, empty/missing Line
 */
class PayrollDataProcessorTest extends TestCase
{
    // ==========================================
    // Helper
    // ==========================================

    private function makePayslip(array $values = []): Payslip
    {
        $p = Payslip::getInstance();
        $p->setPayrollNumber($values['payrollNumber'] ?? 1);
        $p->setEmployeeName($values['employeeName']   ?? 'Test Employee');

        if (array_key_exists('employeePension', $values)) {
            $p->setEmployeePension($values['employeePension']);
        }
        if (array_key_exists('employerPension', $values)) {
            $p->setEmployerPension($values['employerPension']);
        }
        if (array_key_exists('totalPay', $values)) {
            $p->setTotalPay($values['totalPay']);
        }
        if (array_key_exists('netPay', $values)) {
            $p->setNetPay($values['netPay']);
        }

        return $p;
    }

    // ==========================================
    // processPayslips — normal / data passthrough
    // ==========================================

    public function testNormalPayslipProducesNoMessages(): void
    {
        // 20% deductions (well under threshold), positive pensions
        $payslip  = $this->makePayslip(['totalPay' => 2000.0, 'netPay' => 1600.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertTrue($response->success);
        $this->assertEmpty($response->messages);
    }

    public function testReturnsPayslipArrayAsResponseData(): void
    {
        $payslips = [$this->makePayslip(['totalPay' => 1000.0, 'netPay' => 800.0])];
        $response = PayrollDataProcessor::processPayslips($payslips);

        $this->assertSame($payslips, $response->data);
    }

    public function testEmptyPayslipArrayProducesSuccessWithNoMessages(): void
    {
        $response = PayrollDataProcessor::processPayslips([]);

        $this->assertTrue($response->success);
        $this->assertEmpty($response->messages);
    }

    // ==========================================
    // processPayslips — negative employee pension
    // ==========================================

    public function testNegativeEmployeePensionAddsInfoMessage(): void
    {
        $payslip  = $this->makePayslip(['employeePension' => -50.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertTrue($response->hasMessagesOfType('info'));

        $info = array_values(array_filter($response->messages, fn($m) => $m['type'] === 'info'))[0];

        $this->assertStringContainsString('negative pension contribution', $info['message']);
        $this->assertSame(-50.0, $info['context']['value']);
        $this->assertSame('employeePension', $info['context']['field']);
    }

    public function testZeroEmployeePensionDoesNotAddInfoMessage(): void
    {
        // Set a non-zero totalPay so the zero-pay check does not also fire
        $payslip  = $this->makePayslip(['employeePension' => 0.0, 'totalPay' => 1000.0, 'netPay' => 800.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertFalse($response->hasMessagesOfType('info'));
    }

    // ==========================================
    // processPayslips — negative employer pension
    // ==========================================

    public function testNegativeEmployerPensionAddsWarningMessage(): void
    {
        $payslip  = $this->makePayslip(['employerPension' => -100.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertTrue($response->hasMessagesOfType('warning'));

        $warning = array_values(array_filter($response->messages, fn($m) => $m['type'] === 'warning'))[0];

        $this->assertStringContainsString('negative employer pension', $warning['message']);
        $this->assertSame(-100.0, $warning['context']['value']);
    }

    public function testZeroEmployerPensionDoesNotAddWarning(): void
    {
        $payslip  = $this->makePayslip(['employerPension' => 0.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertFalse($response->hasMessagesOfType('warning'));
    }

    // ==========================================
    // processPayslips — high deductions
    // ==========================================

    public function testDeductionsExactlyAt50PercentDoNotTriggerWarning(): void
    {
        // (1000 - 500) / 1000 * 100 = 50.0 — condition is > 50, not >= 50
        $payslip  = $this->makePayslip(['totalPay' => 1000.0, 'netPay' => 500.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertFalse($response->hasMessagesOfType('warning'));
    }

    public function testDeductionsAbove50PercentAddWarningWithPercentage(): void
    {
        // (1000 - 400) / 1000 * 100 = 60.0
        $payslip  = $this->makePayslip(['totalPay' => 1000.0, 'netPay' => 400.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertTrue($response->hasMessagesOfType('warning'));

        $warning = array_values(array_filter($response->messages, fn($m) => $m['type'] === 'warning'))[0];

        $this->assertStringContainsString('high deductions', $warning['message']);
        $this->assertEqualsWithDelta(60.0, $warning['context']['deductionPercentage'], 0.001);
    }

    public function testDeductionCheckIsSkippedWhenNetPayIsZero(): void
    {
        // Guard: both totalPay > 0 AND netPay > 0 required before deduction check
        $payslip  = $this->makePayslip(['totalPay' => 1000.0, 'netPay' => 0.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertFalse($response->hasMessagesOfType('warning'));
    }

    public function testDeductionCheckIsSkippedWhenTotalPayIsZero(): void
    {
        // totalPay = 0 triggers zero-pay info but NOT the deduction percentage check
        $payslip  = $this->makePayslip(['totalPay' => 0.0, 'netPay' => 0.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertFalse($response->hasMessagesOfType('warning'));
    }

    // ==========================================
    // processPayslips — zero / negative total pay
    // ==========================================

    public function testZeroTotalPayAddsInfoMessage(): void
    {
        $payslip  = $this->makePayslip(['totalPay' => 0.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertTrue($response->hasMessagesOfType('info'));

        $info = array_values(array_filter($response->messages, fn($m) => $m['type'] === 'info'))[0];

        $this->assertStringContainsString('zero total pay', $info['message']);
    }

    public function testNegativeTotalPayAddsInfoMessage(): void
    {
        $payslip  = $this->makePayslip(['totalPay' => -200.0]);
        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $this->assertTrue($response->hasMessagesOfType('info'));

        $info = array_values(array_filter($response->messages, fn($m) => $m['type'] === 'info'))[0];

        $this->assertStringContainsString('negative total pay', $info['message']);
    }

    // ==========================================
    // processPayslips — multiple payslips
    // ==========================================

    public function testEachPayslipIsCheckedIndependently(): void
    {
        $normal  = $this->makePayslip(['payrollNumber' => 1, 'totalPay' => 2000.0, 'netPay' => 1600.0]);
        $zeroPay = $this->makePayslip(['payrollNumber' => 2, 'totalPay' => 0.0]);

        $response = PayrollDataProcessor::processPayslips([$normal, $zeroPay]);

        // Only zeroPay triggers a message
        $this->assertCount(1, $response->messages);
        $this->assertSame('info', $response->messages[0]['type']);
    }

    public function testPayslipsWithMultipleIssuesGenerateMultipleMessages(): void
    {
        // Negative employee pension (info) + negative total pay (info)
        $payslip = $this->makePayslip([
            'employeePension' => -10.0,
            'totalPay'        => -50.0,
        ]);

        $response = PayrollDataProcessor::processPayslips([$payslip]);

        $infos = array_filter($response->messages, fn($m) => $m['type'] === 'info');
        $this->assertCount(2, $infos);
    }

    // ==========================================
    // validateJournalEntry
    // ==========================================

    public function testBalancedJournalProducesNoWarning(): void
    {
        $journal = [
            'Line' => [
                ['Amount' => 500.0, 'JournalEntryLineDetail' => ['PostingType' => 'Debit']],
                ['Amount' => 500.0, 'JournalEntryLineDetail' => ['PostingType' => 'Credit']],
            ],
        ];

        $processor = new PayrollDataProcessor();
        $response  = $processor->validateJournalEntry($journal);

        $this->assertFalse($response->hasMessagesOfType('warning'));
    }

    public function testUnbalancedJournalAddsWarningWithDifference(): void
    {
        $journal = [
            'Line' => [
                ['Amount' => 600.0, 'JournalEntryLineDetail' => ['PostingType' => 'Debit']],
                ['Amount' => 500.0, 'JournalEntryLineDetail' => ['PostingType' => 'Credit']],
            ],
        ];

        $processor = new PayrollDataProcessor();
        $response  = $processor->validateJournalEntry($journal);

        $this->assertTrue($response->hasMessagesOfType('warning'));

        $warning = $response->messages[0];
        $this->assertStringContainsString('unbalanced', $warning['message']);
        $this->assertEqualsWithDelta(100.0, $warning['context']['difference'], 0.001);
        $this->assertEqualsWithDelta(600.0, $warning['context']['debits'],     0.001);
        $this->assertEqualsWithDelta(500.0, $warning['context']['credits'],    0.001);
    }

    public function testDifferenceExactlyAtToleranceBoundaryProducesNoWarning(): void
    {
        // Tolerance is > 0.01; a difference of exactly 0.01 should NOT warn
        $journal = [
            'Line' => [
                ['Amount' => 500.01, 'JournalEntryLineDetail' => ['PostingType' => 'Debit']],
                ['Amount' => 500.00, 'JournalEntryLineDetail' => ['PostingType' => 'Credit']],
            ],
        ];

        $processor = new PayrollDataProcessor();
        $response  = $processor->validateJournalEntry($journal);

        $this->assertFalse($response->hasMessagesOfType('warning'));
    }

    public function testEmptyLineArrayIsConsideredBalanced(): void
    {
        $journal   = ['Line' => []];
        $processor = new PayrollDataProcessor();

        $this->assertFalse($processor->validateJournalEntry($journal)->hasMessagesOfType('warning'));
    }

    public function testMissingLineKeyIsConsideredBalanced(): void
    {
        $journal   = [];
        $processor = new PayrollDataProcessor();

        $this->assertFalse($processor->validateJournalEntry($journal)->hasMessagesOfType('warning'));
    }
}
