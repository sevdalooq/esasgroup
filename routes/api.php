<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\PersonnelGroupController;
use App\Http\Controllers\Api\PersonnelController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectDayController;
use App\Http\Controllers\Api\DayOperationsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\PersonnelPaymentController;
use App\Http\Controllers\Api\GroupPaymentController;
use App\Http\Controllers\Api\CustomerPaymentController;
use App\Http\Controllers\Api\ExpenseApprovalController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Customers (Müşteriler)
    Route::get('/customers/all', [CustomerController::class, 'all']);
    Route::middleware('permission:customers.view')->get('/customers', [CustomerController::class, 'index']);
    Route::middleware('permission:customers.view')->get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::middleware('permission:customers.view')->get('/customers/{customer}/details', [CustomerController::class, 'details']);
    Route::middleware('permission:customers.create')->post('/customers', [CustomerController::class, 'store']);
    Route::middleware('permission:customers.edit')->put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::middleware('permission:customers.delete')->delete('/customers/{customer}', [CustomerController::class, 'destroy']);

    // Groups (Aracı Firmalar)
    Route::get('/groups/all', [GroupController::class, 'all']);
    Route::middleware('permission:groups.view')->get('/groups', [GroupController::class, 'index']);
    Route::middleware('permission:groups.view')->get('/groups/{group}', [GroupController::class, 'show']);
    Route::middleware('permission:groups.create')->post('/groups', [GroupController::class, 'store']);
    Route::middleware('permission:groups.edit')->put('/groups/{group}', [GroupController::class, 'update']);
    Route::middleware('permission:groups.delete')->delete('/groups/{group}', [GroupController::class, 'destroy']);

    // Personnel Groups (Personel Grupları)
    Route::middleware('permission:personnel.view')->get('/personnel-groups/all', [PersonnelGroupController::class, 'all']);
    Route::middleware('permission:personnel.create|personnel.edit')->post('/personnel-groups', [PersonnelGroupController::class, 'store']);

    // Personnel (Personel Havuzu)
    Route::get('/personnel/all', [PersonnelController::class, 'all']);
    Route::middleware('permission:personnel.view')->get('/personnel', [PersonnelController::class, 'index']);
    Route::middleware('permission:personnel.view')->get('/personnel/{personnel}', [PersonnelController::class, 'show']);
    Route::middleware('permission:personnel.create')->post('/personnel', [PersonnelController::class, 'store']);
    Route::middleware('permission:personnel.edit')->put('/personnel/{personnel}', [PersonnelController::class, 'update']);
    Route::middleware('permission:personnel.delete')->delete('/personnel/{personnel}', [PersonnelController::class, 'destroy']);

    // Inventory (Envanter)
    Route::get('/inventory/all', [InventoryController::class, 'all']);
    Route::middleware('permission:inventory.view')->get('/inventory', [InventoryController::class, 'index']);
    Route::middleware('permission:inventory.view')->get('/inventory/{inventory}', [InventoryController::class, 'show']);
    Route::middleware('permission:inventory.create')->post('/inventory', [InventoryController::class, 'store']);
    Route::middleware('permission:inventory.edit')->put('/inventory/{inventory}', [InventoryController::class, 'update']);
    Route::middleware('permission:inventory.edit')->post('/inventory/{inventory}/assign', [InventoryController::class, 'assignToPersonnel']);
    Route::middleware('permission:inventory.edit')->post('/inventory/{inventory}/return', [InventoryController::class, 'returnFromPersonnel']);
    Route::middleware('permission:inventory.delete')->delete('/inventory/{inventory}', [InventoryController::class, 'destroy']);

    // Projects (Projeler)
    Route::middleware('permission:projects.view')->get('/projects', [ProjectController::class, 'index']);
    Route::middleware('permission:projects.view')->get('/projects/{project}', [ProjectController::class, 'show']);
    Route::middleware('permission:projects.create')->post('/projects', [ProjectController::class, 'store']);
    Route::middleware('permission:projects.edit')->put('/projects/{project}', [ProjectController::class, 'update']);
    Route::middleware('permission:projects.change_status')->post('/projects/{project}/status', [ProjectController::class, 'updateStatus']);
    Route::middleware('permission:projects.approve')->post('/projects/{project}/approve', [ProjectController::class, 'approve']);
    Route::middleware('permission:projects.approve')->post('/projects/{project}/reject', [ProjectController::class, 'reject']);
    Route::middleware('permission:projects.view')->get('/projects/{project}/calculate-cost', [ProjectController::class, 'calculateCost']);
    Route::middleware('permission:projects.view')->get('/projects/{project}/accounting-summary', [ProjectController::class, 'accountingSummary']);
    Route::middleware('permission:accounting.finalize')->post('/projects/{project}/finalize', [ProjectController::class, 'finalize']);
    Route::middleware('permission:projects.delete')->delete('/projects/{project}', [ProjectController::class, 'destroy']);

    // Project Days (Proje Günleri)
    Route::middleware('permission:projects.view')->get('/project-days/{projectDay}', [ProjectDayController::class, 'show']);
    Route::middleware('permission:projects.manage_days')->put('/project-days/{projectDay}', [ProjectDayController::class, 'update']);
    Route::middleware('permission:projects.manage_days')->post('/project-days/{projectDay}/personnel', [ProjectDayController::class, 'assignPersonnel']);
    Route::middleware('permission:projects.manage_days')->post('/project-days/{projectDay}/personnel/bulk', [ProjectDayController::class, 'bulkAssignPersonnel']);
    Route::middleware('permission:projects.manage_days')->delete('/project-days/{projectDay}/personnel/{assignment}', [ProjectDayController::class, 'removePersonnel']);
    Route::middleware('permission:projects.manage_days')->put('/project-days/{projectDay}/personnel/{assignment}', [ProjectDayController::class, 'updatePersonnelAssignment']);
    Route::middleware('permission:projects.manage_days')->post('/project-days/{projectDay}/inventory', [ProjectDayController::class, 'assignInventory']);
    Route::middleware('permission:projects.manage_days')->delete('/project-days/{projectDay}/inventory/{assignment}', [ProjectDayController::class, 'removeInventory']);
    Route::middleware('permission:projects.manage_days')->post('/project-days/{projectDay}/inventory/{assignment}/deliver', [ProjectDayController::class, 'deliverInventory']);
    Route::middleware('permission:projects.manage_days')->post('/project-days/{projectDay}/copy-previous', [ProjectDayController::class, 'copyFromPreviousDay']);
    Route::middleware('permission:projects.manage_days')->post('/project-days/{projectDay}/expenses', [ProjectDayController::class, 'addExpense']);
    Route::middleware('permission:projects.manage_days')->delete('/project-days/{projectDay}/expenses/{expense}', [ProjectDayController::class, 'deleteExpense']);

    // Day Operations (Gün Operasyonları - Wizard)
    Route::middleware('permission:projects.start_day')->get('/project-days/{projectDay}/start-data', [DayOperationsController::class, 'getStartDayData']);
    Route::middleware('permission:projects.start_day')->post('/project-days/{projectDay}/check-in/{assignment}', [DayOperationsController::class, 'checkInPersonnel']);
    Route::middleware('permission:projects.start_day')->post('/project-days/{projectDay}/start', [DayOperationsController::class, 'startDay']);
    Route::middleware('permission:projects.end_day')->get('/project-days/{projectDay}/end-data', [DayOperationsController::class, 'getEndDayData']);
    Route::middleware('permission:projects.end_day')->post('/project-days/{projectDay}/check-out/{assignment}', [DayOperationsController::class, 'checkOutPersonnel']);
    Route::middleware('permission:projects.end_day')->post('/project-days/{projectDay}/end', [DayOperationsController::class, 'endDay']);
    Route::get('/zones/suggestions', [DayOperationsController::class, 'getZoneSuggestions']);
    Route::post('/upload/photo', [DayOperationsController::class, 'uploadPhoto']);
    Route::middleware('permission:inventory.edit')->post('/inventory/{inventory}/damage', [DayOperationsController::class, 'reportDamage']);

    // Management - Users (Kullanıcı Yönetimi)
    Route::middleware('permission:users.view')->get('/users/all', [UserController::class, 'all']);
    Route::middleware('permission:users.view')->get('/users', [UserController::class, 'index']);
    Route::middleware('permission:users.view')->get('/users/{user}', [UserController::class, 'show']);
    Route::middleware('permission:users.view')->get('/users/{user}/permissions', [UserController::class, 'getPermissions']);
    Route::middleware('permission:users.create')->post('/users', [UserController::class, 'store']);
    Route::middleware('permission:users.edit')->put('/users/{user}', [UserController::class, 'update']);
    Route::middleware('permission:users.edit')->put('/users/{user}/permissions', [UserController::class, 'updatePermissions']);
    Route::middleware('permission:users.delete')->delete('/users/{user}', [UserController::class, 'destroy']);

    // Management - Roles (Rol Yönetimi)
    Route::middleware('permission:roles.view')->get('/roles/all', [RoleController::class, 'all']);
    Route::middleware('permission:roles.view')->get('/roles/permissions', [RoleController::class, 'permissions']);
    Route::middleware('permission:roles.view')->get('/roles', [RoleController::class, 'index']);
    Route::middleware('permission:roles.view')->get('/roles/{role}', [RoleController::class, 'show']);
    Route::middleware('permission:roles.create')->post('/roles', [RoleController::class, 'store']);
    Route::middleware('permission:roles.edit')->put('/roles/{role}', [RoleController::class, 'update']);
    Route::middleware('permission:roles.delete')->delete('/roles/{role}', [RoleController::class, 'destroy']);

    // Accounting - Kasalar
    Route::get('/accounts/all', [AccountController::class, 'index']);
    Route::middleware('permission:accounting.view')->get('/accounts', [AccountController::class, 'index']);
    Route::middleware('permission:accounting.view')->get('/accounts/{account}', [AccountController::class, 'show']);
    Route::middleware('permission:accounting.view')->get('/accounts/{account}/transactions', [AccountController::class, 'transactions']);
    Route::middleware('permission:accounting.manage')->post('/accounts', [AccountController::class, 'store']);
    Route::middleware('permission:accounting.manage')->put('/accounts/{account}', [AccountController::class, 'update']);
    Route::middleware('permission:accounting.manage')->delete('/accounts/{account}', [AccountController::class, 'destroy']);
    Route::middleware('permission:accounting.manage')->post('/accounts/recalculate-balances', [AccountController::class, 'recalculateBalances']);

    // Accounting - Personnel Payments (Personel Ödemeleri)
    Route::middleware('permission:accounting.view')->get('/personnel-payments/balances', [PersonnelPaymentController::class, 'allBalances']);
    Route::middleware('permission:accounting.view')->get('/personnel/{personnel}/payments', [PersonnelPaymentController::class, 'index']);
    Route::middleware('permission:accounting.make_payment')->post('/personnel/{personnel}/payments', [PersonnelPaymentController::class, 'store']);

    // Accounting - Group Payments (Grup Ödemeleri)
    Route::middleware('permission:accounting.view')->get('/group-payments/balances', [GroupPaymentController::class, 'allBalances']);
    Route::middleware('permission:accounting.view')->get('/groups/{group}/payments', [GroupPaymentController::class, 'index']);
    Route::middleware('permission:accounting.make_payment')->post('/groups/{group}/payments', [GroupPaymentController::class, 'store']);

    // Accounting - Customer Payments (Müşteri Ödemeleri)
    Route::middleware('permission:accounting.view')->get('/customers/{customer}/payments', [CustomerPaymentController::class, 'index']);
    Route::middleware('permission:accounting.view')->get('/projects/{project}/customer-payments', [CustomerPaymentController::class, 'projectPayments']);
    Route::middleware('permission:accounting.receive_payment')->post('/customers/{customer}/payments', [CustomerPaymentController::class, 'store']);
    Route::middleware('permission:accounting.receive_payment')->put('/customer-payments/{payment}', [CustomerPaymentController::class, 'update']);
    Route::middleware('permission:accounting.receive_payment')->delete('/customer-payments/{payment}', [CustomerPaymentController::class, 'destroy']);

    // Accounting - Expense Approvals (Masraf Onayları)
    Route::middleware('permission:accounting.approve_expenses')->get('/expenses/pending', [ExpenseApprovalController::class, 'pending']);
    Route::middleware('permission:projects.view')->get('/projects/{project}/expenses', [ExpenseApprovalController::class, 'projectExpenses']);
    Route::middleware('permission:accounting.approve_expenses')->post('/expenses/{expense}/approve', [ExpenseApprovalController::class, 'approve']);
    Route::middleware('permission:accounting.approve_expenses')->post('/expenses/{expense}/reject', [ExpenseApprovalController::class, 'reject']);
    Route::middleware('permission:accounting.approve_expenses')->post('/expenses/bulk-approve', [ExpenseApprovalController::class, 'bulkApprove']);

    // Settings (Ayarlar)
    Route::middleware('permission:settings.view')->get('/settings', [SettingController::class, 'index']);
    Route::middleware('permission:settings.view')->get('/settings/group/{group}', [SettingController::class, 'getByGroup']);
    Route::middleware('permission:settings.edit')->put('/settings', [SettingController::class, 'update']);
    Route::middleware('permission:settings.edit')->post('/settings/logo', [SettingController::class, 'uploadLogo']);
    Route::middleware('permission:settings.edit')->delete('/settings/logo', [SettingController::class, 'deleteLogo']);

    // Expense Categories (Gider Kategorileri)
    Route::get('/expense-categories/all', [ExpenseCategoryController::class, 'all']);
    Route::middleware('permission:settings.view')->get('/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::middleware('permission:settings.view')->get('/expense-categories/{category}', [ExpenseCategoryController::class, 'show']);
    Route::middleware('permission:settings.edit')->post('/expense-categories', [ExpenseCategoryController::class, 'store']);
    Route::middleware('permission:settings.edit')->put('/expense-categories/{category}', [ExpenseCategoryController::class, 'update']);
    Route::middleware('permission:settings.edit')->delete('/expense-categories/{category}', [ExpenseCategoryController::class, 'destroy']);
    Route::middleware('permission:settings.edit')->post('/expense-categories/order', [ExpenseCategoryController::class, 'updateOrder']);

    // Proposals (Teklif Formu)
    Route::middleware('permission:projects.view')->get('/projects/{project}/proposal', [ProposalController::class, 'generate']);
    Route::middleware('permission:projects.view')->get('/projects/{project}/proposal/preview', [ProposalController::class, 'preview']);
});
