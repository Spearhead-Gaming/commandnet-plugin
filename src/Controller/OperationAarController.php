<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Form\OperationAarType;
use MajesticDev\CommandNet\Repository\OperationAARRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;

class OperationAarController extends AbstractController
{
    public function __construct(
        private readonly OperationAARRepository $aarRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
    ) {
    }

    #[Route('/operations/{id}/aar', name: 'operation_aar', requirements: ['id' => '\d+'])]
    public function __invoke(Operation $operation, Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.operations.submit_aar');

        /** @var User $user */
        $user = $this->getUser();
        $aar = new OperationAAR($operation, $user);

        $form = $this->createForm(OperationAarType::class, $aar);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->submit($aar, $operation);
        }

        return $this->render('@CommandNetPlugin/frontend/operations/aar_form.html.twig', [
            'operation' => $operation,
            'form' => $form,
        ]);
    }

    private function submit(OperationAAR $aar, Operation $operation): RedirectResponse
    {
        $this->aarRepository->save($aar);

        // Writing a combat record for everyone actually marked as attended - not just
        // everyone who RSVP'd - keeps this in sync with reality rather than intent.
        // This is the concrete example of the "everything feeds the timeline" design:
        // the Operations module doesn't render its own history anywhere, it just writes
        // into ServiceRecord and lets the personnel file show it.
        foreach ($operation->getRsvps() as $rsvp) {
            if ($rsvp->getAttended() !== true) {
                continue;
            }

            $record = new ServiceRecord(
                $rsvp->getSoldier(),
                ServiceRecordType::COMBAT,
                $operation->getTitle(),
            );
            $record->setDate($operation->getStartDateTime());
            $this->serviceRecordRepository->save($record, false);
        }
        $this->serviceRecordRepository->flush();

        $this->addFlash('success', 'After-action report submitted.');
        return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
    }
}
