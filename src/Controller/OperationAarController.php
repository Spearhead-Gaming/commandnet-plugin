<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Form\OperationAarType;
use MajesticDev\CommandNet\Repository\OperationAARRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Service\EventRules;
use MajesticDev\CommandNet\Service\OperationAttendanceService;

class OperationAarController extends AbstractController
{
    public function __construct(
        private readonly OperationAARRepository $aarRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
        private readonly OperationAttendanceService $attendanceService,
        private readonly EventRules $eventRules,
    ) {
    }

    #[Route('/operations/{id}/aar', name: 'operation_aar', requirements: ['id' => '\d+'])]
    public function __invoke(Operation $operation, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        // Non-patrol events still need submit_aar; a patrol's leader, attendees and staff may file its AAR.
        $canFile = $this->eventRules->canFileAar(
            $operation,
            $user,
            $this->isGranted('command-net.admin.operations.manage'),
            $this->isGranted('command-net.operations.submit_aar'),
        );
        if (!$canFile) {
            throw $this->createAccessDeniedException();
        }

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

    /**
     * Corrections happen by removing the wrong report and re-submitting, not by editing one
     * in place - the same "records are facts, not form fields" rule used for personnel
     * records. Removing the report also removes every combat record it wrote, so re-filing
     * a corrected AAR for the same operation doesn't leave attendees with duplicates.
     * Anyone who can manage operations can remove any report; a submitter can also remove
     * their own.
     */
    #[Route('/operations/{id}/aar/{aarId}/delete', name: 'operation_aar_delete', requirements: ['id' => '\d+', 'aarId' => '\d+'], methods: ['POST'])]
    public function delete(Operation $operation, int $aarId, Request $request): RedirectResponse
    {
        $aar = $this->aarRepository->find($aarId);
        if ($aar === null || $aar->getOperation() !== $operation) {
            throw $this->createNotFoundException();
        }

        $isOwnReport = $aar->getSubmittedBy() === $this->getUser();
        if (!$isOwnReport && !$this->isGranted('command-net.admin.operations.manage')) {
            throw $this->createAccessDeniedException();
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('operation_aar_delete_' . $aarId, $token)) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
        }

        foreach ($this->serviceRecordRepository->findBySource(ServiceRecord::SOURCE_OPERATION_AAR, $aarId) as $record) {
            $this->serviceRecordRepository->remove($record, false);
        }
        $this->aarRepository->remove($aar);
        $operation->getAars()->removeElement($aar);
        $this->attendanceService->syncCombatRecords($operation);

        $this->addFlash('success', 'After-action report removed.');
        return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
    }

    private function submit(OperationAAR $aar, Operation $operation): RedirectResponse
    {
        $this->aarRepository->save($aar);

        // Combat records are derived from attendance (once an AAR exists, for the events that
        // need one), one per attendee however many reports are filed - see EventRules.
        $operation->getAars()->add($aar);
        $this->attendanceService->syncCombatRecords($operation);

        $this->addFlash('success', 'After-action report submitted.');
        return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
    }
}
