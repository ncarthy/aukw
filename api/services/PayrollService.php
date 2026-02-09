<?php

namespace Services;

use Config\PayrollConfig;
use Errors\PayrollException;
use Errors\ValidationException;
use Errors\StaffologyApiException;
use Errors\QuickBooksApiException;
use Models\QuickbooksPayrollJournal;
use Models\QuickbooksEmployerNIJournal;
use Models\QuickbooksEnterprisesJournal;
use Validators\PayrollValidator;

/**
 * Service for orchestrating payroll journal creation workflow
 *
 * This service provides a high-level API for the complete payroll process:
 * 1. Validate input data
 * 2. Transform payslip data for journal creation
 * 3. Create journal entries using the factory
 * 4. Post to QuickBooks
 * 5. Handle errors consistently
 *
 * Usage:
 *   $service = new PayrollService();
 *   $result = $service->createEmployeeJournal($payrollData, $realmId);
 *
 * @category Service
 */
class PayrollService
{
    /**
     * Journal line factory
     *
     * @var JournalLineFactory
     */
    private JournalLineFactory $factory;

    /**
     * Validator
     *
     * @var PayrollValidator
     */
    private PayrollValidator $validator;

    /**
     * Constructor
     *
     * @param JournalLineFactory|null $factory Optional factory instance
     * @param PayrollValidator|null $validator Optional validator instance
     */
    public function __construct(
        ?JournalLineFactory $factory = null,
        ?PayrollValidator $validator = null
    ) {
        $this->factory = $factory ?? new JournalLineFactory();
        $this->validator = $validator ?? new PayrollValidator();
    }

    /**
     * Create employee journal entry
     *
     * @param array $data Employee payroll data
     * @param string $realmId QuickBooks realm ID
     * @param string $txnDate Transaction date (Y-m-d format)
     * @param string $docNumber Document number
     * @param bool $useFactory Whether to use factory approach (default: true)
     * @return array Result with id, date, label
     * @throws ValidationException If validation fails
     * @throws QuickBooksApiException If QuickBooks API call fails
     */
    public function createEmployeeJournal(
        array $data,
        string $realmId,
        string $txnDate,
        string $docNumber,
        bool $useFactory = true
    ): array {
        // Validate realm ID
        $this->validator->validateRealmId($realmId);

        // Validate date
        $this->validator->validateDate($txnDate, 'txnDate');

        // Create journal model
        $journal = QuickbooksPayrollJournal::getInstance()
            ->setRealmID($realmId)
            ->setTxnDate($txnDate)
            ->setDocNumber($docNumber);

        // Inject factory and validator if using factory approach
        if ($useFactory) {
            $journal->setFactory($this->factory)
                    ->setValidator($this->validator);
        }

        // Set payroll data
        $journal->setQuickbooksEmployeeId($data['quickbooksEmployeeId'])
                ->setGrossSalary($data['grossSalary'])
                ->setNetSalary($data['netSalary'] ?? 0)
                ->setPAYE($data['paye'] ?? 0)
                ->setEmployeeNI($data['employeeNI'] ?? 0)
                ->setSalarySacrifice($data['salarySacrifice'] ?? 0)
                ->setEmployeePension($data['employeePensionContribution'] ?? 0)
                ->setStudentLoan($data['studentLoan'] ?? 0)
                ->setOtherDeduction($data['otherDeduction'] ?? 0);

        // Validate journal balance (legacy method)
        if (!$journal->validate()) {
            throw ValidationException::unbalancedJournal(0, ['message' => 'Employee journal validation failed']);
        }

        // Create journal entry
        return $journal->create_employee_journal($useFactory);
    }

    /**
     * Create employer NI journal entry
     *
     * @param array $entries Array of employer NI entries
     * @param string $realmId QuickBooks realm ID
     * @param string $txnDate Transaction date (Y-m-d format)
     * @param string $docNumber Document number
     * @param bool $useFactory Whether to use factory approach (default: true)
     * @return array Result with id, date, label
     * @throws ValidationException If validation fails
     * @throws QuickBooksApiException If QuickBooks API call fails
     */
    public function createEmployerNIJournal(
        array $entries,
        string $realmId,
        string $txnDate,
        string $docNumber,
        bool $useFactory = true
    ): array {
        // Validate realm ID
        $this->validator->validateRealmId($realmId);

        // Validate date
        $this->validator->validateDate($txnDate, 'txnDate');

        // Validate entries if using factory
        if ($useFactory) {
            $builder = $this->factory->getBuilder(PayrollConfig::TRANSACTION_TYPES['EMPLOYER_NI']);
            $builder->validateData(['entries' => $entries]);
        }

        // Create journal model
        $journal = QuickbooksEmployerNIJournal::getInstance()
            ->setRealmID($realmId)
            ->setTxnDate($txnDate)
            ->setDocNumber($docNumber);

        // Inject factory and validator if using factory approach
        if ($useFactory) {
            $journal->setFactory($this->factory)
                    ->setValidator($this->validator);
        }

        // Create journal entry
        return $journal->create_employerNI_journal($entries, $useFactory);
    }

