<?php

namespace Services;

use Config\PayrollConfig;
use Errors\ValidationException;

/**
 * Factory for creating journal lines based on transaction type
 *
 * This factory uses the Strategy pattern to delegate line creation to
 * transaction-type-specific builders. This eliminates code duplication
 * across journal models and centralizes journal line creation logic.
 *
 * Usage:
 *   $factory = new JournalLineFactory();
 *   $lines = $factory->buildLines('employee', $payrollData);
 *
 * @category Service
 */
class JournalLineFactory
{
    /**
     * Registered line builders (transaction type => builder instance)
     *
     * @var array<string, JournalLineBuilderInterface>
     */
    private array $builders = [];

    /**
     * Constructor - registers all available builders
     */
    public function __construct()
    {
        $this->registerDefaultBuilders();
    }

    /**
     * Register default builders for all transaction types
     *
     * @return void
     */
    private function registerDefaultBuilders(): void
    {
        $this->registerBuilder(new EmployeeJournalLineBuilder());
        $this->registerBuilder(new EmployerNILineBuilder());
        $this->registerBuilder(new PensionLineBuilder());
        $this->registerBuilder(new ShopPayrollLineBuilder());
    }

    /**
     * Register a line builder
     *
     * @param JournalLineBuilderInterface $builder The builder to register
     * @return self For method chaining
     */
    public function registerBuilder(JournalLineBuilderInterface $builder): self
    {
        $this->builders[$builder->getTransactionType()] = $builder;
        return $this;
    }

    /**
     * Get a builder for a specific transaction type
     *
     * @param string $transactionType Transaction type identifier
     * @return JournalLineBuilderInterface The builder for this transaction type
     * @throws ValidationException If transaction type is invalid or no builder registered
     */
    public function getBuilder(string $transactionType): JournalLineBuilderInterface
    {
        // Validate transaction type
        if (!PayrollConfig::isValidTransactionType($transactionType)) {
            throw ValidationException::invalidTransactionType(
                $transactionType,
                array_values(PayrollConfig::TRANSACTION_TYPES)
            );
        }

        // Check if builder is registered
        if (!isset($this->builders[$transactionType])) {
            throw new ValidationException(
                "No builder registered for transaction type: {$transactionType}",
                ValidationException::ERROR_INVALID_TRANSACTION_TYPE,
                'transactionType',
                $transactionType,
                ['registeredTypes' => array_keys($this->builders)]
            );
        }

        return $this->builders[$transactionType];
    }

    /**
     * Build journal lines for a specific transaction type
     *
     * @param string $transactionType Transaction type identifier
     * @param array $data Transaction data (payslips, allocations, etc.)
     * @param bool $validate Whether to validate data before building (default: true)
     * @return array Array of journal lines in QuickBooks format
     * @throws ValidationException If validation fails
     */
    public function buildLines(string $transactionType, array $data, bool $validate = true): array
    {
        $builder = $this->getBuilder($transactionType);

        // Validate data if requested
        if ($validate) {
            $builder->validateData($data);
        }

        // Build lines
        return $builder->buildLines($data);
    }

    /**
     * Build a complete journal entry for a specific transaction type
     *
     * This method creates the full journal entry structure including metadata
     *
     * @param string $transactionType Transaction type identifier
     * @param array $data Transaction data
     * @param string $txnDate Transaction date (Y-m-d format)
     * @param string $docNumber Document number
     * @param bool $validate Whether to validate data before building (default: true)
     * @return array Complete journal entry array ready for QuickBooks
     * @throws ValidationException If validation fails
     */
    public function buildJournalEntry(
        string $transactionType,
        array $data,
        string $txnDate,
        string $docNumber,
        bool $validate = true
    ): array {
        // Build lines
        $lines = $this->buildLines($transactionType, $data, $validate);

        // Create journal entry structure
        $journalEntry = [
            'TxnDate' => $txnDate,
            'DocNumber' => $docNumber,
            'Line' => $lines,
            'TotalAmt' => 0 // QuickBooks calculates this automatically
        ];

        return $journalEntry;
    }

    /**
     * Validate journal entry balance (debits = credits)
     *
     * @param array $lines Array of journal lines
     * @return bool True if balanced
     * @throws ValidationException If journal is not balanced
     */
    public function validateBalance(array $lines): bool
    {
        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            $amount = $line['Amount'];
            $postingType = $line['JournalEntryLineDetail']['PostingType'];

            if ($postingType === 'Debit') {
                $debits += $amount;
            } elseif ($postingType === 'Credit') {
                $credits += $amount;
            }
        }

        $balance = $debits - $credits;

        if (!PayrollConfig::isBalanced($balance)) {
            throw ValidationException::unbalancedJournal(
                $balance,
                [
                    'debits' => $debits,
                    'credits' => $credits,
                    'lineCount' => count($lines)
                ]
            );
        }

        return true;
    }

    /**
     * Get all registered transaction types
     *
     * @return array<string> Array of transaction type identifiers
     */
    public function getRegisteredTransactionTypes(): array
    {
        return array_keys($this->builders);
    }

    /**
     * Check if a builder is registered for a transaction type
     *
     * @param string $transactionType Transaction type identifier
     * @return bool True if builder is registered
     */
    public function hasBuilder(string $transactionType): bool
    {
        return isset($this->builders[$transactionType]);
    }
}
