<?php

namespace Tests\Feature;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemCaseCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_bookings_do_not_block_new_requests_but_approved_bookings_do(): void
    {
        $guest = $this->createUser('guest');
        $cottageId = $this->createCottage();
        $checkIn = now()->addDays(30)->toDateString();
        $checkOut = now()->addDays(31)->toDateString();
        $this->createBooking($guest->id, $cottageId, $checkIn, $checkOut, 'pending');

        $this->actingAs($guest)->post('/actions/book', [
            'cottage_id' => $cottageId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'adults' => 1,
            'children' => 0,
        ])->assertRedirect('/profile?tab=bookings');

        $this->assertSame(2, DB::table('bookings')->where('cottage_id', $cottageId)->count());

        DB::table('bookings')->where('cottage_id', $cottageId)->update(['status' => 'approved']);
        $this->actingAs($guest)->post('/actions/book', [
            'cottage_id' => $cottageId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'adults' => 1,
            'children' => 0,
        ])->assertRedirect('/booking?cottage=' . $cottageId);

        $this->assertSame(2, DB::table('bookings')->where('cottage_id', $cottageId)->count());
    }

    public function test_overlapping_pending_requests_cannot_both_be_approved(): void
    {
        $admin = $this->createUser('admin');
        $guest = $this->createUser('guest');
        $cottageId = $this->createCottage();
        $checkIn = now()->addDays(30)->toDateString();
        $checkOut = now()->addDays(31)->toDateString();
        $firstId = $this->createBooking($guest->id, $cottageId, $checkIn, $checkOut, 'pending');
        $secondId = $this->createBooking($guest->id, $cottageId, $checkIn, $checkOut, 'pending');

        $this->actingAs($admin)->post('/admin/bookings/action', [
            'booking_id' => $firstId,
            'action' => 'approve',
        ])->assertRedirect('/admin/bookings');

        $this->actingAs($admin)->post('/admin/bookings/action', [
            'booking_id' => $secondId,
            'action' => 'approve',
        ])->assertRedirect('/admin/bookings')->assertSessionHas('flashes');

        $this->assertDatabaseHas('bookings', ['id' => $firstId, 'status' => 'approved']);
        $this->assertDatabaseHas('bookings', ['id' => $secondId, 'status' => 'pending']);
    }

    public function test_booking_calendar_lists_only_approved_reservations_as_unavailable(): void
    {
        $guest = $this->createUser('guest');
        $cottageId = $this->createCottage();
        $pendingCheckIn = now()->addDays(30)->toDateString();
        $pendingCheckOut = now()->addDays(31)->toDateString();
        $approvedCheckIn = now()->addDays(40)->toDateString();
        $approvedCheckOut = now()->addDays(41)->toDateString();
        $this->createBooking($guest->id, $cottageId, $pendingCheckIn, $pendingCheckOut, 'pending');
        $this->createBooking($guest->id, $cottageId, $approvedCheckIn, $approvedCheckOut, 'approved');

        $ranges = $this->bookingCalendarRanges($guest, $cottageId);

        $this->assertCount(1, $ranges);
        $this->assertSame($approvedCheckIn, $ranges[0]['check_in']);
        $this->assertSame($approvedCheckOut, $ranges[0]['check_out']);
    }

    public function test_booking_calendar_availability_tracks_approval_cancellation_and_decline(): void
    {
        $admin = $this->createUser('admin');
        $guest = $this->createUser('guest');
        $cottageId = $this->createCottage();
        $checkIn = now()->addDays(30)->toDateString();
        $checkOut = now()->addDays(31)->toDateString();
        $bookingId = $this->createBooking($guest->id, $cottageId, $checkIn, $checkOut, 'pending');

        $this->assertSame([], $this->bookingCalendarRanges($guest, $cottageId));

        $this->actingAs($admin)->post('/admin/bookings/action', [
            'booking_id' => $bookingId,
            'action' => 'approve',
        ])->assertRedirect('/admin/bookings');
        $this->assertCount(1, $this->bookingCalendarRanges($guest, $cottageId));

        $this->actingAs($admin)->post('/admin/bookings/action', [
            'booking_id' => $bookingId,
            'action' => 'cancel',
        ])->assertRedirect('/admin/bookings');
        $this->assertSame([], $this->bookingCalendarRanges($guest, $cottageId));

        $declinedId = $this->createBooking($guest->id, $cottageId, $checkIn, $checkOut, 'pending');
        $this->actingAs($admin)->post('/admin/bookings/action', [
            'booking_id' => $declinedId,
            'action' => 'decline',
        ])->assertRedirect('/admin/bookings');
        $this->assertSame([], $this->bookingCalendarRanges($guest, $cottageId));
    }

    public function test_reset_code_locks_after_five_wrong_attempts(): void
    {
        $user = $this->createUser('guest');
        $reset = PasswordReset::create([
            'user_id' => $user->id,
            'token_hash' => password_hash('654321', PASSWORD_DEFAULT),
            'purpose' => 'code',
            'expires_at' => now()->addHour(),
            'used' => false,
            'attempts' => 0,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->post('/forgot-password/verify', [
                'email' => $user->email,
                'code' => '000000',
            ]);
        }

        $page = $this->get('/forgot-password');
        $page->assertOk()
            ->assertSee('Too many incorrect codes. Request a new code.')
            ->assertDontSee('New Password');
        $response->assertRedirect('/forgot-password');
        $this->assertDatabaseHas('password_resets', [
            'id' => $reset->id,
            'attempts' => 5,
            'used' => true,
        ]);
    }

    public function test_verified_reset_code_updates_password_and_cannot_be_reused(): void
    {
        $user = $this->createUser('guest');
        PasswordReset::create([
            'user_id' => $user->id,
            'token_hash' => password_hash('654321', PASSWORD_DEFAULT),
            'purpose' => 'code',
            'expires_at' => now()->addHour(),
            'used' => false,
            'attempts' => 0,
        ]);

        $response = $this->post('/forgot-password/verify', [
            'email' => $user->email,
            'code' => '654321',
        ])->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $token = (string) ($query['token'] ?? '');
        $this->assertNotSame('', $token);

        $this->post('/reset-password', [
            'token' => $token,
            'password' => 'NewPass@123',
            'confirm_password' => 'NewPass@123',
        ])->assertRedirect('/login');

        $this->get($location)
            ->assertOk()
            ->assertSee('This reset request is invalid or has expired.');
        $this->assertTrue(password_verify('NewPass@123', (string) $user->fresh()->password_hash));
        $this->assertStringStartsWith('$2y$', (string) $user->fresh()->password_hash);
        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id,
            'used' => true,
        ]);
    }

    public function test_account_deletion_is_blocked_by_active_bookings(): void
    {
        $guest = $this->createUser('guest');
        $cottageId = $this->createCottage();
        $this->createBooking(
            $guest->id,
            $cottageId,
            now()->addDays(30)->toDateString(),
            now()->addDays(31)->toDateString(),
            'pending'
        );

        $this->actingAs($guest)->post('/profile?tab=settings', [
            'form' => 'delete_account',
            'delete_password' => 'Guest@1234',
        ])->assertRedirect('/profile?tab=settings')->assertSessionHas('flashes');

        $this->assertSame('guest01@example.com', $guest->fresh()->email);
    }

    public function test_account_deletion_locks_after_three_wrong_passwords(): void
    {
        $guest = $this->createUser('guest');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->actingAs($guest)->post('/profile?tab=settings', [
                'form' => 'delete_account',
                'delete_password' => 'wrong-password',
            ]);
        }

        $response->assertRedirect('/profile?tab=settings')->assertSessionHas('flashes');

        $this->actingAs($guest)->post('/profile?tab=settings', [
            'form' => 'delete_account',
            'delete_password' => 'Guest@1234',
        ])->assertRedirect('/profile?tab=settings');

        $this->assertSame('guest01@example.com', $guest->fresh()->email);
        $this->assertDatabaseCount('account_deletion_attempts', 3);
    }

    public function test_account_deletion_anonymizes_personal_data_and_logs_out(): void
    {
        config(['booking.mail_driver' => 'array']);
        $guest = $this->createUser('guest');

        $this->actingAs($guest)->post('/profile?tab=settings', [
            'form' => 'delete_account',
            'delete_password' => 'Guest@1234',
        ])->assertRedirect('/');

        $anonymized = $guest->fresh();
        $this->assertGuest();
        $this->assertSame('Deleted', $anonymized->first_name);
        $this->assertSame('Guest', $anonymized->last_name);
        $this->assertStringEndsWith('@deleted.invalid', $anonymized->email);
        $this->assertSame('', $anonymized->phone);
        $this->assertSame('deactivated', $anonymized->status);
    }

    public function test_admin_can_approve_and_remove_reviews(): void
    {
        $admin = $this->createUser('admin');
        $guest = $this->createUser('guest');
        $cottageId = $this->createCottage();
        $bookingId = $this->createBooking(
            $guest->id,
            $cottageId,
            now()->subDays(3)->toDateString(),
            now()->subDays(2)->toDateString(),
            'completed'
        );
        $reviewId = (int) DB::table('reviews')->insertGetId([
            'booking_id' => $bookingId,
            'user_id' => $guest->id,
            'cottage_id' => $cottageId,
            'rating' => 5,
            'comment' => 'A lovely stay.',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->post('/admin/reviews/action', [
            'review_id' => $reviewId,
            'action' => 'approve',
        ])->assertRedirect('/admin/reviews');
        $this->assertDatabaseHas('reviews', ['id' => $reviewId, 'status' => 'approved']);

        $this->actingAs($admin)->post('/admin/reviews/action', [
            'review_id' => $reviewId,
            'action' => 'remove',
        ])->assertRedirect('/admin/reviews');
        $this->assertDatabaseHas('reviews', ['id' => $reviewId, 'status' => 'removed']);
    }

    public function test_admin_can_read_guest_feedback(): void
    {
        $admin = $this->createUser('admin');
        $guest = $this->createUser('guest');
        DB::table('feedback')->insert([
            'user_id' => $guest->id,
            'comment' => 'Please add more shaded seating.',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/reviews')
            ->assertOk()
            ->assertSee('Please add more shaded seating.');
    }

    public function test_guest_settings_exposes_the_account_deletion_flow(): void
    {
        $guest = $this->createUser('guest');

        $this->actingAs($guest)->get('/profile?tab=settings')
            ->assertOk()
            ->assertSee('Delete account')
            ->assertSee('name="delete_password"', false);
    }

    public function test_signup_links_to_terms_and_privacy_and_requires_consent(): void
    {
        $this->get('/signup')
            ->assertOk()
            ->assertSee('href="/contact#terms"', false)
            ->assertSee('href="/contact#privacy"', false)
            ->assertSee('name="consent" value="1" required', false);
    }

    public function test_feedback_submit_starts_disabled_until_a_comment_is_entered(): void
    {
        $guest = $this->createUser('guest');

        $this->actingAs($guest)->get('/reviews')
            ->assertOk()
            ->assertSee('data-feedback-form', false)
            ->assertSee('type="submit" disabled aria-disabled="true"', false);
    }

    public function test_public_pages_include_seo_metadata_and_structured_data(): void
    {
        $guest = $this->createUser('guest');
        $cottageId = $this->createCottage();

        $this->get('/')->assertOk()
            ->assertSee('name="description"', false)
            ->assertSee('Up to 4 guests')
            ->assertSee('application/ld+json');

        $this->actingAs($guest)->get('/booking?cottage=' . $cottageId)
            ->assertOk()
            ->assertSee('Capacity:</strong> 4 guests', false)
            ->assertSee('application/ld+json')
            ->assertSee('"@type":"WebSite"', false);
    }

    public function test_unknown_password_reset_email_uses_a_generic_response(): void
    {
        $this->followingRedirects()
            ->post('/forgot-password', ['email' => 'nobody@example.test'])
            ->assertOk()
            ->assertSee('If an active account uses that email, a 6-digit code has been sent.')
            ->assertDontSee('Email address not found');

        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_not_found_page_is_branded_and_does_not_expose_a_stack_trace(): void
    {
        $this->get('/not-a-real-page')
            ->assertNotFound()
            ->assertSee('We could not find that page.')
            ->assertDontSee('Stack trace');
    }

    private function createUser(string $role): User
    {
        return User::create([
            'first_name' => $role === 'guest' ? 'Guest' : 'Site',
            'last_name' => 'Tester',
            'email' => $role === 'guest' ? 'guest01@example.com' : 'admin@example.test',
            'phone' => '09171234567',
            'password_hash' => password_hash('Guest@1234', PASSWORD_DEFAULT),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function createCottage(): int
    {
        return (int) DB::table('cottages')->insertGetId([
            'code' => 'CTG-' . random_int(1000, 9999),
            'name' => 'Test Cottage',
            'capacity' => 4,
            'rate_per_day' => 1000,
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    private function createBooking(int $userId, int $cottageId, string $checkIn, string $checkOut, string $status): int
    {
        return (int) DB::table('bookings')->insertGetId([
            'receipt_no' => (string) random_int(100000, 999999),
            'user_id' => $userId,
            'cottage_id' => $cottageId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'adults' => 1,
            'children' => 0,
            'entrance_fee' => 100,
            'total_amount' => 1100,
            'payment_method' => 'pay_at_resort',
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    private function bookingCalendarRanges(User $guest, int $cottageId): array
    {
        $response = $this->actingAs($guest)->get('/booking?cottage=' . $cottageId);
        $response->assertOk()
            ->assertSee('data-booking-calendar', false)
            ->assertSee('id="bookingCalendarDays"', false)
            ->assertSee('data-calendar-prev', false)
            ->assertSee('data-calendar-next', false);

        $matches = [];
        $this->assertSame(
            1,
            preg_match('/<script type="application\\/json" id="unavailableDates">(.*?)<\\/script>/s', $response->getContent(), $matches)
        );

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
