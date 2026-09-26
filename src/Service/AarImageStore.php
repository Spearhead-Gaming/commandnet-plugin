<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Forumify\Core\Service\HttpClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Where an AAR's map and intel images live. Images attached on the website are stored by the
 * upload field; this brings in the ones attached in Discord, and deletes both kinds when a report
 * or patrol is removed.
 *
 * A Discord attachment arrives as a link the bot passes along, which forumify then fetches, so the
 * link is not trusted: only https on Discord's own CDN hosts is fetched (no redirects, so it cannot
 * be bounced elsewhere), and what comes back must really be a small JPEG, PNG, GIF or WebP.
 */
class AarImageStore
{
    public const int MAX_IMAGES = 5;
    private const int MAX_BYTES = 8 * 1024 * 1024;

    /** Discord's attachment hosts. */
    private const array HOSTS = ['cdn.discordapp.com', 'media.discordapp.net'];

    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly HttpClientFactory $httpClientFactory,
        private readonly FilesystemOperator $assetStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string> $urls Discord attachment links
     * @param string $label what the images are ("map" or "intel"), for the file names and messages
     * @return list<string> the paths the images were stored under
     *
     * @throws AarImageException when there are too few or too many, or one cannot be used - in
     *                           which case nothing is left stored
     */
    public function importFromDiscord(array $urls, string $label, int $patrolId): array
    {
        $count = count($urls);
        if ($count < 1 || $count > self::MAX_IMAGES) {
            throw new AarImageException(sprintf('Attach between 1 and %d %s images.', self::MAX_IMAGES, $label));
        }

        $stored = [];
        try {
            foreach ($urls as $url) {
                [$bytes, $extension] = $this->download($url, $label);
                $path = uniqid("aar-$patrolId-$label-") . '.' . $extension;
                try {
                    $this->assetStorage->write($path, $bytes);
                } catch (FilesystemException $ex) {
                    throw new AarImageException("We could not save your $label image. Please try again.", previous: $ex);
                }
                $stored[] = $path;
            }
        } catch (AarImageException $ex) {
            $this->delete($stored);
            throw $ex;
        }

        return $stored;
    }

    /**
     * Deletes stored images. A file that is already gone, or cannot be deleted, is logged and
     * skipped: removing a report must not fail because of a leftover file.
     *
     * @param array<string> $paths
     */
    public function delete(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->assetStorage->delete($path);
            } catch (FilesystemException $ex) {
                $this->logger->warning('Could not delete an AAR image.', ['path' => $path, 'exception' => $ex]);
            }
        }
    }

    /**
     * @return array{string, string} the image's bytes and file extension
     */
    private function download(string $url, string $label): array
    {
        $this->assertDiscordUrl($url, $label);

        try {
            $response = $this->httpClientFactory
                ->getClient(['timeout' => 15, 'connect_timeout' => 5, 'allow_redirects' => false, 'http_errors' => false])
                ->get($url, ['stream' => true]);
        } catch (GuzzleException $ex) {
            throw new AarImageException("We could not download your $label image. Please try again.", previous: $ex);
        }
        if ($response->getStatusCode() !== 200) {
            throw new AarImageException("We could not download your $label image. Please try again.");
        }

        $bytes = $this->readLimited($response->getBody(), $label);
        $info = @getimagesizefromstring($bytes);
        $mime = $info === false ? null : $info['mime'];
        if ($mime === null || !isset(self::EXTENSIONS[$mime])) {
            throw new AarImageException("Your $label attachment is not a picture. Use a JPEG, PNG, GIF or WebP image.");
        }

        return [$bytes, self::EXTENSIONS[$mime]];
    }

    private function assertDiscordUrl(string $url, string $label): void
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';
        $valid = is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && in_array($host, self::HOSTS, true)
            && !isset($parts['user'], $parts['pass'])
            && (!isset($parts['port']) || $parts['port'] === 443);

        if (!$valid) {
            throw new AarImageException("Your $label image did not come from Discord, so it was not used.");
        }
    }

    private function readLimited(StreamInterface $stream, string $label): string
    {
        $bytes = '';
        while (!$stream->eof() && strlen($bytes) <= self::MAX_BYTES) {
            $bytes .= $stream->read(65536);
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new AarImageException(sprintf('Your %s image is larger than %d MB.', $label, self::MAX_BYTES / 1024 / 1024));
        }

        return $bytes;
    }
}
