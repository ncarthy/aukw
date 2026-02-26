<?php

namespace Controllers\QuickBooks;

use Models\QuickbooksSalesReceipt;
use Models\Takings;
use Core\ErrorResponse as Error;
use Exception;
use Errors\QuickBooksApiException;

/**
 * Controller to accomplish QBO sales receipt related tasks.
 *
 * @category  Controller
*/
class QBSalesReceiptCtl
{
    /**
     * Return details of the sales receipt identified by $id
     * @param string $realmid The company ID for the QBO company.
     * @param int $id The QBO id, not the DocNumber
     * @return void Output is echoed directly to response
     */
    public static function read_one(string $realmid, int $id)
    {
        try {
            $model = QuickbooksSalesReceipt::getInstance()
                        ->setId($id)
                        ->setRealmID($realmid);

            echo json_encode($model->readone(), JSON_NUMERIC_CHECK);
        } catch (Exception $e) {
            Error::response("Unable to return details of the sales receipt identified by Id=$id.", $e);
        }
    }

    /**
     * Delete from QBO the sales receipt identified by $id
     * @param string $realmid The company ID for the QBO company.
     * @param int $id The QBO id, not the DocNumber
     * @return void Output is echoed directly to response
     */
    public static function delete(string $realmid, int $id)
    {
        try {
            $model = QuickbooksSalesReceipt::getInstance()
                ->setId($id)
                ->setRealmID($realmid);

            if ($model->delete()) {
                echo json_encode(
                    array(
                    "message" => "Takings with id=$id was deleted.",
                    "id" => $id),
                    JSON_NUMERIC_CHECK
                );
            }
        } catch (Exception $e) {
            Error::response("Unable to delete the sales receipt identified by Id=$id.", $e);
        }
    }

    /**
     * Create a QBO sales receipt from data supplied via http POST
     * Sales items should be positive, Expenses and cash/credit cards are negative.
     *
     * Sample data:
     *  { "date": "2022-04-29", "donations": { "number": 0, "sales": 0 },
     *   "cashDiscrepency": 0.05,"creditCards": -381.2,"cash": -183.30,
     *   "operatingExpenses": -1.3,"volunteerExpenses": -5,
     *   "clothing": { "number": 53, "sales": 310.50 },
     *   "brica": { "number": 75, "sales": 251.75 },
     *   "books": { "number": 4, "sales": 3.5 },
     *   "linens": { "number": 1, "sales": 5 },
     *   "cashToCharity": 0, "shopid": 1
     *  }
     *
     * @param string $realmid The company ID for the QBO company.
     * @return void Output is echoed directly to response
     *
     */
    public static function create(string $realmid)
    {

        $emptySales = (object) [ 'number' => 0, 'sales' => 0];

        $data = json_decode(file_get_contents("php://input"));

        try {
            $model = QuickbooksSalesReceipt::getInstance()
              ->setDate($data->date)
              ->setShopid($data->shopid ?? 1)
              ->setClothing($data->clothing ?? $emptySales)
              ->setBrica($data->brica ?? $emptySales)
              ->setBooks($data->books ?? $emptySales)
              ->setLinens($data->linens ?? $emptySales)
              ->setRagging($data->ragging ?? $emptySales)
              ->setDonations($data->donations ?? $emptySales)
              ->setCashDiscrepancy($data->cashDiscrepancy ?? 0)
              ->setCreditCards($data->creditCards ?? 0)
              ->setCash($data->cash ?? 0)
              ->setOperatingExpenses($data->operatingExpenses ?? 0)
              ->setVolunteerExpenses($data->volunteerExpenses ?? 0)
              ->setCashToCharity($data->cashToCharity ?? 0)
              ->setPrivateNote($data->comments ?? '')
              ->setRealmID($realmid);

        } catch (\TypeError $e) {
            Error::response("Unable to enter daily sales receipt in Quickbooks.", $e, 422);
        } catch (Exception $e) {
            Error::response("Unable to enter daily sales receipt in Quickbooks.", $e);
        }

        if (!$model->validate()) {
            Error::response("Unable to enter sales receipt in QuickBooks. Transaction is not in balance for '" .
                $data->date . "'.");
        }

        try {
            $result = $model->create();
            if ($result) {
                echo json_encode(
                    array("message" => "Sales Receipt [". $result['label']  ."] has been added for " . $result['date'] . ".",
                        "id" => $result['id'])
                );
            }
        } catch (Exception $e) {
            Error::response("Unable to create sales receipt in QuickBooks.", $e);
        }
    }

