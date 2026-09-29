<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Security\Voter\PermissionVoter;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Squad;
use MajesticDev\CommandNet\Entity\Unit;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A unit commander with command-net.units.manage_own creates a squad through the self-service
 * flow and lands on that squad's own page (not back on the unit); a member who does not command
 * the unit is refused. Kernel is not rebooted between requests, as in PatrolFlowTest.
 */
class UnitSelfServiceFlowTest extends WebTestCase
{
    use UserTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $sfx;

    public function testCommanderCreatesSquadAndLandsOnIt(): void
    {
        $this->boot();
        $commander = $this->member('cmdr');
        $outsider = $this->member('outsider');

        $unit = new Unit();
        $unit->setName('Unit ' . $this->sfx);
        $unit->setAbbreviation('U' . $this->sfx);
        $profile = new SoldierProfile($commander);
        $profile->setEnlistmentDate(new DateTime('-100 days'));
        $unit->setCommander($profile);
        $this->em->persist($profile);
        $this->em->persist($unit);
        $this->em->flush();
        $unitId = $unit->getId();

        $this->login($outsider);
        $this->client->request('GET', '/command-net/my-units/' . $unitId . '/create-squad');
        $this->assertSame(403, $this->client->getResponse()->getStatusCode(), 'Only the unit\'s commander may create a squad.');

        $this->login($commander);
        $crawler = $this->client->request('GET', '/command-net/my-units/' . $unitId . '/create-squad');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $form = $crawler->selectButton('Create Squad')->form();
        $form['squad_self_service[name]'] = 'Squad ' . $this->sfx;
        $this->client->submit($form);

        $squad = $this->em->getRepository(Squad::class)->findOneBy(['name' => 'Squad ' . $this->sfx]);
        $this->assertNotNull($squad, 'The squad was persisted.');
        $this->assertResponseRedirects('/command-net/my-squads/' . $squad->getId());
    }

    private function boot(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->sfx = substr(uniqid(), -6);
    }

    private function login(User $user): void
    {
        $voter = static::getContainer()->get(PermissionVoter::class);
        (new ReflectionProperty($voter, 'permissions'))->setValue($voter, null);
        $this->client->loginUser($user);
    }

    private function member(string $tag): User
    {
        $role = new Role();
        $role->setTitle('Squad test ' . $tag . ' ' . $this->sfx);
        $role->setPermissions(['command-net.units.manage_own']);
        $this->em->persist($role);
        $user = $this->createUser($tag . $this->sfx, $tag . $this->sfx . '@example.org');
        $user->addRoleEntity($role);
        $this->em->flush();

        return $user;
    }
}
