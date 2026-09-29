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
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The print view of a service record's document: filled in for its soldier, only for members who
 * may see personnel files, and only under the record's own soldier's URL.
 */
class ServiceRecordDocumentTest extends WebTestCase
{
    use UserTrait;

    public function testPrintViewRendersTheDocumentForAuthorisedViewersOnly(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $sfx = substr(uniqid(), -6);

        $subject = $this->createUser('sub' . $sfx, 'sub' . $sfx . '@example.org');
        $other = $this->createUser('oth' . $sfx, 'oth' . $sfx . '@example.org');
        $document = new Document();
        $document->setName('Citation ' . $sfx);
        $document->setContent('<p>Promoted: {user_name} / {record_title}</p>');
        $soldier = new SoldierProfile($subject);
        $soldier->setEnlistmentDate(new DateTime('-100 days'));
        $withDocument = new ServiceRecord($soldier, ServiceRecordType::PROMOTION, 'Corporal');
        $withDocument->setDocument($document);
        $without = new ServiceRecord($soldier, ServiceRecordType::PROMOTION, 'Sergeant');
        foreach ([$document, $soldier, $withDocument, $without] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $url = fn (string $username, ServiceRecord $record): string => '/roster/' . $username . '/service-record/' . $record->getId() . '/document';

        $client->loginUser($this->member($em, $sfx, ['command-net.roster.view']));
        $client->request('GET', $url($subject->getUsername(), $withDocument));
        $this->assertResponseIsSuccessful();
        $html = (string)$client->getResponse()->getContent();
        $this->assertStringContainsString('Promoted: ' . $subject->getDisplayName() . ' / Corporal', $html, 'Placeholders are filled for the soldier and record.');
        $this->assertStringContainsString('window.print()', $html);

        $client->request('GET', $url($other->getUsername(), $withDocument));
        $this->assertResponseStatusCodeSame(404, "A record can't be opened under another member's URL.");

        $client->request('GET', $url($subject->getUsername(), $without));
        $this->assertResponseStatusCodeSame(404, 'A record with no document has no print view.');

        $client->loginUser($this->member($em, $sfx . 'x', []));
        $client->request('GET', $url($subject->getUsername(), $withDocument));
        $this->assertResponseStatusCodeSame(403, 'Without roster.view the personnel file, and its documents, are off limits.');
    }

    /**
     * @param array<string> $permissions
     */
    private function member(EntityManagerInterface $em, string $tag, array $permissions): User
    {
        $role = new Role();
        $role->setTitle('Doc viewer ' . $tag);
        $role->setPermissions($permissions);
        $em->persist($role);
        $user = $this->createUser('viewer' . $tag, 'viewer' . $tag . '@example.org');
        $user = $em->find(User::class, $user->getId());
        $user->addRoleEntity($role);
        $em->flush();

        return $user;
    }
}