    /**
     * Create a QB sales receipt from a Takings referenced by the given ID.
     * if the QB object is successfully created then update
     * @param string $realmid The company ID for the QBO company.
     * @param int $takingsid The id of the Takings
     * @return void Output is echoed directly to response
     *
     */
    public static function create_from_takings(string $realmid, int $takingsid)
    {
        try {
            $takings = new Takings();
            $takings->id = $takingsid;
            $takings->readOne();

            if ($takings->quickbooks != 0) {
                throw new Exception("ID " . $takingsid ." already entered into QuickBooks.");
            } elseif ($takings->date == null) {
                throw new Exception("No takings found in aukw database with that id ($takingsid).");
            } elseif ($takings->id == 0) {
                exit(0);
            }

            $model = QBSalesReceiptCtl::transfer_takings_data($takings);
            $model->setRealmID($realmid);
            $result = $model->create();
            if ($result) {
                $takings->quickbooks = 1;
                $takings->patch_quickbooks();
                echo json_encode(
                    array("message" => "Sales Receipt [". $result['label']  ."] has been added for " . $result['date'] . ".",
                          "id" => $result['id'])
                );
            }
        } catch (QuickBooksApiException $qbError) {
            Error::response("Duplicate DocNumber for " . $takings->date . " in QuickBooks.", $qbError);
        
        } catch (Exception $e) {
            Error::response("Unable to create QBO Sales Receipt from aukw takings record.", $e);
        }
    }

    /**
     * Create a QBO Sales receipt for each Takings item that has Quickbooks = 0
     * @param string $realmid The company ID for the QBO company.
     * @return void Output is echoed directly to response
     *
     */
    public static function create_all_from_takings(string $realmid)
    {
        try {
            // search for all the takings objects that are not yet entered into QuickBooks
            $takingsModel = new \Models\Takings();
            $takingsArray = $takingsModel->read_by_quickbooks_status(false);

            // Empty array ?
            if (count($takingsArray) == 0) {
                http_response_code(200); // This is not an error situation.
                echo json_encode(
                    array("message" => "No takings available to be entered into QuickBooks. Empty array returned from database.")
                );
                exit(0);
            }

            $message = array();

            foreach ($takingsArray as $takingsRow) {

                $takings = new Takings();
                $takings->id = $takingsRow["id"];
                $takings->readOne();

                $model = QBSalesReceiptCtl::transfer_takings_data($takings);

                // TODO: USe QBO Batch https://intuit.github.io/QuickBooks-V3-PHP-SDK/quickstart.html#batch-request

                $result = $model->create();
                if ($result) {
                    $takings->quickbooks = 1;
                    $takings->patch_quickbooks();
                    $message[] = array("message" => "Sales Receipt [". $result['label']
                                ."] has been added for " . $result['date']
                                . ".", "id" => $result['id']);
                }
            }

            echo json_encode($message);
        } catch (Exception $e) {
            Error::response("Error occurred while processing takings into QBO.", $e);
        }
    }

    /**
     * Prepare a sales receipt for insertion by transferring data from the given Takings object.
     *
     * @param Takings $takings
     *
     * @return QuickbooksSalesReceipt
     *
     */
    private static function transfer_takings_data($takings): QuickbooksSalesReceipt
    {
        try {
            $model = QuickbooksSalesReceipt::getInstance()
              ->setDate($takings->date)
              ->setShopid($takings->shopid ?? 1)
              ->setClothing((object) [ 'number' => $takings->clothing_num, 'sales' => $takings->clothing ])
              ->setBrica((object) [ 'number' => $takings->brica_num, 'sales' => $takings->brica ])
              ->setBooks((object) [ 'number' => $takings->books_num, 'sales' => $takings->books ])
              ->setLinens((object) [ 'number' => $takings->linens_num, 'sales' => $takings->linens ])
              ->setRagging((object) [ 'number' => $takings->rag_num, 'sales' => $takings->rag ])
              ->setDonations((object) [ 'number' => $takings->donations_num, 'sales' => $takings->donations ])
              ->setCashDiscrepancy($takings->cash_difference)
              ->setCreditCards($takings->credit_cards * -1)
              ->setCash($takings->cash_to_bank * -1)
              ->setOperatingExpenses($takings->operating_expenses * -1 + $takings->other_adjustments * -1)
              ->setVolunteerExpenses($takings->volunteer_expenses * -1)
              ->setCashToCharity($takings->cash_to_charity * -1)
              ->setPrivateNote(
                  $takings->comments ?? ''
              );
        } catch (\TypeError $e) {
            Error::response("Unable to enter daily sales receipt in Quickbooks.", $e, 422);
        } catch (Exception $e) {
            Error::response("Unable to enter daily sales receipt in Quickbooks.", $e);
        }

        if (!$model->validate()) {
            Error::response("Unable to enter sales receipt in QuickBooks. Transaction is not in balance for '" .
                $takings->date . "'.");
        }

        return $model;
    }

}
