<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use MajesticDev\CommandNet\Service\DtgFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DtgFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function moments(): iterable
    {
        yield 'evening' => ['2026-09-26 19:00:00 UTC', '261900ZSEP26'];
        yield 'single digit day is padded' => ['2026-01-05 06:07:00 UTC', '050607ZJAN26'];
        yield 'midnight' => ['2026-12-31 00:00:00 UTC', '310000ZDEC26'];
        yield 'a year later' => ['2027-03-14 12:30:00 UTC', '141230ZMAR27'];
    }

    #[DataProvider('moments')]
    public function testFormatsTheDateTimeGroupOfTheTemplate(string $moment, string $expected): void
    {
        self::assertSame($expected, DtgFormatter::format(new DateTimeImmutable($moment)));
    }

    public function testIsAlwaysInUtcWhateverTheTimezoneOfTheDate(): void
    {
        // 20:00 in New York in September is 00:00 UTC the next day.
        $eastern = new DateTime('2026-09-26 20:00:00', new DateTimeZone('America/New_York'));

        self::assertSame('270000ZSEP26', DtgFormatter::format($eastern));
    }
}
