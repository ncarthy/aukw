<?php

namespace Models;

use Core\QuickbooksConstants as QBO;
use QuickBooksOnline\API\Facades\JournalEntry;

/**
 * Factory class that all creation of QB Employer NI journals
 *
 * @category Model
 */
class QuickbooksEmployerNIJournal extends QuickbooksJournal
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
     * Create the general journal entry for Employer NI using the new factory approach
     *
     * @param array $entries Array of employer NI entries
     * @param bool $useFactory Whether to use the new factory method (default: true)
     * @return array|false On success return an array with details of the new object. On failure return 'false'.
     */
    public function create_employerNI_journal($entries, bool $useFactory = true): array|false
    {
        if ($useFactory) {
            // New approach: use factory
            return $this->createJournalEntry('employer_ni', [
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

        $sum = 0;
        foreach ($entries as $line) {
            //&$line_array, $description, $amount, $employee, $class, $account)
            // This code will only add the respective line if amount != 0
            $this->payrolljournal_line(
                $payrolljournal['Line'],
                QBO::EMPLOYER_NI_DESCRIPTION,
                $line->amount,
                $line->quickbooksId,
                $line->class,
                $line->account
            );

            $sum -= $line->amount;
        }

        $this->payrolljournal_line(
            $payrolljournal['Line'],
            "Total of " . QBO::EMPLOYER_NI_DESCRIPTION,
            $sum,
            '',
            QBO::ADMIN_CLASS,
            QBO::TAX_ACCOUNT
        );

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
                "id" => $resultingObj->Id ?? 0,
                "date" => $this->TxnDate,
                "label" => $this->DocNumber
            );
        }
    }



}
