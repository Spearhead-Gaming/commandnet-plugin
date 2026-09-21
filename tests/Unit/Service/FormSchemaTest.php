<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use InvalidArgumentException;
use MajesticDev\CommandNet\Entity\Enum\FormFieldType;
use MajesticDev\CommandNet\Service\FormSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormSchemaTest extends TestCase
{
    public function testParsesEveryFieldType(): void
    {
        $fields = (new FormSchema())->parse(implode("\n", [
            'text | Full name | required',
            'textarea | Why do you want this?',
            'number | Age | optional',
            'boolean | I agree to the rules | required',
            'date | Start date',
            'select | Branch | required | Army, Navy, Air Force',
        ]));

        $this->assertSame(
            [FormFieldType::TEXT, FormFieldType::TEXTAREA, FormFieldType::NUMBER, FormFieldType::BOOLEAN, FormFieldType::DATE, FormFieldType::SELECT],
            array_map(static fn ($f) => $f->type, $fields),
        );
        $this->assertSame([true, false, false, true, false, true], array_map(static fn ($f) => $f->required, $fields));
        $this->assertSame('Full name', $fields[0]->label);
        $this->assertSame(['Army', 'Navy', 'Air Force'], $fields[5]->options);
        $this->assertSame([], $fields[0]->options);
    }

    public function testKeysAreUniqueEvenForRepeatedLabels(): void
    {
        $fields = (new FormSchema())->parse("text | Name\ntext | Name\ntext | ???");

        $keys = array_map(static fn ($f) => $f->key, $fields);
        $this->assertSame($keys, array_unique($keys));
        $this->assertSame('f0_name', $keys[0]);
        $this->assertSame('f2_field', $keys[2]);
    }

    public function testBlankLinesCommentsAndTypeCaseAreIgnored(): void
    {
        $fields = (new FormSchema())->parse("# heading\n\nTEXT | Name | REQUIRED\n   \n");

        $this->assertCount(1, $fields);
        $this->assertTrue($fields[0]->required);
    }

    #[DataProvider('invalidSchemas')]
    public function testRejectsInvalidLinesAndNamesTheLine(string $schema, string $expectedMessagePart): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        (new FormSchema())->parse($schema);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidSchemas(): array
    {
        return [
            'empty' => ['', 'at least one field'],
            'only comments' => ["# nothing here\n", 'at least one field'],
            'unknown type' => ["text | Name\ncolour | Favourite", 'Line 2: unknown field type'],
            'no label' => ["text | Name\ntext", 'Line 2 needs a label'],
            'bad flag' => ['text | Name | mandatory', 'Line 1: the third part'],
            'select without options' => ['select | Branch | required', 'Line 1: a choice field needs options'],
            'options on a text field' => ['text | Name | required | a, b', 'only choice fields take options'],
            'too many parts' => ['text | Name | required | | extra', 'too many parts'],
        ];
    }

    public function testRejectsMoreThanTheMaximumNumberOfFields(): void
    {
        $lines = array_fill(0, FormSchema::MAX_FIELDS + 1, 'text | Question');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at most');

        (new FormSchema())->parse(implode("\n", $lines));
    }
}
