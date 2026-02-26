<?php

namespace Models;

use QuickBooksOnline\API\Facades\SalesReceipt;
use QuickBooksOnline\API\Exception\SdkException;
use DateTime;
use Exception;
use QuickBooksOnline\API\Exception\IdsException;
use ReflectionException;
use Errors\QuickBooksApiException;

/**
 * Create and retrieve QBO sales receipt objects.
 *
 * @category Model
 */
class QuickbooksSalesReceipt
{
    /**
     * QBO Takings identifier. Not DocNumber.
     *
     * @var int
     */
    protected int $id;
    /**
     * The date the takings ocurred
     *
     * @var string
     */
    protected string $date;
    /**
     * The id of the charity shop
     *
     * @var int
     */
    protected int $shopid;
    /**
     * The number and value of clothing sales
     *
     * @var object
     */
    protected object $clothing;
    /**
   * The number and value of bric-a-brac sales
   *
   * @var object
   */
    protected object $brica;
    /**
   * The number and value of books sales
   *
   * @var object
   */
    protected object $books;
    /**
   * The number and value of linens sales
   *
   * @var object
   */
    protected object $linens;
    /**
   * The number and value of daily ragging sales
   * Rarely used: usually recorded on a monthly basis nowadays
   *
   * @var object
   */
    protected object $ragging;
    /**
   * The number and value of donations
   *
   * @var object
   */
    protected object $donations;
    /**
     * The cost of volunteer expenses
     *
     * @var float
     */
    protected float $volunteerExpenses;
    /**
     * The cost of operating expenses
     *
     * @var float
     */
    protected float $operatingExpenses;
    /**
     * The amount of cash banked after expenses
     *
     * @var float
     */
    protected float $cash;
    /**
     * The amount of sales paid for by credit card
     *
     * @var float
     */
    protected float $creditCards;
    /**
     * Overage/underage. Can be positive or negative
     *
     * @var float
     */
    protected float $cashDiscrepancy;
    /**
     * The amount of cash given to the charity rather than banked.
     * Rarely used.
     *
     * @var float
     */
    protected float $cashToCharity;
    /**
     * A comment or note about the days sales.
     *
     * @var string
     */
    protected string $privatenote;
    /**
     * The QBO company ID
     *
     * @var string
     */
    protected string $realmid;

