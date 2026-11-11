<?php

namespace App\Console;

use Illuminate\Support\Facades\File;

/**
 * Reads and updates the variables in a .env file, leaving its other lines untouched.
 */
class EnvironmentFile
{
    public function __construct(private string $path) {}

    public function exists(): bool
    {
        return File::exists($this->path);
    }

    /**
     * Start the file from a template when it does not exist yet.
     */
    public function createFrom(string $template): void
    {
        if (! $this->exists()) {
            File::copy($template, $this->path);
        }
    }

    /**
     * The raw value of a variable that is set and not commented out.
     */
    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        if (! preg_match($this->pattern($key), File::get($this->path), $matches)) {
            return null;
        }

        return trim($matches[1], " \t\"'");
    }

    /**
     * Set variables, replacing a line that already sets one (or has it commented out) and appending the rest.
     *
     * @param  array<string, string|bool|int|null>  $values
     */
    public function set(array $values): void
    {
        $contents = $this->exists() ? File::get($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);

            $pattern = collect([$this->pattern($key), $this->pattern($key, commented: true)])
                ->first(fn (string $pattern): bool => preg_match($pattern, $contents) === 1);

            $contents = $pattern === null
                ? rtrim($contents)."\n".$line."\n"
                : (string) preg_replace_callback($pattern, fn (): string => $line, $contents, 1);
        }

        File::put($this->path, ltrim($contents));
    }

    private function pattern(string $key, bool $commented = false): string
    {
        $prefix = $commented ? '#[ \t]*' : '';

        return '/^'.$prefix.preg_quote($key, '/').'=(.*)$/m';
    }

    /**
     * Quote a value so the file reads back exactly what was given.
     */
    private function format(string|bool|int|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=*,-]+$/', $value) === 1) {
            return $value;
        }

        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }
}
