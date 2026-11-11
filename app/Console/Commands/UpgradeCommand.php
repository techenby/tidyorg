<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

#[Signature('tessera:upgrade')]
#[Description('Finish an upgrade after the code is updated: migrate the database and rebuild the caches')]
class UpgradeCommand extends Command
{
    /**
     * The steps of an upgrade, in order, between going into and coming out of maintenance mode.
     *
     * @var array<string, array{0: string, 1: array<string, bool>}>
     */
    private const array STEPS = [
        'Updating the database' => ['migrate', ['--force' => true]],
        'Clearing old caches' => ['optimize:clear', []],
        'Rebuilding caches' => ['optimize', []],
        'Restarting queue workers' => ['queue:restart', []],
    ];

    /**
     * Execute the console command.
     *
     * Tessera stays in maintenance mode when a step fails, so a half-upgraded
     * install is never served.
     */
    public function handle(): int
    {
        Artisan::call('down', ['--retry' => 15]);

        foreach (self::STEPS as $description => [$command, $arguments]) {
            $failure = $this->runStep($description, $command, $arguments);

            if ($failure !== null) {
                $this->components->error("The upgrade stopped at [{$command}]. {$failure}");
                $this->components->warn('Tessera is still in maintenance mode. Fix the problem and run tessera:upgrade again, or run "php artisan up" to bring it back as it is.');

                return self::FAILURE;
            }
        }

        Artisan::call('up');

        $this->components->info('Tessera is upgraded and back online.');

        return self::SUCCESS;
    }

    /**
     * Run one step, returning what went wrong or null when it worked.
     *
     * @param  array<string, bool>  $arguments
     */
    private function runStep(string $description, string $command, array $arguments): ?string
    {
        $failure = null;

        $this->components->task($description, function () use ($command, $arguments, &$failure): bool {
            try {
                if (Artisan::call($command, $arguments) === self::SUCCESS) {
                    return true;
                }

                $failure = trim(Artisan::output()) ?: 'It did not finish.';
            } catch (Throwable $exception) {
                $failure = $exception->getMessage();
            }

            return false;
        });

        return $failure;
    }
}
