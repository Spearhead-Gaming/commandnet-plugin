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
 * The unit self-service flow: a commander with command-net.units.manage_own creates a squad and
 * lands on that squad's own page (not back on the unit), commanding a unit or an ancestor is what
 * grants access (holding the permission alone is not enough), and an ORBAT outline can be imported
 * by a commander (under their unit) or by staff (top-level). Kernel is not rebooted between
 * requests, as in PatrolFlowTest.
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

        $unit = $this->unit('Unit');
        $unit->setCommander($this->profile($commander));
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

    public function testOnlyACommanderOfTheUnitOrAnAncestorMayManageItsSquads(): void
    {
        $this->boot();
        $parentCommander = $this->member('parent');
        $outsider = $this->member('outsider');
        $noPermission = $this->member('none', []);

        $parent = $this->unit('Parent');
        $parent->setCommander($this->profile($parentCommander));
        $child = $this->unit('Child');
        $child->setParent($parent);
        $squad = new Squad($child);
        $squad->setName('Squad ' . $this->sfx);
        $this->em->persist($squad);
        $this->em->flush();
        $childUrl = '/command-net/my-units/' . $child->getId();
        $squadUrl = '/command-net/my-squads/' . $squad->getId();

        foreach ([$childUrl, $childUrl . '/edit', $squadUrl, $squadUrl . '/edit'] as $url) {
            $this->login($parentCommander);
            $this->client->request('GET', $url);
            $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'The parent unit\'s commander manages ' . $url);

            $this->login($outsider);
            $this->client->request('GET', $url);
            $this->assertSame(403, $this->client->getResponse()->getStatusCode(), 'Holding manage_own is not enough without commanding the unit: ' . $url);
        }

        $this->login($noPermission);
        $this->client->request('GET', '/command-net/my-units');
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testCommanderImportsAnOutlineUnderTheirOwnUnit(): void
    {
        $this->boot();
        $commander = $this->member('cmdr');
        $unit = $this->unit('Unit');
        $unit->setCommander($this->profile($commander));
        $this->em->flush();
        $unitId = $unit->getId();

        $this->login($commander);
        $crawler = $this->client->request('GET', '/command-net/my-units/' . $unitId . '/import');
        $form = $crawler->selectButton('Import')->form();
        $form['orbat_outline[outline]'] = "Det {$this->sfx}\n  = Lead {$this->sfx}\n  + Squad {$this->sfx}\n    = Medic {$this->sfx}";
        $this->client->submit($form);
        $this->assertResponseRedirects('/command-net/my-units/' . $unitId);

        $det = $this->em->getRepository(Unit::class)->findOneBy(['name' => 'Det ' . $this->sfx]);
        $this->assertNotNull($det);
        $this->assertSame($unitId, $det->getParent()?->getId(), 'The imported unit nests under the unit it was imported into.');
        $this->assertNotNull($this->em->getRepository(Squad::class)->findOneBy(['name' => 'Squad ' . $this->sfx]));
        $this->assertCount(1, $det->getPositions(), 'The billet under the unit is linked to it.');
    }

    public function testAdminImportsAnOutlineAsTopLevelUnits(): void
    {
        $this->boot();
        $staff = $this->member('staff', ['command-net.admin.units.manage'], true);

        $this->login($staff);
        $crawler = $this->client->request('GET', '/admin/command-net/units/import');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $form = $crawler->selectButton('Import')->form();
        $form['orbat_import[outline]'] = "Top {$this->sfx}\n  + Squad {$this->sfx}";
        $this->client->submit($form);
        $this->assertResponseRedirects();

        $top = $this->em->getRepository(Unit::class)->findOneBy(['name' => 'Top ' . $this->sfx]);
        $this->assertNotNull($top);
        $this->assertNull($top->getParent());
        $this->assertNotNull($this->em->getRepository(Squad::class)->findOneBy(['name' => 'Squad ' . $this->sfx]));
    }

    private function unit(string $name): Unit
    {
        $unit = new Unit();
        $unit->setName($name . ' ' . $this->sfx);
        $unit->setAbbreviation(substr($name, 0, 1) . $this->sfx);
        $this->em->persist($unit);

        return $unit;
    }

    private function profile(User $user): SoldierProfile
    {
        $profile = new SoldierProfile($user);
        $profile->setEnlistmentDate(new DateTime('-100 days'));
        $this->em->persist($profile);

        return $profile;
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

    /**
     * @param array<string>|null $permissions defaults to command-net.units.manage_own
     */
    private function member(string $tag, ?array $permissions = null, bool $admin = false): User
    {
        $role = new Role();
        $role->setTitle('Squad test ' . $tag . ' ' . $this->sfx);
        $role->setPermissions($permissions ?? ['command-net.units.manage_own']);
        $role->setAdministrator($admin);
        $this->em->persist($role);
        $user = $this->createUser($tag . $this->sfx, $tag . $this->sfx . '@example.org');
        $user->addRoleEntity($role);
        $this->em->flush();

        return $user;
    }
}
