<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs after the app boots but before RefreshDatabase migrates. Abort
     * unless we're on the in-memory test database, so a misconfigured
     * environment can never wipe the dev database.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against [{$connection}:{$database}] — expected sqlite :memory:. Check phpunit.xml."
            );
        }

        return parent::setUpTraits();
    }
}
