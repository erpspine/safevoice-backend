<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserAuthController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\CompanyAuthController;
use App\Http\Controllers\Api\Admin\BranchController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Public\ThreadController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Branch\BranchAuthController;
use App\Http\Controllers\Api\Branch\BranchCaseController;
use App\Http\Controllers\Api\Branch\BranchIncidentCategoryController;
use App\Http\Controllers\Api\Branch\BranchFeedbackCategoryController;
use App\Http\Controllers\Api\Branch\BranchDepartmentController;
use App\Http\Controllers\Api\Branch\BranchThreadController;
use App\Http\Controllers\Api\Admin\ThreadManagementController;
use App\Http\Controllers\Api\Admin\SubscriptionController;
use App\Http\Controllers\Api\Company\CompanyCaseController;
use App\Http\Controllers\Api\Branch\BranchProfileController;
use App\Http\Controllers\Api\Public\CaseMessagingController;
use App\Http\Controllers\Api\Company\CompanyBranchController;
use App\Http\Controllers\Api\Public\CaseSubmissionController;
use App\Http\Controllers\Api\Admin\FeedbackCategoryController;
use App\Http\Controllers\Api\Admin\IncidentCategoryController;
use App\Http\Controllers\Api\Admin\SubscriptionPlanController;
use App\Http\Controllers\Api\Branch\BranchDashboardController;
use App\Http\Controllers\Api\Company\CompanyProfileController;
use App\Http\Controllers\Api\Public\CaseTrackingAuthController;
use App\Http\Controllers\Api\Company\CompanyDepartmentController;
use App\Http\Controllers\Api\Public\SimpleCaseTrackingController;
use App\Http\Controllers\Api\Admin\InvestigatorMessagingController;
use App\Http\Controllers\Api\Admin\InvestigatorAssignmentController;
use App\Http\Controllers\Api\Company\CompanyFeedbackCategoryController;
use App\Http\Controllers\Api\Company\CompanyIncidentCategoryController;
use App\Http\Controllers\Api\Admin\InvestigatorCompanyAssignmentController;
use App\Http\Controllers\Api\Admin\CompanySettingsController;
use App\Http\Controllers\Api\CaseResolutionController;
use App\Http\Controllers\Api\DepartmentalCaseDistributionController;
use App\Http\Controllers\Api\CategoryCaseDistributionController;
use App\Http\Controllers\Api\InvestigatorAllocationController;

// Admin Controllers
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\IncidentReportController;
use App\Http\Controllers\Api\Admin\SectorIncidentTemplateController;
use App\Http\Controllers\Api\Admin\SectorFeedbackTemplateController;
use App\Http\Controllers\Api\Admin\SectorDepartmentTemplateController;
use App\Http\Controllers\Api\Admin\CaseTrackingController;
use App\Http\Controllers\Api\Admin\EscalationRuleController;

// Investigator Controllers
use App\Http\Controllers\Api\Investigator\InvestigatorAuthController;
use App\Http\Controllers\Api\Investigator\InvestigatorProfileController;
use App\Http\Controllers\Api\Investigator\InvestigatorDashboardController;
use App\Http\Controllers\Api\Investigator\InvestigatorCaseController;
use App\Http\Controllers\Api\Investigator\InvestigatorThreadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes

// Test route for user creation
Route::post('test/create-admin', function (Request $request) {
    $admin = App\Models\User::firstOrCreate(
        ['email' => 'admin@safevoice.tz'],
        [
            'name' => 'Super Admin',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
            'status' => 'active',
            'is_verified' => true
        ]
    );

    $token = $admin->createToken('auth_token')->plainTextToken;

    return response()->json([
        'admin' => $admin,
        'token' => $token
    ]);
});
// Invitation Routes (Public - no auth required)
Route::prefix('invitations')->group(function () {
    Route::post('verify-token', [InvitationController::class, 'verifyToken'])->name('invitations.verify-token');
    Route::post('accept', [InvitationController::class, 'acceptInvitation'])->name('invitations.accept');
    Route::get('{token}/details', [InvitationController::class, 'getInvitationDetails'])->name('invitations.details');
});

