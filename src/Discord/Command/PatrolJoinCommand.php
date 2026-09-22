<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Service\EventRules;
use MajesticDev\CommandNet\Service\RawPermissionChecker;

/**
 * Discord counterpart to OperationRsvpController: same operations.rsvp permission, same
 * "only enlisted personnel" and joiner-cap rules.
 */
class PatrolJoinCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly DiscordUserResolver $userResolver,
        private readonly RawPermissionChecker $permissionChecker,
        private readonly OperationRepository $operationRepository,
        private readonly OperationRSVPRepository $rsvpRepository,
        private readonly EventRules $eventRules,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-patrol-join';
    }

    public function getDescription(): string
    {
        return 'Marks you as attending a patrol.';
    }

    public function getOptions(): array
    {
        return [
            new DiscordCommandOption()
                ->setName('id')
                ->setDescription('The patrol\'s id, from /command-net-patrol-list.')
                ->setRequired(),
        ];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        $soldier = $this->userResolver->resolveSoldier($command->discordUserId);
        if ($soldier === null || !$soldier->isEnlisted()) {
            $result->content = 'We could not find your personnel file. Only enlisted personnel can join a patrol.';
            return $result;
        }
        if (!$this->permissionChecker->isGranted($soldier->getUser(), 'command-net.operations.rsvp')) {
            $result->content = 'You do not have permission to RSVP.';
            return $result;
        }

        $patrol = $this->operationRepository->findPatrol((int)($command->options['id'] ?? 0));
        if ($patrol === null) {
            $result->content = 'We could not find that patrol.';
            return $result;
        }

        if ($this->eventRules->isFull($patrol, $soldier)) {
            $result->content = 'That patrol is full.';
            return $result;
        }

        $rsvp = $patrol->getRsvpFor($soldier) ?? new OperationRSVP($patrol, $soldier);
        $rsvp->setStatus(RsvpStatus::ATTENDING);
        $this->rsvpRepository->save($rsvp);

        $result->content = sprintf("You're marked as attending **%s**.", $patrol->getTitle());
        return $result;
    }
}
