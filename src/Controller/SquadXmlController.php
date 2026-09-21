<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use League\Flysystem\FilesystemOperator;
use MajesticDev\CommandNet\Service\SquadXmlGenerator;
use MajesticDev\CommandNet\Service\SquadXmlSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

/**
 * The squad.xml, squad.dtd and logo.paa files Arma fetches from a unit web address. Public by
 * design, and switched off until enabled in the admin since it lists members and their Steam IDs.
 */
class SquadXmlController extends AbstractController
{
    public const string CACHE_KEY = 'command_net.squadxml';
    private const int CACHE_SECONDS = 900;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SquadXmlGenerator $generator,
        private readonly SquadXmlSettings $settings,
        private readonly FilesystemOperator $assetStorage,
    ) {
    }

    #[Route('/squad.xml', name: 'squad_xml')]
    public function xml(): Response
    {
        $this->assertEnabled();

        $xml = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): string {
            $item->expiresAfter(self::CACHE_SECONDS);

            return $this->generator->generate();
        });

        return new Response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    #[Route('/squad.dtd', name: 'squad_dtd')]
    public function dtd(): Response
    {
        return new Response(implode("\n", [
            '<!ELEMENT squad (name, email, web?, picture?, title?, member+)>',
            '<!ATTLIST squad nick CDATA #REQUIRED>',
            '<!ELEMENT member (name, email, icq?, remark?)>',
            '<!ATTLIST member id CDATA #REQUIRED nick CDATA #REQUIRED>',
            '<!ELEMENT name (#PCDATA)>',
            '<!ELEMENT email (#PCDATA)>',
            '<!ELEMENT icq (#PCDATA)>',
            '<!ELEMENT web (#PCDATA)>',
            '<!ELEMENT picture (#PCDATA)>',
            '<!ELEMENT title (#PCDATA)>',
            '<!ELEMENT remark (#PCDATA)>',
        ]) . "\n", 200, ['Content-Type' => 'text/plain']);
    }

    #[Route('/logo.paa', name: 'squad_logo')]
    public function logo(): Response
    {
        $this->assertEnabled();

        $picture = $this->settings->all()['picture'];
        if ($picture === null || $picture === '') {
            throw $this->createNotFoundException();
        }

        try {
            $file = $this->assetStorage->read($picture);
        } catch (Throwable) {
            throw $this->createNotFoundException();
        }

        return new Response($file, 200, ['Content-Type' => 'application/octet-stream']);
    }

    private function assertEnabled(): void
    {
        if (!$this->settings->all()['enabled']) {
            throw $this->createNotFoundException();
        }
    }
}
