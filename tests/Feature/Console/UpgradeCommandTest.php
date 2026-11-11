<?php

use App\Console\Commands\UpgradeCommand;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Run the command directly, so the Artisan calls it makes can be replaced.
 *
 * @return array{0: int, 1: string}
 */
function runUpgrade(): array
{
    $command = app(UpgradeCommand::class);
    $command->setLaravel(app());

    $output = new BufferedOutput;

    return [$command->run(new ArrayInput([]), $output), $output->fetch()];
}

test('an upgrade migrates and rebuilds the caches inside maintenance mode', function () {
    $calls = [];

    Artisan::shouldReceive('call')->andReturnUsing(function (string $command, array $arguments = []) use (&$calls) {
        $calls[] = [$command, $arguments];

        return 0;
    });

    [$status, $output] = runUpgrade();

    expect($status)->toBe(0)
        ->and($calls)->toBe([
            ['down', ['--retry' => 15]],
            ['migrate', ['--force' => true]],
            ['optimize:clear', []],
            ['optimize', []],
            ['queue:restart', []],
            ['up', []],
        ])
        ->and($output)->toContain('back online');
});

test('a failed step stops the upgrade and leaves maintenance mode on', function (Closure $migrate) {
    $calls = [];

    Artisan::shouldReceive('output')->andReturn('');
    Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$calls, $migrate) {
        $calls[] = $command;

        return $command === 'migrate' ? $migrate() : 0;
    });

    [$status, $output] = runUpgrade();

    expect($status)->toBe(1)
        ->and($calls)->toBe(['down', 'migrate'])
        ->and($output)->toContain('stopped at [migrate]')
        ->and($output)->toContain('still in maintenance mode');
})->with([
    'a failing exit code' => [fn () => 1],
    'an exception' => [fn () => throw new RuntimeException('The database is not reachable.')],
]);