// Public API Routes (No authentication required)
Route::prefix('public')->group(function () {
    // Get companies for frontend dropdowns, registration forms, etc.
    Route::get('companies', [CompanyController::class, 'publicIndex'])->name('public.companies.index');

    // Get available sectors for companies
    Route::get('sectors', [CompanyController::class, 'sectors'])->name('public.sectors');

    // Get admin company settings (for invoices, etc.)
    Route::get('company-info', [CompanySettingsController::class, 'publicSettings'])->name('public.company-info');

    // Get active subscription plans for public use
    Route::get('subscription-plans', [SubscriptionPlanController::class, 'active'])->name('public.subscription-plans.active');

    // Get branches for a particular company
    Route::get('companies/{companyId}/branches', [BranchController::class, 'publicByCompany'])->name('public.companies.branches');

    // Get users for a particular company
    Route::get('companies/{companyId}/users', [UserController::class, 'publicByCompany'])->name('public.companies.users');

    // Get users for a particular branch
    Route::get('companies/{companyId}/branches/{branchId}/users', [UserController::class, 'publicByBranch'])->name('public.branches.users');

    // Get incident categories (all active)
    Route::get('incident-categories', [IncidentCategoryController::class, 'publicIndex'])->name('public.incident-categories.index');

    // Get incident categories for a particular company (hierarchical - parent with nested subcategories)
    Route::get('companies/{companyId}/incident-categories', [IncidentCategoryController::class, 'publicByCompany'])->name('public.companies.incident-categories');

    // Get parent (root) categories only for a company (for first dropdown)
    Route::get('companies/{companyId}/incident-categories/parents', [IncidentCategoryController::class, 'publicParentCategories'])->name('public.companies.incident-categories.parents');

    // Get subcategories for a specific parent category (for second dropdown)
    Route::get('companies/{companyId}/incident-categories/{parentId}/subcategories', [IncidentCategoryController::class, 'publicSubcategories'])->name('public.companies.incident-categories.subcategories');

    // Get feedback categories (all active)
    Route::get('feedback-categories', [FeedbackCategoryController::class, 'publicIndex'])->name('public.feedback-categories.index');

    // Get feedback categories for a particular company (hierarchical - parent with nested subcategories)
    Route::get('companies/{companyId}/feedback-categories', [FeedbackCategoryController::class, 'publicByCompany'])->name('public.companies.feedback-categories');

    // Get parent (root) feedback categories only for a company (for first dropdown)
    Route::get('companies/{companyId}/feedback-categories/parents', [FeedbackCategoryController::class, 'publicParentCategories'])->name('public.companies.feedback-categories.parents');

    // Get subcategories for a specific parent feedback category (for second dropdown)
    Route::get('companies/{companyId}/feedback-categories/{parentId}/subcategories', [FeedbackCategoryController::class, 'publicSubcategories'])->name('public.companies.feedback-categories.subcategories');

    // Debug route to test payload
    Route::post('cases/test-payload', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'message' => 'Payload received successfully',
            'data' => [
                'all_data' => $request->all(),
                'has_files' => $request->hasFile('files'),
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method(),
            ]
        ]);
    })->name('public.cases.test-payload');

    // Case Submission API
    Route::post('cases/submit', [CaseSubmissionController::class, 'submit'])->name('public.cases.submit');
    Route::post('cases/track', [CaseSubmissionController::class, 'track'])->name('public.cases.track');
    Route::post('cases/track-simple', [SimpleCaseTrackingController::class, 'track'])->name('public.cases.track-simple');

    // Case Tracking Authentication API - Login (no auth required)
    Route::post('cases/login', [CaseTrackingAuthController::class, 'login'])->name('public.cases.login');

    // Case Session Protected Routes (require valid session token)
    Route::middleware('case.session')->group(function () {
        // Case details and logout
        Route::get('cases/details', [CaseTrackingAuthController::class, 'getCaseDetails'])->name('public.cases.details');
        Route::post('cases/logout', [CaseTrackingAuthController::class, 'logout'])->name('public.cases.logout');

        // Case file download
        Route::get('cases/{caseId}/files/{fileId}/download', [CaseTrackingAuthController::class, 'downloadFile'])->name('public.cases.files.download');

        // Case Messaging API (for case reporters using session token)
        Route::prefix('cases/{caseId}')->group(function () {
            // Thread Management
            Route::get('threads', [ThreadController::class, 'index'])->name('public.cases.threads.index');
            Route::get('threads/{threadId}', [ThreadController::class, 'show'])->name('public.cases.threads.show');
            Route::post('threads', [ThreadController::class, 'store'])->name('public.cases.threads.store');

            // Thread Messaging
            Route::post('threads/{threadId}/messages', [ThreadController::class, 'addMessage'])->name('public.cases.threads.messages.store');
            Route::get('threads/{threadId}/messages', [ThreadController::class, 'getMessages'])->name('public.cases.threads.messages.index');
            Route::put('threads/{threadId}/messages/read', [ThreadController::class, 'markAsRead'])->name('public.cases.threads.messages.read');

            // Legacy Case Messaging (kept for backward compatibility)
            Route::get('messages', [CaseMessagingController::class, 'getMessages'])->name('public.cases.messages.index');
            Route::post('messages', [CaseMessagingController::class, 'sendMessage'])->name('public.cases.messages.store');
            Route::put('messages/read', [CaseMessagingController::class, 'markAsRead'])->name('public.cases.messages.read');
            Route::get('attachments', [CaseMessagingController::class, 'getCaseAttachments'])->name('public.cases.attachments.index');
            Route::get('messages/{messageId}/attachments/{filename}', [CaseMessagingController::class, 'downloadAttachment'])->name('public.cases.messages.download');
        });
    });
});

// Admin Authentication Routes
Route::prefix('admin/auth')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login');

    // Protected admin routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::get('me', [AdminAuthController::class, 'me'])->name('admin.me');
        Route::post('refresh', [AdminAuthController::class, 'refresh'])->name('admin.refresh');
        Route::post('force-logout-all-sessions', [AdminAuthController::class, 'forceLogoutAllSessions'])->name('admin.force-logout-all-sessions');
    });
});

// Company Authentication Routes (for branch managers)
Route::prefix('company/auth')->group(function () {
    Route::post('login', [CompanyAuthController::class, 'login'])->name('company.login');

    // Protected company routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [CompanyAuthController::class, 'logout'])->name('company.logout');
        Route::get('me', [CompanyAuthController::class, 'me'])->name('company.me');
        Route::post('refresh', [CompanyAuthController::class, 'refresh'])->name('company.refresh');
    });
});

