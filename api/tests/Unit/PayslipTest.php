<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Models\Payslip;

/**
 * Unit tests for Payslip
 *
 * Tests:
 * - isBalanced / getImbalanceAmount
 *   Balance condition (derived from the formula in getImbalanceAmount):
 *     debits  = netPay − paye − employeeNI − otherDeductions − studentLoan
 *               + employeePension + salarySacrifice + employerNI
 *     credits = totalPay + employerNI
 *     balanced ↔ debits == credits
 *
 * - addTo* accumulator methods: accumulation, rounding, fluent chaining
 */
class PayslipTest extends TestCase
{
    // ==========================================
    // Helper
    // ==========================================

    private function makePayslip(array $fields = []): Payslip
    {
        $p = Payslip::getInstance();
        foreach ($fields as $field => $value) {
            $setter = 'set' . ucfirst($field);
            $p->$setter($value);
        }
        return $p;
    }

    // ==========================================
    // isBalanced / getImbalanceAmount
    // ==========================================

    public function testAllZeroFieldsIsBalanced(): void
    {
        $payslip = $this->makePayslip();

        $this->assertNull($payslip->getImbalanceAmount());
        $this->assertTrue($payslip->isBalanced());
    }

    public function testTotalPayEqualToNetPayIsBalanced(): void
    {
        // debits = 2000, credits = 2000
        $payslip = $this->makePayslip(['totalPay' => 2000.0, 'netPay' => 2000.0]);

        $this->assertNull($payslip->getImbalanceAmount());
        $this->assertTrue($payslip->isBalanced());
    }

    public function testEmployerNIAppearingOnBothSidesDoesNotAffectBalance(): void
    {
        // debits = 1000 + 300 = 1300, credits = 1000 + 300 = 1300
        $payslip = $this->makePayslip([
            'totalPay'   => 1000.0,
            'netPay'     => 1000.0,
            'employerNI' => 300.0,
        ]);

        $this->assertNull($payslip->getImbalanceAmount());
        $this->assertTrue($payslip->isBalanced());
    }

    public function testPayeReducesDebitsCreatingImbalance(): void
    {
        // debits = 2000 − 400 = 1600, credits = 2000 → imbalance = −400
        $payslip = $this->makePayslip([
            'totalPay' => 2000.0,
            'netPay'   => 2000.0,
            'paye'     => 400.0,
        ]);

        $this->assertFalse($payslip->isBalanced());
        $this->assertSame(-400.0, $payslip->getImbalanceAmount());
    }

    public function testTotalPayAloneWithoutMatchingNetPayIsUnbalanced(): void
    {
        // debits = 0, credits = 500 → imbalance = −500
        $payslip = $this->makePayslip(['totalPay' => 500.0]);

        $this->assertFalse($payslip->isBalanced());
        $this->assertSame(-500.0, $payslip->getImbalanceAmount());
    }

    public function testGetImbalanceAmountIsRoundedToTwoDecimalPlaces(): void
    {
        // debits = 1000.12, credits = 1000.0 → imbalance = 0.12
        $payslip = $this->makePayslip(['totalPay' => 1000.0, 'netPay' => 1000.12]);

        $this->assertSame(0.12, $payslip->getImbalanceAmount());
    }

    public function testIsBalancedReturnsFalseWhenImbalanceAmountIsNotNull(): void
    {
        $payslip = $this->makePayslip(['totalPay' => 100.0]);

        $this->assertNotNull($payslip->getImbalanceAmount());
        $this->assertFalse($payslip->isBalanced());
    }

    // ==========================================
    // addTo* accumulator methods
    // ==========================================

    public function testAddToEmployeePensionAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToEmployeePension(100.0)->addToEmployeePension(50.0)->addToEmployeePension(25.0);

        $this->assertSame(175.0, $p->getEmployeePension());
    }

    public function testAddToEmployerPensionAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToEmployerPension(200.0)->addToEmployerPension(300.0);

        $this->assertSame(500.0, $p->getEmployerPension());
    }

    public function testAddToEmployerNIAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToEmployerNI(150.0)->addToEmployerNI(75.0);

        $this->assertSame(225.0, $p->getEmployerNI());
    }

    public function testAddToNetPayRoundsToTwoDecimalPlaces(): void
    {
        $p = Payslip::getInstance();
        $p->addToNetPay(10.123);

        $this->assertSame(10.12, $p->getNetPay());
    }

    public function testAddToTotalPaySupportsFluentChaining(): void
    {
        $p = Payslip::getInstance()
            ->addToTotalPay(500.0)
            ->addToTotalPay(250.0);

        $this->assertSame(750.0, $p->getTotalPay());
    }

    public function testAddToPAYEAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToPAYE(200.0)->addToPAYE(100.0);

        $this->assertSame(300.0, $p->getPAYE());
    }

    public function testAddToStudentLoanAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToStudentLoan(50.0)->addToStudentLoan(25.0);

        $this->assertSame(75.0, $p->getStudentLoan());
    }

    public function testAddToOtherDeductionsAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToOtherDeductions(30.0)->addToOtherDeductions(20.0);

        $this->assertSame(50.0, $p->getOtherDeductions());
    }

    public function testAddToSalarySacrificeAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToSalarySacrifice(100.0)->addToSalarySacrifice(50.0);

        $this->assertSame(150.0, $p->getSalarySacrifice());
    }

    public function testAddToEmployeeNIAccumulatesCorrectly(): void
    {
        $p = Payslip::getInstance();
        $p->addToEmployeeNI(80.0)->addToEmployeeNI(40.0);

        $this->assertSame(120.0, $p->getEmployeeNI());
    }
}
