<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * System status: the things that tell an Admin the system is unhealthy before a
 * resident does.
 *
 * Every check is allowed to fail without failing the page. A status screen that
 * 500s when the disk is full is useless precisely when it is needed, so each
 * check returns a pass or a message and the page always renders.
 *
 * Nothing here returns a credential. The database check reports the server
 * version and the schema name, never the username or password, and the
 * environment check reports whether a key is set rather than what it holds.
 */
class SystemStatusController extends Controller
{
    public function index(): View
    {
        return view('staff.status.index', [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'migrations' => $this->checkMigrations(),
            'environment' => $this->checkEnvironment(),
            'counts' => $this->counts(),
        ]);
    }

    /**
     * @return array{ok: bool, label: string, detail: string}
     */
    private function checkDatabase(): array
    {
        try {
            $version = DB::selectOne('select version() as v')?->v ?? 'unknown';
            $name = DB::selectOne('select database() as n')?->n ?? '(none)';

            return [
                'ok' => true,
                'label' => 'Connected',
                // The server version and the schema name. Deliberately not the
                // username or password, which this page has no reason to show.
                'detail' => sprintf('%s on %s', $this->shortVersion((string) $version), $name),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'label' => 'Unreachable',
                'detail' => $this->safeMessage($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, label: string, detail: string}
     */
    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk('local');

            $probe = 'framework/health-'.bin2hex(random_bytes(6)).'.txt';
            $disk->put($probe, 'ok');
            $readBack = $disk->get($probe) === 'ok';
            $disk->delete($probe);

            if (! $readBack) {
                return ['ok' => false, 'label' => 'Not writable', 'detail' => 'A probe file was written but read back empty.'];
            }

            return ['ok' => true, 'label' => 'Writable', 'detail' => 'Private disk accepts writes.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'label' => 'Unwritable', 'detail' => $this->safeMessage($e)];
        }
    }

    /**
     * @return array{ok: bool, label: string, detail: string, pending: list<string>}
     */
    private function checkMigrations(): array
    {
        try {
            // A list, not an exit code: Artisan::call would swallow the output
            // this needs, and it must not be allowed to throw either.
            Artisan::call('migrate:status');
            $output = trim(Artisan::output());

            $pending = [];

            foreach (explode("\n", $output) as $line) {
                if (str_contains($line, 'Pending')) {
                    $pending[] = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
                }
            }

            return [
                'ok' => $pending === [],
                'label' => $pending === [] ? 'Up to date' : count($pending).' pending',
                'detail' => $pending === [] ? 'Every migration has run.' : 'Run php artisan migrate.',
                'pending' => array_slice($pending, 0, 5),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'label' => 'Unknown',
                'detail' => $this->safeMessage($e),
                'pending' => [],
            ];
        }
    }

    /**
     * Whether the environment is publishable, without reading any value out of
     * it. APP_DEBUG and APP_ENV are the two that actually change behaviour, so
     * those are shown; the rest are a presence check.
     *
     * @return array{ok: bool, label: string, detail: string, issues: list<string>, keys: array<string, bool>}
     */
    private function checkEnvironment(): array
    {
        // The config key each variable is read from, named explicitly. Deriving
        // the key by lowercasing the variable name would silently read a
        // different key than the one the application actually uses.
        $required = [
            'APP_KEY' => 'app.key',
            'APP_URL' => 'app.url',
            'DB_HOST' => 'database.connections.mysql.host',
            'DB_DATABASE' => 'database.connections.mysql.database',
            'DB_USERNAME' => 'database.connections.mysql.username',
            'DB_PASSWORD' => 'database.connections.mysql.password',
        ];

        $keys = [];
        $issues = [];

        foreach ($required as $variable => $configKey) {
            // Presence only. A password is reported as set or not set, never
            // printed - this page has no reason to show a credential.
            $set = filled(config($configKey));
            $keys[$variable] = $set;

            if (! $set) {
                $issues[] = $variable.' is not set.';
            }
        }

        if (config('app.debug') === true) {
            $issues[] = 'APP_DEBUG is on. Turn it off before this is used for real.';
        }

        $environment = (string) config('app.env');

        if ($environment === 'production') {
            $issues[] = 'APP_ENV is production.';
        }

        return [
            'ok' => $issues === [],
            'label' => $issues === [] ? 'Ready' : count($issues).' to review',
            // The environment name is shown because it changes what the app does.
            'detail' => sprintf('Environment: %s, debug: %s.', $environment, config('app.debug') ? 'on' : 'off'),
            'issues' => $issues,
            'keys' => $keys,
        ];
    }

    /**
     * Row counts, so an Admin can see the system is actually holding data.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'users' => User::query()->count(),
            'profiles' => ResidentProfile::query()->count(),
            'requests' => DocumentRequest::query()->count(),
            'batches' => ReliefBatch::query()->count(),
            'distributions' => ReliefDistribution::query()->count(),
            'announcements' => Announcement::query()->count(),
            'audit_entries' => AuditLog::query()->count(),
        ];
    }

    private function shortVersion(string $version): string
    {
        // "10.4.28-MariaDB-1:10.4.28+maria~bionic" reads as noise on a status page.
        return preg_match('/^([\d.]+)/', $version, $matches) === 1 ? $matches[1] : $version;
    }

    /**
     * An exception message is shown to an Admin but trimmed, because a driver
     * error can quote the connection string it failed on.
     */
    private function safeMessage(\Throwable $e): string
    {
        $message = preg_replace('/\s+/', ' ', $e->getMessage()) ?? $e->getMessage();

        return mb_substr($message, 0, 200);
    }
}