// Admin Management Routes (Protected)
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    // Dashboard
    Route::get('dashboard', [AdminDashboardController::class, 'dashboard'])->name('admin.dashboard');

    // Reports
    Route::get('reports/incident', [IncidentReportController::class, 'index'])->name('admin.reports.incident');

    // Company Settings (Admin company info for invoices)
    Route::get('settings', [CompanySettingsController::class, 'show'])->name('admin.settings.show');
    Route::put('settings', [CompanySettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('settings/logo', [CompanySettingsController::class, 'uploadLogo'])->name('admin.settings.upload-logo');
    Route::delete('settings/logo', [CompanySettingsController::class, 'deleteLogo'])->name('admin.settings.delete-logo');

    // Company Management
    Route::apiResource('companies', CompanyController::class);
    Route::get('companies/statistics/dashboard', [CompanyController::class, 'statistics'])->name('admin.companies.statistics');

    // Branch Management
    Route::apiResource('branches', BranchController::class);
    Route::get('companies/{companyId}/branches', [BranchController::class, 'byCompany'])->name('admin.companies.branches');

    // Department Management
    Route::apiResource('departments', DepartmentController::class);
    Route::get('departments/statistics/dashboard', [DepartmentController::class, 'statistics'])->name('admin.departments.statistics');
    Route::get('companies/{companyId}/departments', [DepartmentController::class, 'byCompany'])->name('admin.companies.departments');

    // Incident Category Management
    Route::apiResource('incident-categories', IncidentCategoryController::class);
    Route::get('incident-categories/statistics/dashboard', [IncidentCategoryController::class, 'statistics'])->name('admin.incident-categories.statistics');
    Route::get('companies/{companyId}/incident-categories', [IncidentCategoryController::class, 'byCompany'])->name('admin.companies.incident-categories');

    // Sector Incident Template Management (Category Templates)
    Route::apiResource('category-templates', SectorIncidentTemplateController::class);
    Route::get('category-templates/statistics/dashboard', [SectorIncidentTemplateController::class, 'statistics'])->name('admin.category-templates.statistics');
    Route::get('category-templates/sector/{sector}', [SectorIncidentTemplateController::class, 'bySector'])->name('admin.category-templates.by-sector');
    Route::post('category-templates/sector/{sector}/sync', [SectorIncidentTemplateController::class, 'syncToCompanies'])->name('admin.category-templates.sync-to-companies');
    Route::post('category-templates/bulk', [SectorIncidentTemplateController::class, 'bulkStore'])->name('admin.category-templates.bulk-store');
    Route::delete('category-templates/sector/{sector}', [SectorIncidentTemplateController::class, 'destroyBySector'])->name('admin.category-templates.destroy-by-sector');

    // Feedback Category Management
    Route::apiResource('feedback-categories', FeedbackCategoryController::class);
    Route::get('feedback-categories/statistics/dashboard', [FeedbackCategoryController::class, 'statistics'])->name('admin.feedback-categories.statistics');
    Route::get('companies/{companyId}/feedback-categories', [FeedbackCategoryController::class, 'byCompany'])->name('admin.companies.feedback-categories');

    // Sector Feedback Template Management (Feedback Category Templates)
    Route::apiResource('feedback-category-templates', SectorFeedbackTemplateController::class);
    Route::get('feedback-category-templates/statistics/dashboard', [SectorFeedbackTemplateController::class, 'statistics'])->name('admin.feedback-category-templates.statistics');
    Route::get('feedback-category-templates/sector/{sector}', [SectorFeedbackTemplateController::class, 'bySector'])->name('admin.feedback-category-templates.by-sector');
    Route::post('feedback-category-templates/sector/{sector}/sync', [SectorFeedbackTemplateController::class, 'syncToCompanies'])->name('admin.feedback-category-templates.sync-to-companies');
    Route::post('feedback-category-templates/bulk', [SectorFeedbackTemplateController::class, 'bulkStore'])->name('admin.feedback-category-templates.bulk-store');
    Route::delete('feedback-category-templates/sector/{sector}', [SectorFeedbackTemplateController::class, 'destroyBySector'])->name('admin.feedback-category-templates.destroy-by-sector');

    // Sector Department Template Management (Department Templates)
    Route::apiResource('department-templates', SectorDepartmentTemplateController::class);
    Route::get('department-templates/statistics/dashboard', [SectorDepartmentTemplateController::class, 'statistics'])->name('admin.department-templates.statistics');
    Route::get('department-templates/sector/{sector}', [SectorDepartmentTemplateController::class, 'bySector'])->name('admin.department-templates.by-sector');
    Route::post('department-templates/sector/{sector}/sync', [SectorDepartmentTemplateController::class, 'syncToCompanies'])->name('admin.department-templates.sync-to-companies');
    Route::post('department-templates/bulk', [SectorDepartmentTemplateController::class, 'bulkStore'])->name('admin.department-templates.bulk-store');
    Route::delete('department-templates/sector/{sector}', [SectorDepartmentTemplateController::class, 'destroyBySector'])->name('admin.department-templates.destroy-by-sector');

    // User Management
    Route::apiResource('users', UserController::class);
    Route::get('users/statistics/dashboard', [UserController::class, 'statistics'])->name('admin.users.statistics');
    Route::get('users/investigators/list', [UserController::class, 'investigators'])->name('admin.users.investigators');
    Route::get('users/{userId}/available-companies', [UserController::class, 'availableCompanies'])->name('admin.users.available-companies');
    Route::post('users/{id}/resend-invitation', [UserController::class, 'resendInvitation'])->name('admin.users.resend-invitation');
    Route::post('users/{id}/deactivate', [UserController::class, 'deactivate'])->name('admin.users.deactivate');
    Route::post('users/{id}/activate', [UserController::class, 'activate'])->name('admin.users.activate');
    Route::get('users/by-company-branch', [UserController::class, 'getByCompanyBranch'])->name('admin.users.by-company-branch');
    Route::get('users/fast-by-company-branch', [UserController::class, 'fastByCompanyBranch'])->name('admin.users.fast-by-company-branch');

    // Subscription Plan Management
    Route::apiResource('subscription-plans', SubscriptionPlanController::class);
    Route::get('subscription-plans/{id}/toggle-status', [SubscriptionPlanController::class, 'toggleStatus'])->name('admin.subscription-plans.toggle-status');
    Route::get('subscription-plans/{id}/pricing', [SubscriptionPlanController::class, 'calculatePricing'])->name('admin.subscription-plans.calculate-pricing');

    // Subscription Management
    Route::get('subscriptions/stats', [SubscriptionController::class, 'stats'])->name('admin.subscriptions.stats');
    Route::get('subscriptions/available-branches', [SubscriptionController::class, 'getAvailableBranches'])->name('admin.subscriptions.available-branches');
    Route::get('subscriptions/{subscription}/edit', [SubscriptionController::class, 'edit'])->name('admin.subscriptions.edit');
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::put('subscriptions/{subscription}/branches', [SubscriptionController::class, 'updateBranches'])->name('admin.subscriptions.update-branches');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('admin.subscriptions.cancel');
    Route::post('subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend'])->name('admin.subscriptions.extend');
    Route::get('subscriptions/{subscription}/invoices', [SubscriptionController::class, 'getSubscriptionInvoices'])->name('admin.subscriptions.invoices');

    // Invoice Routes
    Route::get('invoices/{payment}/download', [SubscriptionController::class, 'downloadInvoice'])->name('invoices.download');
    Route::get('invoices/{payment}/view', [SubscriptionController::class, 'viewInvoice'])->name('invoices.view');
    Route::get('invoices/{payment}/data', [SubscriptionController::class, 'getInvoiceData'])->name('invoices.data');

    // Investigator Assignment Management (Company to Investigator)
    Route::get('investigator-assignments', [InvestigatorAssignmentController::class, 'index'])->name('admin.investigator-assignments.index');
    Route::get('investigator-assignments/stats', [InvestigatorAssignmentController::class, 'stats'])->name('admin.investigator-assignments.stats');
    Route::get('investigator-assignments/companies/{company}/investigators', [InvestigatorAssignmentController::class, 'companyInvestigators'])->name('admin.investigator-assignments.company-investigators');
    Route::get('investigator-assignments/companies/{company}/available', [InvestigatorAssignmentController::class, 'availableInvestigators'])->name('admin.investigator-assignments.available');
    Route::get('investigator-assignments/investigators/{investigator}/companies', [InvestigatorAssignmentController::class, 'investigatorCompanies'])->name('admin.investigator-assignments.investigator-companies');
    Route::post('investigator-assignments/companies/{company}/assign', [InvestigatorAssignmentController::class, 'assign'])->name('admin.investigator-assignments.assign');
    Route::post('investigator-assignments/companies/{company}/unassign', [InvestigatorAssignmentController::class, 'unassign'])->name('admin.investigator-assignments.unassign');

    // Investigator Company Assignment Management (Investigator to Company)
    Route::get('investigator-company-assignments', [InvestigatorCompanyAssignmentController::class, 'index'])->name('admin.investigator-company-assignments.index');
    Route::get('investigator-company-assignments/investigators', [InvestigatorCompanyAssignmentController::class, 'investigators'])->name('admin.investigator-company-assignments.investigators');
    Route::get('investigator-company-assignments/stats', [InvestigatorCompanyAssignmentController::class, 'stats'])->name('admin.investigator-company-assignments.stats');
    Route::get('investigator-company-assignments/investigators/{investigator}/companies', [InvestigatorCompanyAssignmentController::class, 'investigatorCompanies'])->name('admin.investigator-company-assignments.investigator-companies');
    Route::get('investigator-company-assignments/investigators/{investigator}/available', [InvestigatorCompanyAssignmentController::class, 'availableCompanies'])->name('admin.investigator-company-assignments.available');
    Route::get('investigator-company-assignments/companies/{company}/investigators', [InvestigatorCompanyAssignmentController::class, 'companyInvestigators'])->name('admin.investigator-company-assignments.company-investigators');
    Route::post('investigator-company-assignments/investigators/{investigator}/assign', [InvestigatorCompanyAssignmentController::class, 'assign'])->name('admin.investigator-company-assignments.assign');
    Route::post('investigator-company-assignments/investigators/{investigator}/unassign', [InvestigatorCompanyAssignmentController::class, 'unassign'])->name('admin.investigator-company-assignments.unassign');

    // Case Messaging (Admin/Investigator side)
    Route::prefix('cases/{caseId}')->group(function () {
        Route::get('messages', [InvestigatorMessagingController::class, 'getMessages'])->name('admin.cases.messages.index');
        Route::post('messages', [InvestigatorMessagingController::class, 'sendMessage'])->name('admin.cases.messages.store');
        Route::put('messages/read', [InvestigatorMessagingController::class, 'markAsRead'])->name('admin.cases.messages.read');
        Route::get('messages/stats', [InvestigatorMessagingController::class, 'getMessageStats'])->name('admin.cases.messages.stats');

        // Thread Management
        Route::get('threads', [ThreadManagementController::class, 'index'])->name('admin.threads.index');
        Route::post('threads', [ThreadManagementController::class, 'store'])->name('admin.threads.store');
        Route::get('threads/unread-counts', [ThreadManagementController::class, 'getUnreadCounts'])->name('admin.threads.unread-counts');
        Route::get('threads/{threadId}', [ThreadManagementController::class, 'show'])->name('admin.threads.show');
        Route::post('threads/{threadId}/messages', [ThreadManagementController::class, 'addMessage'])->name('admin.threads.add-message');
        Route::post('threads/{threadId}/participants', [ThreadManagementController::class, 'addParticipants'])->name('admin.threads.add-participants');
        Route::post('threads/{threadId}/mark-read', [ThreadManagementController::class, 'markAsRead'])->name('admin.threads.mark-read');

        // Case Tracking (Timeline & Durations)
        Route::get('timeline', [CaseTrackingController::class, 'getTimeline'])->name('admin.cases.timeline');
        Route::get('duration-summary', [CaseTrackingController::class, 'getDurationSummary'])->name('admin.cases.duration-summary');
        Route::get('escalations', [CaseTrackingController::class, 'getCaseEscalations'])->name('admin.cases.escalations');
    });

    // Case Tracking Dashboard & Analytics
    Route::prefix('case-tracking')->group(function () {
        Route::get('dashboard', [CaseTrackingController::class, 'getDashboardStats'])->name('admin.case-tracking.dashboard');
        Route::get('overdue', [CaseTrackingController::class, 'getOverdueCases'])->name('admin.case-tracking.overdue');
    });

    // Escalation Management
    Route::prefix('escalations')->group(function () {
        Route::post('{escalationId}/resolve', [CaseTrackingController::class, 'resolveEscalation'])->name('admin.escalations.resolve');
    });

    // Escalation Rules Management
    Route::prefix('escalation-rules')->group(function () {
        Route::get('stages', [EscalationRuleController::class, 'getStages'])->name('admin.escalation-rules.stages');
        Route::get('levels', [EscalationRuleController::class, 'getEscalationLevels'])->name('admin.escalation-rules.levels');
        Route::get('business-hours', [EscalationRuleController::class, 'getDefaultBusinessHours'])->name('admin.escalation-rules.business-hours');
        Route::post('{id}/toggle', [EscalationRuleController::class, 'toggleActive'])->name('admin.escalation-rules.toggle');
    });
    Route::apiResource('escalation-rules', EscalationRuleController::class)->names([
        'index' => 'admin.escalation-rules.index',
        'store' => 'admin.escalation-rules.store',
        'show' => 'admin.escalation-rules.show',
        'update' => 'admin.escalation-rules.update',
        'destroy' => 'admin.escalation-rules.destroy',
    ]);
});

