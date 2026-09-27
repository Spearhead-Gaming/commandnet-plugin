<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use Forumify\Core\Service\HttpClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MajesticDev\CommandNet\Service\AarImageException;
use MajesticDev\CommandNet\Service\AarImageStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AarImageStoreTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const string DISCORD = 'https://cdn.discordapp.com/attachments/1/2/map.png?ex=abc&is=def&hm=123';

    private string $directory;
    private Filesystem $storage;
    private MockHandler $responses;

    /** @var list<array<string, mixed>> */
    private array $clientConfigs = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/aar-images-' . uniqid();
        mkdir($this->directory);
        $this->storage = new Filesystem(new LocalFilesystemAdapter($this->directory));
        $this->responses = new MockHandler();
        $this->clientConfigs = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    private function store(): AarImageStore
    {
        $factory = $this->createStub(HttpClientFactory::class);
        $factory->method('getClient')->willReturnCallback(function (array $config = []): Client {
            $this->clientConfigs[] = $config;
            return new Client(['handler' => HandlerStack::create($this->responses)]);
        });

        return new AarImageStore($factory, $this->storage, $this->createStub(LoggerInterface::class));
    }

    /**
     * @return list<string> the files now in storage
     */
    private function stored(): array
    {
        return array_map('basename', glob($this->directory . '/*') ?: []);
    }

    private function png(): Response
    {
        return new Response(200, [], (string)base64_decode(self::PNG));
    }

    public function testStoresEachImageUnderANameThatSaysWhatItIs(): void
    {
        $this->responses->append($this->png(), $this->png());

        $paths = $this->store()->importFromDiscord([self::DISCORD, self::DISCORD], 'map', 12);

        self::assertCount(2, $paths);
        foreach ($paths as $path) {
            self::assertMatchesRegularExpression('/^aar-12-map-[0-9a-f]{13}\.png$/', $path);
            self::assertSame((string)base64_decode(self::PNG), $this->storage->read($path));
        }
        self::assertCount(2, $this->stored());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untrustedUrls(): iterable
    {
        yield 'another site' => ['https://evil.example/map.png'];
        yield 'plain http' => ['http://cdn.discordapp.com/attachments/1/2/map.png'];
        yield 'a look-alike host' => ['https://cdn.discordapp.com.evil.example/map.png'];
        yield 'credentials that make it look like Discord' => ['https://cdn.discordapp.com@evil.example/map.png'];
        yield 'an internal address' => ['https://127.0.0.1/map.png'];
        yield 'a different port' => ['https://cdn.discordapp.com:8443/map.png'];
        yield 'not a link' => ['not a url'];
    }

    #[DataProvider('untrustedUrls')]
    public function testOnlyDiscordsOwnHostsAreFetched(string $url): void
    {
        $this->responses->append($this->png());

        try {
            $this->store()->importFromDiscord([$url], 'map', 12);
            self::fail('The link should have been refused.');
        } catch (AarImageException) {
            self::assertCount(1, $this->responses, 'Nothing may be requested for an untrusted link.');
            self::assertSame([], $this->stored());
        }
    }

    public function testRedirectsAreNeverFollowed(): void
    {
        $this->responses->append($this->png());

        $this->store()->importFromDiscord([self::DISCORD], 'map', 12);

        self::assertFalse($this->clientConfigs[0]['allow_redirects'], 'A redirect could send the fetch to another host.');
    }

    public function testSomethingThatIsNotAPictureIsRefused(): void
    {
        $this->responses->append(new Response(200, [], '<html>not an image</html>'));

        $this->expectException(AarImageException::class);
        $this->expectExceptionMessage('not a picture');
        try {
            $this->store()->importFromDiscord([self::DISCORD], 'map', 12);
        } finally {
            self::assertSame([], $this->stored());
        }
    }

    public function testAnImageThatIsTooBigIsRefused(): void
    {
        // Starts like a PNG so it is the size, not the type, that is refused.
        $this->responses->append(new Response(200, [], (string)base64_decode(self::PNG) . str_repeat('a', 8 * 1024 * 1024)));

        $this->expectException(AarImageException::class);
        $this->expectExceptionMessage('larger than 8 MB');
        $this->store()->importFromDiscord([self::DISCORD], 'map', 12);
    }

    public function testAFailedDownloadIsRefused(): void
    {
        $this->responses->append(new Response(404));

        $this->expectException(AarImageException::class);
        $this->expectExceptionMessage('could not download');
        $this->store()->importFromDiscord([self::DISCORD], 'intel', 12);
    }

    public function testATooShortOrTooLongListIsRefusedWithoutFetching(): void
    {
        $this->responses->append($this->png());
        $store = $this->store();

        foreach ([[], array_fill(0, AarImageStore::MAX_IMAGES + 1, self::DISCORD)] as $urls) {
            try {
                $store->importFromDiscord($urls, 'map', 12);
                self::fail('The number of images should have been refused.');
            } catch (AarImageException $ex) {
                self::assertStringContainsString('between 1 and 5', $ex->getMessage());
            }
        }
        self::assertCount(1, $this->responses);
    }

    public function testWhenALaterImageFailsTheEarlierOnesAreNotLeftBehind(): void
    {
        $this->responses->append($this->png(), new Response(200, [], 'nope'));

        try {
            $this->store()->importFromDiscord([self::DISCORD, self::DISCORD], 'map', 12);
            self::fail('The second image should have been refused.');
        } catch (AarImageException) {
            self::assertSame([], $this->stored(), 'A half-imported set must not leave files behind.');
        }
    }

    public function testDeletingRemovesFilesAndToleratesOnesThatAreGone(): void
    {
        $this->storage->write('aar-12-map-a.png', 'x');
        $this->storage->write('aar-12-map-b.png', 'x');

        $this->store()->delete(['aar-12-map-a.png', 'already-gone.png', 'aar-12-map-b.png']);

        self::assertSame([], $this->stored());
    }
}
