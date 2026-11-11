<?php

namespace TidyOrg\Inventory\Enums;

enum ItemType: string
{
    case Location = 'location';
    case Bin = 'bin';
    case Item = 'item';

    /**
     * The untranslated label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Location => 'Location',
            self::Bin => 'Bin',
            self::Item => 'Item',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Location => 'map-pin',
            self::Bin => 'archive-box',
            self::Item => 'cube',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Location => 'red',
            self::Bin => 'orange',
            self::Item => 'amber',
        };
    }
}