// Company Management Routes (Protected - for company admins)
Route::middleware(['auth:sanctum'])->prefix('company')->group(function () {

    // Branch Management (Company can only manage their own branches)
    Route::apiResource('branches', CompanyBranchController::class)->names([
        'index' => 'company.branches.index',
        'store' => 'company.branches.store',
        'show' => 'company.branches.show',
        'update' => 'company.branches.update',
        'destroy' => 'company.branches.destroy'
    ]);
    Route::get('branches/statistics/dashboard', [CompanyBranchController::class, 'statistics'])->name('company.branches.statistics');
    Route::get('branches/managers/available', [CompanyBranchController::class, 'availableManagers'])->name('company.branches.available-managers');

    // Department Management (Company can only manage their own departments)
    Route::apiResource('departments', CompanyDepartmentController::class)->names([
        'index' => 'company.departments.index',
        'store' => 'company.departments.store',
        'show' => 'company.departments.show',
        'update' => 'company.departments.update',
        'destroy' => 'company.departments.destroy'
    ]);
    Route::get('departments/statistics/dashboard', [CompanyDepartmentController::class, 'statistics'])->name('company.departments.statistics');
    Route::get('departments/heads/available', [CompanyDepartmentController::class, 'availableHeads'])->name('company.departments.available-heads');

    // Incident Category Management (Company can only manage their own incident categories)
    Route::apiResource('incident-categories', CompanyIncidentCategoryController::class)->names([
        'index' => 'company.incident-categories.index',
        'store' => 'company.incident-categories.store',
        'show' => 'company.incident-categories.show',
        'update' => 'company.incident-categories.update',
        'destroy' => 'company.incident-categories.destroy'
    ]);
    Route::get('incident-categories/statistics/dashboard', [CompanyIncidentCategoryController::class, 'statistics'])->name('company.incident-categories.statistics');
    Route::get('incident-categories/departments/available', [CompanyIncidentCategoryController::class, 'availableDepartments'])->name('company.incident-categories.available-departments');

    // Feedback Category Management (Company can only manage their own feedback categories)
    Route::apiResource('feedback-categories', CompanyFeedbackCategoryController::class)->names([
        'index' => 'company.feedback-categories.index',
        'store' => 'company.feedback-categories.store',
        'show' => 'company.feedback-categories.show',
        'update' => 'company.feedback-categories.update',
        'destroy' => 'company.feedback-categories.destroy'
    ]);
    Route::get('feedback-categories/statistics/dashboard', [CompanyFeedbackCategoryController::class, 'statistics'])->name('company.feedback-categories.statistics');

    // Case Management (Company can view and manage their own cases)
    Route::get('cases', [CompanyCaseController::class, 'index'])->name('company.cases.index');
    Route::get('cases/dashboard', [CompanyCaseController::class, 'dashboard'])->name('company.cases.dashboard');
    Route::get('cases/statistics/dashboard', [CompanyCaseController::class, 'statistics'])->name('company.cases.statistics');
    Route::get('cases/investigators/available', [CompanyCaseController::class, 'availableInvestigators'])->name('company.cases.available-investigators');
    Route::get('cases/{id}', [CompanyCaseController::class, 'show'])->name('company.cases.show');
    Route::put('cases/{id}', [CompanyCaseController::class, 'update'])->name('company.cases.update');
    Route::get('cases/{id}/timeline', [CompanyCaseController::class, 'timeline'])->name('company.cases.timeline');
    Route::get('cases/{id}/duration-summary', [CompanyCaseController::class, 'getDurationSummary'])->name('company.cases.duration-summary');
    Route::get('cases/{id}/escalations', [CompanyCaseController::class, 'getCaseEscalations'])->name('company.cases.escalations');
    Route::get('cases/{id}/tracking', [CompanyCaseController::class, 'getFullTracking'])->name('company.cases.tracking');

    // Case Department Assignment
    Route::post('cases/{id}/departments', [CompanyCaseController::class, 'assignDepartments'])->name('company.cases.assign-departments');
    Route::get('cases/{id}/departments', [CompanyCaseController::class, 'getCaseDepartments'])->name('company.cases.departments');
    Route::delete('cases/{id}/departments/{departmentId}', [CompanyCaseController::class, 'unassignDepartment'])->name('company.cases.unassign-department');

    // Case Category Assignment
    Route::post('cases/{id}/categories', [CompanyCaseController::class, 'assignCategories'])->name('company.cases.assign-categories');
    Route::get('cases/{id}/categories', [CompanyCaseController::class, 'getCaseCategories'])->name('company.cases.categories');
    Route::delete('cases/{id}/categories/{categoryId}', [CompanyCaseController::class, 'unassignCategory'])->name('company.cases.unassign-category');

    // Case Investigator Assignment
    Route::post('cases/{id}/investigators', [CompanyCaseController::class, 'assignInvestigators'])->name('company.cases.assign-investigators');
    Route::get('cases/{id}/investigators', [CompanyCaseController::class, 'getCaseInvestigators'])->name('company.cases.investigators');
    Route::delete('cases/{id}/investigators/{assignmentId}', [CompanyCaseController::class, 'unassignInvestigator'])->name('company.cases.unassign-investigator');

    // Case Files
    Route::get('cases/{id}/files', [CompanyCaseController::class, 'getCaseFiles'])->name('company.cases.files');

    // Company Profile Management
    Route::get('profile', [CompanyProfileController::class, 'show'])->name('company.profile.show');
    Route::put('profile', [CompanyProfileController::class, 'update'])->name('company.profile.update');
    Route::post('profile/change-password', [CompanyProfileController::class, 'changePassword'])->name('company.profile.change-password');
    Route::post('profile/upload-logo', [CompanyProfileController::class, 'uploadProfilePicture'])->name('company.profile.upload-logo');
    Route::delete('profile/delete-logo', [CompanyProfileController::class, 'deleteProfilePicture'])->name('company.profile.delete-logo');
});

