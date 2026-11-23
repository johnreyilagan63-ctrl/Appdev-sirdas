@include('partials.header')
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="/assets/img/logo.svg" alt="{{ config('app.name') }} logo">
        </div>
        <h1 class="auth-title">Choose a New Password</h1>

        @if ($invalid)
            <p class="form-error mb-2" role="alert">This reset request is invalid or has expired.</p>
            <a class="btn btn-primary btn-block" href="/forgot-password">Request a new code</a>
        @else
            <p class="auth-sub">Your code was verified. Use at least 8 characters. This reset request can only be used once.</p>

            @if (! empty($errors['form']))
                <p class="form-error mb-2" role="alert">{{ $errors['form'] }}</p>
            @endif

            <form class="form" method="post" action="/reset-password" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="field">
                    <label for="password">New Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" autocomplete="new-password"
                               minlength="8" required @if (isset($errors['password'])) aria-invalid="true" @endif>
                        {!! password_toggle() !!}
                    </div>
                    @if (isset($errors['password']))
                        <p class="field-error">{{ $errors['password'] }}</p>
                    @endif
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                               minlength="8" required @if (isset($errors['confirm_password'])) aria-invalid="true" @endif>
                        {!! password_toggle() !!}
                    </div>
                    @if (isset($errors['confirm_password']))
                        <p class="field-error">{{ $errors['confirm_password'] }}</p>
                    @endif
                </div>

                <button class="btn btn-primary btn-block" type="submit">Save New Password</button>
            </form>
        @endif
    </div>
</div>
@include('partials.footer')
