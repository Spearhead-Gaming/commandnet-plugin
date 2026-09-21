<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use InvalidArgumentException;
use MajesticDev\CommandNet\Entity\Enum\FormFieldType;

/**
 * Parses the text a form is defined with. One field per line, parts separated by a pipe:
 *
 *     type | Label | required | option one, option two
 *
 * The type is one of text, textarea, number, boolean, date or select. The third part is
 * "required" or "optional" (or empty, meaning optional). The fourth is only for select fields
 * and lists the choices. Blank lines and lines starting with # are ignored.
 */
class FormSchema
{
    public const int MAX_FIELDS = 50;

    /**
     * @return array<FormFieldDefinition>
     * @throws InvalidArgumentException with a message naming the line that is wrong
     */
    public function parse(string $schema): array
    {
        $fields = [];
        foreach (preg_split('/\R/', $schema) ?: [] as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $fields[] = $this->parseLine($line, $index + 1, count($fields));
        }

        if ($fields === []) {
            throw new InvalidArgumentException('A form needs at least one field.');
        }
        if (count($fields) > self::MAX_FIELDS) {
            throw new InvalidArgumentException('A form can have at most ' . self::MAX_FIELDS . ' fields.');
        }

        return $fields;
    }

    private function parseLine(string $line, int $lineNumber, int $position): FormFieldDefinition
    {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) > 4) {
            throw new InvalidArgumentException("Line $lineNumber has too many parts. Use: type | Label | required | options");
        }

        $type = FormFieldType::tryFrom(strtolower($parts[0]));
        if ($type === null) {
            $known = implode(', ', array_map(static fn (FormFieldType $t): string => $t->value, FormFieldType::cases()));
            throw new InvalidArgumentException("Line $lineNumber: unknown field type \"{$parts[0]}\". Use one of: $known.");
        }

        $label = $parts[1] ?? '';
        if ($label === '') {
            throw new InvalidArgumentException("Line $lineNumber needs a label after the type.");
        }
        if (mb_strlen($label) > 255) {
            throw new InvalidArgumentException("Line $lineNumber: the label is too long.");
        }

        $flag = strtolower($parts[2] ?? '');
        if (!in_array($flag, ['', 'required', 'optional'], true)) {
            throw new InvalidArgumentException("Line $lineNumber: the third part must be \"required\" or \"optional\".");
        }

        $options = array_values(array_filter(
            array_map('trim', explode(',', $parts[3] ?? '')),
            static fn (string $option): bool => $option !== '',
        ));
        if ($type === FormFieldType::SELECT && $options === []) {
            throw new InvalidArgumentException("Line $lineNumber: a choice field needs options as the fourth part, separated by commas.");
        }
        if ($type !== FormFieldType::SELECT && $options !== []) {
            throw new InvalidArgumentException("Line $lineNumber: only choice fields take options.");
        }

        return new FormFieldDefinition('f' . $position . '_' . $this->slug($label), $type, $label, $flag === 'required', $options);
    }

    private function slug(string $label): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)) ?? '', '_');

        return substr($slug !== '' ? $slug : 'field', 0, 40);
    }
}
