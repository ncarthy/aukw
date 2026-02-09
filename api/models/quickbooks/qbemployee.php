<?php

namespace Models;

use QuickBooksOnline\API\Facades\Employee;
use QuickBooksOnline\API\Exception\SdkException;
use QuickBooksOnline\API\Data\IPPIntuitEntity;

/**
 * Factory class that provides data about QBO Employees.
 *
 * @category Model
 */
class QuickbooksEmployee
{
    /**
     * The QBO id of the Quickbooks Employee.
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
     * The number of the employee. This is used in Payroll, to link the Iris salary calcualtions to the employee.
     *
     * @var string
     */
    protected string $employeenumber;
    /**
     * The employee's first name
     *
     * @var string
     */
    protected string $givenname;
    /**
     * The employee's surname
     *
     * @var string
     */
    protected string $familyname;
    /**
     * ID setter
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }
    /**
     * realmID setter.
     */
    public function setRealmID(string $realmid)
    {
        $this->realmid = $realmid;
        return $this;
    }
    /**
     * EmployeeNumber setter.
     */
    public function setEmployeeNumber(string $employeenumber)
    {
        $this->employeenumber = $employeenumber;
        return $this;
    }
    /**
     * Given Name setter.
     */
    public function setGivenName(string $givenname)
    {
        $this->givenname = $givenname;
        return $this;
    }
    /**
     * Family name setter.
     */
    public function setFamilyName(string $familyname)
    {
        $this->familyname = $familyname;
        return $this;
    }

    /**
     * realmID getter.
     */
    public function getrealmId(): string
    {
        return $this->realmid;
    }

    /**
     * Id getter.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Family name getter.
     */
    public function getFamilyName(): string
    {
        return $this->familyname;
    }
    /**
     * GivenName getter.
     */
    public function getGivenName(): string
    {
        return $this->givenname;
    }
    /**
     * Employee Number getter.
     */
    public function getEmployeeNumber(): string
    {
        return $this->employeenumber;
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
     * Return details of the QBEmployee identified by $id
     *
     * @param int $id The QBO id of the Quickbooks Item.
     *
     * @return IPPIntuitEntity Returns an item of specified Id.
     *
     */
    public function readOne()
    {

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->realmid);

        $dataService->forceJsonSerializers();
        $item = $dataService->FindbyId('Employee', $this->id);
        $error = $dataService->getLastError();
        if ($error) {
            throw new \Exception("The QBO Response message is: " . $error->getResponseBody() . "\n");
        } else {
            if (property_exists($item, 'Employee')) {
                /** @disregard Intelephense error on next line */
                return $item->Employee;
            } else {
                return $item;
            }
        }
    }

    /**
     * Return details of all QBO Employees
     *
     * @return array An array of QBO Employees, associated by QBO Id
     *
     */
    public function readAll(): array
    {
        return $this->readAllImpl(false);
    }

    /**
     * Return details of all QBO Employees
     *
     * @return array An array of QBO Employees, associated by Name
     *
     */
    public function readAllAssociatedByName(): array
    {
        return $this->readAllImpl(true);
    }

    /**
     * Return details of all QBO Employees. However who do not have an EmployeeID asigned to them are excluded from this list.
     * @param bool $associateByName If 'true' return an associative array, sorted by Display Name
     * @return array An array of QBO Employees who have a valid EmployeeID associated with them
     *
     */
    private function readAllImpl(bool $associateByName = false): array
    {

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->realmid);

        $items = $dataService->FindAll('Employee');
        $error = $dataService->getLastError();
        if ($error) {
            throw new \Exception("The QBO Response message is: " . $error->getResponseBody() . "\n");
        } else {
            $employeeArray = array();
            foreach ($items as $item) {

                $firstName = $item->GivenName ?? '';
                $lastName = $item->FamilyName ?? '';

                if ($firstName != '' && $lastName != '') {
                    $fullName = $firstName . ' ' . $lastName;
                } else {
                    $fullName = $item->DisplayName ?? '';
                }

                $employee = array(
                  "quickbooksId" => $item->Id,
                  "name" => $fullName,
                  "payrollNumber" => $item->EmployeeNumber,
                  "firstName" => $firstName,
                  "lastName" => $lastName,
                  "middleName" => $item->MiddleName ?? '',
                );
                if ($item->EmployeeNumber) {
                    if ($associateByName) {
                        if ($fullName == '') {
                            $fullName = $item->DisplayName ?? '';
                        }
                        if ($employeeArray[$fullName] ?? false) {
                            throw new \Exception("Duplicate employee name found in QBO: " . 
                                $fullName . 
                                ". Please ensure all employees have unique names or use the 'readAll' method instead of 'readAllAssociatedByName'."
                            );
                        }
                        $employeeArray[$fullName] = $employee;
                    } else {
                        $employeeArray[$item->Id] = $employee;
                    }
                }
            }

            return $employeeArray;
        }
    }

    /**
     * Create this employee in QBO
     *
     * @return IPPIntuitEntity On success return an array with details of the new object. On failure return 'false'.
     */
    public function create()
    {

        $auth = new QuickbooksAuth();
        $dataService = $auth->prepare($this->realmid);

        $employee = Employee::create([
          "GivenName" => $this->givenname,
          "FamilyName" => $this->familyname,
          "EmployeeNumber" => $this->employeenumber,
        ]);

        /** @var IPPIntuitEntity $result */
        $result = $dataService->Add($employee);
        $error = $dataService->getLastError();
        if ($error) {
            throw new SdkException("The QBO Response message is: " . $error->getResponseBody());
        } else {
            return $result;
        }
    }

}
