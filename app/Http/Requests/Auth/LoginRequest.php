<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // The credentials were correct. Whether the account may now hold a
        // session is a separate question, and for an archived account the answer
        // is no - see refuseIfArchived().
        $this->refuseIfArchived();
    }

    /**
     * Refuse the session an archived account would otherwise get.
     *
     * This runs AFTER Auth::attempt() on purpose. Attempting first is what lets
     * the archive check read the authenticated user rather than re-querying by
     * email, and - more importantly - it means a wrong password on an archived
     * account still fails as a wrong password. Checking the email column first
     * would let anybody confirm which addresses are archived without holding a
     * credential, and would tell a genuinely archived resident that their old
     * password is "wrong" rather than that their account is closed.
     *
     * The session is torn down before the exception is thrown, not after. Throwing
     * first would leave Auth::attempt()'s login event and the session row behind
     * for a redirect that never happens, so the account would be signed in and
     * locked out of its own area - signed in, but unable to do anything. There is
     * one authorised caller and it is handled in one place:
     * EnsureUserHasRole ends an archive that happened mid-session, with the same
     * message from the same constant.
     *
     * @throws ValidationException
     */
    private function refuseIfArchived(): void
    {
        // Auth::user() is typed as the Authenticatable contract, so it is narrowed to
        // this project's own User here rather than assumed - which also means a guard
        // that resolved some other authenticatable cannot silently skip the check.
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isArchived()) {
            return;
        }

        Auth::guard('web')->logout();

        $this->session()->invalidate();
        $this->session()->regenerateToken();

        // Not a rate-limit hit: the credentials were right, so counting this
        // against the throttle key would lock out a resident who is only ever
        // going to be told the same thing. The key is left cleared.

        throw ValidationException::withMessages([
            'email' => User::ARCHIVED_LOGIN_MESSAGE,
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
