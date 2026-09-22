<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Used by the container entrypoint on every start: seeds demo data on a
 * brand-new database only, so a plain restart never duplicates or resets it.
 */
#[Signature('app:seed-if-empty')]
#[Description('Run the database seeders only if the database has no users yet.')]
class SeedIfEmpty extends Command
{
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('Database already has users; skipping seed.');

            return self::SUCCESS;
        }

        $this->info('Empty database; seeding demo data.');

        return $this->call('db:seed', ['--force' => true]);
    }
}
