<?php

use App\Actions\Modules\DisableModule;
use App\Actions\Modules\EnableModule;
use App\Models\Team;
use App\Models\User;
use App\Modules\Contracts\ModuleEntitlements;
use App\Modules\Facades\Modules;
use App\Modules\Module;
use App\Modules\ModuleException;
use App\Modules\ModuleRegistry;
use Tests\Fixtures\Modules\Dependent\DependentModule;
use Tests\Fixtures\Modules\Example\ExampleModule;

test('a module can be enabled and disabled for an organization', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;

    app(EnableModule::class)->handle($team, 'example');

    expect(Modules::enabled('example', $team))->toBeTrue();

    app(DisableModule::class)->handle($team, 'example');

    expect(Modules::enabled('example', $team))->toBeFalse();
});

test('a module that is not active cannot be enabled', function () {
    installModules(active: '');

    $team = User::factory()->create()->currentTeam;

    expect(fn () => app(EnableModule::class)->handle($team, 'example'))->toThrow(ModuleException::class);

    expect($team->modules()->count())->toBe(0);
});

test('a module cannot be enabled before the modules it depends on', function () {
    installModules([ExampleModule::class, DependentModule::class]);

    $team = User::factory()->create()->currentTeam;

    expect(fn () => app(EnableModule::class)->handle($team, 'dependent'))->toThrow(ModuleException::class);

    app(EnableModule::class)->handle($team, 'example');
    app(EnableModule::class)->handle($team, 'dependent');

    expect(Modules::enabledFor($team)->keys()->all())->toBe(['example', 'dependent']);
});

test('a module cannot be disabled while an enabled module depends on it', function () {
    installModules([ExampleModule::class, DependentModule::class]);

    $team = User::factory()->create()->currentTeam;
    $team->modules()->createMany([['module' => 'example'], ['module' => 'dependent']]);

    expect(fn () => app(DisableModule::class)->handle($team, 'example'))->toThrow(ModuleException::class);

    expect(Modules::enabled('example', $team))->toBeTrue();
});

test('a module cannot be enabled when the organization is not entitled to it', function () {
    installModules();

    app()->bind(ModuleEntitlements::class, fn () => new class implements ModuleEntitlements
    {
        public function allows(Team $team, Module $module): bool
        {
            return false;
        }
    });

    $team = User::factory()->create()->currentTeam;

    expect(fn () => app(EnableModule::class)->handle($team, 'example'))->toThrow(ModuleException::class);

    expect(Modules::enabled('example', $team))->toBeFalse();
});

test('the deployment refuses to boot with an active module whose dependency is not active', function () {
    installModules([ExampleModule::class, DependentModule::class], active: 'dependent');

    app(ModuleRegistry::class)->validate();
})->throws(ModuleException::class);

test('the deployment refuses to boot with circular module dependencies', function () {
    $registry = app(ModuleRegistry::class);

    foreach (['first' => 'second', 'second' => 'first'] as $key => $dependency) {
        $registry->add(new class(app(), $key, $dependency) extends Module
        {
            public function __construct($app, private string $moduleKey, private string $dependency)
            {
                parent::__construct($app);
            }

            public function key(): string
            {
                return $this->moduleKey;
            }

            public function name(): string
            {
                return $this->moduleKey;
            }

            public function path(): string
            {
                return __DIR__;
            }

            public function dependencies(): array
            {
                return [$this->dependency];
            }
        });
    }

    $registry->validate();
})->throws(ModuleException::class);

test('two modules cannot share a key', function () {
    installModules();

    app(ModuleRegistry::class)->add(new ExampleModule(app()));
})->throws(ModuleException::class);
