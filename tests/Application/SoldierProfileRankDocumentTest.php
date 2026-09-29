<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The admin personnel form's "Rank change document" picker is an unmapped field, read from the
 * submitted request rather than from the form. This drives the real form to prove the document
 * lands on the promotion record it produces, and that leaving it blank attaches nothing.
 */
class SoldierProfileRankDocumentTest extends WebTestCase
{
    use UserTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    public function testAdminRankChangeAttachesTheChosenDocumentToItsRecord(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $sfx = substr(uniqid(), -6);
        $n = random_int(1000, 900000);

        $group = new RankGroup();
        $group->setName('G' . $sfx);
        $private = $this->rank('Private' . $sfx, $n, $group);
        $corporal = $this->rank('Corporal' . $sfx, $n + 10, $group);
        $sergeant = $this->rank('Sergeant' . $sfx, $n + 20, $group);
        $document = new Document();
        $document->setName('Citation ' . $sfx);
        $document->setContent('<p>{user_name}</p>');
        $soldier = new SoldierProfile($this->createUser('sub' . $sfx, 'sub' . $sfx . '@example.org'));
        $soldier->setEnlistmentDate(new DateTime('-100 days'));
        $soldier->setRank($private);
        foreach ([$group, $private, $corporal, $sergeant, $document, $soldier] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $soldierId = $soldier->getId();
        $ids = ['corporal' => (string)$corporal->getId(), 'sergeant' => (string)$sergeant->getId(), 'document' => (string)$document->getId()];
        $documentId = $document->getId();

        $this->client->loginUser($this->staff($sfx));

        $this->editRank($soldierId, $ids['corporal'], $ids['document']);
        $records = $this->promotions($soldierId);
        $this->assertCount(1, $records, 'The rank change wrote one promotion record.');
        $this->assertSame($documentId, $records[0]->getDocument()?->getId(), 'The chosen document is attached to it.');

        $this->editRank($soldierId, $ids['sergeant'], '');
        $records = $this->promotions($soldierId);
        $this->assertCount(2, $records);
        $this->assertNull($records[1]->getDocument(), 'A blank picker attaches nothing.');
    }

    private function editRank(int $soldierId, string $rankId, string $documentId): void
    {
        $crawler = $this->client->request('GET', '/admin/command-net/personnel/' . $soldierId . '/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filterXPath('//select[contains(@name,"[document]")]/ancestor::form[1]')->form();
        $form['soldier_profile[rank]'] = $rankId;
        $form['soldier_profile[document]'] = $documentId;
        $this->client->submit($form);
        $this->assertResponseRedirects();
        $this->em->clear();
    }

    /**
     * @return list<ServiceRecord>
     */
    private function promotions(int $soldierId): array
    {
        /** @var list<ServiceRecord> $records */
        $records = $this->em->getRepository(ServiceRecord::class)->findBy(
            ['soldier' => $soldierId, 'type' => ServiceRecordType::PROMOTION],
            ['id' => 'ASC'],
        );

        return $records;
    }

    private function rank(string $name, int $position, RankGroup $group): Rank
    {
        $rank = new Rank();
        $rank->setName($name);
        $rank->setAbbreviation(substr($name, 0, 3));
        $rank->setPosition($position);
        $rank->setGroup($group);

        return $rank;
    }

    private function staff(string $sfx): User
    {
        $role = new Role();
        $role->setTitle('Personnel staff ' . $sfx);
        $role->setPermissions(['command-net.admin.personnel.view', 'command-net.admin.personnel.manage']);
        $role->setAdministrator(true);
        $this->em->persist($role);
        $user = $this->createUser('stf' . $sfx, 'stf' . $sfx . '@example.org');
        $user = $this->em->find(User::class, $user->getId());
        $user->addRoleEntity($role);
        $this->em->flush();

        return $user;
    }
}