// Branch Admin Authentication Routes
Route::prefix('branch/auth')->group(function () {
    Route::post('login', [BranchAuthController::class, 'login'])->name('branch.login');

    // Protected branch admin routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [BranchAuthController::class, 'logout'])->name('branch.logout');
        Route::get('me', [BranchAuthController::class, 'me'])->name('branch.me');
        Route::post('refresh', [BranchAuthController::class, 'refresh'])->name('branch.refresh');
    });
});

// Branch Admin Profile Management
Route::middleware(['auth:sanctum'])->prefix('branch')->group(function () {
    Route::get('dashboard', [BranchDashboardController::class, 'dashboard'])->name('branch.dashboard');
    Route::get('profile', [BranchProfileController::class, 'show'])->name('branch.profile.show');
    Route::put('profile', [BranchProfileController::class, 'update'])->name('branch.profile.update');
    Route::post('profile/change-password', [BranchProfileController::class, 'changePassword'])->name('branch.profile.change-password');
    Route::post('profile/upload-picture', [BranchProfileController::class, 'uploadProfilePicture'])->name('branch.profile.upload-picture');
    Route::delete('profile/delete-picture', [BranchProfileController::class, 'deleteProfilePicture'])->name('branch.profile.delete-picture');

    // Branch Case Management
    Route::get('cases', [BranchCaseController::class, 'index'])->name('branch.cases.index');
    Route::get('cases/with-threads', [BranchCaseController::class, 'getCasesWithThreads'])->name('branch.cases.with-threads');
    Route::get('cases/dashboard', [BranchCaseController::class, 'dashboard'])->name('branch.cases.dashboard');
    Route::get('cases/statistics', [BranchCaseController::class, 'statistics'])->name('branch.cases.statistics');
    Route::get('cases/investigators/available', [BranchCaseController::class, 'availableInvestigators'])->name('branch.cases.available-investigators');
    Route::get('cases/{id}', [BranchCaseController::class, 'show'])->name('branch.cases.show');
    Route::get('cases/{id}/thread-activity', [BranchCaseController::class, 'getCaseThreadActivity'])->name('branch.cases.thread-activity');
    Route::put('cases/{id}', [BranchCaseController::class, 'update'])->name('branch.cases.update');
    Route::get('cases/{id}/timeline', [BranchCaseController::class, 'timeline'])->name('branch.cases.timeline');
    Route::get('cases/{id}/duration-summary', [BranchCaseController::class, 'getDurationSummary'])->name('branch.cases.duration-summary');
    Route::get('cases/{id}/escalations', [BranchCaseController::class, 'getCaseEscalations'])->name('branch.cases.escalations');
    Route::get('cases/{id}/tracking', [BranchCaseController::class, 'getFullTracking'])->name('branch.cases.tracking');

    // Case Department Assignment (Branch)
    Route::post('cases/{id}/departments', [BranchCaseController::class, 'assignDepartments'])->name('branch.cases.assign-departments');
    Route::get('cases/{id}/departments', [BranchCaseController::class, 'getCaseDepartments'])->name('branch.cases.departments');
    Route::delete('cases/{id}/departments/{departmentId}', [BranchCaseController::class, 'unassignDepartment'])->name('branch.cases.unassign-department');

    // Case Category Assignment (Branch)
    Route::post('cases/{id}/categories', [BranchCaseController::class, 'assignCategories'])->name('branch.cases.assign-categories');
    Route::get('cases/{id}/categories', [BranchCaseController::class, 'getCaseCategories'])->name('branch.cases.categories');
    Route::delete('cases/{id}/categories/{categoryId}', [BranchCaseController::class, 'unassignCategory'])->name('branch.cases.unassign-category');

    // Case Investigator Assignment (Branch)
    Route::post('cases/{id}/investigators', [BranchCaseController::class, 'assignInvestigators'])->name('branch.cases.assign-investigators');
    Route::get('cases/{id}/investigators', [BranchCaseController::class, 'getCaseInvestigators'])->name('branch.cases.investigators');
    Route::delete('cases/{id}/investigators/{assignmentId}', [BranchCaseController::class, 'unassignInvestigator'])->name('branch.cases.unassign-investigator');

    // Case Files (Branch)
    Route::get('cases/{id}/files', [BranchCaseController::class, 'getCaseFiles'])->name('branch.cases.files');

    // Branch Incident Category Management (Branch admins can manage company incident categories)
    Route::apiResource('incident-categories', BranchIncidentCategoryController::class)->names([
        'index' => 'branch.incident-categories.index',
        'store' => 'branch.incident-categories.store',
        'show' => 'branch.incident-categories.show',
        'update' => 'branch.incident-categories.update',
        'destroy' => 'branch.incident-categories.destroy'
    ]);
    Route::get('incident-categories/statistics/dashboard', [BranchIncidentCategoryController::class, 'statistics'])->name('branch.incident-categories.statistics');

    // Branch Feedback Category Management (Branch admins can manage company feedback categories)
    Route::apiResource('feedback-categories', BranchFeedbackCategoryController::class)->names([
        'index' => 'branch.feedback-categories.index',
        'store' => 'branch.feedback-categories.store',
        'show' => 'branch.feedback-categories.show',
        'update' => 'branch.feedback-categories.update',
        'destroy' => 'branch.feedback-categories.destroy'
    ]);
    Route::get('feedback-categories/statistics/dashboard', [BranchFeedbackCategoryController::class, 'statistics'])->name('branch.feedback-categories.statistics');

    // Branch Department Management (Branch admins can manage company departments)
    Route::apiResource('departments', BranchDepartmentController::class)->names([
        'index' => 'branch.departments.index',
        'store' => 'branch.departments.store',
        'show' => 'branch.departments.show',
        'update' => 'branch.departments.update',
        'destroy' => 'branch.departments.destroy'
    ]);
    Route::get('departments/statistics/dashboard', [BranchDepartmentController::class, 'statistics'])->name('branch.departments.statistics');

    // Thread Management
    Route::prefix('cases/{caseId}/threads')->group(function () {
        Route::get('/', [BranchThreadController::class, 'index'])->name('branch.threads.index');
        Route::post('/', [BranchThreadController::class, 'store'])->name('branch.threads.store');
        Route::get('/unread-counts', [BranchThreadController::class, 'getUnreadCounts'])->name('branch.threads.unread-counts');
        Route::get('/{threadId}', [BranchThreadController::class, 'show'])->name('branch.threads.show');
        Route::get('/{threadId}/messages', [BranchThreadController::class, 'getMessages'])->name('branch.threads.messages.index');
        Route::post('/{threadId}/messages', [BranchThreadController::class, 'addMessage'])->name('branch.threads.add-message');
        Route::post('/{threadId}/mark-read', [BranchThreadController::class, 'markAsRead'])->name('branch.threads.mark-read');
        Route::get('/{threadId}/messages/{messageId}/attachments/{filename}', [BranchThreadController::class, 'downloadAttachment'])->name('branch.threads.messages.download');
    });
});

