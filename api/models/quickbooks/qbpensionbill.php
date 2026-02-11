<?php

namespace Models;

use Core\QuickbooksConstants as QBO;
use QuickBooksOnline\API\Facades\Bill;

/**
 * Factory class that provides create and delete methods for QBO Bills.
 *
 * @category Model
 */
class QuickbooksPensionBill extends QuickbooksBill
{
    /**
     * Total of whole bill
     *
     * @var float
     */
    protected float $total;

    /**
     * Salary sacrifice total
     *
     * @var float
     */
    protected float $salarySacrificeTotal;

    /**
     * Employee pension Contribution total
     *
     * @var float
     */
    protected float $employeePensContribTotal;

    /**
     * Costs of employee pensions, split into allocations
     *
     * @var Array
     */
    protected array $pensionCosts;

    /**
     * Total setter
     */
    public function setTotal(float $total)
    {
        $this->total = $total;
        return $this;
    }

    /**
     * Salary Sacrifice Total setter
     */
    public function setSalarySacrificeTotal(float $salarySacrificeTotal)
    {
        $this->salarySacrificeTotal = $salarySacrificeTotal;
        return $this;
    }

    /**
     * Employee Pension Contribution Total setter
     */
    public function setEmployeePensContribTotal(float $employeePensContribTotal)
    {
        $this->employeePensContribTotal = $employeePensContribTotal;
        return $this;
    }

    /**
     * Pension Costs setter
     */
    public function setPensionCosts(array $pensionCosts)
    {
        $this->pensionCosts = $pensionCosts;
        return $this;
    }

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
     * Create this bill in QBO
     *
     * @return array|false On success return an array with details of the new object. On failure return 'false'.
     */
    public function create(): array|false
    {

        $bill = array(
          "TxnDate" => $this->TxnDate,
          "DocNumber" => $this->DocNumber,
          "Line" => [],
          "VendorRef" => QBO::LEGAL_AND_GENERAL_VENDOR,
          "TotalAmt" => $this->total
        );

        // For each line below it will only add the respective line if amount != 0

        $this->bill_line(
            $bill['Line'],
            "Monthly total of salary sacrifices.",
            $this->salarySacrificeTotal,
            QBO::ADMIN_CLASS,
            QBO::SALARY_SACRIFICE_ACCOUNT,
            QBO::NOVAT_TAX_CODE
        );

        $this->bill_line(
            $bill['Line'],
            "Monthly total of employee pension contributions.",
            $this->employeePensContribTotal,
            QBO::ADMIN_CLASS,
            QBO::EMPLOYEE_PENSION_CONTRIB_ACCOUNT,
            QBO::NOVAT_TAX_CODE
        );

        foreach ($this->pensionCosts as $pensionAllocation) {
            //&$line_array, $description, $amount, $class, $account, $taxcoderef)
            $this->bill_line(
                $bill['Line'],
                $pensionAllocation->name . ' (' . $pensionAllocation->payrollNumber . ')' ?? 'Employer pension contribution',
                $pensionAllocation->amount,
                $pensionAllocation->class,
                $pensionAllocation->account == QBO::AUEW_ACCOUNT ? QBO::AUEW_ACCOUNT : QBO::PENSION_COSTS_ACCOUNT,
                QBO::NOVAT_TAX_CODE
            );
        }

        $theResourceObj = Bill::create($bill);

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


    /**
     * Delete this bill from the QB system.
     *
     * @return bool 'true' if success.
     *
     */
    public function delete(): bool
    {
        $auth = new QuickbooksAuth();
        try {
            $dataService = $auth->prepare($this->realmid);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(
                array("message" =>  $e->getMessage() )
            );
            return false;
        }

        // Do not use $dataService->FindbyId to create the entity to delete
        // Use this simple representation instead
        // The problem is that FindbyId forces use of JSON and that doesnt work
        // with the delete uri call
        $bill = Bill::create([
          "Id" => $this->id,
          "SyncToken" => "0"
        ]);

        $dataService->Delete($bill);

        $error = $dataService->getLastError();
        if ($error) {
            echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
            echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
            echo "The QBO Response message is: " . $error->getResponseBody() . "\n";
            return false;
        } else {
            return true;
        }
    }

}
