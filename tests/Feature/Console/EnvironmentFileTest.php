<?php

use App\Console\EnvironmentFile;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = storage_path('framework/testing/env-'.uniqid());

    File::ensureDirectoryExists(dirname($this->path));
});

afterEach(function () {
    File::delete($this->path);
});

test('a missing file is created from the template', function () {
    $file = new EnvironmentFile($this->path);

    expect($file->exists())->toBeFalse()
        ->and($file->get('APP_NAME'))->toBeNull();

    $file->createFrom(base_path('.env.example'));

    expect($file->exists())->toBeTrue()
        ->and($file->get('APP_NAME'))->toBe('Tidy Org');
});

test('an existing file is not replaced by the template', function () {
    File::put($this->path, "APP_NAME=Mine\n");

    $file = new EnvironmentFile($this->path);
    $file->createFrom(base_path('.env.example'));

    expect(File::get($this->path))->toBe("APP_NAME=Mine\n");
});

test('setting a variable replaces its line, uncomments it, or appends it', function () {
    File::put($this->path, "APP_NAME=TidyOrg\n\n# The database\nDB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\n");

    (new EnvironmentFile($this->path))->set([
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => 'db.internal',
        'TIDYORG_SINGLE_ORGANIZATION' => true,
    ]);

    expect(File::get($this->path))->toBe(
        "APP_NAME=TidyOrg\n\n# The database\nDB_CONNECTION=pgsql\nDB_HOST=db.internal\nTIDYORG_SINGLE_ORGANIZATION=true\n"
    );
});

test('a variable that only starts with the same name is left alone', function () {
    File::put($this->path, "DB_HOST_READ=replica\n");

    $file = new EnvironmentFile($this->path);
    $file->set(['DB_HOST' => 'primary']);

    expect($file->get('DB_HOST_READ'))->toBe('replica')
        ->and($file->get('DB_HOST'))->toBe('primary');
});

test('values read back exactly as they were given', function (string $value) {
    (new EnvironmentFile($this->path))->set(['DB_PASSWORD' => $value]);

    expect(Dotenv::parse(File::get($this->path))['DB_PASSWORD'])->toBe($value);
})->with([
    'plain' => ['secret'],
    'empty' => [''],
    'spaces' => ['correct horse battery'],
    'hash' => ['pa#ss'],
    'dollar' => ['pa$$word${HOME}'],
    'double quote' => ['say "hi"'],
    'single quote' => ["it's"],
    'both quotes and a backslash' => ['it\'s "a" \\ $mix'],
    'url' => ['https://tidyorg.example.org'],
    'key' => ['base64:abc+/=='],
]);
