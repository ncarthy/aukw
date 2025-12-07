<?php

/**
 * 
 * Define endpoints and associated routes for the api
 * 
 * Router logic supplied by {@link https://github.com/bramus/router bramus\router}.
 * 
 * Using some example code from {@link https://github.com/wdekkers/raspberry-pi-app github}.
 * 
 * Regex cheat sheet: {@link https://courses.cs.washington.edu/courses/cse154/15sp/cheat-sheets/php-regex-cheat-sheet.pdf PDF File}.
 */

// General config
$router->setNamespace('\Controllers'); // Allows us to omit '\Controllers' from method names

// Custom 404 Handler
$router->set404(function() {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
  http_response_code(404);  
  echo json_encode(
      array("message" => "404, route not found!")
  );
});

/***************/
/* Auth Routes */
/***************/
$router->mount('/auth', function() use ($router) {
    // Login, JWT tokens returned + cookie
    $router->post('/', function () {include 'authenticate/auth.php'; } );
    // Generate a new access token from refresh token
    $router->get('/refresh', function () {include 'authenticate/refresh.php'; } );
    // Logout
    $router->delete('/', function () {include 'authenticate/revoke.php'; } );
    // Returns the uri needed to start the QBO authorisation process
    $router->get('/qb/auth', 'QBAuthCtl@oauth2_begin');
    // QBO callback endpoint for QBO authentication process
    $router->get('/qb/callback', 'QBAuthCtl@oauth2_callback');
});

/***************/
/* Takings Routes */
/***************/
$router->mount('/takings', function () use ($router) {
    // new takings entry
    $router->post('/', 'TakingsCtl@create');
    // update takings
    $router->put('/(\d+)', 'TakingsCtl@update');
    // delete takings
    $router->delete('/(\d+)', 'TakingsCtl@delete');
    // return all takings objects defined by quickbooks status
    $router->get('/quickbooks/(\d+)', 'TakingsCtl@read_by_quickbooks_status');
    // return all takings defined by shopid
    $router->get('/shop/(\d+)', 'TakingsCtl@read_by_shop');
    // return a single takings with the given id (primary key)
    $router->get('/(\d+)', 'TakingsCtl@read_one');

    // Return the Takings for the most recent date
    $router->get('/most-recent/(\d+)', 'TakingsCtl@read_most_recent');

    // Update single property on existing takings object
    // Sample body : { "quickbooks": 0 } ... or ... { "quickbooks": 1 }
    $router->patch('/(\d+)', 'TakingsCtl@patch');
});

/***************/
/* Report Routes */
/***************/
$router->mount('/report', function () use ($router) {
    $router->get('/histogram', 'ReportCtl@dailySalesHistogram');
    $router->get('/moving-avg', 'ReportCtl@dailySalesMovingAverage');
    $router->get('/summarytable', 'ReportCtl@performanceSummary');
    $router->get('/sales-chart', 'ReportCtl@salesChart');
    $router->get('/dept-chart', 'ReportCtl@departmentChart');
    $router->get('/avg-weekly-sales/(\d+)', 'ReportCtl@avgWeeklySales');
    $router->get('/avg-weekly-sales-by-quarter/(\d+)', 'ReportCtl@avgWeeklySalesByQuarter');
    $router->get('/avg-daily-transaction-size/(\d+)', 'ReportCtl@avgDailyTransactionSize');
    $router->get('/avg-daily-txn-by-quarter/(\d+)', 'ReportCtl@avgDailyTransactionSizeByQuarter');
    $router->get('/sales-by-department/(\d+)', 'ReportCtl@salesByDepartment');
    $router->get('/cash-ratio-moving-avg/(\d+)', 'ReportCtl@cashRatioMovingAverage');
    $router->get('/customer-insights/(\d+)', 'ReportCtl@salesByDepartmentAndCustomerMovingAverage');

    // Dynamic route with (successive) optional subpatterns: /monthly-sales/shopid(/year(/month(/day)))
    $router->get('/monthly-sales/(\d+)(/\d{4}(/\d{2}(/\d{2})?)?)?', 'ReportCtl@salesByMonth');
    $router->get('/quarterly-sales/(\d+)(/\d{4})?', 'ReportCtl@salesByQuarter');

    // Show takings data for the last 90 days for a given shop
    // (A simplification of the following api method)
    $router->get('/takingssummary/shop/(\d+)', 'ReportCtl@takingsSummary');
    // Show takings data for the last 'datapoints' days for a given shop
    $router->get('/saleslist/shop/(\d+)/datapoints/(\d+)', 'ReporCtl@salesList');

});

