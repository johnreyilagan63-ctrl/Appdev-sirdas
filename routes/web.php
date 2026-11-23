<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CottageController as AdminCottageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OwnerController as AdminOwnerController;
use App\Http\Controllers\Admin\RecordController as AdminRecordController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
Route::post('/signup', [AuthController::class, 'signup'])->name('signup.store');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendReset'])->name('password.email');
Route::post('/forgot-password/verify', [AuthController::class, 'verifyResetCode'])->name('password.verify');
Route::match(['get', 'post'], '/reset-password', [AuthController::class, 'showReset'])->name('password.reset');

Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
Route::match(['get', 'post'], '/contact', [ContactController::class, 'index'])->name('contact');

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], '/profile', [ProfileController::class, 'index'])->name('profile')->middleware('role:guest,owner');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews')->middleware('role:guest,owner');
    Route::post('/actions/submit-review', [ReviewController::class, 'store'])->name('reviews.store')->middleware('role:guest');
    Route::post('/actions/submit-feedback', [ReviewController::class, 'storeFeedback'])->name('feedback.store')->middleware('role:guest');
    Route::post('/actions/cancel-booking', [BookingController::class, 'cancel'])->name('bookings.cancel');
});

Route::middleware('role:guest')->group(function () {
    Route::get('/booking', [BookingController::class, 'index'])->name('booking');
    Route::post('/actions/book', [BookingController::class, 'store'])->name('booking.store');
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms');
});

Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/owners', [AdminOwnerController::class, 'index'])->name('owners');
    Route::post('/owners/action', [AdminOwnerController::class, 'action'])->name('owners.action');

    Route::match(['get', 'post'], '/cottages', [AdminCottageController::class, 'index'])->name('cottages');

    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings');
    Route::post('/bookings/action', [AdminBookingController::class, 'action'])->name('bookings.action');

    Route::match(['get', 'post'], '/users', [AdminUserController::class, 'index'])->name('users');

    Route::get('/records', [AdminRecordController::class, 'index'])->name('records');
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews');
    Route::post('/reviews/action', [AdminReviewController::class, 'action'])->name('reviews.action');
});

Route::get('/actions/approve-booking.php', fn () => redirect('/admin/bookings'));
Route::get('/actions/approve-owner.php', fn () => redirect('/admin/owners'));

$legacy = [
    'index.php' => '/',
    'login.php' => '/login',
    'signup.php' => '/signup',
    'logout.php' => '/logout',
    'forgot-password.php' => '/forgot-password',
    'reset-password.php' => '/reset-password',
    'profile.php' => '/profile',
    'booking.php' => '/booking',
    'rooms.php' => '/rooms',
    'reviews.php' => '/reviews',
    'gallery.php' => '/gallery',
    'contact.php' => '/contact',
    'admin/index.php' => '/admin',
    'admin/owners.php' => '/admin/owners',
    'admin/cottages.php' => '/admin/cottages',
    'admin/bookings.php' => '/admin/bookings',
    'admin/users.php' => '/admin/users',
    'admin/reviews.php' => '/admin/reviews',
    'admin/records.php' => '/admin/records',
];

foreach ($legacy as $old => $new) {
    Route::get('/' . $old, function () use ($new) {
        $query = request()->getQueryString();

        return redirect($new . ($query ? '?' . $query : ''));
    });
}
