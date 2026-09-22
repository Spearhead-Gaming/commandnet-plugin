<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Repository\OperationAARRepository;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Service\EventRules;
use MajesticDev\CommandNet\Service\OperationAttendanceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Only the patrol's own leader can file its AAR from here - an attendee or staff member
 * who needs to file it can still use the web form (linked in every reply), which keeps
 * EventRules::canFileAar()'s fuller "leader, attendee or staff" rule for the web only.
 */
class PatrolAarCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly DiscordUserResolver $userResolver,
        private readonly OperationRepository $operationRepository,
        private readonly OperationAARRepository $aarRepository,
        private readonly OperationAttendanceService $attendanceService,
        private readonly EventRules $eventRules,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-patrol-aar';
    }

    public function getDescription(): string
    {
        return 'Files the after-action report for a patrol you led.';
    }

    public function getOptions(): array
    {
        return [
            new DiscordCommandOption()
                ->setName('id')
                ->setDescription('The patrol\'s id, from /command-net-patrol-list.')
                ->setRequired(),
            new DiscordCommandOption()
                ->setName('summary')
                ->setDescription('What happened. Leave blank to file it on the forum instead.'),
            new DiscordCommandOption()
                ->setName('objectives_met')
                ->setDescription('yes or no.'),
        ];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        $user = $this->userResolver->resolveUser($command->discordUserId);
        if ($user === null) {
            $result->content = 'We could not find your linked forum account. Connect your Discord account in your forum account settings first.';
            return $result;
        }

        $patrol = $this->operationRepository->findPatrol((int)($command->options['id'] ?? 0));
        if ($patrol === null) {
            $result->content = 'We could not find that patrol.';
            return $result;
        }

        $aarUrl = $this->urlGenerator->generate('command_net_operation_aar', ['id' => $patrol->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $summary = trim((string)($command->options['summary'] ?? ''));
        if ($summary === '') {
            $result->content = "Write it on the forum instead: $aarUrl";
            return $result;
        }

        if (!$this->eventRules->isLeader($patrol, $user)) {
            $result->content = "Only the patrol's own leader can file its AAR from Discord. If you attended or are staff, use the forum instead: $aarUrl";
            return $result;
        }

        $aar = new OperationAAR($patrol, $user);
        $aar->setSummary($summary);
        $aar->setObjectivesMet($this->parseObjectivesMet($command->options['objectives_met'] ?? null));
        $this->aarRepository->save($aar);
        $patrol->getAars()->add($aar);
        $this->attendanceService->syncCombatRecords($patrol);

        $result->content = sprintf('After-action report filed for **%s**.', $patrol->getTitle());
        return $result;
    }

    private function parseObjectivesMet(mixed $value): ?bool
    {
        return match (strtolower(trim((string)$value))) {
            'yes', 'y', 'true' => true,
            'no', 'n', 'false' => false,
            default => null,
        };
    }
}