/***************/
/* Shop Routes */
/***************/
$router->mount('/shop', function () use ($router) {
    // return all shops
    $router->get('/', 'ShopCtl@read_all');
    // return one shop, with the given id (primary key)
    $router->get('/(\d+)', 'ShopCtl@read_one');
    // return one shop, with the given name
    $router->get('/(\D+)', 'ShopCtl@read_one_name');
});

/*********************/
/* QuickBooks Routes */
/*********************/
$router->mount('/qb', function () use ($router) {
    // The first parameter is realmid, as is customary with the QBO api
    // The second param is the QuickBooks Journal Id. This number is not easily 
    // seen on the normal QB website but it can been seen in Audit Log.
    // It is not the DocNumber which can be seen when adding/editing on QBO.
    $router->get('/(\d+)/journal/(\d+)', 'QuickBooks\QBJournalCtl@read_one');
    // Get a list of journals whose DocNumber starts with the given string                                    
    $router->get('/(\d+)/journal/docnumber/(\w+)', 'QuickBooks\QBJournalCtl@query_by_docnumber');
    // QB Payroll Journal for individual employee
    $router->post('/(\d+)/journal/employee', 'QuickBooks\QBPayrollJournalCtl@create_employee_payslip_journal');
    // QB Payroll Journal for Employer NI 
    $router->post('/(\d+)/journal/employerni', 'QuickBooks\QBPayrollJournalCtl@create_employer_ni_journal');
    // QB Payroll Journal for shop (aka 'Enterprises')
    $router->post('/(\d+)/journal/enterprises', 'QuickBooks\QBPayrollJournalCtl@create_enterprises_journal');

    // The 2nd param is the QuickBooks Journal Id. This number is not easily seen on 
    // the normal QB website but it can been seen in the QBO Audit Log.
    // It is not the DocNumber which can be seen when adding/editing on QBO website.
    $router->get('/(\d+)/salesreceipt/(\w+)', 'QuickBooks\QBSalesReceiptCtl@read_one');

    // Create a new sales receipt in QB
    $router->post('/(\d+)/salesreceipt', 'QuickBooks\QBSalesReceiptCtl@create');
    // Delete a sales receipt in QB
    $router->delete('/(\d+)/salesreceipt/(\w+)', 'QuickBooks\QBSalesReceiptCtl@delete');

    // The param is the takingsid value in the takings table in MySQL dB
    $router->post('/(\d+)/salesreceipt/takings/(\d+)', 'QuickBooks\QBSalesReceiptCtl@create_from_takings');
    // take action on takings journal; Only 'create_all' implemented so far.
    // Create All adds to QB any takings which has Quickbooks=0 in the mariaDB
    $router->patch('/(\d+)/salesreceipt/takings/', 'QuickBooks\QBSalesReceiptCtl@create_all_from_takings');

    // Returns the uri needed to start the QBO authorisation process
    $router->get('/auth', 'QuickBooks\QBAuthCtl@oauth2_begin');    
    // Exchange a refresh token for a new access token
    $router->get('/(\d+)/refresh/(\d+)', 'QuickBooks\QBAuthCtl@oauth2_refresh');
    // Delete QBO authorisation
    $router->delete('/(\d+)/connection', 'QuickBooks\QBAuthCtl@oauth2_revoke');
    // Retrieve details of one of the connections to QB (if any)
    $router->get('/(\d+)/connection', 'QuickBooks\QBAuthCtl@connection_details');
    // Retrieve details of the connections to QB (if any)
    $router->get('/connections', 'QuickBooks\QBAuthCtl@all_connection_details');
    
    // Retrieve details of the QBO company
    $router->get('/(\d+)/companyinfo', 'QuickBooks\QBCompanyCtl@companyInfo');
    // return list of all QB realms in database
    $router->get('/realm', 'QuickBooks\QBRealmCtl@read_all');
    // return single user that has the given realm id
    $router->get('/realm/(\w+)', 'QuickBooks\QBRealmCtl@read_one');

    // QB item is for Products/Services
    $router->get('/(\d+)/item/(\w+)', 'QuickBooks\QBItemCtl@read_one');
    $router->get('/(\d+)/items', 'QuickBooks\QBItemCtl@read_all');

    // Return details of a QB bill (aka invoice)
    $router->get('/(\d+)/bill/(\w+)', 'QuickBooks\QBBillCtl@read_one');
    // QB Bill for Pension payments
    $router->post('/(\d+)/bill/pensions', 'QuickBooks\QBBillCtl@create_pensions_bill');
    // Get a list of bills whose DocNumber starts with the given string                                    
    $router->get('/(\d+)/bill/docnumber/(\w+)', 'QuickBooks\QBBillCtl@query_by_docnumber');
    // Delete a bill in QB
    $router->delete('/(\d+)/bill/(\w+)', 'QuickBooks\QBBillCtl@delete');

    // QB Class
    $router->get('/(\d+)/class/(\w+)', 'QuickBooks\QBClassCtl@read_one');
    $router->get('/(\d+)/classes', 'QuickBooks\QBClassCtl@read_all');
    
    // QB Employee
    $router->get('/(\d+)/employee/(\d+)', 'QuickBooks\QBEmployeeCtl@read_one');
    $router->get('/(\d+)/employee', 'QuickBooks\QBEmployeeCtl@read_all');    
    $router->post('/(\d+)/employee', 'QuickBooks\QBEmployeeCtl@create');

    // QB Recurring Transactions
    $router->get('/(\d+)/recurringtransaction/(\w+)', 'QuickBooks\QBRecurringTransactionCtl@read_one');
    $router->get('/(\d+)/recurringtransactions', 'QuickBooks\QBRecurringTransactionCtl@read_all');

    // QB Payroll Query
    $router->get('/(\d+)/query/payroll/(\d{4})/(\d{2})', 'QuickBooks\QBPayrollQueryCtl@query');

    // QB Report
    $router->get('/(\d+)/report/generalledger', 'QuickBooks\QBReportCtl@general_ledger');
    $router->get('/(\d+)/report/profitandlossraw', 'QuickBooks\QBReportCtl@profit_and_loss_raw');
    $router->get('/(\d+)/report/profitandloss', 'QuickBooks\QBReportCtl@profit_and_loss');
    $router->get('/(\d+)/report/salesbyitem', 'QuickBooks\QBReportCtl@sales_by_item');
    $router->get('/(\d+)/report/salesbyitemraw', 'QuickBooks\QBReportCtl@sales_by_item_raw');
    $router->get('/(\d+)/report/qma', 'QuickBooks\QBReportCtl@quarterly_market_report');
    $router->get('/(\d+)/report/ragging-by-quarter', 'QuickBooks\QBReportCtl@ragging_by_quarter');

    //QB Attachments
    $router->get('/(\d+)/attachments', 'QuickBooks\QBAttachmentCtl@read_by_entity');
    $router->get('/(\d+)/attachment/(\w+)', 'QuickBooks\QBAttachmentCtl@read_by_id');
    $router->get('/(\d+)/download-attachments', 'QuickBooks\QBAttachmentCtl@download');
    $router->post('/(\d+)/attachments', 'QuickBooks\QBAttachmentCtl@create');

    // QB Transfer
    $router->get('/(\d+)/transfer/(\w+)', 'QuickBooks\QBTransferCtl@read_one');
    $router->post('/(\d+)/transfer', 'QuickBooks\QBTransferCtl@create');                                 
    $router->delete('/(\d+)/transfer/(\w+)', 'QuickBooks\QBTransferCtl@delete');
    $router->post('/(\d+)/enterprises-interco', 'QuickBooks\QBTransferCtl@create_enterprises_interco');  

    // QB Purchase
    $router->get('/(\d+)/purchase/(\w+)', 'QuickBooks\QBPurchaseCtl@read_one');
    $router->post('/(\d+)/purchase', 'QuickBooks\QBPurchaseCtl@create');                                 
    $router->delete('/(\d+)/purchase/(\d+)', 'QuickBooks\QBPurchaseCtl@delete');

    // QB Tax Rates
    $router->get('/(\d+)/tax-code', 'QuickBooks\QBTaxCtl@read_all');
    $router->get('/(\d+)/tax-code/(\w+)', 'QuickBooks\QBTaxCtl@read_one');

    // QB Entities
    $router->get('/(\d+)/entity/vendor', 'QuickBooks\QBEntityCtl@read_all_vendors');
    $router->get('/(\d+)/entity/customer', 'QuickBooks\QBEntityCtl@read_all_customers');
    $router->get('/(\d+)/entity/account', 'QuickBooks\QBEntityCtl@read_all_accounts');
    $router->get('/(\d+)/entity/class', 'QuickBooks\QBEntityCtl@read_all_classes');

    // interco transaction matching
    $router->post('/(\d+)/transaction-match', 'QuickBooks\QBEntityCtl@interco_trade_from_rules');

    // Allocations
    $router->get('/(\d+)/employee/allocations', 'QuickBooks\QBPayrollJournalCtl@read_employee_allocations');
});

