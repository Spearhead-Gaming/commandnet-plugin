<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Security\Voter\PermissionVoter;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Phase A acceptance criteria for patrols, driven through the real controllers, forms and
 * templates: a member with patrols.create posts a patrol and leads it, a member without it cannot,
 * the leader (and only the leader) marks attendance and files the AAR for their own patrol, an
 * unfiled patrol shows as overdue, and its attendees get no combat record until the AAR is filed.
 *
 * Like FlowTest, the kernel is not rebooted between requests: this test interleaves direct
 * entity-manager writes (creating the soldier, RSVPs) with HTTP requests, and rebooting would
 * hand later requests a different EntityManager than the one those writes used.
 */
class PatrolFlowTest extends WebTestCase
{
    use UserTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $sfx;

    public function testPatrolLifecycle(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->sfx = substr(uniqid(), -6);
        $viewer = ['command-net.operations.view', 'command-net.operations.rsvp'];

        $leader = $this->member('leader', [...$viewer, 'command-net.patrols.create']);
        $outsider = $this->member('outsider', [...$viewer, 'command-net.operations.submit_aar']);
        $rival = $this->member('rival', [...$viewer, 'command-net.patrols.create']);
        $noPermission = $this->member('none', $viewer);

        // By default every member can post: the migration puts patrols.create on the built-in
        // "user" role, and UserRolePermissionVoter applies that role to every account.
        $this->login($noPermission);
        $this->client->request('GET', '/patrols/new');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'A member with no role of their own can post through the "user" role.');

        // Take it off the "user" role and the same member is refused, so it can still be restricted.
        $userRole = $this->em->getRepository(Role::class)->findOneBy(['slug' => 'user']);
        $userRole->setPermissions(array_values(array_diff($userRole->getPermissions(), ['command-net.patrols.create'])));
        $this->em->flush();
        $this->login($noPermission);
        $this->client->request('GET', '/patrols/new');
        $this->assertSame(403, $this->client->getResponse()->getStatusCode(), 'Without the permission anywhere, a member must not post a patrol.');

