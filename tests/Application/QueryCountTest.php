<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\SoldierQualification;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Tests\Support\QueryCounter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The pages that list every soldier must run the same number of queries however many soldiers
 * there are. Each page is loaded with a few soldiers, then with several times as many, and the
 * two query counts are compared. A page that loads something per soldier (an N+1) grows.
 */
class QueryCountTest extends WebTestCase
{
    use UserTrait;

    private const int SMALL = 3;
    private const int LARGE = 15;

    private KernelBrowser $client;
    private string $sfx;
    private int $adminId;
    private int $soldiers = 0;

    public function testQueryCountsStayFlatAsTheRosterGrows(): void
    {
        $this->client = static::createClient();
        $this->sfx = substr(uniqid(), -6);

        $admin = $this->createAdmin('qa' . $this->sfx, 'qa' . $this->sfx . '@example.org');
        $this->adminId = $admin->getId();
        $ids = $this->seedCatalogue();

        $urls = [
            '/roster', '/roster?roster=' . $ids['roster'], '/units', '/promotions', '/attendance',
            '/operations/' . $ids['operation'], '/qualifications', '/admin/command-net/personnel',
        ];

        $this->addSoldiers($ids, self::SMALL);
        $small = $this->measure($urls);
        $this->addSoldiers($ids, self::LARGE - self::SMALL);
        $large = $this->measure($urls);

        $report = [];
        $grown = [];
        foreach ($urls as $url) {
            $report[] = sprintf('%-45s %3d queries with %d soldiers, %3d with %d', $url, $small[$url], self::SMALL, $large[$url], self::LARGE);
            if ($large[$url] > $small[$url]) {
                $grown[] = $url;
            }
        }

        $this->assertSame([], array_keys(array_filter($small, static fn (int $count) => $count === 0)), 'The query counter saw no queries, so it is not wired up.');
        $this->assertSame([], $grown, "Query counts grew with the roster:\n" . implode("\n", $report));    }

    /**
     * @param array<string> $urls
     * @return array<string, int>
     */
    private function measure(array $urls): array
    {
        $counts = [];
        foreach ($urls as $url) {
            // The first load warms the caches Symfony and Doctrine fill on first use.
            $this->load($url);
            $counts[$url] = $this->load($url);
        }

        return $counts;
    }

    private function load(string $url): int
    {
        $this->client->loginUser($this->em()->find(User::class, $this->adminId));
        QueryCounter::$count = 0;
        $this->client->request('GET', $url);
        $count = QueryCounter::$count;
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), $url . ' did not load');

        return $count;
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em;
    }

    /**
     * @return array<string, int|string>
     */
    private function seedCatalogue(): array
    {
        $em = $this->em();
        $s = $this->sfx;
        $n = random_int(1000, 900000);

        $group = new RankGroup();
        $group->setName('G' . $s);
        $qualification = new Qualification();
        $qualification->setName('Q' . $s);
        $qualification->setPosition($n);
        $entities = [$group, $qualification];
        $ranks = [];
        foreach (['Private', 'Corporal', 'Sergeant', 'Captain'] as $i => $name) {
            $rank = new Rank();
            $rank->setName($name . $s);
            $rank->setAbbreviation(substr($name, 0, 3));
            $rank->setPosition($n + $i * 10);
            $rank->setGroup($group);
            if ($i > 0) {
                $rank->addRequiredQualification($qualification);
            }
            $ranks[] = $rank;
            $entities[] = $rank;
        }
        $units = [];
        foreach (['Alpha', 'Bravo'] as $i => $name) {
            $unit = new Unit();
            $unit->setName($name . $s);
            $unit->setAbbreviation(substr($name, 0, 3));
            $unit->setPosition($i);
            $units[] = $unit;
            $entities[] = $unit;
        }
        $specialty = new Specialty();
        $specialty->setName('Medic' . $s);
        $specialty->setAbbreviation('MED');
        $roster = new Roster();
        $roster->setName('R' . $s);
        $roster->setPosition($n);
        $roster->addUnit($units[0]);
        $roster->addUnit($units[1]);
        $entities[] = $specialty;
        $entities[] = $roster;
        $operations = [];
        foreach ([-9, -6, -3] as $days) {
            $operation = new Operation();
            $operation->setTitle('Op' . $days . $s);
            $operation->setStartDateTime(new DateTime($days . ' days'));
            $operations[] = $operation;
            $entities[] = $operation;
        }
        foreach ($entities as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [
            'qualification' => $qualification->getId(), 'specialty' => $specialty->getId(),
            'roster' => $roster->getId(), 'operation' => $operations[2]->getId(),
            'operations' => implode(',', array_map(static fn (Operation $o) => $o->getId(), $operations)),
            'units' => implode(',', array_map(static fn (Unit $u) => $u->getId(), $units)),
            'ranks' => implode(',', array_map(static fn (Rank $r) => $r->getId(), $ranks)),
        ];
    }

    /**
     * @param array<string, int|string> $ids
     */
    private function addSoldiers(array $ids, int $count): void
    {
        $rankIds = explode(',', (string)$ids['ranks']);
        $unitIds = explode(',', (string)$ids['units']);
        $operationIds = explode(',', (string)$ids['operations']);

        for ($i = 0; $i < $count; $i++) {
            $this->soldiers++;
            $k = $this->soldiers;
            $user = $this->createUser('qs' . $k . $this->sfx, 'qs' . $k . $this->sfx . '@example.org');
            $em = $this->em();
            $user = $em->find(User::class, $user->getId());
            $profile = new SoldierProfile($user);
            $profile->setRank($em->find(Rank::class, $rankIds[$k % count($rankIds)]));
            $profile->setSpecialty($em->find(Specialty::class, $ids['specialty']));
            $profile->setEnlistmentDate(new DateTime('-200 days'));
            $em->persist($profile);
            $em->flush();

            $assignment = new Assignment($profile, $em->find(Unit::class, $unitIds[$k % 2]));
            $assignment->setIsPrimary(true);
            $assignment->setStartDate(new DateTime('-100 days'));
            $em->persist($assignment);
            if ($k % 2 === 0) {
                $em->persist(new SoldierQualification($profile, $em->find(Qualification::class, $ids['qualification'])));
            }
            foreach ($operationIds as $operationId) {
                $rsvp = new OperationRSVP($em->find(Operation::class, $operationId), $profile);
                $rsvp->setStatus(RsvpStatus::ATTENDING);
                $rsvp->setAttended($k % 3 !== 0);
                $em->persist($rsvp);
            }
            $em->flush();
        }
    }
}
