<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Security\Voter\PermissionVoter;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Attendance review: a unit commander reviews and corrects soldiers in their unit but not another
 * unit's, and cannot correct themselves; a member reads only their own history; community managers
 * reach everyone. Kernel is not rebooted between requests, as in the other flow tests.
 */
class AttendanceReviewTest extends WebTestCase
{
    use UserTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $sfx;

    public function testReviewIsScopedToWhatEachViewerMayReadAndChange(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->sfx = substr(uniqid(), -6);

        $alpha = $this->unit('Alpha');
        $bravo = $this->unit('Bravo');
        $commander = $this->user('cmdr', ['command-net.units.manage_own']);
        $commanderProfile = $this->profile($commander, $alpha);
        $alpha->setCommander($commanderProfile);
        $inAlpha = $this->profile($this->user('alpha', []), $alpha);
        $inBravo = $this->profile($this->user('bravo', ['command-net.attendance.view_own']), $bravo);
        $operation = new Operation();
        $operation->setTitle('Op ' . $this->sfx);
        $operation->setStartDateTime(new DateTime('-2 days'));
        $em->persist($operation);
        $rsvps = [];
        foreach ([$commanderProfile, $inAlpha, $inBravo] as $soldier) {
            $rsvp = new OperationRSVP($operation, $soldier);
            $rsvp->setAttended(false);
            $operation->getRsvps()->add($rsvp);
            $em->persist($rsvp);
            $rsvps[$soldier->getId()] = $rsvp;
        }
        $em->flush();
        $name = static fn (SoldierProfile $s): string => $s->getUser()->getUsername();
        $review = '/attendance/review';

        // The commander sees their own unit's soldiers and no one else's.
        $this->login($commander);
        $this->client->request('GET', $review);
        $this->assertResponseIsSuccessful();
        $html = (string)$this->client->getResponse()->getContent();
        $this->assertStringContainsString($inAlpha->getUser()->getDisplayName(), $html);
        $this->assertStringNotContainsString($inBravo->getUser()->getDisplayName(), $html, 'Another unit is out of scope.');
        $this->client->request('GET', $review . '/' . $name($inBravo));
        $this->assertResponseStatusCodeSame(403);
        // Holding only units.manage_own, they still reach /attendance and find the way in from there.
        $this->client->request('GET', '/attendance');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Leadership review', (string)$this->client->getResponse()->getContent());

        // ...and corrects an in-unit no-show through the real form.
        $crawler = $this->client->request('GET', $review . '/' . $name($inAlpha));
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save')->form();
        $form['attended'] = '1';
        $this->client->submit($form);
        $this->assertResponseRedirects();
        $this->em->clear();
        $this->assertTrue($this->em->find(OperationRSVP::class, $rsvps[$inAlpha->getId()]->getId())?->getAttended(), 'The correction was saved.');

        // They cannot correct another unit's soldier, or themselves.
        $this->client->request('POST', $review . '/' . $name($inBravo) . '/rsvp/' . $rsvps[$inBravo->getId()]->getId());
        $this->assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', $review . '/' . $name($commanderProfile));
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->selectButton('Save'), 'No correction controls on their own record.');
        $this->client->request('POST', $review . '/' . $name($commanderProfile) . '/rsvp/' . $rsvps[$commanderProfile->getId()]->getId());
        $this->assertResponseStatusCodeSame(403);

        // A member with only view_own reads their own history and nothing else.
        $member = $this->em->find(User::class, $inBravo->getUser()->getId());
        $this->assertNotNull($member);
        $this->login($member);
        $this->client->request('GET', $review);
        $this->assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', $review . '/' . $name($inBravo));
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->selectButton('Save'), 'Members cannot change their own attendance.');
        $this->client->request('GET', $review . '/' . $name($inAlpha));
        $this->assertResponseStatusCodeSame(403);

        // Community managers reach everyone, with controls.
        $staff = $this->user('staff', ['command-net.admin.attendance.view', 'command-net.admin.attendance.manage']);
        $this->login($staff);
        $crawler = $this->client->request('GET', $review . '/' . $name($inBravo));
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->selectButton('Save'));

        // Units admins may edit every unit, but that is not an attendance grant.
        $unitsAdmin = $this->user('unitsadmin', ['command-net.units.manage_own', 'command-net.admin.units.manage']);
        $this->login($unitsAdmin);
        $this->client->request('GET', $review);
        $this->assertResponseStatusCodeSame(403, 'admin.units.manage must not open attendance review for a non-commander.');
        $this->client->request('GET', $review . '/' . $name($inBravo));
        $this->assertResponseStatusCodeSame(403);
    }

    private function unit(string $name): Unit
    {
        $unit = new Unit();
        $unit->setName($name . ' ' . $this->sfx);
        $unit->setAbbreviation(substr($name, 0, 1) . $this->sfx);
        $this->em->persist($unit);

        return $unit;
    }

    private function profile(User $user, Unit $unit): SoldierProfile
    {
        $profile = new SoldierProfile($user);
        $profile->setEnlistmentDate(new DateTime('-100 days'));
        $assignment = new Assignment($profile, $unit);
        $assignment->setIsPrimary(true);
        $profile->addAssignment($assignment);
        $this->em->persist($profile);
        $this->em->persist($assignment);
        $this->em->flush();

        return $profile;
    }

    /**
     * @param array<string> $permissions
     */
    private function user(string $tag, array $permissions): User
    {
        $role = new Role();
        $role->setTitle('Attendance test ' . $tag . ' ' . $this->sfx);
        $role->setPermissions($permissions);
        $this->em->persist($role);
        $user = $this->createUser($tag . $this->sfx, $tag . $this->sfx . '@example.org');
        $user = $this->em->find(User::class, $user->getId());
        $user->addRoleEntity($role);
        $this->em->flush();

        return $user;
    }

    private function login(User $user): void
    {
        $voter = static::getContainer()->get(PermissionVoter::class);
        (new ReflectionProperty($voter, 'permissions'))->setValue($voter, null);
        $this->client->loginUser($user);
    }
}
