<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Base test case, and the lock on which database the suite may touch.
 *
 * Every test here wraps its work in RefreshDatabase, which runs
 * `migrate:fresh` - dropping and rebuilding every table. Pointed at the wrong
 * connection that is not a slow test run, it is data loss, so the target
 * database is verified here rather than trusted to configuration.
 *
 * WHY createApplication() AND NOT setUp()
 *
 * Laravel runs, in this order:
 *
 *   1. setUpTheTestEnvironment() -> refreshApplication() -> createApplication()
 *   2. setUpTraits()             -> RefreshDatabase::refreshDatabase()
 *                                -> migrateDatabases() -> `migrate:fresh`
 *
 * Step 1 is the last point before the destructive step 2, so that is where the
 * check lives. Registering it in setUp() instead would attach a listener only
 * after migrate:fresh had already dropped the tables - a guard that arrives
 * after the fire.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * The only database the suite is allowed to touch.
     */
    private const TEST_CONNECTION = 'mysql';

    private const TEST_DATABASE = 'bdrpis_testing';

    /**
     * The real database, named explicitly so the failure message can say what
     * was nearly destroyed.
     */
    private const REAL_DATABASE = 'bdrpis';

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $this->assertConnectionIsIsolated($app);
        $this->watchEveryQuery($app);
        $this->assertTheServerAgrees($app);

        return $app;
    }

    /**
     * Refuse to run unless the default connection is the pinned MySQL one,
     * pointed at the testing database.
     *
     * Read from configuration, not from the connection, because this has to
     * pass or fail before anything opens a connection at all.
     */
    private function assertConnectionIsIsolated(Application $app): void
    {
        $config = $app['config'];

        $connection = $config->get('database.default');

        if ($connection !== self::TEST_CONNECTION) {
            throw new RuntimeException(sprintf(
                'Refusing to run the test suite: the default connection is [%s], expected [%s]. '
                .'It is pinned in phpunit.xml with force="true".',
                is_string($connection) ? $connection : gettype($connection),
                self::TEST_CONNECTION,
            ));
        }

        $database = $config->get("database.connections.{$connection}.database");

        if ($database !== self::TEST_DATABASE) {
            throw new RuntimeException(sprintf(
                'Refusing to run the test suite: it would drop and rebuild every table in [%s]. '
                .'Only [%s] may be used.',
                is_string($database) ? $database : gettype($database),
                self::TEST_DATABASE,
            ));
        }
    }

    /**
     * Fail any query aimed at a database other than the testing one.
     *
     * The check above covers the default connection. This covers the case it
     * cannot see: a test that resolves a different named connection, or that
     * overrides config/database.php mid-test, would otherwise walk straight past
     * it. Registered before the first query, so it is armed for the migrate.
     */
    private function watchEveryQuery(Application $app): void
    {
        $app['db']->listen(function ($query) use ($app): void {
            $database = $app['config']->get("database.connections.{$query->connectionName}.database");

            if ($database !== self::TEST_DATABASE) {
                throw new RuntimeException(sprintf(
                    'Refusing a query against the [%s] database; only [%s] may be used.',
                    is_string($database) ? $database : 'unknown',
                    self::TEST_DATABASE,
                ));
            }
        });
    }

    /**
     * Ask the server which database it actually resolved, and insist it agrees.
     *
     * Configuration can be right and the connection still wrong - a stale
     * env override, a socket pointing somewhere unexpected. SELECT DATABASE()
     * is the only answer that comes from the server rather than from the file,
     * and it is the same thing migrate:fresh is about to drop, so it is worth
     * one round trip to be certain.
     */
    private function assertTheServerAgrees(Application $app): void
    {
        $actual = $app['db']->connection(self::TEST_CONNECTION)->getPdo()->query('SELECT DATABASE()')->fetchColumn();

        if ($actual !== self::TEST_DATABASE) {
            throw new RuntimeException(sprintf(
                'Refusing to run the test suite: the server reports the connection is on [%s], '
                .'but [%s] was required. Nothing has been run. Check DB_DATABASE in .env.',
                is_string($actual) ? $actual : 'none',
                self::TEST_DATABASE,
            ));
        }
    }
}
