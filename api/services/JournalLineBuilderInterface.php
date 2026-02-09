<?php

namespace Services;

/**
 * Interface for journal line builders
 *
 * Each transaction type has a concrete implementation of this interface
 * that knows how to build the journal lines for that specific type.
 *
 * @category Service
 */
interface JournalLineBuilderInterface
{
    /**
     * Build journal lines for this transaction type
     *
     * @param array $data Transaction data (payslips, allocations, etc.)
     * @return array Array of journal lines in QuickBooks format
     */
    public function buildLines(array $data): array;

    /**
     * Get the transaction type this builder handles
     *
     * @return string Transaction type identifier
     */
    public function getTransactionType(): string;

    /**
     * Validate the input data for this transaction type
     *
     * @param array $data Transaction data to validate
     * @throws \Errors\ValidationException If validation fails
     * @return void
     */
    public function validateData(array $data): void;
}
