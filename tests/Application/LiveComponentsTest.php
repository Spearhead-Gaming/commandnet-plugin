<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Award;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\AwolService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Throwable;

/**
 * Two things that happen through live components rather than pages: reordering rows in the admin
 * tables, and a member seeing a notification in the site. Each section reports its own failures.
 */
class LiveComponentsTest extends WebTestCase
{
    use UserTrait;
    use InteractsWithLiveComponents;

    private KernelBrowser $client;
    private string $sfx;
    /** @var array<string> */
    private array $failures = [];
    private int $users = 0;

    public function testReorderingAndNotificationsThroughLiveComponents(): void
    {
        $this->client = static::createClient();
        $this->sfx = substr(uniqid(), -6);
        $n = random_int(1000, 900000);

        $tables = [
            'AwardTable' => ['awards', fn (string $name, int $position) => $this->award($name, $position)],
            'QualificationTable' => ['qualifications', fn (string $name, int $position) => $this->qualification($name, $position)],
            'RankTable' => ['ranks', fn (string $name, int $position) => $this->rank($name, $position)],
            'RosterDefinitionTable' => ['rosters', fn (string $name, int $position) => $this->roster($name, $position)],
            'UnitTable' => ['units', fn (string $name, int $position) => $this->unit($name, $position)],
        ];
        foreach ($tables as $component => [$area, $make]) {
            $this->section($component . ' reorders', fn () => $this->reorder($component, $area, $make, $n));
        }
        $this->section('a notification reaches the member in the site', fn () => $this->notification());

        $this->assertSame([], $this->failures, "Live components differ from what they should do:\n" . implode("\n", $this->failures));
    }

    /**
     * @param callable(string, int): object $make
     */
    private function reorder(string $component, string $area, callable $make, int $n): void
    {
        $em = $this->em();
        $first = $make('First' . $this->sfx, $n);
        $second = $make('Second' . $this->sfx, $n + 10);
        $third = $make('Third' . $this->sfx, $n + 20);
        foreach ([$first, $second, $third] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        [$firstId, $secondId, $thirdId] = [$first->getId(), $second->getId(), $third->getId()];
        $class = $first::class;
        $em->clear();

        $viewer = $this->member(['command-net.admin.' . $area . '.view']);
        $manager = $this->member(['command-net.admin.' . $area . '.view', 'command-net.admin.' . $area . '.manage']);

        $this->move($component, $viewer, $secondId, 'up');
        $this->expect($this->order($class, [$firstId, $secondId, $thirdId]) === [$firstId, $secondId, $thirdId], $component . ': a member who can only view the table cannot reorder it');

        $this->move($component, $manager, $secondId, 'up');
        $this->expect($this->order($class, [$firstId, $secondId, $thirdId]) === [$secondId, $firstId, $thirdId], $component . ': moving a row up swaps it with the one above');

        $this->move($component, $manager, $secondId, 'down');
        $this->expect($this->order($class, [$firstId, $secondId, $thirdId]) === [$firstId, $secondId, $thirdId], $component . ': moving it down again restores the order');

        $this->move($component, $manager, $firstId, 'up');
        $this->expect($this->order($class, [$firstId, $secondId, $thirdId]) === [$firstId, $secondId, $thirdId], $component . ': moving the top row up changes nothing');
    }

    private function notification(): void
    {
        $em = $this->em();
        $recipient = $this->createUser('rec' . $this->sfx, 'rec' . $this->sfx . '@example.org');
        $other = $this->createUser('oth' . $this->sfx, 'oth' . $this->sfx . '@example.org');
        $recipient = $em->find(User::class, $recipient->getId());
        $profile = new SoldierProfile($recipient);
        $profile->setEnlistmentDate(new DateTime('-100 days'));
        $em->persist($profile);
        $em->flush();
        $recipientId = $recipient->getId();
        $otherId = $other->getId();

        // The same call the AWOL and Report In code makes, so this covers the real notification.
        /** @var AwolService $awol */
        $awol = static::getContainer()->get(AwolService::class);
        $awol->notify($em->find(SoldierProfile::class, $profile->getId()), 'Report in soon', 'You have 3 day(s) left to report in.');
        $em->clear();

        $html = $this->createLiveComponent('Notifications')->actingAs($this->em()->find(User::class, $recipientId))->render()->toString();
        $this->expect(str_contains($html, 'Report in soon'), 'the recipient sees the notification title in the bell');
        $this->expect(str_contains($html, 'You have 3 day(s) left'), 'the recipient sees the notification text');

        $html = $this->createLiveComponent('Notifications')->actingAs($this->em()->find(User::class, $otherId))->render()->toString();
        $this->expect(!str_contains($html, 'Report in soon'), 'another member does not see it');
    }

    private function move(string $component, User $user, int $id, string $direction): void
    {
        $this->createLiveComponent($component)
            ->actingAs($this->em()->find(User::class, $user->getId()))
            ->call('changePosition', ['id' => $id, 'direction' => $direction]);
        $this->em()->clear();
    }

    /**
     * The given ids, sorted by their current position.
     *
     * @param class-string $class
     * @param array<int> $ids
     * @return array<int>
     */
    private function order(string $class, array $ids): array
    {
        $em = $this->em();
        $em->clear();
        $positions = [];
        foreach ($ids as $id) {
            $positions[$id] = $em->find($class, $id)->getPosition();
        }
        asort($positions);

        return array_keys($positions);
    }

    /**
     * @param array<string> $permissions
     */
    private function member(array $permissions): User
    {
        $em = $this->em();
        $n = ++$this->users;
        $role = new Role();
        $role->setTitle('Reorder role ' . $n . ' ' . $this->sfx);
        $role->setPermissions($permissions);
        $em->persist($role);
        $created = $this->createUser('lc' . $n . $this->sfx, 'lc' . $n . $this->sfx . '@example.org');
        $user = $em->find(User::class, $created->getId());
        $user->addRoleEntity($role);
        $em->flush();

        return $user;
    }

    private function award(string $name, int $position): Award
    {
        $award = new Award();
        $award->setName($name);
        $award->setPosition($position);

        return $award;
    }

    private function qualification(string $name, int $position): Qualification
    {
        $qualification = new Qualification();
        $qualification->setName($name);
        $qualification->setPosition($position);

        return $qualification;
    }

    private function rank(string $name, int $position): Rank
    {
        $rank = new Rank();
        $rank->setName($name);
        $rank->setAbbreviation(substr($name, 0, 3));
        $rank->setPosition($position);

        return $rank;
    }

    private function roster(string $name, int $position): Roster
    {
        $roster = new Roster();
        $roster->setName($name);
        $roster->setPosition($position);

        return $roster;
    }

    private function unit(string $name, int $position): Unit
    {
        $unit = new Unit();
        $unit->setName($name);
        $unit->setAbbreviation(substr($name, 0, 3));
        $unit->setPosition($position);

        return $unit;
    }

    private function expect(bool $ok, string $what): void
    {
        if (!$ok) {
            $this->failures[] = $what;
        }
    }

    private function section(string $title, callable $run): void
    {
        try {
            $run();
        } catch (Throwable $e) {
            $this->failures[] = $title . ': ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 300) . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
        }
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em;
    }
}