    /**
     * ID setter
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }
    /**
     * Date setter
     */
    public function setDate(string $date)
    {
        $this->date = $date;
        return $this;
    }
    /**
     * ShopID setter
     */
    public function setShopid(int $shopid)
    {
        $this->shopid = $shopid;
        return $this;
    }
    /**
     * Clothing setter
     */
    public function setClothing(object $clothing)
    {
        $this->clothing = $clothing;
        return $this;
    }
    /**
     * Brica setter
     */
    public function setBrica(object $brica)
    {
        $this->brica = $brica;
        return $this;
    }
    /**
     * Books setter
     */
    public function setBooks(object $books)
    {
        $this->books = $books;
        return $this;
    }
    /**
     * Linens setter
     */
    public function setLinens(object $linens)
    {
        $this->linens = $linens;
        return $this;
    }
    /**
     * Ragging setter
     */
    public function setRagging(object $ragging)
    {
        $this->ragging = $ragging;
        return $this;
    }
    /**
     * Donations setter
     */
    public function setDonations(object $donations)
    {
        $this->donations = $donations;
        return $this;
    }
    /**
     * Volunteer Expenses setter
     */
    public function setVolunteerExpenses(float $volunteerExpenses)
    {
        $this->volunteerExpenses = $volunteerExpenses;
        return $this;
    }
    /**
     * Operating Expenses setter
     */
    public function setOperatingExpenses(float $operatingExpenses)
    {
        $this->operatingExpenses = $operatingExpenses;
        return $this;
    }
    /**
     * Cash setter
     */
    public function setCash(float $cash)
    {
        $this->cash = $cash;
        return $this;
    }
    /**
     * Credit Cards setter
     */
    public function setCreditCards(float $creditCards)
    {
        $this->creditCards = $creditCards;
        return $this;
    }
    /**
     * Cash Discrepancy setter
     */
    public function setCashDiscrepancy(float $cashDiscrepancy)
    {
        $this->cashDiscrepancy = $cashDiscrepancy;
        return $this;
    }
    /**
     * Cash To Charity setter
     */
    public function setCashToCharity(float $cashToCharity)
    {
        $this->cashToCharity = $cashToCharity;
        return $this;
    }
    /**
     * Private Note setter. This is a comment field.
     */
    public function setPrivateNote(string $privatenote)
    {
        $this->privatenote = $privatenote;
        return $this;
    }
    /**
     * Private realmID setter.
     */
    public function setRealmID(string $realmid)
    {
        $this->realmid = $realmid;
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
     * Check the provided values make sense.
     */
    public function validate(): bool
    {

        // is transaction in balance?
        // Sales = clothing+brica+books+linens+ragging+donations
        // Money Received = cash + creditcards + vol expenses + op expenses
        // Sales must equal Money Received + Cash Discrepancy
        $balance = $this->donations->sales + $this->clothing->sales + $this->brica->sales;
        $balance += $this->books->sales + $this->linens->sales + $this->ragging->sales;
        $balance += $this->cashDiscrepancy + $this->cashToCharity + $this->creditCards;
        $balance += $this->volunteerExpenses + $this->operatingExpenses + $this->cash;

        if (abs($balance) >= 0.005) {
            return false;
        }

        return true;
    }

    /**
     * Return details of the QBO sales receipt identified by $id
     * @return object Returns a sales receipt with specified Id.
     * @throws Exception
     * @throws SdkException
     * @throws IdsException
     */
    public function readOne(): object
    {

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->realmid);

        $dataService->forceJsonSerializers();
        $salesreceipt = $dataService->FindbyId('SalesReceipt', $this->id);
        $error = $dataService->getLastError();
        if ($error) {
            throw new SdkException("The QBO Response message is: " . $error->getResponseBody());
        } else {
            if (property_exists($salesreceipt, 'SalesReceipt')) {
                /** @disregard Intelephense error on next line */
                return $salesreceipt->SalesReceipt;
            } else {
                return $salesreceipt;
            }
        }
    }

