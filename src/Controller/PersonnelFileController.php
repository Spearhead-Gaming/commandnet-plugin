<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

class PersonnelFileController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
    ) {
    }

    #[Route('/roster/{username}', name: 'roster_profile')]
    public function __invoke(string $username): Response
    {
        $this->denyAccessUnlessGranted('command-net.roster.view');

        // Routed by username (not soldier id) so a personnel file's URL matches the
        // forum profile URL scheme the community already uses.
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]);
        if ($profile === null) {
            // A forumify user with no service record - not enlisted, not a 404 of the
            // user itself, just nothing for this plugin to show.
            throw $this->createNotFoundException('This user has no personnel file.');
        }

        return $this->render('@CommandNetPlugin/frontend/personnel/file.html.twig', [
            'profile' => $profile,
            'primaryAssignment' => $profile->getPrimaryAssignment(),
        ]);
    }
}
