<?php

namespace Tests\Feature;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class PasswordResetCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_code_and_verifies_it_before_reset(): void
    {
        $user = $this->createUser();

        $this->get('/forgot-password')
            ->assertOk()
            ->assertDontSee('Verification Code');

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('forgot_code_sent', true);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Verification Code')
            ->assertSee('Send a New Code');

        $code = $this->sentCode();
        $wrongCode = $this->wrongCode($code);

        $this->get('/reset-password?token=' . $code)
            ->assertOk()
            ->assertSee('This reset request is invalid or has expired.');

        $response = $this->post('/forgot-password/verify', [
            'email' => $user->email,
            'code' => $wrongCode,
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('forgot_code_error', 'That code is incorrect. Please try again.');

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('That code is incorrect. Please try again.');

        $response = $this->post('/forgot-password/verify', [
            'email' => $user->email,
            'code' => $code,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $token = (string) ($query['token'] ?? '');

        $this->assertNotSame('', $token);
        $this->assertSame('/reset-password', parse_url($location, PHP_URL_PATH));
        $codeReset = PasswordReset::where('user_id', $user->id)
            ->where('purpose', 'code')
            ->firstOrFail();
        $this->assertTrue(password_verify($code, $codeReset->token_hash));
        $this->assertTrue($codeReset->used);
        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'purpose' => 'token',
            'used' => false,
        ]);

        $this->get($location)
            ->assertOk()
            ->assertSee('Choose a New Password');

        $this->post('/reset-password', [
            'token' => $token,
            'password' => 'NewPass123!',
            'confirm_password' => 'NewPass123!',
        ])->assertRedirect('/login');

        $this->assertTrue(password_verify('NewPass123!', $user->fresh()->password_hash));
    }

    public function test_code_is_invalidated_after_too_many_wrong_attempts(): void
    {
        $user = $this->createUser();

        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');
        $code = $this->sentCode();
        $wrongCode = $this->wrongCode($code);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password/verify', [
                'email' => $user->email,
                'code' => $wrongCode,
            ])->assertRedirect('/forgot-password');
        }

        $this->post('/forgot-password/verify', [
            'email' => $user->email,
            'code' => $code,
        ])->assertRedirect('/forgot-password')
            ->assertSessionHas('forgot_code_error', 'That code is incorrect or has expired. Request a new code.');

        $this->assertSame(0, PasswordReset::where('used', false)->count());
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'Guest',
            'email' => 'reset-test@example.com',
            'phone' => '09170000000',
            'password_hash' => password_hash('OldPass123!', PASSWORD_DEFAULT),
            'role' => 'guest',
            'status' => 'active',
        ]);
    }

    private function wrongCode(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function sentCode(): string
    {
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->last()->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);

        $body = (string) $email->getTextBody();
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $body);
        $this->assertStringNotContainsString('/reset-password?token=', $body);
        $this->assertStringContainsString('password reset code', $body);

        preg_match('/\b(\d{6})\b/', $body, $matches);

        return $matches[1];
    }
}
