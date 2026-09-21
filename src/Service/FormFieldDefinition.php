<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Enum\FormFieldType;

/**
 * One parsed line of a form field list. The key is only used to name the field while the
 * form is being filled in; submissions keep the label, not the key.
 */
final class FormFieldDefinition
{
    /**
     * @param array<string> $options the choices of a choice field, empty for every other type
     */
    public function __construct(
        public readonly string $key,
        public readonly FormFieldType $type,
        public readonly string $label,
        public readonly bool $required,
        public readonly array $options = [],
    ) {
    }
}
