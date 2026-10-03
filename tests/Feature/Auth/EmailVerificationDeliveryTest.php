<?php

namespace Tests\Feature\Auth;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

/**
 * What a resident actually receives, and what happens when it does not arrive.
 *
 * The framework's own tests cover the mechanism - that a link can be generated,
 * that a hash mismatch is refused. What is not covered anywhere is the part that
 * decides whether this feature is usable in this barangay: that the message
 * says which office is asking, that the link is on the address the resident is
 * reading the link from, that the number of hours promised is the number
 * enforced, and above all that a wrong App Password on our side produces a log
 * line rather than a 500 in front of somebody who has just filled in a five-step
 * form.
 *
 * Every test here is written to fail if the behaviour it describes is removed.
 */
class EmailVerificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The uploaded ID goes to the private disk; a fake keeps a test run from
        // writing real files into storage/app/private.
        Storage::fake('local');
    }

    /**
     * A complete wizard submission, as the five steps post it.
     *
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'date_of_birth' => '1990-05-14',
            'gender' => 'Male',
            'civil_status' => 'Single',
            'nationality' => 'Filipino',
            'address' => 'Purok 3, Barangay Linasin',
            'sector_id' => Sector::factory()->create()->id,
            'email' => 'juan@example.com',
            'household_number' => 'HH-0001',
            'valid_id_photo' => UploadedFile::fake()->image('valid-id.jpg'),
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    /**
     * The signed link the message points at, built the way the framework builds
     * it - the same route, the same id and hash, and the lifetime read from
     * config rather than written out here, so a change to the setting is what
     * makes these tests notice.
     */
    private function linkFor(User $user, ?int $lifetimeMinutes = null): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($lifetimeMinutes ?? (int) config('auth.verification.expire')),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        );
    }

    // ---------------------------------------------------------------------
    // One message, to the address that was registered
    // ---------------------------------------------------------------------

    public function test_registration_sends_exactly_one_verification_notice_to_the_registered_address(): void
    {
        Notification::fake();

        $this->post('/register', $this->submission(['email' => 'juan@example.com']));

        $user = User::where('email', 'juan@example.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            function (VerifyEmail $notification, array $channels): bool {
                return $channels === ['mail'];
            },
        );

        // "Exactly one, and to that address" is the whole claim. A resident who
        // registers once and finds three identical messages has been told, by
        // the office, that something about their registration is unusual - and
        // one addressed to a different address is worse than none.
        $sent = [];

        foreach (Notification::sentNotifications() as $byId) {
            foreach ($byId as $byNotification) {
                foreach ($byNotification as $entries) {
                    foreach ($entries as $entry) {
                        /** @var User $notifiable */
                        $notifiable = $entry['notifiable'];
                        $sent[] = [$notifiable->email, $entry['notification']::class, $entry['channels']];
                    }
                }
            }
        }

        $this->assertSame([
            ['juan@example.com', VerifyEmail::class, ['mail']],
        ], $sent);
    }

    // ---------------------------------------------------------------------
    // The message itself
    // ---------------------------------------------------------------------

    public function test_the_notice_names_the_barangay_from_settings_in_the_subject_and_the_sender(): void
    {
        Setting::set('barangay_name', 'Barangay Linasin, Zone 1');

        $user = User::factory()->unverified()->create(['name' => 'Juan Dela Cruz']);

        $message = $this->messageFor($user);

        // The subject is the first thing a resident reads in a list of mail, and
        // the sender is the first thing they check when deciding whether an
        // unfamiliar message is real. Neither may say "Laravel".
        $this->assertStringContainsString('Barangay Linasin, Zone 1', $message->subject);
        $this->assertSame('Barangay Linasin, Zone 1', $message->from[1]);
    }

    public function test_the_notice_addresses_the_resident_by_name_and_carries_the_link(): void
    {
        Setting::set('barangay_name', 'Barangay Linasin, Zone 1');

        $user = User::factory()->unverified()->create(['name' => 'Juan Dela Cruz']);

        $message = $this->messageFor($user);

        $this->assertStringContainsString('Juan Dela Cruz', $message->greeting);
        $this->assertNotEmpty($message->actionText);
        $this->assertStringContainsString('/verify-email/', (string) $message->actionUrl);
        $this->assertStringContainsString('signature=', (string) $message->actionUrl);
    }

    public function test_the_notice_carries_no_personal_data_beyond_the_name(): void
    {
        Setting::set('barangay_name', 'Barangay Linasin, Zone 1');

        $profile = ResidentProfile::factory()->create([
            'user_id' => User::factory()->unverified()->create(['name' => 'Juan Dela Cruz'])->id,
            'household_number' => 'HH-4242',
            'address' => 'Purok 3, Barangay Linasin',
        ]);

        $user = $profile->user;

        $message = $this->messageFor($user);

        $rendered = implode("\n", array_merge($message->introLines, $message->outroLines));

        // A verification mail is read by whoever received it, including whoever
        // forwarded it. It confirms an address; it must not confirm a household.
        $this->assertStringNotContainsString('HH-4242', $rendered);
        $this->assertStringNotContainsString('Purok 3', $rendered);
        $this->assertStringNotContainsString('date_of_birth', $rendered);
    }

    // ---------------------------------------------------------------------
    // The link is on the host the resident is browsing from
    // ---------------------------------------------------------------------

    public function test_the_generated_link_uses_the_configured_app_url_host(): void
    {
        // The URL generator reads app.url once, when it is resolved, so setting
        // config afterwards changes nothing. This is the same trap an operator
        // falls into: change APP_URL, restart, and the old host is still baked
        // into every signed link until the server is actually restarted.
        config(['app.url' => 'http://bdrpis.test:8123']);
        URL::forceRootUrl('http://bdrpis.test:8123');

        $user = User::factory()->unverified()->create();

        $message = $this->messageFor($user);

        $url = (string) $message->actionUrl;

        $this->assertSame('bdrpis.test', parse_url($url, PHP_URL_HOST));
        $this->assertSame(8123, parse_url($url, PHP_URL_PORT));
        $this->assertSame('http', parse_url($url, PHP_URL_SCHEME));
    }

    // ---------------------------------------------------------------------
    // The lifetime promised is the lifetime enforced
    // ---------------------------------------------------------------------

    public function test_the_configured_link_lifetime_is_twenty_four_hours(): void
    {
        // 1440 minutes. Pinned because the reason for it is a decision, not a
        // default: a reader on a phone whose mail sits unread for a day is the
        // ordinary case here, and the framework's 60 minutes only makes the
        // first attempt fail.
        $this->assertSame(1440, (int) config('auth.verification.expire'));
    }

    public function test_the_notice_states_how_long_the_link_lasts(): void
    {
        Setting::set('barangay_name', 'Barangay Linasin, Zone 1');

        $user = User::factory()->unverified()->create();

        $message = $this->messageFor($user);

        $rendered = implode("\n", array_merge($message->introLines, $message->outroLines));

        // Read from the same config value the URL is built from, so the two
        // cannot drift apart into a message promising a day for a link that
        // lives an hour.
        $this->assertStringContainsString(
            sprintf('%d hours', (int) round((int) config('auth.verification.expire') / 60)),
            $rendered,
        );
    }

    public function test_a_link_younger_than_the_configured_lifetime_is_accepted(): void
    {
        $user = User::factory()->unverified()->create();

        $link = $this->linkFor($user);

        // Twenty-three hours in, so this is inside the window rather than at its
        // comfortable middle - the case that would break if the setting were
        // quietly shortened.
        $this->travel(23)->hours();

        $this->actingAs($user)
            ->get($link)
            ->assertRedirect(route('dashboard').'?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_older_than_the_configured_lifetime_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $link = $this->linkFor($user);

        // One minute past the window. Signed links carry their own expiry, so
        // this is refused by the middleware and not by a check in our code,
        // which is the point of it being signed rather than a lookup.
        $this->travel((int) config('auth.verification.expire') + 1)->minutes();

        $this->actingAs($user)->get($link)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_same_link_is_accepted_before_the_deadline_and_refused_after_it(): void
    {
        // One link, two moments. A test that only builds two links would pass
        // even if the expiry in the signature were ignored altogether.
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);

        $this->travel((int) config('auth.verification.expire') - 1)->minutes();
        $this->actingAs($user)->get($link)->assertRedirect(route('dashboard').'?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // A second account, so the first one's verified state cannot mask the
        // refusal.
        $later = User::factory()->unverified()->create();
        $laterLink = $this->linkFor($later);

        $this->travel((int) config('auth.verification.expire') + 2)->minutes();
        $this->actingAs($later)->get($laterLink)->assertForbidden();
        $this->assertFalse($later->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_whose_signature_has_been_edited_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $tampered = str_replace(
            'signature=',
            'signature=0',
            $this->linkFor($user),
        );

        $this->actingAs($user)->get($tampered)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_for_a_different_accounts_hash_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $wrongHash = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire')),
            ['id' => $user->getKey(), 'hash' => sha1('somebody-else@example.com')],
        );

        $this->actingAs($user)->get($wrongHash);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    // ---------------------------------------------------------------------
    // The resident area is closed until the address is confirmed
    // ---------------------------------------------------------------------

    /**
     * Every resident route that can be reached while unverified, with the verb,
     * the path, and the refusal an unverified account actually receives.
     *
     * The third column is not decoration. Two of these routes bind a
     * DocumentRequest, and Laravel runs route-model binding inside the `web`
     * group - which is *before* the `verified` middleware on the route. The
     * binding is owner-scoped, so an unverified resident asking for a
     * neighbour's request is refused with a 404 before the verification check
     * is ever reached. That is the better answer, and it is still a refusal;
     * asserting a redirect there would fail on correct code.
     *
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function residentRoutesUnderVerification(): array
    {
        return [
            'request list' => ['GET', '/resident/requests', 302],
            'new request form' => ['GET', '/resident/requests/create', 302],
            'request detail' => ['GET', '/resident/requests/{id}', 404],
            'cancel request' => ['POST', '/resident/requests/{id}/cancel', 404],
            'download document' => ['GET', '/resident/requests/{id}/document.pdf', 404],
            'email a copy' => ['POST', '/resident/requests/{id}/email-copy', 404],
            'relief' => ['GET', '/resident/relief', 302],
        ];
    }

    #[DataProvider('residentRoutesUnderVerification')]
    public function test_an_unverified_resident_is_stopped_at_every_resident_route(string $method, string $path, int $expected): void
    {
        $user = User::factory()->unverified()->resident()->create();

        $path = str_replace('{id}', (string) $this->somebodyElsesRequestId(), $path);

        $response = $this->actingAs($user)->call($method, $path);

        $response->assertStatus($expected);

        if ($expected === 302) {
            $response->assertRedirect(route('verification.notice'));
        }
    }

    #[DataProvider('residentRoutesUnderVerification')]
    public function test_a_verified_resident_is_not_stopped_at_that_route(string $method, string $path, int $expected): void
    {
        $user = User::factory()->resident()->create();

        $path = str_replace('{id}', (string) $this->somebodyElsesRequestId(), $path);

        $response = $this->actingAs($user)->call($method, $path);

        // Anything other than the redirect is the point. A verified resident is
        // refused for their own reasons on some of these - somebody else's
        // request - and this test is not about which.
        $this->assertNotSame(
            route('verification.notice'),
            (string) $response->headers->get('Location'),
            sprintf('A verified resident was redirected to the confirmation page by %s %s', $method, $path),
        );
    }

    /**
     * A request that exists and belongs to the resident in the test, so the
     * owner-scoped binding does not turn every assertion into a 404.
     */
    private function requestIdFor(User $owner): int
    {
        return DocumentRequest::factory()->forResident($owner)->create()->getKey();
    }

    /**
     * An id that exists but belongs to somebody else.
     *
     * It exists rather than being invented on purpose: a route that 404s on a
     * made-up id would pass this test for the wrong reason, and the gap being
     * closed is "cannot reach anything", not "cannot guess an id".
     */
    private function somebodyElsesRequestId(): int
    {
        return $this->requestIdFor(User::factory()->resident()->create());
    }

    public function test_an_unverified_resident_cannot_read_their_own_request_either(): void
    {
        // The owner-scoped binding passes here - this one really is theirs - so
        // this route is the one where `verified` is the only thing standing
        // between the resident and their own request history.
        $user = User::factory()->unverified()->resident()->create();

        $requestId = $this->requestIdFor($user);

        $this->actingAs($user)
            ->get("/resident/requests/{$requestId}")
            ->assertRedirect(route('verification.notice'));
    }

    public function test_an_unverified_resident_cannot_submit_a_request(): void
    {
        $user = User::factory()->unverified()->resident()->create();

        $this->actingAs($user)
            ->post('/resident/requests', [])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_an_unverified_resident_cannot_download_their_own_document(): void
    {
        // Their own request, ready to be produced. Without `verified` this would
        // render and encrypt a PDF for an account whose address has never been
        // proven - the one document in this application that leaves the building.
        $user = User::factory()->unverified()->resident()->create();

        $requestId = $this->requestIdFor($user);

        $this->actingAs($user)
            ->get("/resident/requests/{$requestId}/document.pdf")
            ->assertRedirect(route('verification.notice'));
    }

    public function test_an_unverified_resident_cannot_email_a_copy_of_their_own_document(): void
    {
        // Same document, same account, leaving by post instead of by download.
        // An emailed copy is the worse of the two: it can be read on a machine
        // that is not this one, on a connection that is not this office's.
        $user = User::factory()->unverified()->resident()->create();

        $requestId = $this->requestIdFor($user);

        $this->actingAs($user)
            ->post("/resident/requests/{$requestId}/email-copy")
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseMissing('document_deliveries', [
            'document_request_id' => $requestId,
        ]);
    }

    public function test_the_dashboard_itself_is_closed_to_an_unverified_account(): void
    {
        $user = User::factory()->unverified()->resident()->create();

        // The signpost, not the destination - but it is still a place an
        // unverified account should not come to rest.
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_my_account_is_closed_to_an_unverified_account(): void
    {
        $user = User::factory()->unverified()->resident()->create();

        // Otherwise a resident could change the address they never confirmed and
        // the loop would never end.
        $this->actingAs($user)->get('/account')->assertRedirect(route('verification.notice'));
    }

    // ---------------------------------------------------------------------
    // Resending
    // ---------------------------------------------------------------------

    public function test_the_resend_button_sends_another_notice(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from('/verify-email')
            ->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_confirmation_page_confirms_that_a_new_link_was_sent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // Following the resend through to the page it redirects back to, rather
        // than asserting the session flag alone: a resident who presses Resend
        // and sees no change has been told the office ignored them.
        $response = $this->actingAs($user)
            ->from('/verify-email')
            ->post('/email/verification-notification');

        $response->assertRedirect('/verify-email');

        $this->followingRedirects()
            ->from('/verify-email')
            ->post('/email/verification-notification')
            ->assertSee('A new verification link has been sent');
    }

    public function test_resending_is_throttled(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // Six an hour, from routes/auth.php. The seventh is refused: this button
        // is a button a person can hold down, and an unbounded resend is a way
        // to have the office's mail server mail-bombelled by one resident.
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($user)
                ->from('/verify-email')
                ->post('/email/verification-notification')
                ->assertRedirect('/verify-email');
        }

        $tooMany = $this->actingAs($user)
            ->from('/verify-email')
            ->post('/email/verification-notification');

        $tooMany->assertStatus(429);
    }

    public function test_a_verified_account_cannot_ask_for_another_notice(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post('/email/verification-notification');

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------------
    // Changing the address
    // ---------------------------------------------------------------------

    public function test_changing_the_email_address_requires_confirming_the_new_one(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'is_verified' => true,
        ]);

        $this->actingAs($user)
            ->patch('/account', [
                'name' => $user->name,
                'email' => 'new@example.com',
            ])
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        // Otherwise a resident could claim an address they do not own, keep the
        // session they signed in with, and have the confirmation sent to the
        // address they are claiming.
        $this->assertNull($user->email_verified_at);
    }

    public function test_a_resident_who_changed_their_address_is_sent_back_to_the_confirmation_page(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'is_verified' => true,
        ]);

        $this->actingAs($user)->patch('/account', [
            'name' => $user->name,
            'email' => 'new@example.com',
        ]);

        $this->actingAs($user)
            ->get('/resident/requests')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_saving_the_same_address_leaves_the_account_verified(): void
    {
        $user = User::factory()->create([
            'email' => 'same@example.com',
            'is_verified' => true,
        ]);

        $this->actingAs($user)->patch('/account', [
            'name' => 'Juan Dela Cruz',
            'email' => 'same@example.com',
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_staff_account_who_changes_their_address_also_has_to_confirm_the_new_one(): void
    {
        // The same controller and the same rule for all four roles. Staff are
        // seeded verified, so this is the only path by which a Secretary loses
        // access, and it has to be deliberate.
        $user = User::factory()->secretary()->create([
            'email' => 'secretary.old@example.com',
        ]);

        $this->actingAs($user)->patch('/account', [
            'name' => $user->name,
            'email' => 'secretary.new@example.com',
        ]);

        $user->refresh();

        $this->assertNull($user->email_verified_at);

        $this->actingAs($user)
            ->get('/staff/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    // ---------------------------------------------------------------------
    // When the mail cannot be sent
    // ---------------------------------------------------------------------

    /**
     * Make sending fail the way an operator's mail settings fail.
     *
     * A real transport that throws on connect or on authentication, rather than a
     * mock of the mail facade: the point of these tests is that the failure arrives
     * through the same channel the real one does, and a facade mock that
     * intercepts `send` would let a bug in how the notification reaches the mailer
     * pass unnoticed.
     *
     * Both halves matter. The mailer has to be *declared* in mail.mailers as
     * well as registered as a creator, because MailManager looks the name up
     * there first and answers "is not defined" otherwise - which would be a
     * failure of this test rather than of the code, and would pass for all the
     * wrong reasons.
     */
    private function breakMailSending(string $reason = 'SMTP authentication failed'): void
    {
        Mail::extend('always-fails', fn () => new class($reason) implements TransportInterface
        {
            public function __construct(private readonly string $reason) {}

            /**
             * A real Symfony transport, so the throw happens at the point an SMTP
             * rejection would: inside send(), on the way to the server, after
             * the message has been fully rendered. Throwing from the mailer
             * factory instead would fail earlier than a bad password ever does,
             * and would never exercise the render path at all.
             */
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException($this->reason);
            }

            public function __toString(): string
            {
                return 'always-fails';
            }
        });

        config([
            'mail.mailers.always-fails' => [
                'driver' => 'always-fails',
                'transport' => 'always-fails',
            ],
            'mail.default' => 'always-fails',
        ]);
    }

    public function test_registration_still_succeeds_when_the_mail_transport_fails(): void
    {
        // The real failure: an operator has put the account password in
        // MAIL_PASSWORD, or the host is unreachable. It happens on a deployment
        // where everything else works, which is exactly when a 500 in the middle
        // of a five-step registration is worst.
        $this->breakMailSending('Failed to authenticate on SMTP server with username "barangay@gmail.com".');

        $response = $this->post('/register', $this->submission());

        // No 500. The resident lands on the confirmation page carrying a calm
        // explanation and the button to try again.
        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('status', 'verification-notice-failed');
        $this->assertAuthenticated();

        // The account exists. Rolling it back would mean the resident retypes
        // everything because our mail settings are wrong.
        $this->assertDatabaseHas('users', ['email' => 'juan@example.com']);
        $this->assertDatabaseHas('resident_profiles', ['household_number' => 'HH-0001']);
    }

    public function test_a_failed_notice_shows_a_calm_message_and_still_offers_the_resend(): void
    {
        $this->breakMailSending('Failed to authenticate on SMTP server.');

        $this->post('/register', $this->submission());

        // Follow the redirect the way the resident does, rather than asserting on
        // the flashed value: the point is what they read on the page.
        $response = $this->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('we could not send the verification email');
        $response->assertSee('problem with our email settings, not with yours');

        // The way out is still on the page. A message that only explains the
        // problem, with no button, is a dead end.
        $response->assertSee('Resend Verification Email');
        $response->assertSee(route('verification.send'), escape: false);
    }

    public function test_a_failed_notice_does_not_blame_the_resident(): void
    {
        $this->breakMailSending('Failed to authenticate on SMTP server.');

        $this->post('/register', $this->submission());

        $response = $this->get(route('verification.notice'));

        // The default introduction claims a link "we just emailed to you". Left
        // alone on this page it would be a false statement about an email that
        // was never sent.
        $response->assertDontSee('clicking on the link we just emailed to you');
    }

    public function test_the_failure_reaching_the_controller_is_a_transport_failure(): void
    {
        // Guards the helper above. If the fake mailer stopped being a transport
        // and went back to being undeclared, every other failure test in this
        // class would still pass while testing "the mailer name is misspelled".
        $this->breakMailSending('Failed to authenticate on SMTP server.');

        Log::spy();

        $this->post('/register', $this->submission(['email' => 'juan@example.com']));

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($context['exception']->getMessage(), 'SMTP'));
    }

    public function test_a_failed_notice_is_logged_with_the_account_it_belongs_to(): void
    {
        $this->breakMailSending('SMTP connection refused');

        Log::spy();

        $this->post('/register', $this->submission(['email' => 'juan@example.com']));

        // "The resident says no email arrived" is unanswerable without this
        // line: the request itself recorded nothing wrong.
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'verification')
                && ($context['user_id'] ?? null) !== null);
    }

    public function test_a_resident_whose_notice_failed_can_ask_for_another_one(): void
    {
        $this->breakMailSending('SMTP connection refused');

        $this->post('/register', $this->submission(['email' => 'juan@example.com']));

        $user = User::where('email', 'juan@example.com')->firstOrFail();

        // The account is unusable until the address is confirmed, and this is
        // the way out: no account to delete, no re-registration, no second
        // household number in the Secretary's queue.
        $this->assertFalse($user->hasVerifiedEmail());

        // With the mailer repaired, the resend the resident is told to press has
        // to work. Asserting the redirect while the transport is still broken
        // would pass even if the button were wired to nothing.
        config(['mail.default' => 'array']);
        Notification::fake();

        $this->actingAs($user)
            ->from('/verify-email')
            ->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_failed_notice_leaves_the_account_on_the_confirmation_page_rather_than_an_error(): void
    {
        $this->breakMailSending('SMTP connection refused');

        $this->post('/register', $this->submission(['email' => 'juan@example.com']));

        $user = User::where('email', 'juan@example.com')->firstOrFail();

        // The calm page: it says a link was sent, and offers another.
        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertSee('Resend Verification Email');
    }

    public function test_a_failed_notice_does_not_delete_the_uploaded_id_or_the_account(): void
    {
        $this->breakMailSending('SMTP connection refused');

        $this->post('/register', $this->submission(['email' => 'juan@example.com']));

        $profile = ResidentProfile::where('household_number', 'HH-0001')->firstOrFail();

        // The resident would otherwise have to register again, and registering
        // again would collide on the household number they already hold.
        $this->assertNotNull($profile->id_photo_path);
        $this->assertTrue(
            Storage::disk('local')->exists($profile->id_photo_path),
            'The uploaded ID was deleted by a mail failure that had nothing to do with it.',
        );
    }

    public function test_a_registration_failure_that_is_not_the_mail_still_rolls_everything_back(): void
    {
        // The catch is scoped to the notice, not to the whole action: a genuine
        // database failure must still undo the rows rather than leaving a
        // half-made account behind.
        $this->post('/register', $this->submission(['household_number' => '']))
            ->assertSessionHasErrors('household_number');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'juan@example.com']);
    }

    // ---------------------------------------------------------------------
    // Pressing Resend while our mail settings are still broken
    // ---------------------------------------------------------------------

    public function test_pressing_resend_while_the_transport_is_still_broken_is_not_an_error_page(): void
    {
        $user = $this->unverifiedResident();

        $this->breakMailSending('SMTP connection refused');

        $response = $this->actingAs($user)->from('/verify-email')
            ->post('/email/verification-notification');

        // The resident did what we told them to do. A 5xx here would be the app
        // failing at the exact moment it is asking for help.
        $response->assertRedirect('/verify-email');
        $response->assertSessionHasNoErrors();
    }

    public function test_a_resend_that_fails_explains_itself_instead_of_claiming_success(): void
    {
        $user = $this->unverifiedResident();

        $this->breakMailSending('SMTP connection refused');

        $this->actingAs($user)->from('/verify-email')
            ->post('/email/verification-notification');

        // Told the message went out when it did not, this is the same lie as the
        // registration path had to be stopped from telling.
        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertOk();
        $response->assertSee('we could not send the verification email');
        $response->assertDontSee('A new verification link has been sent');
        $response->assertSee('Resend Verification Email');
    }

    public function test_a_resend_that_fails_is_logged_against_the_account_it_belongs_to(): void
    {
        $user = $this->unverifiedResident();

        $this->breakMailSending('SMTP connection refused');

        Log::spy();

        $this->actingAs($user)->from('/verify-email')
            ->post('/email/verification-notification');

        // Two attempts, two lines. "No email arrived and they say they pressed
        // resend" is only answerable if the second attempt left a trace too.
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'resent')
                && $context['user_id'] === $user->getKey());
    }

    public function test_a_resend_that_works_reports_success_as_before(): void
    {
        $user = $this->unverifiedResident();

        Notification::fake();

        $this->actingAs($user)->from('/verify-email')
            ->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * A signed-in resident who has not confirmed their address yet.
     */
    private function unverifiedResident(): User
    {
        $user = User::factory()->unverified()->create();

        $user->residentProfile()->create(
            ResidentProfile::factory()->make(['user_id' => $user->getKey()])->toArray()
        );

        return $user;
    }

    // ---------------------------------------------------------------------

    /**
     * The message as the resident receives it, built through the real
     * notification rather than by calling a formatter directly - so a change to
     * the wiring is a change to what these assertions see.
     */
    private function messageFor(User $user): MailMessage
    {
        /** @var MailMessage $message */
        $message = (new VerifyEmail)->toMail($user);

        return $message;
    }
}