        $this->login($leader);
        $crawler = $this->client->request('GET', '/patrols/new');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $form = $crawler->selectButton('Post patrol')->form();
        $title = 'Patrol ' . $this->sfx;
        $form['patrol[title]'] = $title;
        // Three days ago, so its AAR deadline (24 hours after it ends) has already passed.
        $form['patrol[startDateTime]'] = (new DateTime('-3 days 20:00'))->format('Y-m-d\TH:i');
        $form['patrol[endDateTime]'] = (new DateTime('-3 days 22:00'))->format('Y-m-d\TH:i');
        $form['patrol[location]'] = 'Everon';
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect(), 'Posting the patrol should redirect to it.');

        $patrol = $this->em->getRepository(Operation::class)->findOneBy(['title' => $title]);
        $this->assertNotNull($patrol);
        $this->assertSame(OperationType::PATROL, $patrol->getType());
        $this->assertSame($leader->getId(), $patrol->getLeader()?->getId(), 'The poster becomes the leader.');
        $id = $patrol->getId();

        // Unfiled and past its deadline: overdue on the patrol page and on the leader's own page.
        $this->client->request('GET', '/operations/' . $id);
        $this->assertStringContainsString('AAR overdue', (string)$this->client->getResponse()->getContent());
        $this->client->request('GET', '/patrols/mine');
        $this->assertStringContainsString('AAR overdue', (string)$this->client->getResponse()->getContent());
        $this->assertStringContainsString('File AAR', (string)$this->client->getResponse()->getContent());

        // Someone joins and is later marked as attending. The two GET requests above ran
        // Symfony's end-of-request service reset, which clears the entity manager's identity
        // map even without a kernel reboot - $patrol needs re-fetching before it can be used
        // in a new relation, or Doctrine sees it as a detached, uncascaded entity.
        $patrol = $this->em->find(Operation::class, $id);
        $soldierUser = $this->createUser('sol' . $this->sfx, 'sol' . $this->sfx . '@example.org');
        $group = new RankGroup();
        $group->setName('G' . $this->sfx);
        $rank = new Rank();
        $rank->setName('Private' . $this->sfx);
        $rank->setAbbreviation('PVT');
        $rank->setPosition(random_int(1000, 900000));
        $rank->setGroup($group);
        $soldier = new SoldierProfile($soldierUser);
        $soldier->setRank($rank);
        $soldier->setEnlistmentDate(new DateTime('-100 days'));
        $rsvp = new OperationRSVP($patrol, $soldier);
        $rsvp->setStatus(RsvpStatus::ATTENDING);
        $patrol->getRsvps()->add($rsvp);
        foreach ([$group, $rank, $soldier, $rsvp] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $soldierId = $soldier->getId();

        // The leader marks attendance with the button on the patrol page.
        $crawler = $this->client->request('GET', '/operations/' . $id);
        $node = $crawler->filter('form[action$="/operations/' . $id . '/attendance"]');
        $this->assertGreaterThan(0, $node->count(), 'The leader should see the attendance buttons on their own patrol.');
        $this->client->submit($node->first()->form(), ['soldier_id' => (string)$soldierId, 'attended' => '1']);
        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertTrue(
            $this->em->getRepository(OperationRSVP::class)->findOneBy(['soldier' => $soldierId])?->getAttended(),
            'The leader marking attendance is recorded.',
        );
        $this->assertSame([], $this->combatRecords($soldierId), 'No combat record until the AAR is filed.');

        // Nobody else's patrol, and no other event, is the leader's to mark.
        $rivalPatrol = $this->patrolLedBy($rival->getId(), 'Rival ' . $this->sfx);
        $operation = new Operation();
        $operation->setTitle('Op ' . $this->sfx);
        $operation->setStartDateTime(new DateTime('+2 days'));
        $this->em->persist($operation);
        $this->em->flush();
        foreach ([$rivalPatrol->getId(), $operation->getId()] as $otherId) {
            $this->client->request('POST', '/operations/' . $otherId . '/attendance', ['soldier_id' => (string)$soldierId, 'attended' => '1']);
            $this->assertSame(403, $this->client->getResponse()->getStatusCode(), 'The leader must not mark attendance on event ' . $otherId . '.');
        }
        $this->login($outsider);
        $this->client->request('POST', '/operations/' . $id . '/attendance', ['soldier_id' => (string)$soldierId, 'attended' => '1']);
        $this->assertSame(403, $this->client->getResponse()->getStatusCode(), 'A bystander must not mark attendance.');

        // Only the leader (or an attendee, or staff) can file a patrol's AAR; submit_aar alone is not enough.
        $this->client->request('GET', '/operations/' . $id . '/aar');
        $this->assertSame(403, $this->client->getResponse()->getStatusCode(), 'submit_aar alone must not file a patrol AAR.');
        $this->login($leader);
        $crawler = $this->client->request('GET', '/operations/' . $id . '/aar');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'The leader files their own patrol AAR without submit_aar.');
        $form = $crawler->selectButton('Submit AAR')->form();
        $form['operation_aar[summary]'] = 'We patrolled the road and returned.';
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect());

        // Filing it clears the overdue state and credits the attendee.
        $this->client->request('GET', '/operations/' . $id);
        $content = (string)$this->client->getResponse()->getContent();
        $this->assertStringContainsString('AAR filed', $content);
        $this->assertStringNotContainsString('AAR overdue', $content);
        $this->assertCount(1, $this->combatRecords($soldierId), 'The attendee is credited once the AAR is filed.');

        // With an AAR on the record the leader can no longer delete the patrol: no button, and a
        // forged request is refused. Staff still can, and the combat records go with the patrol.
        $crawler = $this->client->request('GET', '/operations/' . $id);
        $this->assertSame(0, $crawler->filter('form[action$="/patrols/' . $id . '/delete"]')->count(), 'No delete button for the leader once an AAR is filed.');
        $this->client->request('POST', '/patrols/' . $id . '/delete');
        $this->assertSame(403, $this->client->getResponse()->getStatusCode(), 'The leader must not delete a patrol that has an AAR.');

        $admin = $this->member('admin', [...$viewer, 'command-net.admin.operations.manage']);
        $this->login($admin);
        $crawler = $this->client->request('GET', '/operations/' . $id);
        $node = $crawler->filter('form[action$="/patrols/' . $id . '/delete"]');
        $this->assertGreaterThan(0, $node->count(), 'Staff can delete any patrol.');
        $this->client->submit($node->first()->form());
        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertNull($this->em->find(Operation::class, $id), 'The patrol is gone.');
        $this->assertSame([], $this->em->getRepository(OperationRSVP::class)->findBy(['soldier' => $soldierId]), 'Its sign-ups went with it.');
        $this->assertSame([], $this->combatRecords($soldierId), 'The combat records it earned are removed too, not left on the personnel file.');
    }

    public function testALeaderCanDeleteTheirOwnPatrolUntilItHasAnAar(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->sfx = substr(uniqid(), -6);
        $viewer = ['command-net.operations.view', 'command-net.operations.rsvp'];

        $leader = $this->member('leader', $viewer);
        $other = $this->member('other', $viewer);
        $admin = $this->member('admin', [...$viewer, 'command-net.admin.operations.manage']);
        $patrolId = $this->patrolLedBy($leader->getId(), 'Test patrol ' . $this->sfx)->getId();
        $operation = new Operation();
        $operation->setTitle('Op ' . $this->sfx);
        $operation->setStartDateTime(new DateTime('+2 days'));
        $this->em->persist($operation);
        $this->em->flush();
        $operationId = $operation->getId();

        // Someone else's patrol is not theirs to delete.
        $this->login($other);
        $crawler = $this->client->request('GET', '/operations/' . $patrolId);
        $this->assertSame(0, $crawler->filter('form[action$="/patrols/' . $patrolId . '/delete"]')->count());
        $this->client->request('POST', '/patrols/' . $patrolId . '/delete');
        $this->assertSame(403, $this->client->getResponse()->getStatusCode(), "Nobody else's patrol can be deleted.");

        // A forged request without the page's token does nothing, even for the leader.
        $this->login($leader);
        $this->client->request('POST', '/patrols/' . $patrolId . '/delete', ['_token' => 'forged']);
        $this->assertTrue($this->client->getResponse()->isRedirect('/operations/' . $patrolId), 'A bad token sends them back to the patrol.');
        $this->assertNotNull($this->em->find(Operation::class, $patrolId), 'A bad token must not delete anything.');

        // The leader deletes their own unfiled patrol from the button.
        $crawler = $this->client->request('GET', '/operations/' . $patrolId);
        $node = $crawler->filter('form[action$="/patrols/' . $patrolId . '/delete"]');
        $this->assertGreaterThan(0, $node->count(), 'The leader sees the delete button on their own unfiled patrol.');
        $this->client->submit($node->first()->form());
        $this->assertTrue($this->client->getResponse()->isRedirect('/patrols/mine'));
        $this->assertNull($this->em->find(Operation::class, $patrolId), 'The patrol is gone.');

        // Only patrols can be deleted this way, even by staff.
        $this->login($admin);
        $this->client->request('POST', '/patrols/' . $operationId . '/delete');
        $this->assertSame(404, $this->client->getResponse()->getStatusCode(), 'A non-patrol event is not deleted through the patrol route.');
        $this->assertNotNull($this->em->find(Operation::class, $operationId));
    }

    /**
     * PermissionVoter caches the first checked user's permissions for as long as the
     * container lives (see PermissionVoter::getPermissions()); with disableReboot() that's
     * this whole test, and it switches between several different members, so each login
     * clears that cache. FlowTest never hits this because it logs in one admin throughout,
     * and PermissionsTest avoids it by rebooting (a fresh voter) on every request instead.
     */
    private function login(User $user): void
    {
        $voter = static::getContainer()->get(PermissionVoter::class);
        (new ReflectionProperty($voter, 'permissions'))->setValue($voter, null);
        $this->client->loginUser($user);
    }

    /**
     * @return array<ServiceRecord>
     */
    private function combatRecords(int $soldierId): array
    {
        return $this->em->getRepository(ServiceRecord::class)->findBy(['soldier' => $soldierId, 'type' => ServiceRecordType::COMBAT]);
    }

    private function patrolLedBy(int $leaderId, string $title): Operation
    {
        $patrol = new Operation();
        $patrol->setType(OperationType::PATROL);
        $patrol->setTitle($title);
        $patrol->setStartDateTime(new DateTime('+2 days'));
        $patrol->setLeader($this->em->find(User::class, $leaderId));
        $this->em->persist($patrol);
        $this->em->flush();

        return $patrol;
    }

    /**
     * @param array<string> $permissions
     */
    private function member(string $tag, array $permissions): User
    {
        $role = new Role();
        $role->setTitle('Patrol test ' . $tag . ' ' . $this->sfx);
        $role->setPermissions($permissions);
        $this->em->persist($role);
        $user = $this->createUser($tag . $this->sfx, $tag . $this->sfx . '@example.org');
        $user->addRoleEntity($role);
        $this->em->flush();

        return $user;
    }
}
