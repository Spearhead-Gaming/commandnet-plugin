<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Repository\UserRepository;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Repository\ReportInRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

class PersonnelFileController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ReportInRepository $reportInRepository,
        private readonly IdentityProviderUserRepository $identityProviderUserRepository,
    ) {
    }

    #[Route('/roster/{username}', name: 'roster_profile', methods: ['GET'])]
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

        // Discord linkage lives on forumify core's generic OAuth identity system, not the
        // optional Discord plugin - this works whether or not that plugin is installed,
        // as long as a Discord identity provider is configured and the user linked it.
        $discordIdentity = null;
        foreach ($this->identityProviderUserRepository->findBy(['user' => $user]) as $idpUser) {
            if ($idpUser->getIdentityProvider()->getType() === DiscordIdp::getType()) {
                $discordIdentity = $idpUser;
                break;
            }
        }

        return $this->render('@CommandNetPlugin/frontend/personnel/file.html.twig', [
            'profile' => $profile,
            'primaryAssignment' => $profile->getPrimaryAssignment(),
            'latestReportIn' => $this->reportInRepository->findLatestFor($profile),
            'discordUsername' => $discordIdentity?->getExternalUsername(),
        ]);
    }
}