    /**
     * Create shop payroll (enterprises) journal entry
     *
     * @param array $entries Array of shop payroll entries
     * @param string $realmId QuickBooks realm ID
     * @param string $txnDate Transaction date (Y-m-d format)
     * @param string $docNumber Document number
     * @param bool $useFactory Whether to use factory approach (default: true)
     * @return array Result with id, date, label
     * @throws ValidationException If validation fails
     * @throws QuickBooksApiException If QuickBooks API call fails
     */
    public function createShopPayrollJournal(
        array $entries,
        string $realmId,
        string $txnDate,
        string $docNumber,
        bool $useFactory = true
    ): array {
        // Validate realm ID
        $this->validator->validateRealmId($realmId);

        // Validate date
        $this->validator->validateDate($txnDate, 'txnDate');

        // Validate entries if using factory
        if ($useFactory) {
            $builder = $this->factory->getBuilder(PayrollConfig::TRANSACTION_TYPES['SHOP_PAYROLL']);
            $builder->validateData(['entries' => $entries]);
        }

        // Create journal model
        $journal = QuickbooksEnterprisesJournal::getInstance()
            ->setRealmID($realmId)
            ->setTxnDate($txnDate)
            ->setDocNumber($docNumber);

        // Inject factory and validator if using factory approach
        if ($useFactory) {
            $journal->setFactory($this->factory)
                    ->setValidator($this->validator);
        }

        // Create journal entry
        return $journal->create_enterprises_journal($entries, $useFactory);
    }

    /**
     * Create multiple employee journals in batch
     *
     * @param array $employees Array of employee payroll data
     * @param string $realmId QuickBooks realm ID
     * @param string $txnDate Transaction date (Y-m-d format)
     * @param string $docNumberPrefix Document number prefix
     * @param bool $useFactory Whether to use factory approach (default: true)
     * @param bool $stopOnError Whether to stop on first error (default: false)
     * @return array Results with 'success' and 'errors' keys
     */
    public function createEmployeeJournalsBatch(
        array $employees,
        string $realmId,
        string $txnDate,
        string $docNumberPrefix,
        bool $useFactory = true,
        bool $stopOnError = false
    ): array {
        $results = [
            'success' => [],
            'errors' => []
        ];

        foreach ($employees as $index => $employeeData) {
            try {
                // Generate unique doc number for each employee
                $docNumber = $docNumberPrefix . '_' . ($employeeData['quickbooksEmployeeId'] ?? $index);

                $result = $this->createEmployeeJournal(
                    $employeeData,
                    $realmId,
                    $txnDate,
                    $docNumber,
                    $useFactory
                );

                $results['success'][] = [
                    'employeeId' => $employeeData['quickbooksEmployeeId'] ?? null,
                    'result' => $result
                ];

            } catch (PayrollException $e) {
                $error = [
                    'employeeId' => $employeeData['quickbooksEmployeeId'] ?? null,
                    'errorCode' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                    'context' => $e->getContext()
                ];

                $results['errors'][] = $error;

                if ($stopOnError) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Generate document number for payroll transaction
     *
     * @param string $payrollDate Date in Y-m-d format
     * @param string $suffix Optional suffix
     * @return string Document number
     */
    public function generateDocNumber(string $payrollDate, string $suffix = ''): string
    {
        return PayrollConfig::generateDocNumber($payrollDate, $suffix);
    }

    /**
     * Validate journal entry data before creation
     *
     * @param string $transactionType Transaction type
     * @param array $data Transaction data
     * @return bool True if valid
     * @throws ValidationException If validation fails
     */
    public function validateJournalData(string $transactionType, array $data): bool
    {
        $builder = $this->factory->getBuilder($transactionType);
        $builder->validateData($data);
        return true;
    }

    /**
     * Get the factory instance
     *
     * @return JournalLineFactory
     */
    public function getFactory(): JournalLineFactory
    {
        return $this->factory;
    }

    /**
     * Get the validator instance
     *
     * @return PayrollValidator
     */
    public function getValidator(): PayrollValidator
    {
        return $this->validator;
    }
}
