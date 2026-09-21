<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Core\Notification\NotificationTypeCollection;
use Forumify\Core\Repository\NotificationRepository;
use Forumify\Forum\Component\Notifications;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Admin\Components\Table\AwardTable;
use MajesticDev\CommandNet\Admin\Components\Table\QualificationTable;
use MajesticDev\CommandNet\Admin\Components\Table\RankTable;
use MajesticDev\CommandNet\Admin\Components\Table\RosterDefinitionTable;
use MajesticDev\CommandNet\Admin\Components\Table\UnitTable;
use MajesticDev\CommandNet\Entity\Award;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\AwolService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Throwable;

/**
 * Two things that happen through live components rather than pages: reordering rows in the admin
 * tables, and a member seeing a notification in the site. The components are built and called
 * directly, acting as a signed-in user, so the checks are on what they do rather than on the
 * live-component request plumbing. Each section reports its own failures.
 *
 * Every user gets a kernel of their own: the permission voter remembers the first user it is
 * asked about for the life of a kernel.
 */
class LiveComponentsTest extends KernelTestCase
{
    use UserTrait;

    private string $sfx;
    /** @var array<string> */
    private array $failures = [];
    private int $users = 0;

    public function testReorderingAndNotificationsThroughLiveComponents(): void
    {
        static::bootKernel();
        $this->sfx = substr(uniqid(), -6);
        $n = random_int(1000, 900000);

        $tables = [
            AwardTable::class => ['awards', fn (string $name, int $position) => $this->award($name, $position)],
            QualificationTable::class => ['qualifications', fn (string $name, int $position) => $this->qualification($name, $position)],
            RankTable::class => ['ranks', fn (string $name, int $position) => $this->rank($name, $position)],
            RosterDefinitionTable::class => ['rosters', fn (string $name, int $position) => $this->roster($name, $position)],
            UnitTable::class => ['units', fn (string $name, int $position) => $this->unit($name, $position)],
        ];
        foreach ($tables as $table => [$area, $make]) {
            $this->section(substr($table, (int)strrpos($table, '\\') + 1) . ' reorders', fn () => $this->reorder($table, $area, $make, $n));
        }
        $this->section('a notification reaches the member in the site', fn () => $this->notification());

        $this->assertSame([], $this->failures, "Live components differ from what they should do:\n" . implode("\n", $this->failures));
    }

    /**
     * @param class-string<AbstractDoctrineTable> $table
     * @param callable(string, int): object $make
     */
    private function reorder(string $table, string $area, callable $make, int $n): void
    {
        $name = substr($table, (int)strrpos($table, '\\') + 1);
        $this->signedOut();
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

        $viewer = $this->member(['command-net.admin.' . $area . '.view']);
        $manager = $this->member(['command-net.admin.' . $area . '.view', 'command-net.admin.' . $area . '.manage']);
        $original = [$firstId, $secondId, $thirdId];

        $this->move($table, $viewer, $secondId, 'up');
        $this->expect($this->order($class, $original) === $original, $name . ': a member who can only view the table cannot reorder it');

        $this->move($table, $manager, $secondId, 'up');
        $this->expect($this->order($class, $original) === [$secondId, $firstId, $thirdId], $name . ': moving a row up swaps it with the one above');

        $this->move($table, $manager, $secondId, 'down');
        $this->expect($this->order($class, $original) === $original, $name . ': moving it down again restores the order');

        $this->move($table, $manager, $firstId, 'up');
        $this->expect($this->order($class, $original) === $original, $name . ': moving the top row up changes nothing');
    }

    private function notification(): void
    {
        $this->signedOut();
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

        $shown = $this->bell($recipientId);
        $this->expect(in_array('Report in soon', array_column($shown, 'title'), true), 'the recipient sees the notification title in the bell');
        $this->expect(in_array('You have 3 day(s) left to report in.', array_column($shown, 'text'), true), 'the recipient sees the notification text');
        $this->expect(!in_array('Report in soon', array_column($this->bell($otherId), 'title'), true), 'another member does not see it');
    }

    /**
     * What the platform's bell lists for this user: the title and text shown for each notification.
     *
     * @return array<int, array{title: string, text: string}>
     */
    private function bell(int $userId): array
    {
        $this->actAs($userId);
        $container = static::getContainer();
        /** @var NotificationRepository $repository */
        $repository = $container->get(NotificationRepository::class);
        /** @var NotificationTypeCollection $types */
        $types = $container->get(NotificationTypeCollection::class);
        $component = new Notifications($repository, $types);
        $component->setContainer($container);

        $generic = new GenericNotificationType($container->get('translator'));
        $shown = [];
        foreach ($component->getNotifications() as $notification) {
            $shown[] = ['title' => $generic->getTitle($notification), 'text' => $generic->getDescription($notification)];
        }

        return $shown;
    }

    /**
     * @param class-string<AbstractDoctrineTable> $table
     */
    private function move(string $table, User $user, int $id, string $direction): void
    {
        $this->actAs($user->getId());
        $component = new $table();
        $component->setServices($this->em(), static::getContainer()->get(Security::class));
        $component->changePosition($id, $direction);
    }

    /**
     * A fresh kernel with nobody signed in. Creating roles while a user is signed in runs the audit
     * log listener for that user, which loops, so seeding always happens signed out.
     */
    private function signedOut(): void
    {
        static::ensureKernelShutdown();
        static::bootKernel();
    }

    /**
     * Signs the user in on a fresh kernel, so the permission voter starts with no memory of anyone.
     */
    private function actAs(int $userId): void
    {
        static::ensureKernelShutdown();
        static::bootKernel();
        $user = $this->em()->find(User::class, $userId);
        static::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
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
