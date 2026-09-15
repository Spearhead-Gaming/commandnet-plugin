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
use MajesticDev\CommandNet\Entity\SoldierAward;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Form\AwardSoldierType;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierAwardRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

class RosterAwardController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly SoldierAwardRepository $soldierAwardRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
    ) {
    }

    #[Route('/roster/{username}/award', name: 'roster_award')]
    public function __invoke(string $username, Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.awards.manage');

        $profile = $this->findProfile($username);

        $form = $this->createForm(AwardSoldierType::class, null, ['soldier' => $profile]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SoldierAward $soldierAward */
            $soldierAward = $form->getData();
            return $this->submit($soldierAward, $profile);
        }

        return $this->render('@CommandNetPlugin/frontend/personnel/award_form.html.twig', [
            'profile' => $profile,
            'form' => $form,
        ]);
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

    private function submit(SoldierAward $soldierAward, SoldierProfile $profile): RedirectResponse
    {
        $this->soldierAwardRepository->save($soldierAward);

        $record = new ServiceRecord(
            $profile,
            ServiceRecordType::AWARD,
            $soldierAward->getAward()->getName(),
        );
        $record->setDate($soldierAward->getDateAwarded());
        $record->setDescription($soldierAward->getCitation());
        $this->serviceRecordRepository->save($record);

        $this->addFlash('success', 'Award issued.');
        return $this->redirectToRoute('command_net_roster_profile', ['username' => $profile->getUser()->getUsername()]);
    }
}
