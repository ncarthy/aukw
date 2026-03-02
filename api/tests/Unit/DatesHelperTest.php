<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Core\DatesHelper;
use DateTimeImmutable;
use Exception;

/**
 * Unit tests for DatesHelper
 *
 * Tests:
 * - validateDate: valid/invalid formats, non-existent dates, custom formats
 * - sanitizeDateValues: all four null-combination branches, invalid input exceptions
 * - previousPeriod: date shift by one year
 * - currentDateTime: format validation
 */
class DatesHelperTest extends TestCase
{
    // ==========================================
    // validateDate
    // ==========================================

    public function testValidateDateAcceptsWellFormedDate(): void
    {
        $this->assertTrue(DatesHelper::validateDate('2024-03-15'));
    }

    public function testValidateDateAcceptsFirstAndLastDayOfYear(): void
    {
        $this->assertTrue(DatesHelper::validateDate('2024-01-01'));
        $this->assertTrue(DatesHelper::validateDate('2024-12-31'));
    }

    public function testValidateDateAcceptsLeapDay(): void
    {
        $this->assertTrue(DatesHelper::validateDate('2024-02-29'));
    }

    public function testValidateDateRejectsDayMonthYearFormat(): void
    {
        $this->assertFalse(DatesHelper::validateDate('15-03-2024'));
    }

    public function testValidateDateRejectsSlashSeparators(): void
    {
        $this->assertFalse(DatesHelper::validateDate('2024/03/15'));
    }

    public function testValidateDateRejectsNonExistentDate(): void
    {
        $this->assertFalse(DatesHelper::validateDate('2024-02-30'));
    }

    public function testValidateDateRejectsLeapDayInNonLeapYear(): void
    {
        $this->assertFalse(DatesHelper::validateDate('2023-02-29'));
    }

    public function testValidateDateRejectsEmptyString(): void
    {
        $this->assertFalse(DatesHelper::validateDate(''));
    }

    public function testValidateDateAcceptsCustomFormat(): void
    {
        $this->assertTrue(DatesHelper::validateDate('15/03/2024', 'd/m/Y'));
    }

    public function testValidateDateRejectsDateThatDoesNotMatchCustomFormat(): void
    {
        $this->assertFalse(DatesHelper::validateDate('2024-03-15', 'd/m/Y'));
    }

    // ==========================================
    // sanitizeDateValues — both null
    // ==========================================

    public function testBothNullReturnsTodayAsEndAndOneYearAgoAsStart(): void
    {
        $today         = (new DateTimeImmutable())->format('Y-m-d');
        $expectedStart = (new DateTimeImmutable($today))
            ->modify('-1 year')
            ->modify('+1 day')
            ->format('Y-m-d');

        [$start, $end] = DatesHelper::sanitizeDateValues(null, null);

        $this->assertSame($expectedStart, $start);
        $this->assertSame($today,         $end);
    }

    // ==========================================
    // sanitizeDateValues — start null, end provided
    // ==========================================

    public function testStartNullDerivesStartAsOneYearBeforeEnd(): void
    {
        $enddate       = '2024-06-30';
        $expectedStart = (new DateTimeImmutable($enddate))
            ->modify('-1 year')
            ->modify('+1 day')
            ->format('Y-m-d');

        [$start, $end] = DatesHelper::sanitizeDateValues(null, $enddate);

        $this->assertSame($expectedStart, $start);
        $this->assertSame($enddate,       $end);
    }

    public function testStartNullWithInvalidEndThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Enddate is in the wrong format');

        DatesHelper::sanitizeDateValues(null, '30/06/2024');
    }

    // ==========================================
    // sanitizeDateValues — end null, start provided
    // ==========================================

    public function testEndNullReturnsTodayAsEndAndStartUnchanged(): void
    {
        $today     = (new DateTimeImmutable())->format('Y-m-d');
        $startdate = '2024-01-01';

        [$start, $end] = DatesHelper::sanitizeDateValues($startdate, null);

        $this->assertSame($startdate, $start);
        $this->assertSame($today,     $end);
    }

    public function testInvalidStartWithNullEndThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Startdate is in the wrong format');

        DatesHelper::sanitizeDateValues('01/01/2024', null);
    }

    // ==========================================
    // sanitizeDateValues — both provided
    // ==========================================

    public function testBothValidReturnsBothDatesUnchanged(): void
    {
        [$start, $end] = DatesHelper::sanitizeDateValues('2024-01-01', '2024-12-31');

        $this->assertSame('2024-01-01', $start);
        $this->assertSame('2024-12-31', $end);
    }

    public function testInvalidStartWithValidEndThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Startdate is in the wrong format');

        DatesHelper::sanitizeDateValues('31-01-2024', '2024-12-31');
    }

    public function testValidStartWithInvalidEndThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Enddate is in the wrong format');

        DatesHelper::sanitizeDateValues('2024-01-01', 'bad-date');
    }

    // ==========================================
    // previousPeriod
    // ==========================================

    public function testPreviousPeriodShiftsBothDatesByOneYear(): void
    {
        [$start, $end] = DatesHelper::previousPeriod('2024-04-01', '2025-03-31');

        $this->assertSame('2023-04-01', $start);
        $this->assertSame('2024-03-31', $end);
    }

    public function testPreviousPeriodHandlesCalendarYearBoundary(): void
    {
        [$start, $end] = DatesHelper::previousPeriod('2025-01-01', '2025-12-31');

        $this->assertSame('2024-01-01', $start);
        $this->assertSame('2024-12-31', $end);
    }

    public function testPreviousPeriodOfTradingYearRange(): void
    {
        // Trading year: Oct 2024 → Sep 2025, previous: Oct 2023 → Sep 2024
        [$start, $end] = DatesHelper::previousPeriod('2024-10-01', '2025-09-30');

        $this->assertSame('2023-10-01', $start);
        $this->assertSame('2024-09-30', $end);
    }

    // ==========================================
    // currentDateTime
    // ==========================================

    public function testCurrentDateTimeReturnsStringInMySqlFormat(): void
    {
        $datetime = DatesHelper::currentDateTime();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $datetime,
            'currentDateTime() should return a MySQL-format datetime (YYYY-MM-DD HH:MM:SS)'
        );
    }
}
