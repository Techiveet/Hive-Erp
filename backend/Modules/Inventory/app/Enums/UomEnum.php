<?php

namespace Modules\Inventory\Enums;

enum UomEnum: string
{
    case PIECE = 'piece';
    case UNIT = 'unit';
    case BOTTLE = 'bottle';
    case CAN = 'can';
    case PACK = 'pack';
    case BOX = 'box';
    case CARTON = 'carton';
    case CRATE = 'crate';
    case DOZEN = 'dozen';
    case KILOGRAM = 'kg';
    case GRAM = 'g';
    case LITER = 'l';
    case MILLILITER = 'ml';
    case METER = 'm';
    case CENTIMETER = 'cm';

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}

