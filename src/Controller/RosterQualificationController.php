<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\SoldierQualification;
use MajesticDev\CommandNet\Form\IssueQualificationType;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\SoldierQualificationRepository;

class RosterQualificationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly SoldierQualificationRepository $soldierQualificationRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
    ) {
    }

    #[Route('/roster/{username}/qualification', name: 'roster_qualification')]
    public function __invoke(string $username, Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.qualifications.manage');

        $profile = $this->findProfile($username);

        $form = $this->createForm(IssueQualificationType::class, null, ['soldier' => $profile]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SoldierQualification $soldierQualification */
            $soldierQualification = $form->getData();
            return $this->submit($soldierQualification, $profile);
        }

        return $this->render('@CommandNetPlugin/frontend/personnel/qualification_form.html.twig', [
            'profile' => $profile,
            'form' => $form,
        ]);
    }

    /**
     * Corrections happen by removing the wrong entry and re-issuing, not by editing one in
     * place - the same "records are facts, not form fields" rule MILHQ uses for its own
     * award/qualification/service records.
     */
    #[Route('/roster/{username}/qualification/{id}/delete', name: 'roster_qualification_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(string $username, int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.admin.qualifications.manage');
        $profile = $this->findProfile($username);

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('roster_qualification_delete_' . $id, $token)) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_roster_profile', ['username' => $username]);
        }

        $soldierQualification = $this->soldierQualificationRepository->find($id);
        if ($soldierQualification !== null && $soldierQualification->getSoldier() === $profile) {
            $this->soldierQualificationRepository->remove($soldierQualification);
            $this->addFlash('success', 'Qualification removed.');
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

    private function submit(SoldierQualification $soldierQualification, SoldierProfile $profile): RedirectResponse
    {
        $this->soldierQualificationRepository->save($soldierQualification);

        $record = new ServiceRecord(
            $profile,
            ServiceRecordType::QUALIFICATION,
            $soldierQualification->getQualification()->getName(),
        );
        $record->setDate($soldierQualification->getDateEarned());
        $this->serviceRecordRepository->save($record);

        $this->addFlash('success', 'Qualification issued.');
        return $this->redirectToRoute('command_net_roster_profile', ['username' => $profile->getUser()->getUsername()]);
    }
}
