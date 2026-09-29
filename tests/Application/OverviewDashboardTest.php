<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Command Net admin overview: gated on personnel.view, and its "needs attention" panel
 * surfaces a pending application, an AWOL soldier and a unit with no commander. The test DB may
 * hold other rows, so this asserts the seeded names appear rather than exact totals.
 */
class OverviewDashboardTest extends WebTestCase
{
    use UserTrait;

    public function testNeedsAttentionPanelShowsSeededItems(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $sfx = substr(uniqid(), -6);

        $awolUser = $this->createUser('awol' . $sfx, 'awol' . $sfx . '@example.org');
        $awol = new SoldierProfile($awolUser);
        $awol->setEnlistmentDate(new DateTime('-100 days'));
        $awol->setStatus(SoldierStatus::AWOL);
        $unit = new Unit();
        $unit->setName('Headless ' . $sfx);
        $unit->setAbbreviation('H' . $sfx);
        $applicant = $this->createUser('apl' . $sfx, 'apl' . $sfx . '@example.org');
        $application = new EnlistmentApplication($applicant);
        $application->setMotivation('I want to join.');
        foreach ([$awol, $unit, $application] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($this->staff($em, $sfx, ['command-net.admin.personnel.view']));
        $client->request('GET', '/admin/command-net');

        $this->assertResponseIsSuccessful();
        $html = (string)$client->getResponse()->getContent();
        $this->assertStringContainsString('Needs Attention', $html);
        $this->assertStringContainsString($awolUser->getDisplayName(), $html, 'The AWOL soldier is listed.');
        $this->assertStringContainsString('Headless ' . $sfx, $html, 'The commanderless unit is listed.');
        $this->assertStringContainsString('pending application', $html);
    }

    public function testOverviewIsRefusedWithoutPersonnelView(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($this->staff($em, substr(uniqid(), -6), ['command-net.admin.units.view']));
        $client->request('GET', '/admin/command-net');

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string> $permissions
     */
    private function staff(EntityManagerInterface $em, string $sfx, array $permissions): User
    {
        $role = new Role();
        $role->setTitle('Overview staff ' . $sfx);
        $role->setPermissions($permissions);
        $role->setAdministrator(true);
        $em->persist($role);
        $user = $this->createUser('stf' . $sfx, 'stf' . $sfx . '@example.org');
        $user = $em->find(User::class, $user->getId());
        $user->addRoleEntity($role);
        $em->flush();

        return $user;
    }
}
