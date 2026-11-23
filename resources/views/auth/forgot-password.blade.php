@include('partials.header')
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="/assets/img/logo.svg" alt="{{ config('app.name') }} logo">
        </div>
        <h1 class="auth-title">Forgot Password</h1>
        <p class="auth-sub">Enter the email on your account and we will send a 6-digit reset code.</p>

        @if ($codeSent)
            <p class="form-success mb-2" role="status">
                If an active account uses that email, a 6-digit code has been sent. Enter it below. The code expires in {{ (int) config('booking.reset_code_expiry_minutes', 10) }} minutes.
            </p>
        @endif

        @if (! empty($errors['email']))
            <p class="form-error mb-2" role="alert">{{ $errors['email'] }}</p>
        @endif

        <form class="form" method="post" action="/forgot-password" novalidate>
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ $email }}"
                       autocomplete="email" required @if (isset($errors['email'])) aria-invalid="true" @endif>
            </div>

            @if ($codeSent)
                <div class="field">
                    <label for="code">Verification Code</label>
                    <input type="text" id="code" name="code" value="{{ $code }}"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           autocomplete="one-time-code" required autofocus
                           @if (isset($errors['code'])) aria-invalid="true" @endif>
                    @if (isset($errors['code']))
                        <p class="field-error">{{ $errors['code'] }}</p>
                    @else
                        <p class="helper">Check your inbox for the 6-digit code.</p>
                    @endif
                </div>
                <button class="btn btn-primary btn-block" type="submit" formaction="/forgot-password/verify">Verify Code</button>
                <button class="btn btn-secondary btn-block mt-1" type="submit" formnovalidate>Send a New Code</button>
            @else
                <button class="btn btn-primary btn-block" type="submit">Send Code</button>
            @endif
        </form>
    </div>
</div>
@include('partials.footer')