// Company Branch User Authentication Routes
Route::prefix('user/auth')->group(function () {
    Route::post('login', [UserAuthController::class, 'login'])->name('user.login');

    // Protected user routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [UserAuthController::class, 'logout'])->name('user.logout');
        Route::get('me', [UserAuthController::class, 'me'])->name('user.me');
        Route::post('refresh', [UserAuthController::class, 'refresh'])->name('user.refresh');
        Route::post('change-password', [UserAuthController::class, 'changePassword'])->name('user.change-password');
        Route::put('profile', [UserAuthController::class, 'updateProfile'])->name('user.update-profile');
    });
});

// SMS Testing API Routes (for development and testing)
Route::prefix('sms/test')->group(function () {
    Route::get('status', [App\Http\Controllers\Api\SmsTestController::class, 'status'])->name('sms.test.status');
    Route::get('docs', [App\Http\Controllers\Api\SmsTestController::class, 'documentation'])->name('sms.test.docs');
    Route::post('send-single', [App\Http\Controllers\Api\SmsTestController::class, 'sendSingle'])->name('sms.test.send-single');
    Route::post('send-multiple', [App\Http\Controllers\Api\SmsTestController::class, 'sendMultiple'])->name('sms.test.send-multiple');
    Route::post('send-invitation', [App\Http\Controllers\Api\SmsTestController::class, 'sendInvitation'])->name('sms.test.send-invitation');
    Route::post('send-verification', [App\Http\Controllers\Api\SmsTestController::class, 'sendVerification'])->name('sms.test.send-verification');
    Route::post('send-password-reset', [App\Http\Controllers\Api\SmsTestController::class, 'sendPasswordReset'])->name('sms.test.send-password-reset');
    Route::post('validate-phone', [App\Http\Controllers\Api\SmsTestController::class, 'validatePhoneNumber'])->name('sms.test.validate-phone');
});

