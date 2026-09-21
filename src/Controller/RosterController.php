<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Repository\RosterRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\RosterGrouping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RosterController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly RosterRepository $rosterRepository,
        private readonly RosterGrouping $rosterGrouping,
    ) {
    }

    /**
     * With no rosters defined this is one list of everyone, as it always was. Otherwise there is
     * a tab per roster (the first one unless ?roster= says otherwise) showing its units.
     */
    #[Route('/roster', name: 'roster')]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.roster.view');

        $soldiers = $this->soldierProfileRepository->findRoster();
        $rosters = $this->rosterRepository->findInOrder();
        $selected = $this->selectRoster($rosters, $request->query->getInt('roster'));

        // Only an enlisted soldier can report in, so only they are offered the button.
        $user = $this->getUser();
        $myProfile = $user !== null ? $this->soldierProfileRepository->findOneBy(['user' => $user]) : null;
        $canReportIn = $myProfile?->isEnlisted() === true;

        if ($selected === null) {
            return $this->render('@CommandNetPlugin/frontend/roster/list.html.twig', [
                'roster' => $soldiers,
                'rosters' => [],
                'selectedRoster' => null,
                'groups' => null,
                'canReportIn' => $canReportIn,
            ]);
        }

        $groups = $this->rosterGrouping->group($selected, $soldiers);

        return $this->render('@CommandNetPlugin/frontend/roster/list.html.twig', [
            'roster' => array_merge(...array_column($groups, 'soldiers')),
            'rosters' => $rosters,
            'selectedRoster' => $selected,
            'groups' => $groups,
            'canReportIn' => $canReportIn,
        ]);
    }

    /**
     * @param array<Roster> $rosters
     */
    private function selectRoster(array $rosters, int $requestedId): ?Roster
    {
        foreach ($rosters as $roster) {
            if ($roster->getId() === $requestedId) {
                return $roster;
            }
        }

        return $rosters[0] ?? null;
    }
}
