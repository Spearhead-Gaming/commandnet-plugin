<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Service\DocumentRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A service record's document on its own, as a plain page meant for the browser's print / save as
 * PDF. Same audience as the personnel file it belongs to (roster.view).
 */
class ServiceRecordDocumentController extends AbstractController
{
    public function __construct(
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly DocumentRenderer $documentRenderer,
    ) {
    }

    #[Route('/roster/{username}/service-record/{id}/document', name: 'roster_service_record_document', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(string $username, int $id): Response
    {
        $this->denyAccessUnlessGranted('command-net.roster.view');

        $record = $this->serviceRecordRepository->find($id);
        $document = $record?->getDocument();
        // The username in the URL must be the record's own soldier, so a record id can't be read
        // out from under someone else's file.
        if ($record === null || $document === null || $record->getSoldier()->getUser()->getUsername() !== $username) {
            throw $this->createNotFoundException();
        }

        return $this->render('@CommandNetPlugin/frontend/personnel/record_document.html.twig', [
            'record' => $record,
            'document' => $document,
            'content' => $this->documentRenderer->render($document, $record),
        ]);
    }
}
