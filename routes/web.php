<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MatchingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Forgot Password & Reset
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Authenticated Application Routes (Protected by auth)
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // 1. Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Upload Invoices & Files
    Route::get('/uploads', [UploadController::class, 'invoices'])->name('uploads.index');
    Route::get('/uploads/invoices', [UploadController::class, 'invoices'])->name('uploads.invoices');
    Route::post('/uploads/invoices', [UploadController::class, 'store'])->name('uploads.store');
    Route::post('/uploads/inspect-file', [UploadController::class, 'inspectFile'])->name('uploads.inspect');

    // Document Batches (Upload History)
    Route::get('/batches', [DocumentController::class, 'batches'])->name('batches.index');
    Route::get('/batches/{batch}', [DocumentController::class, 'showBatch'])->name('batches.show');
    Route::post('/batches/{batch}/retry', [DocumentController::class, 'retryBatch'])->name('batches.retry');
    Route::delete('/batches/{batch}', [DocumentController::class, 'destroyBatch'])->name('batches.destroy');

    // Secure Document Downloads, Previews & Management
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::get('/documents/{document}/raw-text', [DocumentController::class, 'rawText'])->name('documents.rawText');
    Route::post('/documents/{document}/retry', [DocumentController::class, 'retry'])->name('documents.retry');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // 3. Upload Shipping Labels
    Route::get('/uploads/shipping', [UploadController::class, 'shipping'])->name('uploads.shipping');
    Route::post('/uploads/shipping', [UploadController::class, 'storeShipping'])->name('uploads.shipping.store');

    // 4. Invoices Management (Policy protected & scoped)
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/matched', [InvoiceController::class, 'matched'])->name('invoices.matched');
    Route::get('/invoices/unmatched', [InvoiceController::class, 'unmatched'])->name('invoices.unmatched');
    Route::get('/invoices/review', [InvoiceController::class, 'review'])->name('invoices.review');
    Route::post('/invoices/{invoice}/quick-validate', [InvoiceController::class, 'quickValidate'])->name('invoices.quickValidate');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('/invoices/{invoice}/export', [InvoiceController::class, 'exportExcel'])->name('invoices.export');

    // 5. Processing Queue & Batch Actions
    Route::get('/processing', [UploadController::class, 'queueStatus'])->name('processing.index');
    Route::post('/processing/batch', [UploadController::class, 'batchProcess'])->name('processing.batch');
    Route::post('/processing/{document}/process', [UploadController::class, 'processSingle'])->name('processing.single');

    // Shipping Documents & Labels
    Route::get('/shipping', [ShippingController::class, 'index'])->name('shipping.index');
    Route::get('/shipping/create', [ShippingController::class, 'create'])->name('shipping.create');
    Route::post('/shipping', [ShippingController::class, 'store'])->name('shipping.store');
    Route::get('/shipping/{shipping}', [ShippingController::class, 'show'])->name('shipping.show');
    Route::delete('/shipping/{shipping}', [ShippingController::class, 'destroy'])->name('shipping.destroy');

    // Extracted Shipping Labels & Manual Correction
    Route::get('/shipping/labels/{label}', [ShippingController::class, 'showLabel'])->name('shipping.labels.show');
    Route::patch('/shipping/labels/{label}/correct-awb', [ShippingController::class, 'correctAwb'])->name('shipping.labels.correctAwb');
    Route::delete('/shipping/labels/{label}', [ShippingController::class, 'destroyLabel'])->name('shipping.labels.destroy');

    // 2-Way & 3-Way Matching Engine
    Route::get('/matching', [MatchingController::class, 'index'])->name('matching.index');
    Route::post('/matching/run', [MatchingController::class, 'runMatch'])->name('matching.run');
    Route::post('/matching/manual', [MatchingController::class, 'manualMatch'])->name('matching.manual');
    Route::patch('/matching/{match}/correct', [MatchingController::class, 'correctMatch'])->name('matching.correct');
    Route::delete('/matching/{match}/unmatch', [MatchingController::class, 'unmatch'])->name('matching.unmatch');
    Route::patch('/matching/{match}/approve', [MatchingController::class, 'approveReview'])->name('matching.approve');
    Route::patch('/matching/{record}', [MatchingController::class, 'updateStatus'])->name('matching.updateStatus');

    // Excel Exports (Scoped to user's records)
    Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('/exports/workbook', [ExportController::class, 'exportWorkbook'])->name('exports.workbook');
    Route::get('/exports/invoices', [ExportController::class, 'exportInvoices'])->name('exports.invoices');
    Route::get('/exports/items', [ExportController::class, 'exportItems'])->name('exports.items');
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    // Processing History (Scoped to user's activity)
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::post('/history/batches/{batch}/reprocess', [HistoryController::class, 'reprocessBatch'])->name('history.batches.reprocess');
    Route::delete('/history/batches/{batch}', [HistoryController::class, 'destroyBatch'])->name('history.batches.destroy');
    Route::post('/history/documents/{document}/reprocess', [HistoryController::class, 'reprocessDocument'])->name('history.documents.reprocess');

    // User Profile, Password & Sessions
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/logout-other-devices', [ProfileController::class, 'logoutOtherDevices'])->name('profile.logoutOtherDevices');

    // ADMIN ONLY ROUTES (Strictly protected by admin middleware)
    Route::middleware('admin')->group(function () {
        // User Management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Application & AI Configuration
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        // Executive Reports & Analytics
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });
});