// Health check route
Route::get('health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
})->name('api.health');

// Test authentication route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// WhatsApp API routes
Route::prefix('whatsapp')->group(function () {
    Route::post('test-message', [App\Http\Controllers\Api\WhatsAppController::class, 'testMessage']);
    Route::post('case-notification', [App\Http\Controllers\Api\WhatsAppController::class, 'sendCaseNotification']);
    Route::post('template-message', [App\Http\Controllers\Api\WhatsAppController::class, 'sendTemplateMessage']);
    Route::post('address-update', [App\Http\Controllers\Api\WhatsAppController::class, 'sendAddressUpdate']);

    // Debug routes
    Route::get('debug-config', [App\Http\Controllers\Api\WhatsAppDebugController::class, 'checkConfig']);
    Route::get('check-phone', [App\Http\Controllers\Api\WhatsAppDebugController::class, 'checkPhoneNumber']);
    Route::get('delivery-status', [App\Http\Controllers\Api\WhatsAppDebugController::class, 'checkDeliveryStatus']);
    Route::get('troubleshoot', [App\Http\Controllers\Api\WhatsAppDebugController::class, 'deliveryTroubleshooting']);
});

// Test page route
Route::get('whatsapp-test', function () {
    return view('whatsapp-test');
});

// Investigator Authentication Routes
Route::prefix('investigator/auth')->group(function () {
    Route::post('login', [InvestigatorAuthController::class, 'login'])->name('investigator.login');

    // Protected investigator routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [InvestigatorAuthController::class, 'logout'])->name('investigator.logout');
        Route::get('me', [InvestigatorAuthController::class, 'me'])->name('investigator.me');
        Route::post('refresh', [InvestigatorAuthController::class, 'refresh'])->name('investigator.refresh');
        Route::post('change-password', [InvestigatorAuthController::class, 'changePassword'])->name('investigator.change-password');
    });
});

