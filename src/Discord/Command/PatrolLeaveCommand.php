<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;

/**
 * The withdraw-RSVP button's Discord counterpart: sets the RSVP back to no_response
 * rather than deleting the row, matching OperationDetailController's web behaviour.
 */
class PatrolLeaveCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly DiscordUserResolver $userResolver,
        private readonly OperationRepository $operationRepository,
        private readonly OperationRSVPRepository $rsvpRepository,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-patrol-leave';
    }

    public function getDescription(): string
    {
        return 'Removes you from a patrol you joined.';
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
        if ($soldier === null) {
            $result->content = 'We could not find your personnel file.';
            return $result;
        }

        $patrol = $this->operationRepository->findPatrol((int)($command->options['id'] ?? 0));
        if ($patrol === null) {
            $result->content = 'We could not find that patrol.';
            return $result;
        }

        $rsvp = $patrol->getRsvpFor($soldier);
        if ($rsvp === null || $rsvp->getStatus() === RsvpStatus::NO_RESPONSE) {
            $result->content = sprintf("You aren't marked as attending **%s**.", $patrol->getTitle());
            return $result;
        }

        $rsvp->setStatus(RsvpStatus::NO_RESPONSE);
        $this->rsvpRepository->save($rsvp);

        $result->content = sprintf("You're no longer marked as attending **%s**.", $patrol->getTitle());
        return $result;
    }
}
