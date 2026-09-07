<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\WeddingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // System Health Check
    Route::get('/health', HealthCheckController::class);

    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
        Route::post('/forgot-password/request-otp', [\App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'requestOtp'])->middleware('throttle:5,1');
        Route::post('/forgot-password/reset', [\App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

    // Public / Unauthenticated Endpoints (Rate Limited)
    Route::prefix('public')->group(function () {
        Route::get('/settings', [\App\Http\Controllers\Api\PublicSettingController::class, 'index']);
        Route::get('/templates', [\App\Http\Controllers\Api\TemplateController::class, 'index']);
        Route::get('/invitations/{slug}', [\App\Http\Controllers\Api\PublicInvitationController::class, 'show']);
        Route::get('/templates/{slugOrId}/preview', [\App\Http\Controllers\Api\TemplateController::class, 'preview']);
        Route::post('/invitations/{slug}/rsvp', [\App\Http\Controllers\Api\RsvpController::class, 'submitPublic'])->middleware('throttle:15,1');
        Route::post('/{slug}/rsvp', [\App\Http\Controllers\Api\RsvpController::class, 'submitPublic'])->middleware('throttle:15,1');
        Route::post('/invitations/{slug}/wishes', [\App\Http\Controllers\Api\RsvpController::class, 'submitWishPublic'])->middleware('throttle:20,1');
        Route::post('/{slug}/wishes', [\App\Http\Controllers\Api\RsvpController::class, 'submitWishPublic'])->middleware('throttle:20,1');
        Route::post('/wishes/approve', [\App\Http\Controllers\Api\RsvpController::class, 'approvePublicWish'])->middleware('throttle:20,1');
    });

    // Webhook Callback for Duitku Payment Gateway (Public, Unauthenticated, Excluded from CSRF)
    Route::post('/payment/duitku/callback', [\App\Http\Controllers\Api\PaymentController::class, 'callback'])->name('api.payment.duitku.callback');

    // Authenticated Project Management Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // User Profile & Account Settings (Change Password via Email OTP)
        Route::get('/user/profile', [\App\Http\Controllers\Api\UserProfileController::class, 'show']);
        Route::put('/user/profile', [\App\Http\Controllers\Api\UserProfileController::class, 'update']);
        Route::post('/user/password/request-otp', [\App\Http\Controllers\Api\UserProfileController::class, 'requestPasswordOtp'])->middleware('throttle:3,1');
        Route::post('/user/password/confirm', [\App\Http\Controllers\Api\UserProfileController::class, 'confirmPasswordChange'])->middleware('throttle:5,1');

        // Wedding Trash & Restoration
        Route::get('/weddings/trash', [WeddingController::class, 'trash']);
        Route::post('/weddings/{id}/restore', [WeddingController::class, 'restore']);
        Route::delete('/weddings/{id}/force-delete', [WeddingController::class, 'forceDelete']);

        Route::apiResource('weddings', WeddingController::class);

        // Design Studio API
        Route::get('/weddings/{wedding}/design', [\App\Http\Controllers\Api\StudioDesignController::class, 'show']);
        Route::put('/weddings/{wedding}/design', [\App\Http\Controllers\Api\StudioDesignController::class, 'update']);
        Route::post('/weddings/{wedding}/apply-template/{template}', [\App\Http\Controllers\Api\TemplateController::class, 'applyToWedding']);
        Route::get('/weddings/{wedding}/content', [\App\Http\Controllers\Api\WeddingContentController::class, 'show']);
        Route::put('/weddings/{wedding}/content', [\App\Http\Controllers\Api\WeddingContentController::class, 'update']);
        Route::match(['get', 'post'], '/weddings/{wedding}/validate', [\App\Http\Controllers\Api\PublishingController::class, 'validateWedding']);
        Route::match(['get', 'post'], '/weddings/{wedding}/publish/validate', [\App\Http\Controllers\Api\PublishingController::class, 'validateWedding']);
        Route::post('/weddings/{wedding}/publish', [\App\Http\Controllers\Api\PublishingController::class, 'publish']);
        Route::post('/weddings/{wedding}/unpublish', [\App\Http\Controllers\Api\PublishingController::class, 'unpublish']);

        // Payment & Coupon API
        Route::post('/coupons/validate', [\App\Http\Controllers\Api\CouponController::class, 'validateCoupon']);
        Route::post('/weddings/{wedding}/checkout', [\App\Http\Controllers\Api\PaymentController::class, 'checkout']);
        Route::get('/weddings/{wedding}/pending-payment', [\App\Http\Controllers\Api\PaymentController::class, 'pendingForWedding']);
        Route::get('/weddings/{wedding}/payment-status/{orderId}', [\App\Http\Controllers\Api\PaymentController::class, 'checkStatus']);
        Route::post('/payments/{merchantOrderId}/resume', [\App\Http\Controllers\Api\PaymentController::class, 'resume']);
        Route::post('/payments/{merchantOrderId}/sync-status', [\App\Http\Controllers\Api\PaymentController::class, 'syncStatus']);
        Route::post('/payments/{merchantOrderId}/cancel', [\App\Http\Controllers\Api\PaymentController::class, 'cancelTransaction']);
        Route::get('/user/transactions', [\App\Http\Controllers\Api\PaymentController::class, 'userTransactions']);

        // Guest Management & Groups API
        Route::apiResource('weddings.guest-groups', \App\Http\Controllers\Api\GuestGroupController::class);
        Route::post('/weddings/{wedding}/guests/batch', [\App\Http\Controllers\Api\GuestController::class, 'batchStore']);
        Route::apiResource('weddings.guests', \App\Http\Controllers\Api\GuestController::class);
        Route::post('/weddings/{wedding}/guests/generate-tokens', [\App\Http\Controllers\Api\GuestController::class, 'generateTokens']);
        Route::patch('/weddings/{wedding}/guests/{guest}/mark-wa-sent', [\App\Http\Controllers\Api\GuestController::class, 'markWhatsAppSent']);
        Route::get('/weddings/{wedding}/whatsapp-template', [\App\Http\Controllers\Api\GuestController::class, 'getWhatsAppTemplate']);
        Route::post('/weddings/{wedding}/whatsapp-template', [\App\Http\Controllers\Api\GuestController::class, 'saveWhatsAppTemplate']);

        // RSVP & Analytics Management API
        Route::get('/weddings/{wedding}/rsvps', [\App\Http\Controllers\Api\RsvpController::class, 'index']);
        Route::post('/weddings/{wedding}/rsvps/{rsvp}/toggle-approval', [\App\Http\Controllers\Api\RsvpController::class, 'toggleApproval']);
        Route::delete('/weddings/{wedding}/rsvps/{rsvp}/wishes', [\App\Http\Controllers\Api\RsvpController::class, 'deleteWish']);
        Route::delete('/weddings/{wedding}/rsvps/{rsvp}', [\App\Http\Controllers\Api\RsvpController::class, 'destroy']);
        Route::get('/weddings/{wedding}/analytics', [\App\Http\Controllers\Api\AnalyticsController::class, 'show']);

        // Subscription & Quota API
        Route::get('/plans', [\App\Http\Controllers\Api\SubscriptionController::class, 'plans']);
        Route::get('/user/subscription', [\App\Http\Controllers\Api\SubscriptionController::class, 'mySubscription']);
        Route::post('/user/subscribe', [\App\Http\Controllers\Api\SubscriptionController::class, 'subscribe']);
        Route::post('/weddings/{wedding}/unlock-single', [\App\Http\Controllers\Api\SubscriptionController::class, 'unlockSingleWedding']);

        // Template System API
        Route::apiResource('templates', \App\Http\Controllers\Api\TemplateController::class);
        Route::post('/templates/{template}/duplicate', [\App\Http\Controllers\Api\TemplateController::class, 'duplicate']);

        // User Media Library ("Media Saya")
        Route::get('/weddings/{wedding}/media', [\App\Http\Controllers\Api\UserMediaController::class, 'index']);
        Route::post('/weddings/{wedding}/media', [\App\Http\Controllers\Api\UserMediaController::class, 'store']);
        Route::delete('/weddings/{wedding}/media/{media}', [\App\Http\Controllers\Api\UserMediaController::class, 'destroy']);

        // Global Asset Library (Music, Stickers, Ornaments)
        Route::get('/assets', [\App\Http\Controllers\Api\GlobalAssetController::class, 'index']);

        // Admin Operations (Protected by admin middleware)
        Route::middleware(['admin'])->prefix('admin')->group(function () {
            Route::get('/overview', \App\Http\Controllers\Api\AdminOverviewController::class);

            // Admin Global Asset Library
            Route::prefix('assets')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\AdminAssetController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\AdminAssetController::class, 'store']);
                Route::put('/{media}', [\App\Http\Controllers\Api\AdminAssetController::class, 'update']);
                Route::delete('/{media}', [\App\Http\Controllers\Api\AdminAssetController::class, 'destroy']);
            });

            // Admin Finance, Gateway & Discount Management
            Route::prefix('finance')->group(function () {
                Route::get('/settings', [\App\Http\Controllers\Api\AdminFinanceController::class, 'getSettings']);
                Route::post('/settings', [\App\Http\Controllers\Api\AdminFinanceController::class, 'updateSettings']);
                Route::get('/transactions', [\App\Http\Controllers\Api\AdminFinanceController::class, 'getTransactions']);
                Route::get('/summary', [\App\Http\Controllers\Api\AdminFinanceController::class, 'getSummary']);
                Route::apiResource('coupons', \App\Http\Controllers\Api\CouponController::class);
            });

            // Admin Wedding Projects Management
            Route::prefix('weddings')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\AdminWeddingController::class, 'index']);
                Route::get('/statistics', [\App\Http\Controllers\Api\AdminWeddingController::class, 'statistics']);
                Route::get('/{wedding}', [\App\Http\Controllers\Api\AdminWeddingController::class, 'show']);
                Route::put('/{wedding}', [\App\Http\Controllers\Api\AdminWeddingController::class, 'update']);
                Route::patch('/{wedding}/toggle-publish', [\App\Http\Controllers\Api\AdminWeddingController::class, 'togglePublish']);
                Route::patch('/{wedding}/toggle-premium', [\App\Http\Controllers\Api\AdminWeddingController::class, 'togglePremium']);
                Route::delete('/{wedding}', [\App\Http\Controllers\Api\AdminWeddingController::class, 'destroy']);
            });

            // Admin User Management
            Route::apiResource('users', \App\Http\Controllers\Api\AdminUserController::class);
        });
    });
});
