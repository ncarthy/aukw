<?php

/**
 * This is the entry point for the api. It loads third party scripts from '/vendor/' and
 * then requires all the php code files that make up the api. Finally it starts the 
 * router so that when the user visits api endpoints they are directed to the correct place.
 * 
 * The structure of the app is:
 * 1) Core php scripts that store constants, allow access to the database and provide helper functions
 * 2) Controller classes that provide CRUD operations on the various models.
 * 3) Model classes that retrieve/store data and contian business logic.
 * 4) Routes.php and pre_routes.php which govern app routings.
 * 5) Third party code in /vendor/
 */

// Load the composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Create Router instance
$router = new \Bramus\Router\Router();

// Define core, database and helper classes
require 'core/config.php';
require 'core/database.php';
require 'core/errorresponse.php';
require 'core/headers.php';
require 'core/GUID.php';
require 'core/dateshelper.php';
require 'core/QuickbooksConstants.php';

// Define services
require 'services/payrollapiservice.php';

// Define controllers
require 'controllers/allocations.controller.php';
require 'controllers/auditlog.controller.php';
require 'controllers/quickbooks/qbauth.controller.php';
require 'controllers/quickbooks/qbattachment.controller.php';
require 'controllers/quickbooks/qbbill.controller.php';
require 'controllers/quickbooks/qbclass.controller.php';
require 'controllers/quickbooks/qbcompany.controller.php';
require 'controllers/quickbooks/qbemployee.controller.php';
require 'controllers/quickbooks/qbentity.controller.php';
require 'controllers/quickbooks/qbitem.controller.php';
require 'controllers/quickbooks/qbjournal.controller.php';
require 'controllers/quickbooks/qbpayrolljournal.controller.php';
require 'controllers/quickbooks/qbpayrollquery.controller.php';
require 'controllers/quickbooks/qbpurchase.controller.php';
require 'controllers/quickbooks/qbrealm.controller.php';
require 'controllers/quickbooks/qbrecurringtxn.controller.php';
require 'controllers/quickbooks/qbreport.controller.php';
require 'controllers/quickbooks/qbtax.controller.php';
require 'controllers/quickbooks/qbtransfer.controller.php';
require 'controllers/quickbooks/qbsalesreceipt.controller.php';
require 'controllers/staffology/employee.controller.php';
require 'controllers/staffology/payrun.controller.php';
require 'controllers/staffology/report.controller.php';
require 'controllers/staffology/taxyear.controller.php';
require 'controllers/report.controller.php';
require 'controllers/rule.controller.php';
require 'controllers/shop.controller.php';
require 'controllers/takings.controller.php';
require 'controllers/user.controller.php';

// Define models
require 'models/allocation.php';
require 'models/allocations.php';
require 'models/auditlog.php';
require 'models/jwt.php';
require 'models/payslip.php';
require 'models/quickbooks/qbauth.php';
require 'models/quickbooks/qbattachment.php';
require 'models/quickbooks/qbdatemacroenum.php';
require 'models/quickbooks/qbbill.php'; // This must be included before the files that depend on it.
require 'models/quickbooks/qbclass.php';
require 'models/quickbooks/qbemployee.php';
require 'models/quickbooks/qbitem.php';
require 'models/quickbooks/qbjournal.php'; // This must be included before the files that depend on it.
require 'models/quickbooks/qbnijournal.php'; // Depends on qbjournal.php
require 'models/quickbooks/qbpayrolljournal.php'; // Depends on qbjournal.php
require 'models/quickbooks/qbpensionbill.php'; // Depends on qbbill.php
require 'models/quickbooks/qbpurchase.php';
require 'models/quickbooks/qbquery.php';
require 'models/quickbooks/qbsalesreceipt.php';
require 'models/quickbooks/qbshopjournal.php'; // Depends on qbjournal.php
require 'models/quickbooks/qbrealm.php';
require 'models/quickbooks/qbrecurringtxn.php';
require 'models/quickbooks/qbreport.php';
require 'models/quickbooks/qbtoken.php';
require 'models/quickbooks/qbtransfer.php';
require 'models/staffology/employee.php';
require 'models/staffology/employees.php';
require 'models/staffology/grosstonet.php';
require 'models/staffology/parsegrosstonet.php';
require 'models/staffology/payruns.php';
require 'models/report.php';
require 'models/rowitem.php';
require 'models/rules.php';
require 'models/shop.php';
require 'models/summary.php';
require 'models/takings.php';
require 'models/user.php';
require 'models/usertoken.php';

// QB Report models
require 'models/quickbooks/qbreport/qbitemsales.php';
require 'models/quickbooks/qbreport/qbgeneralledger.php';
require 'models/quickbooks/qbreport/qbprofitandloss.php';

// Define routes
require 'pre_routes.php';
require 'routes.php';

/**
 * This function will be used to convert errors in this PHP code into Exceptions, which can
 * then be handled by try...catch blocks.
 * From {@link https://stackoverflow.com/a/40096085/6941165 stackoverflow} and {@link https://www.php.net/manual/en/class.errorexception.php php.net}
 * @param int $errno The level of the error raised, an integer
 * @param string $errstr The error message
 * @param string $errfile The filename that the error was raised in
 * @param int $errline The line number where the error was raised
 * @return false If the function returns false then the normal error handler continues.
 * @throws ErrorException This 
 */
function error_handler(int $errno, string $errstr, string $errfile, int $errline)
{
    if( ($errno & error_reporting()) > 0 ) {
        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            // Do not throw an Exception for deprecation warnings as new or unexpected
            // deprecations would break the application.
            return false;
        }
        throw new ErrorException($errstr, 500, $errno, $errfile, $errline);        
    } else
        return false;
}
set_error_handler('error_handler');

// Run it!
$router->run();