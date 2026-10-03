<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\DigitalCopyRenderer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The font directory is a constructor argument rather than something the
        // renderer resolves for itself, so the path the PDF library reads its
        // metrics from is stated once here where it can be read and changed, and so
        // a test can point the renderer at a different directory if it ever needs
        // to prove the committed one is what was used.
        $this->app->bind(
            DigitalCopyRenderer::class,
            fn ($app): DigitalCopyRenderer => new DigitalCopyRenderer(
                resource_path('pdf-fonts')
            )
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->localiseEmailVerificationNotice();
    }

    /**
     * The wording of the "confirm your email address" message.
     *
     * The framework's own text is correct and short, but it says "Verify your
     * email address" and nothing else: a resident of this barangay is being asked
     * by their own local government to confirm an address, and the message should
     * say which office is asking and how long the link lasts. That is the whole of
     * the change - the button, the layout and the Markdown rendering are the
     * framework's, because a verification mail is the one message a stranger
     * reads before deciding whether this site is real, and it should not be
     * redesigned by a project it was not designed for.
     *
     * Only the name is used from the account: the message is addressed to
     * $notifiable, whose address it already has, so nothing about the household,
     * the sector or the request history travels in it.
     *
     * The sender name is the barangay from Settings rather than APP_NAME, because
     * APP_NAME is whatever the deployment was called and the header is the one
     * line a resident sees in their inbox before they open anything.
     */
    private function localiseEmailVerificationNotice(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            $barangay = Setting::text('barangay_name');
            $office = $barangay !== '' ? $barangay : (string) config('app.name');
            $minutes = (int) config('auth.verification.expire', 60);

            $message = (new MailMessage)
                ->subject(sprintf('Confirm your email address - %s', $office))
                ->greeting(sprintf('Hello %s,', $notifiable->name))
                ->line('Please confirm the email address you registered so your account can be used.')
                ->action('Confirm my email address', $verificationUrl)
                ->line(sprintf(
                    'This link expires in %s. If it has expired, use the Resend button on the '
                        .'confirmation page and we will send a new one.',
                    $minutes >= 60
                        ? sprintf('%d hours', (int) round($minutes / 60))
                        : sprintf('%d minutes', $minutes)
                ))
                ->line('If you did not register with us, you can ignore this message and nothing will happen.');

            // The from name only. The address itself stays whatever the
            // deployment configured, because inventing one here would either send
            // from a domain that does not exist or quietly override a working
            // MAIL_FROM_ADDRESS with a null.
            if (filled(config('mail.from.address'))) {
                $message->from(config('mail.from.address'), $office);
            }

            return $message;
        });
    }
}