    /**
     * Add a new sales receipt to QBO
     * @return array{id: int, date: string, label: string}
     * @throws Exception
     * @throws ReflectionException
     * @throws SdkException
     * @throws IdsException
     */
    public function create()
    {

        $docnumber = (new DateTime($this->date))->format('Ymd') . ($this->shopid == 2 ? 'C' : 'H'); //'H' is short for Harrow Road

        $salesreceipt = array(
          "TxnDate" => $this->date,
          "DocNumber" => $docnumber,
          "PrivateNote" => $this->privatenote ?? "",
          "Line" => [],
          "TxnTaxDetail" => [
            "TaxLine" => [
              "Amount" => 0,
              "DetailType" => "TaxLineDetail",
              "TaxLineDetail" => [
                "TaxRateRef" => $this->zero_rated_taxrate,
                "PercentBased" => true,
                "TaxPercent" => 0,
                "NetAmountTaxable" => round($this->salesTotal(), 2)
              ]
            ]
              ],
          "CustomerRef" => $this->customer,
          "GlobalTaxCalculation" => "TaxExcluded",
          "TotalAmt" => abs($this->cash),
          "PrintStatus" => "NotSet",
          "EmailStatus" => "NotSet"
        );

        $class = $this->shopid == 1 ? $this->harrow_road_class : $this->church_street_class;

        try {

            //&$line_array, $description, $amount, $item, $class, $quantity, $account, $taxcoderef)
            // This code will only add the respective line if amount != 0
            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Daily sales of second-hand and donated clothing",
                $this->clothing->sales,
                $this->clothing_item,
                $class,
                $this->clothing->number,
                $this->sales_account,
                $this->zero_rated_taxcode
            );
            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Sales of donated household goods",
                $this->brica->sales,
                $this->brica_item,
                $class,
                $this->brica->number,
                $this->sales_account,
                $this->zero_rated_taxcode
            );
            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Sales of donated books and DVDs",
                $this->books->sales,
                $this->books_item,
                $class,
                $this->books->number,
                $this->sales_account,
                $this->zero_rated_taxcode
            );
            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Sales of donated linen products",
                $this->linens->sales,
                $this->linens_item,
                $class,
                $this->linens->number,
                $this->sales_account,
                $this->zero_rated_taxcode
            );
            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Cash donations to parent charity",
                $this->donations->sales,
                $this->donations_item,
                $class,
                $this->donations->number,
                $this->donations_account,
                $this->no_vat_taxcode
            );
            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Textile/book recycling",
                $this->ragging->sales,
                $this->ragging_item,
                $class,
                $this->ragging->number,
                $this->ragging_account,
                $this->zero_rated_taxcode
            );

            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Volunteer expenses paid in cash",
                $this->volunteerExpenses,
                $this->volexpenses_item,
                $class,
                1,
                $this->volunteer_expenses_account,
                $this->no_vat_taxcode
            ); //$quantity = 1
            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Minor operating expenses paid in cash",
                $this->operatingExpenses,
                $this->opexpenses_item,
                $class,
                1,
                $this->other_expenses_account,
                $this->no_vat_taxcode
            ); //$quantity = 1

            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Credit card payments received from customers",
                $this->creditCards,
                $this->ccards_item,
                $class,
                1,
                $this->credit_card_account,
                $this->no_vat_taxcode
            ); //$quantity = 1

            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Cash discrepancies between sales total and cash/credit card subtotals",
                $this->cashDiscrepancy,
                $this->overage_item,
                $class,
                1,
                $this->cash_discrepancies_account,
                $this->no_vat_taxcode
            ); //$quantity = 1

            $this->salesreceipt_line(
                $salesreceipt['Line'],
                "Cash that has gone to the parent charity without being deposited into the Enterprises bank account",
                $this->cashToCharity,
                $this->charitycash_item,
                $class,
                1,
                $this->cash_to_charity_account,
                $this->no_vat_taxcode
            ); //$quantity = 1
        } catch (Exception) {
            throw new Exception("Unable to enter daily sales receipt in Quickbooks. ".
                                      "Error ocurred in preparation of SalesReceipt lines.");
        }

        $theResourceObj = SalesReceipt::create($salesreceipt);

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->realmid);

        $resultingObj = $dataService->Add($theResourceObj);

        $error = $dataService->getLastError();
        if ($error) {
            $errorMessage = $error->getIntuitErrorMessage();
            if (str_contains($errorMessage, "Duplicate Doc")) {
                throw QuickBooksApiException::duplicateEntry($errorMessage);
            } else {
              throw new SdkException("The QBO Response message is: " . $errorMessage);
            }
        } else {
            if ($resultingObj) {
                return array(
                    "id" => $resultingObj->Id,
                    "date" => $this->date,
                    "label" => $docnumber
                );
            } else {
                throw new Exception("No result body returned from QuickBooks.");
            }
        }
    }


    /**
     * Push a new array describing a single line of a QBO sales receipt into the given array
     * Helper function used in create.
     *
     * @param mixed $line_array The given array
     * @param mixed $description
     * @param mixed $amount
     * @param mixed $item
     * @param mixed $class
     * @param mixed $quantity
     * @param mixed $account
     * @param mixed $taxcoderef
     *
     * @return void
     *
     */
    private function salesreceipt_line(
        &$line_array,
        $description,
        $amount,
        $item,
        $class,
        $quantity,
        $account,
        $taxcoderef
    ) {
        if (abs($amount) <= 0.005) {
            return;
        }

        if ($quantity == 0) {
            throw new \Exception("The value for 'quantity' is zero or missing. Line description:'" . $description . "'");
        }

        array_push($line_array, array(
          "Description" => $description,
          "Amount" => $amount,
          "DetailType" => "SalesItemLineDetail",
          "SalesItemLineDetail" => [
            "ItemRef" => $item,
            "ClassRef" => $class,
            "UnitPrice" => $quantity == 1 ? $amount : $amount / $quantity,
            "Qty" => $quantity,
            "ItemAccountRef" => $account,
            "TaxCodeRef" => $taxcoderef
          ]
        ));
    }

    /**
     * Delete this QBO Sales receipt from the QBO system.
     * @return true 'true' if success.
     * @throws Exception
     * @throws SdkException
     * @throws ReflectionException
     * @throws IdsException
     */
    public function delete(): true
    {
        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->realmid);

        // Do not use $dataService->FindbyId to create the entity to delete
        // Use this simple representation instead
        // The problem is that FindbyId forces use of JSON and that doesnt work
        // with the delete uri call
        $salesreceipt = SalesReceipt::create([
          "Id" => $this->id,
          "SyncToken" => "0"
        ]);
        $dataService->Delete($salesreceipt);

        $error = $dataService->getLastError();
        if ($error) {
            throw new SdkException("The QBO Response message is: " . $error->getResponseBody());
        } else {
            return true;
        }
    }

    /**
     * Simple helper function to return total VAT-able sales for the sale receipt.
     *
     * Formula is: sales = clothing+brica+books+linens+ragging
     *
     * @return float Total VAT-able sales
     *
     */
    private function salesTotal(): float
    {
        return $this->clothing->sales + $this->brica->sales +
          $this->books->sales + $this->linens->sales + $this->ragging->sales;
    }

    // All these constant properties are set in the QBO company
    private $zero_rated_taxcode = [
      "value" => 4
    ];
    private $no_vat_taxcode = [
      "value" => 20
    ];
    private $zero_rated_taxrate = [
      "value" => 7
    ];
    private $harrow_road_class = [
      "value" => 400000000000618070,
      "name" => "Harrow Rd"
    ];
    private $church_street_class = [
      "value" => 400000000000618073,
      "name" => "Church St"
    ];
    private $other_expenses_account = [
      "value" => 8,
      "name" => "Other Staff Expenses"
    ];
    private $volunteer_expenses_account = [
      "value" => 86,
      "name" => "Volunteer Expenses"
    ];
    private $cash_discrepancies_account = [
      "value" => 93,
      "name" => "Office Expense:Cash Discrepancies"
    ];
    private $ragging_discrepancies_account = [
      "value" => 93,
      "name" => "Office Expense:Cash Discrepancies"
    ];
    private $sales_account = [
      "value" => 191,
      "name" => "Daily Sales income"
    ];
    private $donations_account = [
      "value" => 81,
      "name" => "Donations to Parent"
    ];
    private $credit_card_account = [
      "value" => 96,
      "name" => "Credit Card Receipts"
    ];
    private $undeposited_funds_account = [
      "value" => 100,
      "name" => "Undeposited Funds"
    ];
    private $cash_to_charity_account = [
      "value" => 134,
      "name" => "Cash To Charity"
    ];
    private $ragging_account = [
      "value" => 82,
      "name" => "Ragging"
    ];
    private $clothing_item = [
      "value" => 37,
      "name" => "Daily Sales:Clothing"
    ];
    private $brica_item = [
      "value" => 38,
      "name" => "Daily Sales:Bric-a-Brac"
    ];
    private $books_item = [
      "value" => 39,
      "name" => "Daily Sales:Books"
    ];
    private $linens_item = [
      "value" => 40,
      "name" => "Daily Sales:Linens"
    ];
    private $cash_item = [
      "value" => 41,
      "name" => "Daily Sales:Cash"
    ];
    private $ccards_item = [
      "value" => 42,
      "name" => "Daily Sales:Credit Cards"
    ];
    private $overage_item = [
      "value" => 43,
      "name" => "Daily Sales:Overage/Underage"
    ];
    private $donations_item = [
      "value" => 44,
      "name" => "Daily Sales:Donations"
    ];
    private $ragging_item = [
      "value" => 45,
      "name" => "Daily Sales:Ragging"
    ];
    private $opexpenses_item = [
      "value" => 46,
      "name" => "Daily Sales:Operating Expenses"
    ];
    private $volexpenses_item = [
      "value" => 47,
      "name" => "Daily Sales:Volunteer Expenses"
    ];
    private $charitycash_item = [
      "value" => 48,
      "name" => "Daily Sales:Cash To Charity"
    ];
    private $customer = [
      "value" => 136,
      "name" => "Daily Sales"
    ];


}
