<?php

namespace Models;

use Exception;
use QuickBooksOnline\API\Exception\IdsException;
use QuickBooksOnline\API\Facades\JournalEntry;
use QuickBooksOnline\API\Exception\SdkException;
use ReflectionException;
use Services\JournalLineFactory;
use Validators\PayrollValidator;
use Errors\QuickBooksApiException;

/**
 * Factory class that provides data about QBO General Journals.
 *
 * @category Model
 */
class QuickbooksJournal
{
    /**
     * The QBO id of the Quickbooks Journal.
     *
     * @var int
     */
    protected int $id;

    /**
     * The QBO company ID
     *
     * @var string
     */
    protected string $realmid;

    /**
     * The transaction date of the Journal entry
     *
     * @var string
     */
    protected string $TxnDate;

    /**
     * The Reference number for the transaction. Does not have to be unique.
     *
     * @var string
     */
    protected string $DocNumber;

    /**
     * Journal line factory for building lines
     *
     * @var JournalLineFactory|null
     */
    protected ?JournalLineFactory $factory = null;

    /**
     * Validator for input validation
     *
     * @var PayrollValidator|null
     */
    protected ?PayrollValidator $validator = null;

    /**
     * ID setter
     */
    public function setId(int $id)
    {
        $this->id = $id;
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
     * ID getter.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * realmID getter.
     */
    public function getrealmId(): string
    {
        return $this->realmid;
    }

    /**
     * Transaction Date setter.
     */
    public function setTxnDate(string $txnDate)
    {
        $this->TxnDate = $txnDate;
        return $this;
    }

    /**
     * Reference number setter.
     */
    public function setDocNumber(string $docNumber)
    {
        $this->DocNumber = $docNumber;
        return $this;
    }

    /**
     * Reference number getter.
     */
    public function getDocNumber(): string
    {
        return $this->DocNumber;
    }

    /**
     * Transaction Date getter.
     */
    public function getTxnDate(): string
    {
        return $this->TxnDate;
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
     * Set the journal line factory
     *
     * @param JournalLineFactory $factory
     * @return self
     */
    public function setFactory(JournalLineFactory $factory): self
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * Get the journal line factory (create if not exists)
     *
     * @return JournalLineFactory
     */
    protected function getFactory(): JournalLineFactory
    {
        if ($this->factory === null) {
            $this->factory = new JournalLineFactory();
        }
        return $this->factory;
    }

    /**
     * Set the validator
     *
     * @param PayrollValidator $validator
     * @return self
     */
    public function setValidator(PayrollValidator $validator): self
    {
        $this->validator = $validator;
        return $this;
    }

    /**
     * Get the validator (create if not exists)
     *
     * @return PayrollValidator
     */
    protected function getValidator(): PayrollValidator
    {
        if ($this->validator === null) {
            $this->validator = new PayrollValidator();
        }
        return $this->validator;
    }

    /**
     * Create a journal entry using the factory
     *
     * This is a common method that can be used by all child classes to create
     * journal entries without duplicating code.
     *
     * @param string $transactionType Transaction type identifier
     * @param array $data Transaction data
     * @param bool $validate Whether to validate before posting (default: true)
     * @return array|false On success return array with id, date, label. On failure return false.
     */
    protected function createJournalEntry(string $transactionType, array $data, bool $validate = true): array|false
    {
        try {
            $factory = $this->getFactory();

            // Build complete journal entry
            $journalEntry = $factory->buildJournalEntry(
                $transactionType,
                $data,
                $this->TxnDate,
                $this->DocNumber,
                $validate
            );

            // Additional validation if requested
            if ($validate) {
                $validator = $this->getValidator();
                $validator->validateJournalEntry($journalEntry);
            }

            // Create QuickBooks resource object
            $theResourceObj = JournalEntry::create($journalEntry);

            // Prepare data service
            $auth = new QuickbooksAuth();
            $dataService = $auth->prepare($this->getrealmId());

            // Add to QuickBooks
            $resultingObj = $dataService->Add($theResourceObj);

            // Check for errors
            $error = $dataService->getLastError();
            if ($error) {
                throw QuickBooksApiException::fromSdkError($error, $journalEntry, $this->realmid);
            }

            return [
                "id" => $resultingObj->Id,
                "date" => $this->TxnDate,
                "label" => $this->DocNumber
            ];

        } catch (QuickBooksApiException $e) {
            // Re-throw QuickBooks exceptions
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions
            throw new QuickBooksApiException(
                "Failed to create journal entry: " . $e->getMessage(),
                QuickBooksApiException::ERROR_NETWORK_FAILURE,
                null,
                null,
                null,
                null,
                $this->realmid,
                [],
                0,
                $e
            );
        }
    }

    /**
     * Return details of the QBO general journal identified by $id
     * @return object|null Returns an journal with specified Id or nothing.
     *
     */
    public function readOne(): object|null
    {

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->realmid);

        $dataService->forceJsonSerializers();
        $journalentry = $dataService->FindbyId('journalentry', $this->id);
        $error = $dataService->getLastError();
        if ($error) {
            throw new SdkException("The QBO Response message is: " . $error->getResponseBody());
        } else {
            if (property_exists($journalentry, 'JournalEntry')) {
                /** @disregard Intelephense error on next line */
                return $journalentry->JournalEntry;
            } else {
                return $journalentry;
            }
        }
    }

    /**
     * Delete this journal from the QBO system.
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
        $journal = JournalEntry::create([
          "Id" => $this->id,
          "SyncToken" => "0"
        ]);

        $dataService->Delete($journal);

        $error = $dataService->getLastError();
        if ($error) {
            throw new SdkException("The QBO Response message is: " . $error->getResponseBody());
        } else {
            return true;
        }
    }

    /**
     * Push a new array describing a single line of a QBO journal into the given array
     * Helper function used in create.
     *
     * @param mixed $line_array The given array, passed by reference.
     * @param mixed $description
     * @param mixed $amount
     * @param mixed $employee
     * @param mixed $class
     * @param mixed $account
     *
     * @return void
     *
     */
    protected function payrolljournal_line(&$line_array, $description, $amount, $employee, $class, $account)
    {
        if (abs($amount) <= 0.005) {
            return;
        }

        array_push($line_array, array(
          "Description" => $description,
          "Amount" => abs($amount),
          "DetailType" => "JournalEntryLineDetail",
          "JournalEntryLineDetail" => [
            "PostingType" => ($amount < 0 ? "Credit" : "Debit"),
            "Entity" => [
                "Type" => "Employee",
                "EntityRef" => $employee
            ],
            "AccountRef" => $account,
            "ClassRef" => $class,
          ]
        ));
    }
}
