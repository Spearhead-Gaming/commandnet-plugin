<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use DateTime;
use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Service\DeploymentLookup;
use MajesticDev\CommandNet\Service\RawPermissionChecker;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Discord counterpart to PatrolController::create(): same patrols.create permission, same
 * "leader auto-attends when enlisted" behaviour. Announcing the new patrol to Discord is
 * not done here - PatrolPostSubscriber (in the Discord plugin) reacts to any new
 * patrol being persisted, web-posted or command-posted alike, so there is exactly one
 * place deciding "this is new", not two commands racing to announce the same one.
 */
class PatrolCreateCommand implements DiscordCommandInterface
{
    /** The one strict, unambiguous format the "when" option is parsed with. */
    private const string DATE_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private readonly DiscordUserResolver $userResolver,
        private readonly RawPermissionChecker $permissionChecker,
        private readonly OperationRepository $operationRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly DeploymentLookup $deploymentLookup,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-patrol-create';
    }

    public function getDescription(): string
    {
        return 'Posts a patrol. You become its leader.';
    }

    public function getOptions(): array
    {
        return [
            new DiscordCommandOption()
                ->setName('title')
                ->setDescription('A short name for the patrol.')
                ->setRequired(),
            new DiscordCommandOption()
                ->setName('when')
                ->setDescription('Date and time in the site\'s timezone, e.g. "2026-09-24 20:00".')
                ->setRequired(),
            new DiscordCommandOption()
                ->setName('where')
                ->setDescription('Server, map or area of operations.'),
            new DiscordCommandOption()
                ->setName('details')
                ->setDescription('The plan.'),
            new DiscordCommandOption()
                ->setName('deployment')
                ->setDescription('The name of the monthly deployment this patrol belongs to, if any.'),
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
        if (!$this->permissionChecker->isGranted($user, 'command-net.patrols.create')) {
            $result->content = 'You do not have permission to post a patrol.';
            return $result;
        }

        $title = trim((string)($command->options['title'] ?? ''));
        if ($title === '') {
            $result->content = 'Please provide a title.';
            return $result;
        }

        $when = trim((string)($command->options['when'] ?? ''));
        $start = DateTime::createFromFormat(self::DATE_FORMAT, $when);
        // createFromFormat() silently tolerates junk like "2026-13-45 99:99" by rolling it
        // over into a different, valid date - round-tripping the parsed value back through
        // the same format catches that instead of quietly creating the wrong patrol.
        if ($start === false || $start->format(self::DATE_FORMAT) !== $when) {
            $result->content = 'That date and time did not parse. Use the format "2026-09-24 20:00" (YYYY-MM-DD HH:MM), in the site\'s timezone.';
            return $result;
        }

        $where = trim((string)($command->options['where'] ?? ''));
        $details = trim((string)($command->options['details'] ?? ''));

        $deploymentName = trim((string)($command->options['deployment'] ?? ''));
        $deployment = $this->deploymentLookup->findByName($deploymentName);
        // A name that matches nothing is refused, not ignored: silently posting an unlinked
        // patrol would look like it worked.
        if ($deploymentName !== '' && $deployment === null) {
            $known = $this->deploymentLookup->recentNames();
            $result->content = sprintf(
                'We could not find a deployment called "%s".%s',
                $deploymentName,
                $known === [] ? ' There are no deployments yet.' : ' Recent deployments: ' . implode(', ', $known) . '.',
            );
            return $result;
        }

        $patrol = new Operation();
        $patrol->setType(OperationType::PATROL);
        $patrol->setTitle($title);
        $patrol->setStartDateTime($start);
        $patrol->setLeader($user);
        $patrol->setDeployment($deployment);
        if ($where !== '') {
            $patrol->setLocation($where);
        }
        if ($details !== '') {
            $patrol->setContent($details);
        }

        $soldier = $this->userResolver->resolveSoldier($command->discordUserId);
        if ($soldier !== null && $soldier->isEnlisted()) {
            $rsvp = new OperationRSVP($patrol, $soldier);
            $rsvp->setStatus(RsvpStatus::ATTENDING);
            $patrol->getRsvps()->add($rsvp);
        }

        $this->operationRepository->save($patrol);

        $result->embeds[] = new DiscordEmbed(
            title: $patrol->getTitle(),
            description: $start->format('l, F j \a\t g:i A') . ($where !== '' ? " - $where" : ''),
            url: $this->urlGenerator->generate('command_net_operation_detail', ['id' => $patrol->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        );
        return $result;
    }
}
