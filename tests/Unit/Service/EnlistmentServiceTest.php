<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use DateTime;
use DomainException;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\AssignmentRepository;
use MajesticDev\CommandNet\Repository\EnlistmentApplicationRepository;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Repository\UnitRepository;
use MajesticDev\CommandNet\Service\EnlistmentService;
use MajesticDev\CommandNet\Service\EnlistmentSettings;
use MajesticDev\CommandNet\Service\RankRoleSyncer;
use MajesticDev\CommandNet\Service\UnitRoleSyncer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EnlistmentServiceTest extends TestCase
{
    private EnlistmentService $service;
    private NotificationService&MockObject $notificationService;
    private UnitRoleSyncer&MockObject $unitRoleSyncer;
    private SoldierProfileRepository&MockObject $soldierRepository;

    /** @var array<string, mixed> */
    private array $config = ['enabled' => true, 'defaultRank' => null, 'defaultUnit' => null, 'instructions' => ''];
    private ?SoldierProfile $existingProfile = null;
    private ?EnlistmentApplication $latestApplication = null;
    private ?Rank $rank = null;
    private ?Unit $unit = null;
    /** @var ServiceRecord[] */
    private array $records = [];

    protected function setUp(): void
    {
        $settings = $this->createMock(EnlistmentSettings::class);
        $settings->method('all')->willReturnCallback(fn () => $this->config);

        $applicationRepository = $this->createMock(EnlistmentApplicationRepository::class);
        $applicationRepository->method('findLatestFor')->willReturnCallback(fn () => $this->latestApplication);

        $this->soldierRepository = $this->createMock(SoldierProfileRepository::class);
        $this->soldierRepository->method('findOneBy')->willReturnCallback(fn () => $this->existingProfile);

        $recordRepository = $this->createMock(ServiceRecordRepository::class);
        $recordRepository->method('save')->willReturnCallback(function (ServiceRecord $record): void {
            $this->records[] = $record;
        });

        $assignmentRepository = $this->createMock(AssignmentRepository::class);
        $assignmentRepository->method('save')->willReturnCallback(function (Assignment $assignment): void {
            (new ReflectionProperty($assignment, 'id'))->setValue($assignment, 7);
        });

        $rankRepository = $this->createMock(RankRepository::class);
        $rankRepository->method('find')->willReturnCallback(fn () => $this->rank);
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('find')->willReturnCallback(fn () => $this->unit);

        $this->unitRoleSyncer = $this->createMock(UnitRoleSyncer::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/x');

        $this->service = new EnlistmentService(
            $settings,
            $applicationRepository,
            $this->soldierRepository,
            $recordRepository,
            $assignmentRepository,
            $rankRepository,
            $unitRepository,
            $this->unitRoleSyncer,
            $this->createMock(RankRoleSyncer::class),
            $this->notificationService,
            $urlGenerator,
        );
    }

    public function testNobodyCanApplyWhileEnlistmentIsClosed(): void
    {
        $this->config['enabled'] = false;

        $this->assertNotNull($this->service->ineligibleReason($this->user()));
    }

    public function testBannedAndUnverifiedUsersCannotApply(): void
    {
        $this->assertNotNull($this->service->ineligibleReason($this->user(banned: true)));
        $this->assertNotNull($this->service->ineligibleReason($this->user(verified: false)));
    }

    public function testActiveSoldiersCannotApplyButDischargedOnesCan(): void
    {
        $user = $this->user();
        $this->existingProfile = new SoldierProfile($user);
        $this->assertNotNull($this->service->ineligibleReason($user));

        $this->existingProfile->setStatus(SoldierStatus::DISCHARGED);
        $this->assertNull($this->service->ineligibleReason($user));
    }

    public function testAPendingApplicationBlocksAnother(): void
    {
        $user = $this->user();
        $this->latestApplication = new EnlistmentApplication($user);

        $this->assertNotNull($this->service->ineligibleReason($user));

        $this->latestApplication->decide(ApplicationStatus::DECLINED, null, null);
        $this->assertNull($this->service->ineligibleReason($user), 'A declined applicant may apply again.');
    }

    public function testSubmitRefusesAnIneligibleApplicant(): void
    {
        $this->config['enabled'] = false;

        $this->expectException(DomainException::class);
        $this->service->submit(new EnlistmentApplication($this->user()));
    }

    public function testAcceptingCreatesAnActiveProfileWithTheStartingRankAndUnit(): void
    {
        $this->config['defaultRank'] = 1;
        $this->config['defaultUnit'] = 2;
        $this->rank = new Rank();
        $this->unit = new Unit();
        $application = $this->application('Viper', '76561198000000000');

        $this->unitRoleSyncer->expects($this->once())->method('sync');
        $this->notificationService->expects($this->once())->method('sendNotification');

        $profile = $this->service->accept($application, null, 'Welcome.');

        $this->assertSame(SoldierStatus::ACTIVE, $profile->getStatus());
        $this->assertSame($this->rank, $profile->getRank());
        $this->assertSame('Viper', $profile->getCallsign());
        $this->assertSame('76561198000000000', $profile->getSteamId());
        $this->assertNotNull($profile->getEnlistmentDate());
        $this->assertSame($this->unit, $profile->getPrimaryAssignment()?->getUnit());
        $this->assertSame([ServiceRecordType::ENLISTMENT, ServiceRecordType::ASSIGNMENT], array_map(
            static fn (ServiceRecord $r) => $r->getType(),
            $this->records,
        ));
        $this->assertSame(ApplicationStatus::ACCEPTED, $application->getStatus());
    }

    public function testAcceptingWithNoDefaultsStillCreatesTheProfile(): void
    {
        $profile = $this->service->accept($this->application(), null, null);

        $this->assertSame(SoldierStatus::ACTIVE, $profile->getStatus());
        $this->assertNull($profile->getRank());
        $this->assertNull($profile->getPrimaryAssignment());
    }

    public function testAcceptingRestoresADischargedSoldierWithoutReplacingTheirRank(): void
    {
        $this->config['defaultRank'] = 1;
        $this->rank = new Rank();
        $user = $this->user();
        $previousRank = new Rank();
        $returning = new SoldierProfile($user);
        $returning->setStatus(SoldierStatus::DISCHARGED);
        $returning->setRank($previousRank);
        $returning->setDischargeDate(new DateTime('-30 days'));
        $this->existingProfile = $returning;

        $profile = $this->service->accept($this->application(user: $user), null, null);

        $this->assertSame($returning, $profile);
        $this->assertSame(SoldierStatus::ACTIVE, $profile->getStatus());
        $this->assertNull($profile->getDischargeDate());
        $this->assertSame($previousRank, $profile->getRank());
    }

    public function testAnApplicationCanOnlyBeReviewedOnce(): void
    {
        $application = $this->application();
        $this->service->decline($application, null, null);

        $this->expectException(DomainException::class);
        $this->service->accept($application, null, null);
    }

    public function testDecliningNotifiesButCreatesNoProfile(): void
    {
        $this->soldierRepository->expects($this->never())->method('save');
        $this->notificationService->expects($this->once())->method('sendNotification');
        $application = $this->application();

        $this->service->decline($application, null, 'Not a fit right now.');

        $this->assertSame(ApplicationStatus::DECLINED, $application->getStatus());
        $this->assertSame('Not a fit right now.', $application->getDecisionNote());
        $this->assertSame([], $this->records);
    }

    private function user(bool $banned = false, bool $verified = true): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('isBanned')->willReturn($banned);
        $user->method('isEmailVerified')->willReturn($verified);

        return $user;
    }

    private function application(?string $callsign = null, ?string $steamId = null, ?User $user = null): EnlistmentApplication
    {
        $application = new EnlistmentApplication($user ?? $this->user());
        $application->setCallsign($callsign);
        $application->setSteamId($steamId);
        $application->setMotivation('I want to play.');

        return $application;
    }
}
