<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Used by the container entrypoint: MySQL's healthcheck passing and MySQL
 * accepting *this app's* connection (user, database, network) aren't always
 * the same instant, so retry a real connection before migrating.
 */
#[Signature('app:wait-for-database {--timeout=60 : Seconds to keep trying} {--interval=2 : Seconds between attempts}')]
#[Description('Block until the default database connection accepts connections.')]
class WaitForDatabase extends Command
{
    public function handle(): int
    {
        $deadline = microtime(true) + (int) $this->option('timeout');
        $connection = config('database.default');

        while (true) {
            try {
                DB::connection()->getPdo();
                $this->info("Database connection [{$connection}] is ready.");

                return self::SUCCESS;
            } catch (Throwable $e) {
                if (microtime(true) >= $deadline) {
                    $this->error("Database connection [{$connection}] not ready: {$e->getMessage()}");

                    return self::FAILURE;
                }

                $this->line("Waiting for database [{$connection}]…");
                DB::purge($connection); // drop the failed connection so the next attempt reconnects
                sleep((int) $this->option('interval'));
            }
        }
    }
}
