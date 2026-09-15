<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Form\CreateAssignmentType;
use MajesticDev\CommandNet\Repository\AssignmentRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

class RosterAssignmentController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly AssignmentRepository $assignmentRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
    ) {
    }

    #[Route('/roster/{username}/assignment', name: 'roster_assignment')]
    public function __invoke(string $username, Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.personnel.manage');

        $profile = $this->findProfile($username);

        $form = $this->createForm(CreateAssignmentType::class, null, ['soldier' => $profile]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Assignment $assignment */
            $assignment = $form->getData();
            return $this->submit($assignment, $profile);
        }

        return $this->render('@CommandNetPlugin/frontend/personnel/assignment_form.html.twig', [
            'profile' => $profile,
            'form' => $form,
        ]);
    }

    /**
     * Corrections happen by removing the wrong entry and re-creating it, not by editing one
     * in place - the same "records are facts, not form fields" rule MILHQ uses for its own
     * award/qualification/service records. Deleting a primary assignment does not reopen
     * whichever posting it had ended; create a new assignment for that if needed.
     */
    #[Route('/roster/{username}/assignment/{id}/delete', name: 'roster_assignment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(string $username, int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.admin.personnel.manage');
        $profile = $this->findProfile($username);

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('roster_assignment_delete_' . $id, $token)) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_roster_profile', ['username' => $username]);
        }

        $assignment = $this->assignmentRepository->find($id);
        if ($assignment !== null && $assignment->getSoldier() === $profile) {
            $this->assignmentRepository->remove($assignment);
            $this->addFlash('success', 'Assignment removed.');
        }

        return $this->redirectToRoute('command_net_roster_profile', ['username' => $username]);
    }

    private function findProfile(string $username): SoldierProfile
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]);
        if ($profile === null) {
            throw $this->createNotFoundException('This user has no personnel file.');
        }

        return $profile;
    }

    private function submit(Assignment $assignment, SoldierProfile $profile): RedirectResponse
    {
        // Only one assignment can be primary+active at a time - making a new one primary
        // ends the old one, the same way a real transfer would.
        if ($assignment->isPrimary()) {
            $current = $profile->getPrimaryAssignment();
            if ($current !== null) {
                $current->setEndDate($assignment->getStartDate());
                $this->assignmentRepository->save($current, false);
            }
        }

        $this->assignmentRepository->save($assignment);

        $record = new ServiceRecord(
            $profile,
            ServiceRecordType::ASSIGNMENT,
            $assignment->getUnit()->getName() . ($assignment->getPosition() !== null ? ' - ' . $assignment->getPosition()->getTitle() : ''),
        );
        $record->setDate($assignment->getStartDate());
        $this->serviceRecordRepository->save($record);

        $this->addFlash('success', 'Assignment created.');
        return $this->redirectToRoute('command_net_roster_profile', ['username' => $profile->getUser()->getUsername()]);
    }
}