/***************/
/* User Routes */
/***************/
$router->mount('/user', function () use ($router) {

    // return list of all users
    $router->get('/', 'UserCtl@read_all');

    // return single user that has the given id
    $router->get('/(\d+)', 'UserCtl@read_one');

    // return single user that has the given name an email address
    $router->get('/search', 'UserCtl@read_one_by_name_and_email');

    // new user
    $router->post('/', 'UserCtl@create');

    // delete user
    $router->delete('/(\d+)', 'UserCtl@delete');

    // update user
    $router->put('/(\d+)', 'UserCtl@update');
});

/***************/
/* Audit Log Routes */
/***************/
$router->mount('/auditlog', function () use ($router) {
    // new takings entry
    $router->post('/', 'AuditLogCtl@create');
    // return all audit log records
    $router->get('/', 'AuditLogCtl@read');
    // return all audit log event types
    $router->get('/eventtype', 'AuditLogCtl@read_eventtypes');
});

/***************/
/* Rule Routes */
/***************/
$router->mount('/transaction-match', function () use ($router) {
    // return all rule records
    $router->get('/rule', 'RuleCtl@read_all');
    $router->post('/(\d+)/match', 'RuleCtl@interco_trade_from_rules');
});

/*************************/
/* Staffology API Routes */
/*************************/
$router->mount('/payroll', function () use ($router) {
    $router->get('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/payrun/(\w+)', 'Staffology\PayRunCtl@read_all');
    $router->get('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/payrun/most-recent(/\w+)?', 'Staffology\PayRunCtl@read_most_recent');
    $router->get('/taxyear', 'Staffology\TaxYearCtl@read_names');
    $router->get('/taxyear/latest', 'Staffology\TaxYearCtl@read_name_latest');
    $router->get('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/reports/gross-to-net/(\w+)/month/(\d+)'
                            , 'Staffology\PayrollReportCtl@gross_to_net');
    $router->get('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/employees', 'Staffology\EmployeeCtl@read');                              
    $router->get('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/employees/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})', 'Staffology\EmployeeCtl@readOneById');
    $router->get('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/employees/(\w+)', 'Staffology\EmployeeCtl@readOneByPayrollNumber');
});

/*************************/
/* Allocations Routes */
/*************************/
$router->mount('/allocations', function () use ($router) {
    $router->get('/', 'AllocationsCtl@read_all');
    $router->delete('/', 'AllocationsCtl@delete');
    $router->post('/', 'AllocationsCtl@create');

    $router->post('/append', 'AllocationsCtl@append');
    
    $router->get('/id/(\d+)/class/(\d+)', 'AllocationsCtl@read_one');
    

    $router->get('/(\d+)', 'AllocationsCtl@read_one_payrollnumber');
    $router->delete('/(\d+)', 'AllocationsCtl@deleteOne');     
});