// Investigator Management Routes (Protected)
Route::middleware(['auth:sanctum'])->prefix('investigator')->group(function () {

    // Profile Management
    Route::get('profile', [InvestigatorProfileController::class, 'show'])->name('investigator.profile.show');
    Route::put('profile', [InvestigatorProfileController::class, 'update'])->name('investigator.profile.update');
    Route::post('profile/upload-picture', [InvestigatorProfileController::class, 'uploadProfilePicture'])->name('investigator.profile.upload-picture');
    Route::delete('profile/delete-picture', [InvestigatorProfileController::class, 'deleteProfilePicture'])->name('investigator.profile.delete-picture');
    Route::post('profile/change-password', [InvestigatorProfileController::class, 'changePassword'])->name('investigator.profile.change-password');

    // Dashboard
    Route::get('dashboard', [InvestigatorDashboardController::class, 'dashboard'])->name('investigator.dashboard');
    Route::get('quick-stats', [InvestigatorDashboardController::class, 'quickStats'])->name('investigator.quick-stats');

    // Case Management
    Route::get('cases', [InvestigatorCaseController::class, 'index'])->name('investigator.cases.index');
    Route::get('cases/with-threads', [InvestigatorCaseController::class, 'getCasesWithThreads'])->name('investigator.cases.with-threads');
    Route::get('cases/{caseId}', [InvestigatorCaseController::class, 'show'])->name('investigator.cases.show');
    Route::put('cases/{caseId}', [InvestigatorCaseController::class, 'updateCase'])->name('investigator.cases.update');
    Route::get('cases/{caseId}/files/{fileId}/download', [InvestigatorCaseController::class, 'downloadFile'])->name('investigator.cases.files.download');

    // Thread Management
    Route::prefix('cases/{caseId}/threads')->group(function () {
        Route::get('/', [InvestigatorThreadController::class, 'index'])->name('investigator.threads.index');
        Route::get('/{threadId}', [InvestigatorThreadController::class, 'show'])->name('investigator.threads.show');
        Route::get('/{threadId}/messages', [InvestigatorThreadController::class, 'getMessages'])->name('investigator.threads.messages.index');
        Route::post('/{threadId}/messages', [InvestigatorThreadController::class, 'addMessage'])->name('investigator.threads.add-message');
        Route::post('/{threadId}/mark-read', [InvestigatorThreadController::class, 'markAsRead'])->name('investigator.threads.mark-read');
        Route::get('/{threadId}/messages/{messageId}/attachments/{filename}', [InvestigatorThreadController::class, 'downloadAttachment'])->name('investigator.threads.messages.download');
    });
});

// Case Resolution Time Analytics - Available for all authenticated users
Route::middleware(['auth:sanctum'])->prefix('case-resolution')->group(function () {
    Route::get('/analytics', [CaseResolutionController::class, 'getResolutionAnalytics'])->name('case.resolution.analytics');
    Route::get('/trends', [CaseResolutionController::class, 'getResolutionTrends'])->name('case.resolution.trends');
    Route::get('/export', [CaseResolutionController::class, 'exportResolutionData'])->name('case.resolution.export');
});

// Departmental Case Distribution Analytics - Available for all authenticated users
Route::middleware(['auth:sanctum'])->prefix('case-distribution')->group(function () {
    Route::get('/analytics', [DepartmentalCaseDistributionController::class, 'getDistributionAnalytics'])->name('case.distribution.analytics');
    Route::get('/trends', [DepartmentalCaseDistributionController::class, 'getDistributionTrends'])->name('case.distribution.trends');
    Route::get('/export', [DepartmentalCaseDistributionController::class, 'exportDistributionData'])->name('case.distribution.export');
    Route::get('/filters', [DepartmentalCaseDistributionController::class, 'getFilters'])->name('case.distribution.filters');
});

// Category Case Distribution Analytics - Available for all authenticated users
Route::middleware(['auth:sanctum'])->prefix('category-distribution')->group(function () {
    Route::get('/analytics', [CategoryCaseDistributionController::class, 'getCategoryAnalytics'])->name('category.distribution.analytics');
    Route::get('/trends', [CategoryCaseDistributionController::class, 'getCategoryTrends'])->name('category.distribution.trends');
    Route::get('/export', [CategoryCaseDistributionController::class, 'exportCategoryData'])->name('category.distribution.export');
    Route::get('/filters', [CategoryCaseDistributionController::class, 'getFilters'])->name('category.distribution.filters');
});

// Investigator Allocation Analytics - Available for all authenticated users
Route::middleware(['auth:sanctum'])->prefix('investigator-allocation')->group(function () {
    Route::get('/analytics', [InvestigatorAllocationController::class, 'getAllocationAnalytics'])->name('investigator.allocation.analytics');
    Route::get('/trends', [InvestigatorAllocationController::class, 'getAllocationTrends'])->name('investigator.allocation.trends');
    Route::get('/export', [InvestigatorAllocationController::class, 'exportAllocationData'])->name('investigator.allocation.export');
    Route::get('/filters', [InvestigatorAllocationController::class, 'getFilters'])->name('investigator.allocation.filters');
});
