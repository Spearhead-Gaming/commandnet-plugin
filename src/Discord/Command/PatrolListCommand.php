<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\OperationRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Read-only, so no linked-account check - same as PromotionCommand/SoldierCommand/UnitCommand.
 */
class PatrolListCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-patrol-list';
    }

    public function getDescription(): string
    {
        return 'Lists upcoming patrols.';
    }

    public function getOptions(): array
    {
        return [];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        $patrols = $this->operationRepository->findUpcomingPatrols();
        if (empty($patrols)) {
            $result->content = 'No upcoming patrols.';
            return $result;
        }

        $result->content = implode("\n", array_map($this->line(...), $patrols));
        return $result;
    }

    private function line(Operation $patrol): string
    {
        return sprintf(
            "**#%d** %s - %s\n%s",
            $patrol->getId(),
            $patrol->getTitle(),
            $patrol->getStartDateTime()->format('D, M j \a\t g:i A'),
            $this->urlGenerator->generate('command_net_operation_detail', ['id' => $patrol->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
