<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs after the app boots but before RefreshDatabase/DatabaseTruncation
     * touch the schema. Abort unless we're on a dedicated test database
     * (sqlite :memory:, or a MySQL database named *_testing), so a
     * misconfigured environment can never wipe the dev database.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        $isTestDatabase = ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && str_ends_with($database, '_testing'));

        if (! $isTestDatabase) {
            throw new RuntimeException(
                "Refusing to run tests against [{$connection}:{$database}] — expected sqlite :memory: or a *_testing MySQL database. Check the phpunit config."
            );
        }

        return parent::setUpTraits();
    }
}
