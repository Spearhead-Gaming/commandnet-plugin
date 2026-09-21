<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity\Enum;

enum FormFieldType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case SELECT = 'select';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Short text',
            self::TEXTAREA => 'Long text',
            self::NUMBER => 'Number',
            self::BOOLEAN => 'Yes / no',
            self::DATE => 'Date',
            self::SELECT => 'Choice',
        };
    }
}
