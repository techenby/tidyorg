<?php

namespace App\Modules;

use RuntimeException;

class ModuleException extends RuntimeException
{
    public static function invalidKey(string $key): self
    {
        return new self("Module key [{$key}] must be lowercase letters, numbers, and dashes, starting with a letter.");
    }

    public static function duplicate(string $key): self
    {
        return new self("A module with the key [{$key}] is already registered.");
    }

    public static function invalidPermission(string $key, string $permission): self
    {
        return new self("Permission [{$permission}] must be prefixed with its module key, such as [{$key}:view].");
    }

    public static function missingDependency(string $key, string $dependency): self
    {
        return new self("Module [{$key}] requires module [{$dependency}], which is not active on this deployment.");
    }

    public static function circularDependency(string $key): self
    {
        return new self("Module [{$key}] is part of a circular dependency.");
    }

    public static function notActive(string $key): self
    {
        return new self(__('The :module module is not available.', ['module' => $key]));
    }

    public static function notEntitled(string $name): self
    {
        return new self(__('Your plan does not include the :module module.', ['module' => $name]));
    }

    public static function dependencyNotEnabled(string $name, string $dependency): self
    {
        return new self(__(':module needs :dependency to be enabled first.', ['module' => $name, 'dependency' => $dependency]));
    }

    public static function requiredBy(string $name, string $dependent): self
    {
        return new self(__(':module cannot be disabled while :dependent is enabled.', ['module' => $name, 'dependent' => $dependent]));
    }

    public static function invalidCapability(string $key, string $contract): self
    {
        return new self("Module [{$key}] offers [{$contract}] but its implementation does not implement it.");
    }
}
