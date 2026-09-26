<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Discord\Command;

use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\CommandNet\Discord\DiscordUserResolver;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Repository\OperationAARRepository;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Service\AarImageException;
use MajesticDev\CommandNet\Service\AarImageStore;
use MajesticDev\CommandNet\Service\EventRules;
use MajesticDev\CommandNet\Service\OperationAttendanceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Only the patrol's own leader can file its AAR from here - an attendee or staff member
 * who needs to file it can still use the web form (linked in every reply), which keeps
 * EventRules::canFileAar()'s fuller "leader, attendee or staff" rule for the web only.
 *
 * A patrol's AAR follows the community template and needs a map and an intel image, which a slash
 * command cannot carry. So the report is filed by the Submit AAR button on the patrol's Discord
 * post: the bot collects the template's text and the two uploads in two forms and passes them
 * here as extra options (tasking, callsigns, fkia, ekia, report, and map_urls / intel_urls, JSON
 * lists of the attachment links). Run as a plain slash command it just says where to go.
 */
class PatrolAarCommand implements DiscordCommandInterface
{
    private const array TEXT_OPTIONS = ['tasking', 'callsigns', 'fkia', 'ekia', 'report'];

    public function __construct(
        private readonly DiscordUserResolver $userResolver,
        private readonly OperationRepository $operationRepository,
        private readonly OperationAARRepository $aarRepository,
        private readonly OperationAttendanceService $attendanceService,
        private readonly EventRules $eventRules,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AarImageStore $imageStore,
    ) {
    }

    public function getName(): string
    {
        return 'command-net-patrol-aar';
    }

    public function getDescription(): string
    {
        return 'How to file the after-action report for a patrol you led.';
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

        if (!$this->eventRules->isLeader($patrol, $user)) {
            $result->content = "Only the patrol's own leader can file its AAR from Discord. If you attended or are staff, use the forum instead: $aarUrl";
            return $result;
        }

        $text = [];
        foreach (self::TEXT_OPTIONS as $option) {
            $text[$option] = trim((string)($command->options[$option] ?? ''));
        }
        $mapUrls = $this->urls($command->options['map_urls'] ?? null);
        $intelUrls = $this->urls($command->options['intel_urls'] ?? null);

        if (in_array('', $text, true) || $mapUrls === [] || $intelUrls === []) {
            $result->content = "A patrol's AAR needs its tasking, callsigns, casualties, a report, and at least one map and one intel image. "
                . "Press **Submit AAR** on the patrol's post in Discord, or file it on the forum: $aarUrl";
            return $result;
        }

        $patrolId = (int)$patrol->getId();
        try {
            $mapImages = $this->imageStore->importFromDiscord($mapUrls, 'map', $patrolId);
            try {
                $intelImages = $this->imageStore->importFromDiscord($intelUrls, 'intel', $patrolId);
            } catch (AarImageException $ex) {
                $this->imageStore->delete($mapImages);
                throw $ex;
            }
        } catch (AarImageException $ex) {
            $result->content = $ex->getMessage() . ' Your written answers are kept: press **Add map and intel images** again.';
            return $result;
        }

        $aar = new OperationAAR($patrol, $user);
        $aar->setTasking($text['tasking']);
        $aar->setCallsigns($text['callsigns']);
        $aar->setFriendlyCasualties($text['fkia']);
        $aar->setEnemyKia($text['ekia']);
        // The report is typed as plain text but shown as rich text, so escape it and keep its line breaks.
        $aar->setSummary('<p>' . nl2br(htmlspecialchars($text['report'], ENT_QUOTES)) . '</p>');
        $aar->setMapImages($mapImages);
        $aar->setIntelImages($intelImages);

        try {
            $this->aarRepository->save($aar);
            $patrol->getAars()->add($aar);
            $this->attendanceService->syncCombatRecords($patrol);
        } catch (Throwable $ex) {
            // The images are stored before the report is, so a failure here would orphan them.
            $this->imageStore->delete([...$mapImages, ...$intelImages]);
            throw $ex;
        }

        $result->content = sprintf('After-action report filed for **%s**.', $patrol->getTitle());
        $result->embeds[] = new DiscordEmbed(
            title: $patrol->getTitle(),
            description: 'After-action report filed.',
            url: $this->urlGenerator->generate('command_net_operation_detail', ['id' => $patrol->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        );
        return $result;
    }

    /**
     * The bot sends the attachment links as a JSON list inside a string option.
     *
     * @return list<string>
     */
    private function urls(mixed $value): array
    {
        $decoded = json_decode((string)$value, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, static fn (mixed $url): bool => is_string($url) && $url !== ''))
            : [];
    }
}
