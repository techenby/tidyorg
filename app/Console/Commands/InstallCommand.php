<?php

namespace App\Console\Commands;

use App\Actions\Modules\EnableModule;
use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Console\EnvironmentFile;
use App\Models\Team;
use App\Models\User;
use App\Modules\Module;
use App\Modules\ModuleRegistry;
use App\Rules\TeamName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

use function Illuminate\Support\artisan_binary;
use function Illuminate\Support\php_binary;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('tidyorg:install
    {--skip-env : Use the environment as it is instead of writing a .env file}
    {--name= : The first administrator\'s name}
    {--email= : The first administrator\'s email address}
    {--password= : The first administrator\'s password}
    {--organization= : The name of the organization}')]
#[Description('Set up a new Tidy Org install: environment, database, and the first administrator')]
class InstallCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * PHP extensions Tidy Org cannot run without.
     *
     * @var list<string>
     */
    private const array REQUIRED_EXTENSIONS = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo', 'session', 'tokenizer', 'xml'];

    /**
     * PHP extensions that improve Tidy Org, with what each is for.
     *
     * @var array<string, string>
     */
    private const array RECOMMENDED_EXTENSIONS = [
        'gd' => 'smaller copies of uploaded photos',
        'exif' => 'turning phone photos the right way up',
    ];

    /**
     * Execute the console command.
     */
    public function handle(ModuleRegistry $modules, CreateTeam $createTeam, EnableModule $enableModule): int
    {
        if (! $this->meetsRequirements()) {
            return self::FAILURE;
        }

        $environment = new EnvironmentFile($this->laravel->environmentFilePath());

        if ($this->shouldWriteEnvironment($environment)) {
            return $this->continueInFreshProcess($this->writeEnvironment($environment, $modules));
        }

        $this->components->task('Preparing the database', fn () => $this->callSilently('migrate', ['--force' => true]) === self::SUCCESS);

        if (User::query()->exists()) {
            $this->components->info('This install already has an account, so no administrator was created.');

            return self::SUCCESS;
        }

        $answers = $this->administratorAnswers();

        if ($answers === null) {
            return self::FAILURE;
        }

        $team = DB::transaction(function () use ($answers, $createTeam) {
            $user = User::create([
                'name' => $answers['name'],
                'email' => $answers['email'],
                'password' => $answers['password'],
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            return $createTeam->handle($user, $answers['organization']);
        });

        if (! config('tidyorg.single_organization')) {
            $this->enableModules($modules, $enableModule, $team);
        }

        $this->components->info('Tidy Org is ready. Sign in at '.config('app.url')." as {$answers['email']}.");

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->components->warn('Outgoing mail is not set up, so invitations and password resets will not be delivered. See docs/self-hosting/configuration.md.');
        }

        return self::SUCCESS;
    }

    /**
     * Check the PHP extensions and writable directories, reporting everything that is missing.
     */
    private function meetsRequirements(): bool
    {
        $problems = collect(self::REQUIRED_EXTENSIONS)
            ->reject(fn (string $extension): bool => extension_loaded($extension))
            ->map(fn (string $extension): string => "The PHP extension [{$extension}] is not installed.")
            ->merge(collect([storage_path(), $this->laravel->bootstrapPath('cache')])
                ->reject(fn (string $path): bool => is_writable($path))
                ->map(fn (string $path): string => "The directory [{$path}] is not writable."));

        $problems->each(fn (string $problem) => $this->components->error($problem));

        foreach (self::RECOMMENDED_EXTENSIONS as $extension => $purpose) {
            if (! extension_loaded($extension)) {
                $this->components->warn("The PHP extension [{$extension}] is not installed. Tidy Org works without it, but it is used for {$purpose}.");
            }
        }

        return $problems->isEmpty();
    }

    /**
     * A .env file is written unless the settings clearly come from somewhere else, such as a container's environment.
     */
    private function shouldWriteEnvironment(EnvironmentFile $environment): bool
    {
        if ($this->option('skip-env')) {
            return false;
        }

        if (! $environment->exists()) {
            return blank(config('app.key'));
        }

        if (blank($environment->get('APP_KEY'))) {
            return true;
        }

        return $this->input->isInteractive()
            && confirm('This install already has a .env file. Do you want to change its settings?', default: false);
    }

    /**
     * Ask for the settings and write them to .env.
     *
     * @return array<string, string|bool|int|null>
     */
    private function writeEnvironment(EnvironmentFile $environment, ModuleRegistry $modules): array
    {
        $environment->createFrom(base_path('.env.example'));

        $values = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => rtrim(text(
                label: 'What address will people use to open Tidy Org?',
                default: $environment->get('APP_URL') ?? 'http://localhost',
                validate: ['address' => ['required', 'url']],
            ), '/'),
            ...$this->databaseAnswers(),
            'TIDYORG_SINGLE_ORGANIZATION' => confirm(
                label: 'Will this install be used by one organization?',
                hint: 'With one organization, people join by invitation only and every module is switched on for it.',
            ),
            'TIDYORG_MODULES' => $this->moduleAnswer($modules),
        ];

        if (blank($environment->get('APP_KEY'))) {
            $values['APP_KEY'] = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
        }

        $environment->set($values);

        $this->components->info('Settings saved to .env.');

        return $values;
    }

    /**
     * Ask which database to use, repeating until Tidy Org can connect to it.
     *
     * @return array<string, string|int|null>
     */
    private function databaseAnswers(): array
    {
        $driver = select(
            label: 'Which database should Tidy Org use?',
            options: [
                'sqlite' => 'SQLite (a file on this server, nothing else to install)',
                'pgsql' => 'PostgreSQL',
                'mysql' => 'MySQL',
                'mariadb' => 'MariaDB',
            ],
            default: 'sqlite',
        );

        if ($driver === 'sqlite') {
            $path = database_path('database.sqlite');

            if (! File::exists($path)) {
                File::put($path, '');
            }

            return ['DB_CONNECTION' => 'sqlite'];
        }

        while (true) {
            $values = [
                'DB_CONNECTION' => $driver,
                'DB_HOST' => text('Database host', default: '127.0.0.1', required: true),
                'DB_PORT' => text('Database port', default: $driver === 'pgsql' ? '5432' : '3306', required: true),
                'DB_DATABASE' => text('Database name', default: 'tidyorg', required: true),
                'DB_USERNAME' => text('Database user', required: true),
                'DB_PASSWORD' => password('Database password'),
            ];

            $error = $this->databaseError($values);

            if ($error === null) {
                return $values;
            }

            $this->components->error("Tidy Org could not connect to that database: {$error}");
        }
    }

    /**
     * Try the database settings, returning what went wrong or null when they work.
     *
     * @param  array<string, string|int|null>  $values
     */
    private function databaseError(array $values): ?string
    {
        config(['database.connections.tidyorg_install' => [
            ...config("database.connections.{$values['DB_CONNECTION']}"),
            'host' => $values['DB_HOST'],
            'port' => $values['DB_PORT'],
            'database' => $values['DB_DATABASE'],
            'username' => $values['DB_USERNAME'],
            'password' => $values['DB_PASSWORD'],
        ]]);

        try {
            DB::connection('tidyorg_install')->getPdo();

            return null;
        } catch (Throwable $exception) {
            return $exception->getMessage();
        } finally {
            DB::purge('tidyorg_install');
        }
    }

    /**
     * Ask which installed modules this install should offer.
     */
    private function moduleAnswer(ModuleRegistry $modules): string
    {
        $installed = $modules->installed();

        if ($installed->isEmpty()) {
            return '*';
        }

        $chosen = multiselect(
            label: 'Which modules do you want to use?',
            options: $installed->map(fn (Module $module): string => $module->name())->all(),
            default: $installed->keys()->all(),
            hint: 'You can change this later with TIDYORG_MODULES in .env.',
        );

        return count($chosen) === $installed->count() ? '*' : implode(',', $chosen);
    }

    /**
     * Run the rest of the install in a new process, because this one started with the old settings.
     *
     * @param  array<string, string|bool|int|null>  $values
     */
    private function continueInFreshProcess(array $values): int
    {
        $command = [php_binary(), artisan_binary(), 'tidyorg:install', '--skip-env'];

        foreach (['name', 'email', 'password', 'organization'] as $option) {
            if ($this->option($option) !== null) {
                $command[] = "--{$option}={$this->option($option)}";
            }
        }

        if (! $this->input->isInteractive()) {
            $command[] = '--no-interaction';
        }

        $environment = collect($values)
            ->map(fn (string|bool|int|null $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
            ->all();

        $process = Process::forever()->path(base_path())->env($environment);

        if ($this->input->isInteractive() && SymfonyProcess::isTtySupported()) {
            return $process->tty()->run($command)->exitCode() ?? self::FAILURE;
        }

        return $process->run($command, fn (string $type, string $output) => $this->output->write($output))->exitCode() ?? self::FAILURE;
    }

    /**
     * The first administrator's details, from the options or by asking.
     *
     * @return array{name: string, email: string, password: string, organization: string}|null
     */
    private function administratorAnswers(): ?array
    {
        $rules = [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => ['required', 'string', Password::default()],
            'organization' => ['required', 'string', 'max:255', new TeamName],
        ];

        $labels = [
            'name' => 'Your name',
            'email' => 'Your email address',
            'password' => 'Choose a password',
            'organization' => 'The name of your organization',
        ];

        $answers = [];

        foreach ($rules as $field => $fieldRules) {
            $answer = $this->option($field);

            if (! is_string($answer) && ! $this->input->isInteractive()) {
                $this->components->error('Pass --name, --email, --password, and --organization when running without interaction.');

                return null;
            }

            $answers[$field] = match (true) {
                is_string($answer) => $answer,
                $field === 'password' => password(label: $labels[$field], validate: [$field => $fieldRules]),
                default => text(label: $labels[$field], validate: [$field => $fieldRules]),
            };
        }

        $validator = Validator::make($answers, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return null;
        }

        return [
            'name' => $answers['name'],
            'email' => $answers['email'],
            'password' => $answers['password'],
            'organization' => $answers['organization'],
        ];
    }

    /**
     * Switch every active module on for the first organization, dependencies first.
     */
    private function enableModules(ModuleRegistry $modules, EnableModule $enableModule, Team $team): void
    {
        $pending = $modules->active()->keys()->all();

        while ($pending !== []) {
            $ready = array_filter($pending, fn (string $key): bool => array_intersect($modules->find($key)?->dependencies() ?? [], $pending) === []);

            if ($ready === []) {
                return;
            }

            foreach ($ready as $key) {
                $enableModule->handle($team, $key);
            }

            $pending = array_values(array_diff($pending, $ready));
        }
    }
}
