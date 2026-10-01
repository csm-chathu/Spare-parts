<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\UserFeatureController;
use App\Http\Controllers\Api\JobCardController;
use App\Http\Controllers\Api\JobCardPublicController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CloudinaryUploadController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EpfEtfSettingController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GLController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\OpeningBalanceController;
use App\Http\Controllers\Api\PartBrandController;
use App\Http\Controllers\Api\PartCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\QualityTypeController;
use App\Http\Controllers\Api\StockLedgerController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\RentPaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalaryPaymentController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ShopSettingController;
use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TaxSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleModelController;
use App\Http\Controllers\Api\VehicleTypeController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/shop-branding', [ShopSettingController::class, 'branding']);

// Keep-alive ping for shared hosting
Route::get('/ping', fn() => response()->json(['ok' => true]));

// Public — no auth required
Route::get('/sales/public/{token}', [SaleController::class, 'publicView']);
Route::get('/job-cards/public/{token}', [JobCardPublicController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user()->load('branch:id,name,code'));
    Route::get('/me/features', [UserFeatureController::class, 'index']);
    Route::get('/features/by-role/{role}', [UserFeatureController::class, 'byRole']);
    Route::get('/users/{user}/features', [UserFeatureController::class, 'forUser']);
    Route::put('/users/{user}/features', [UserFeatureController::class, 'saveForUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Reference selects
    Route::get('/suppliers/all',  [SupplierController::class, 'all']);
    Route::get('/customers/all',  [CustomerController::class, 'all']);
    Route::post('/uploads/cloudinary', [CloudinaryUploadController::class, 'store']);

    // CRUD resources
    // Job Cards
    Route::get('job-cards',                          [JobCardController::class, 'index']);
    Route::post('job-cards',                         [JobCardController::class, 'store']);
    Route::get('job-cards/{jobCard}',                [JobCardController::class, 'show']);
    Route::put('job-cards/{jobCard}',                [JobCardController::class, 'update']);
    Route::delete('job-cards/{jobCard}',             [JobCardController::class, 'destroy']);
    Route::post('job-cards/{jobCard}/items',         [JobCardController::class, 'addItem']);
    Route::delete('job-cards/{jobCard}/items/{item}',[JobCardController::class, 'removeItem']);
    Route::post('job-cards/{jobCard}/complete',      [JobCardController::class, 'complete']);

    Route::apiResource('suppliers',    SupplierController::class);
    Route::apiResource('products',     ProductController::class);
    Route::apiResource('customers',    CustomerController::class);
    Route::apiResource('sales',        SaleController::class);
    Route::post('/sales/{sale}/settle-booking', [SaleController::class, 'settleBooking']);
    Route::post('/sales/{sale}/send-sms',       [SaleController::class, 'sendSms']);
    Route::post('/sales/{sale}/finalize',       [SaleController::class, 'finalize']);
    Route::apiResource('purchases',    PurchaseController::class)->except(['update']);
    Route::post('purchases/{purchase}/receive',       [PurchaseController::class, 'receive']);
    Route::patch('purchases/{purchase}/cancel',       [PurchaseController::class, 'cancel']);
    Route::post('purchases/{purchase}/settle-cheque',  [PurchaseController::class, 'settleChequePurchase']);
    Route::post('purchases/{purchase}/settle-credit',  [PurchaseController::class, 'settleCreditPurchase']);

    // Stock Ledger
    Route::get('stock-ledger',          [StockLedgerController::class, 'index']);
    Route::get('stock-ledger/products', [StockLedgerController::class, 'products']);

    // Supplier Payments
    Route::get('supplier-payments',             [SupplierPaymentController::class, 'index']);
    Route::post('supplier-payments',            [SupplierPaymentController::class, 'store']);
    Route::delete('supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'destroy']);

    // Purchase Returns
    Route::get('purchase-returns',                      [PurchaseReturnController::class, 'index']);
    Route::post('purchase-returns',                     [PurchaseReturnController::class, 'store']);
    Route::get('purchase-returns/{purchaseReturn}',     [PurchaseReturnController::class, 'show']);
    Route::delete('purchase-returns/{purchaseReturn}',  [PurchaseReturnController::class, 'destroy']);

    // ── Spare Parts Reference Data ──────────────────────────────────────────
    Route::apiResource('vehicle-types',  VehicleTypeController::class);
    Route::apiResource('brands',         BrandController::class);
    Route::apiResource('vehicle-models', VehicleModelController::class);
    Route::apiResource('part-categories', PartCategoryController::class);
    Route::apiResource('part-brands',     PartBrandController::class);
    Route::apiResource('quality-types',  QualityTypeController::class);

    // Tax settings
    Route::apiResource('tax-settings', TaxSettingController::class);

    // Shop Settings
    Route::get('/shop-settings',       [ShopSettingController::class, 'index']);
    Route::post('/shop-settings',      [ShopSettingController::class, 'update']);
    Route::post('/shop-settings/logo', [ShopSettingController::class, 'uploadLogo']);

    // Expenses
    Route::get('/expenses',                [ExpenseController::class, 'index']);
    Route::post('/expenses',               [ExpenseController::class, 'store']);
    Route::get('/expenses/:id',            [ExpenseController::class, 'show']);
    Route::put('/expenses/{expense}',      [ExpenseController::class, 'update']);
    Route::delete('/expenses/{expense}',   [ExpenseController::class, 'destroy']);
    Route::get('/expenses-summary',        [ExpenseController::class, 'summary']);

    // SMS
    Route::get('/sms/logs',             [SmsController::class, 'logs']);
    Route::get('/sms/customer-list',    [SmsController::class, 'customerList']);
    Route::get('/sms/birthday-preview', [SmsController::class, 'birthdayPreview']);
    Route::post('/sms/send-custom',     [SmsController::class, 'sendCustom']);
    Route::post('/sms/send-promotion',  [SmsController::class, 'sendPromotion']);
    Route::post('/sms/send-birthdays',  [SmsController::class, 'sendBirthdays']);

    // User management (admin)
    Route::get('/users',               [UserController::class, 'index']);
    Route::post('/users',              [UserController::class, 'store']);
    Route::put('/users/{user}',        [UserController::class, 'update']);
    Route::delete('/users/{user}',     [UserController::class, 'destroy']);
    Route::get('/branches',            [UserController::class, 'branches']);

    // Reports
    Route::get('/reports/day-end',       [ReportController::class, 'dayEnd']);
    Route::post('/reports/day-end',      [ReportController::class, 'storeDayEnd']);
    Route::get('/reports/sales',         [ReportController::class, 'salesSummary']);
    Route::get('/reports/purchases',     [ReportController::class, 'purchasesSummary']);
    Route::get('/reports/inventory',     [ReportController::class, 'inventory']);
    Route::get('/reports/top-products',  [ReportController::class, 'topProducts']);
    Route::get('/reports/profit-loss',   [ReportController::class, 'profitLoss']);
    Route::get('/reports/salary',        [ReportController::class, 'salaryReport']);
    Route::get('/reports/expenses',      [ReportController::class, 'expensesReport']);

    // Audit log
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Database backup (admin only)
    Route::get('/backup/download', [BackupController::class, 'download']);

    // ── Accounting ──────────────────────────────────────────────────────────
    Route::get('/accounts/all',          [AccountController::class, 'all']);
    Route::get('/accounts',              [AccountController::class, 'index']);
    Route::post('/accounts',             [AccountController::class, 'store']);
    Route::put('/accounts/{account}',    [AccountController::class, 'update']);
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy']);

    Route::get('/opening-balances',  [OpeningBalanceController::class, 'index']);
    Route::post('/opening-balances', [OpeningBalanceController::class, 'store']);

    Route::get('/journal-entries',                   [JournalEntryController::class, 'index']);
    Route::post('/journal-entries',                  [JournalEntryController::class, 'store']);
    Route::get('/journal-entries/{journalEntry}',    [JournalEntryController::class, 'show']);
    Route::delete('/journal-entries/{journalEntry}', [JournalEntryController::class, 'destroy']);

    Route::get('/gl/trial-balance',    [GLController::class, 'trialBalance']);
    Route::get('/gl/balance-sheet',    [GLController::class, 'balanceSheet']);
    Route::get('/gl/income-statement', [GLController::class, 'incomeStatement']);
    Route::get('/gl/ledger/{account}', [GLController::class, 'ledger']);

    // ── HR / Employee & Payroll ─────────────────────────────────────────────
    Route::get('/employees/all',           [EmployeeController::class, 'all']);
    Route::get('/employees',               [EmployeeController::class, 'index']);
    Route::post('/employees',              [EmployeeController::class, 'store']);
    Route::get('/employees/{employee}',    [EmployeeController::class, 'show']);
    Route::put('/employees/{employee}',    [EmployeeController::class, 'update']);
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

    Route::get('/salary-payments',                    [SalaryPaymentController::class, 'index']);
    Route::post('/salary-payments',                   [SalaryPaymentController::class, 'store']);
    Route::delete('/salary-payments/{salaryPayment}', [SalaryPaymentController::class, 'destroy']);
    Route::get('/salary-payments/summary',            [SalaryPaymentController::class, 'summary']);

    Route::get('/epf-etf-settings',          [EpfEtfSettingController::class, 'index']);
    Route::get('/epf-etf-settings/current',  [EpfEtfSettingController::class, 'current']);
    Route::post('/epf-etf-settings',         [EpfEtfSettingController::class, 'store']);

    // ── Finance: Loans & Monthly Rent ──────────────────────────────────────
    Route::get('/loans',               [LoanController::class, 'index']);
    Route::post('/loans',              [LoanController::class, 'store']);
    Route::get('/loans/{loan}',        [LoanController::class, 'show']);
    Route::post('/loans/{loan}/repay', [LoanController::class, 'repay']);

    Route::get('/rent-payments',                      [RentPaymentController::class, 'index']);
    Route::post('/rent-payments',                     [RentPaymentController::class, 'store']);
    Route::post('/rent-payments/{rentPayment}/pay',   [RentPaymentController::class, 'pay']);
    Route::get('/rent-payments/reminders',            [RentPaymentController::class, 'reminders']);
});
