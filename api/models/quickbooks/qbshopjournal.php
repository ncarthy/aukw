<?php

namespace Models;

use Core\QuickbooksConstants as QBO;
use QuickBooksOnline\API\Facades\JournalEntry;

/**
 * Factory class that all creation of QB Employer NI journals
 *
 * @category Model
 */
class QuickbooksEnterprisesJournal extends QuickbooksJournal
{
    /**
     * Constructor
     */
    protected function __construct()
    {
    }

    /**
     * Static constructor / factory
     */
    public static function getInstance()
    {
        return new self();
    }

    /**
     * Create the general journal entry for the shop using the new factory approach
     *
     * @param array $entries Array of shop payroll entries
     * @param bool $useFactory Whether to use the new factory method (default: true)
     * @return array|false On success return an array with details of the new object. On failure return 'false'.
     */
    public function create_enterprises_journal($entries, bool $useFactory = true): array|false
    {
        if ($useFactory) {
            // New approach: use factory
            return $this->createJournalEntry('shop_payroll', [
                'entries' => $entries
            ]);
        }

        // Old approach: manual line creation (kept for backward compatibility)
        $payrolljournal = array(
            "TxnDate" => $this->TxnDate,
            "DocNumber" => $this->DocNumber,
            "Line" => [],
            "TotalAmt" => 0
        );

        foreach ($entries as $line) {
            //&$line_array, $description, $amount, $employee, $class, $account)
            $this->payrolljournal_line(
                $payrolljournal['Line'],
                QBO::GROSS_SALARY_DESCRIPTION,
                $line->totalPay,
                $line->quickbooksId,
                QBO::HARROW_ROAD_CLASS,
                QBO::AUEW_SALARIES_ACCOUNT
            );
            $this->payrolljournal_line(
                $payrolljournal['Line'],
                QBO::GROSS_SALARY_DESCRIPTION,
                -$line->totalPay,
                $line->quickbooksId,
                QBO::HARROW_ROAD_CLASS,
                QBO::AUKW_INTERCO_ACCOUNT
            );

            if ($line->employerNI) {
                $this->payrolljournal_line(
                    $payrolljournal['Line'],
                    QBO::EMPLOYER_NI_DESCRIPTION,
                    $line->employerNI,
                    $line->quickbooksId,
                    QBO::HARROW_ROAD_CLASS,
                    QBO::AUEW_NI_ACCOUNT
                );
                $this->payrolljournal_line(
                    $payrolljournal['Line'],
                    QBO::EMPLOYER_NI_DESCRIPTION,
                    -$line->employerNI,
                    $line->quickbooksId,
                    QBO::HARROW_ROAD_CLASS,
                    QBO::AUKW_INTERCO_ACCOUNT
                );
            }

            if ($line->employerPension) {
                $this->payrolljournal_line(
                    $payrolljournal['Line'],
                    QBO::EMPLOYER_PENSION_CONT_DESCRIPTION,
                    $line->employerPension,
                    $line->quickbooksId,
                    QBO::HARROW_ROAD_CLASS,
                    QBO::AUEW_PENSIONS_ACCOUNT
                );
                $this->payrolljournal_line(
                    $payrolljournal['Line'],
                    QBO::EMPLOYER_PENSION_CONT_DESCRIPTION,
                    -$line->employerPension,
                    $line->quickbooksId,
                    QBO::HARROW_ROAD_CLASS,
                    QBO::AUKW_INTERCO_ACCOUNT
                );
            }
        }

        $theResourceObj = JournalEntry::create($payrolljournal);

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->getrealmId());

        $resultingObj = $dataService->Add($theResourceObj);

        $error = $dataService->getLastError();
        if ($error) {
            echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
            echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
            echo "The QBO Response message is: " . $error->getResponseBody() . "\n";
            return false;
        } else {
            return array(
                "id" => $resultingObj->Id,
                "date" => $this->TxnDate,
                "label" => $this->DocNumber
            );
        }
    }



}
