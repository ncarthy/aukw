<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Models\ApiResponse;

/**
 * Unit tests for ApiResponse
 *
 * Tests:
 * - Constructor defaults and explicit values
 * - addInfo / addWarning / addSuccess / addError message structure
 * - Fluent interface (chaining)
 * - hasMessagesOfType
 * - toArray / toJson
 */
class ApiResponseTest extends TestCase
{
    // ==========================================
    // Constructor
    // ==========================================

    public function testConstructorDefaultsToSuccessWithNullDataAndNoMessages(): void
    {
        $response = new ApiResponse();

        $this->assertTrue($response->success);
        $this->assertNull($response->data);
        $this->assertSame([], $response->messages);
    }

    public function testConstructorStoresExplicitValues(): void
    {
        $data = ['key' => 'value'];
        $messages = [['type' => 'error', 'message' => 'oops', 'context' => [], 'timestamp' => '2024-01-01 00:00:00']];

        $response = new ApiResponse(false, $data, $messages);

        $this->assertFalse($response->success);
        $this->assertSame($data, $response->data);
        $this->assertCount(1, $response->messages);
    }

    // ==========================================
    // addInfo
    // ==========================================

    public function testAddInfoAppendsMessageWithCorrectType(): void
    {
        $response = new ApiResponse();
        $response->addInfo('Something happened', ['field' => 'value']);

        $this->assertCount(1, $response->messages);
        $this->assertSame('info', $response->messages[0]['type']);
        $this->assertSame('Something happened', $response->messages[0]['message']);
        $this->assertSame(['field' => 'value'], $response->messages[0]['context']);
        $this->assertArrayHasKey('timestamp', $response->messages[0]);
    }

    public function testAddInfoWithNoContextDefaultsToEmptyArray(): void
    {
        $response = new ApiResponse();
        $response->addInfo('msg');

        $this->assertSame([], $response->messages[0]['context']);
    }

    // ==========================================
    // addWarning
    // ==========================================

    public function testAddWarningAppendsMessageWithWarningType(): void
    {
        $response = new ApiResponse();
        $response->addWarning('Watch out', ['detail' => 'x']);

        $this->assertCount(1, $response->messages);
        $this->assertSame('warning', $response->messages[0]['type']);
        $this->assertSame('Watch out', $response->messages[0]['message']);
    }

    // ==========================================
    // addSuccess
    // ==========================================

    public function testAddSuccessAppendsMessageWithSuccessType(): void
    {
        $response = new ApiResponse();
        $response->addSuccess('All done');

        $this->assertCount(1, $response->messages);
        $this->assertSame('success', $response->messages[0]['type']);
        $this->assertSame('All done', $response->messages[0]['message']);
    }

    // ==========================================
    // addError
    // ==========================================

    public function testAddErrorAppendsMessageWithErrorType(): void
    {
        $response = new ApiResponse();
        $response->addError('Something broke');

        $this->assertCount(1, $response->messages);
        $this->assertSame('error', $response->messages[0]['type']);
        $this->assertSame('Something broke', $response->messages[0]['message']);
    }

    // ==========================================
    // Fluent interface
    // ==========================================

    public function testAllAddMethodsReturnTheSameInstance(): void
    {
        $response = new ApiResponse();

        $this->assertSame($response, $response->addInfo('a'));
        $this->assertSame($response, $response->addWarning('b'));
        $this->assertSame($response, $response->addSuccess('c'));
        $this->assertSame($response, $response->addError('d'));
    }

    public function testChainingAccumulatesMessagesInOrder(): void
    {
        $response = (new ApiResponse())
            ->addInfo('first')
            ->addWarning('second')
            ->addError('third');

        $this->assertCount(3, $response->messages);
        $this->assertSame('info',    $response->messages[0]['type']);
        $this->assertSame('warning', $response->messages[1]['type']);
        $this->assertSame('error',   $response->messages[2]['type']);
    }

    // ==========================================
    // hasMessagesOfType
    // ==========================================

    public function testHasMessagesOfTypeReturnsTrueWhenTypeExists(): void
    {
        $response = (new ApiResponse())->addWarning('Watch out');

        $this->assertTrue($response->hasMessagesOfType('warning'));
    }

    public function testHasMessagesOfTypeReturnsFalseWhenNoMatchingType(): void
    {
        $response = (new ApiResponse())->addInfo('Info only');

        $this->assertFalse($response->hasMessagesOfType('warning'));
    }

    public function testHasMessagesOfTypeReturnsFalseForEmptyMessages(): void
    {
        $response = new ApiResponse();

        $this->assertFalse($response->hasMessagesOfType('info'));
    }

    public function testHasMessagesOfTypeDoesNotMatchSubstrings(): void
    {
        $response = (new ApiResponse())->addInfo('Informational');

        // 'warn' is not a type, only 'warning' is
        $this->assertFalse($response->hasMessagesOfType('warn'));
    }

    public function testHasMessagesOfTypeOnlyMatchesSpecifiedType(): void
    {
        $response = (new ApiResponse())
            ->addInfo('info msg')
            ->addSuccess('success msg');

        $this->assertTrue($response->hasMessagesOfType('info'));
        $this->assertTrue($response->hasMessagesOfType('success'));
        $this->assertFalse($response->hasMessagesOfType('warning'));
        $this->assertFalse($response->hasMessagesOfType('error'));
    }

    // ==========================================
    // toArray
    // ==========================================

    public function testToArrayReturnsCorrectStructure(): void
    {
        $data = ['payslip' => 123];
        $response = new ApiResponse(true, $data);
        $response->addInfo('Test info');

        $array = $response->toArray();

        $this->assertSame(true,   $array['success']);
        $this->assertSame($data,  $array['data']);
        $this->assertCount(1,     $array['messages']);
    }

    public function testToArrayWithFailureAndNullData(): void
    {
        $response = new ApiResponse(false);

        $array = $response->toArray();

        $this->assertFalse($array['success']);
        $this->assertNull($array['data']);
        $this->assertSame([], $array['messages']);
    }

    // ==========================================
    // toJson
    // ==========================================

    public function testToJsonReturnsValidJsonString(): void
    {
        $response = new ApiResponse(true, ['key' => 'value']);
        $response->addInfo('hello');

        $json = $response->toJson();
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded, 'toJson() should return valid JSON');
        $this->assertTrue($decoded['success']);
        $this->assertSame(['key' => 'value'], $decoded['data']);
        $this->assertCount(1, $decoded['messages']);
    }

    public function testToJsonRoundTripsDataFaithfully(): void
    {
        $data = ['employee' => 'Alice', 'amount' => 1234.56];
        $response = new ApiResponse(false, $data);

        $decoded = json_decode($response->toJson(), true);

        $this->assertSame($data['employee'], $decoded['data']['employee']);
        $this->assertSame($data['amount'],   $decoded['data']['amount']);
    }
}
