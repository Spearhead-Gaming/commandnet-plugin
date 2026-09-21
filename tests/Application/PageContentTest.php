<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use DOMDocument;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\ReportIn;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\SquadXmlSettings;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

/**
 * What the pages actually say and do, where the other application tests only check that they
 * load: the specialty, Loadout card and record types on a personnel file, HTML in a member's name
 * inside a document, the roster with and without rosters, the squad file against its DTD, the
 * Rank form saving, what a discharged soldier is refused, and the Report In task being scheduled.
 * Each section reports its own failures so one broken page does not hide the others.
 */
class PageContentTest extends WebTestCase
{
    use UserTrait;

    private const string SCRIPT_NAME = '<script>alert(1)</script>';

    private KernelBrowser $client;
    private string $sfx;
    /** @var array<string> */
    private array $failures = [];

    public function testPagesShowAndDoWhatTheyShould(): void
    {
        $this->client = static::createClient();
        $this->sfx = substr(uniqid(), -6);
        $ids = $this->seed();

        $this->section('roster without rosters and specialty on the roster row', fn () => $this->rosterWithoutRosters($ids));
        $this->section('personnel file', fn () => $this->personnelFile($ids));
        $this->section('rosters: tabs, membership and order', fn () => $this->rosters($ids));
        $this->section('squad file against its DTD', fn () => $this->squadFile());
        $this->section('Rank form saves', fn () => $this->rankForm($ids));
        $this->section('discharged soldier', fn () => $this->discharged($ids));
        $this->section('scheduler', fn () => $this->scheduler());

        $this->assertSame([], $this->failures, "Pages differ from what they should show:\n" . implode("\n", $this->failures));
    }

    /**
     * @param array<string, int|string> $ids
     */
    private function rosterWithoutRosters(array $ids): void
    {
        $html = $this->get($ids['admin'], '/roster');
        $tabs = $this->client->getCrawler()->filter('a[href*="roster?roster="]')->count();
        $this->expect($tabs === 0, 'with no rosters defined the roster page has no tabs (found ' . $tabs . ')');
        $this->expect(str_contains($html, (string)$ids['otherName']), 'with no rosters defined every soldier is listed');
        $this->expect(preg_match('/&middot;\s*' . preg_quote((string)$ids['abbreviation'], '/') . '/', $html) === 1, 'the specialty abbreviation is on the roster row');
    }

    /**
     * @param array<string, int|string> $ids
     */
    private function personnelFile(array $ids): void
    {
        $html = $this->get($ids['admin'], '/roster/' . $ids['username']);
        $crawler = $this->client->getCrawler();

        $this->expect(str_contains($html, (string)$ids['specialtyName']), 'the specialty name is on the personnel file');
        foreach (['Loadout', 'Primary weapons', 'Secondary weapons', 'Vehicles', $ids['rifle'], $ids['pistol'], $ids['truck']] as $text) {
            $this->expect(str_contains($html, (string)$text), 'the Loadout card shows "' . $text . '"');
        }

        $types = $crawler->filter('td.text-small')->each(static fn ($cell) => trim($cell->text()));
        $this->expect(in_array('AWOL', $types, true), 'an AWOL record is listed with the type "AWOL" (types shown: ' . implode(', ', array_unique($types)) . ')');

        $this->expect(!str_contains($html, self::SCRIPT_NAME), 'a name containing a script tag is not written raw into the page');
        $this->expect(str_contains($html, 'alert(1)'), 'the name still appears (escaped) in the rendered document');

        $other = $this->get($ids['admin'], '/roster/' . $ids['otherUsername']);
        $this->expect(!str_contains($other, '>Loadout<'), 'a soldier with no position or vehicles has no Loadout card');
    }

    /**
     * @param array<string, int|string> $ids
     */
    private function rosters(array $ids): void
    {
        $em = $this->em();
        $first = new Roster();
        $first->setName('First' . $this->sfx);
        $first->setPosition(10);
        $first->addUnit($em->find(Unit::class, $ids['unitA']));
        $second = new Roster();
        $second->setName('Second' . $this->sfx);
        $second->setPosition(20);
        $second->addUnit($em->find(Unit::class, $ids['unitB']));
        $em->persist($first);
        $em->persist($second);
        $em->flush();
        $firstId = $first->getId();
        $secondId = $second->getId();

        $html = $this->get($ids['admin'], '/roster');
        $this->expect($this->tabIds() === [$firstId, $secondId], 'the tabs follow the roster order (' . implode(',', $this->tabIds()) . ')');
        $this->expect(str_contains($html, 'alert(1)') && !str_contains($html, (string)$ids['otherName']), 'the first roster lists only its own units');

        $html = $this->get($ids['admin'], '/roster?roster=' . $secondId);
        $this->expect(str_contains($html, (string)$ids['otherName']) && !str_contains($html, 'alert(1)'), 'the second roster lists only its own units');

        $em = $this->em();
        $em->find(Roster::class, $firstId)->setPosition(30);
        $em->flush();
        $this->get($ids['admin'], '/roster');
        $this->expect($this->tabIds() === [$secondId, $firstId], 'reordering the rosters reorders the tabs (' . implode(',', $this->tabIds()) . ')');
    }

    private function squadFile(): void
    {
        $this->client->restart();
        $this->client->request('GET', '/squad.dtd');
        $dtd = (string)$this->client->getResponse()->getContent();
        $this->client->request('GET', '/squad.xml');
        $xml = (string)$this->client->getResponse()->getContent();

        $path = sys_get_temp_dir() . '/squad-' . $this->sfx . '.dtd';
        file_put_contents($path, $dtd);
        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadXML(str_replace('SYSTEM "squad.dtd"', 'SYSTEM "' . $path . '"', $xml), LIBXML_DTDLOAD);
        $valid = $loaded && $document->validate();
        $errors = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        unlink($path);

        $this->expect($valid, 'the squad file is valid against the DTD it serves' . ($errors === [] ? '' : ': ' . implode('; ', array_slice($errors, 0, 3))));
        $this->expect($loaded && $document->getElementsByTagName('member')->length >= 2, 'the squad file lists the soldiers');
    }

    /**
     * @param array<string, int|string> $ids
     */
    private function rankForm(array $ids): void
    {
        $this->as((int)$ids['admin']);
        $status = $this->submit('/admin/command-net/ranks/' . $ids['rank'] . '/edit', '[abbreviation]', [
            'name' => 'Renamed' . $this->sfx, 'payGrade' => 'E-9',
        ]);
        $rank = $this->em()->find(Rank::class, $ids['rank']);
        $this->expect($status === 302, 'saving the Rank form redirects (' . $status . ')');
        $this->expect($rank?->getName() === 'Renamed' . $this->sfx && $rank->getPayGrade() === 'E-9', 'the Rank form saved the new values');
    }

    /**
     * @param array<string, int|string> $ids
     */
    private function discharged(array $ids): void
    {
        $operation = '/operations/' . $ids['operation'];
        $rsvp = $operation . '/rsvp';

        $this->get($ids['discharged'], $operation);
        $this->expect($this->client->getCrawler()->filter('form[action$="/rsvp"]')->count() === 0, 'a discharged soldier is not offered RSVP buttons');
        $this->get($ids['discharged'], '/roster');
        $this->expect($this->client->getCrawler()->filter('form[action$="/roster/report-in"]')->count() === 0, 'a discharged soldier is not offered the Report In button');

        $this->as((int)$ids['discharged']);
        $this->client->request('POST', $rsvp, ['status' => 'attending']);
        // The page lower-cases "RSVP" when it shows the message, so the match ignores case.
        $this->expect(stripos($this->followed(), 'Only enlisted personnel can RSVP') !== false, 'a discharged soldier is refused when they RSVP');
        $this->client->request('POST', '/roster/report-in');
        $this->expect(stripos($this->followed(), 'Only enlisted personnel can report in') !== false, 'a discharged soldier is refused when they report in');

        $em = $this->em();
        $this->expect($em->getRepository(OperationRSVP::class)->count(['soldier' => $ids['dischargedProfile']]) === 0, 'no RSVP was saved for the discharged soldier');
        $this->expect($em->getRepository(ReportIn::class)->count(['soldier' => $ids['dischargedProfile']]) === 0, 'no report in was saved for the discharged soldier');

        // The same request from an active soldier gets past that check (and stops at the missing CSRF token).
        $this->as((int)$ids['active']);
        $this->client->request('POST', $rsvp, ['status' => 'attending']);
        $this->expect(!str_contains($this->followed(), 'Only enlisted personnel'), 'an active soldier is not told they are not enlisted');
    }

    private function scheduler(): void
    {
        $tester = new CommandTester((new Application(static::$kernel))->find('debug:scheduler'));
        $exit = $tester->execute([]);
        $output = $tester->getDisplay();
        $this->expect($exit === 0 && str_contains($output, 'command-net:report-in:run-checks'), 'the Report In command is in the scheduler (output: ' . trim(substr($output, 0, 300)) . ')');
    }

    private function followed(): string
    {
        $this->client->followRedirect();

        return (string)$this->client->getResponse()->getContent();
    }

    /**
     * @return array<int>
     */
    private function tabIds(): array
    {
        return array_map(
            static fn (string $href) => (int)substr($href, (int)strrpos($href, '=') + 1),
            $this->client->getCrawler()->filter('a[href*="roster?roster="]')->each(static fn ($a) => (string)$a->attr('href')),
        );
    }

    private function get(int|string $userId, string $url): string
    {
        $this->as((int)$userId);
        $this->client->request('GET', $url);
        if ($this->client->getResponse()->getStatusCode() !== 200) {
            $this->failures[] = $url . ' returned ' . $this->client->getResponse()->getStatusCode();
        }

        return (string)$this->client->getResponse()->getContent();
    }

    private function as(int $userId): void
    {
        $this->client->loginUser($this->em()->find(User::class, $userId));
    }

    /**
     * Fills in the form that holds the field whose name contains $anchor, by field-name suffix, and submits it.
     *
     * @param array<string, string> $values
     */
    private function submit(string $url, string $anchor, array $values): int
    {
        $crawler = $this->client->request('GET', $url);
        $node = $crawler->filterXPath('//*[self::input or self::textarea or self::select][contains(@name,"' . $anchor . '")]/ancestor::form[1]');
        if ($node->count() === 0) {
            throw new RuntimeException('no form with a ' . $anchor . ' field on ' . $url . ' (status ' . $this->client->getResponse()->getStatusCode() . ')');
        }
        $form = $node->form();
        foreach ($values as $suffix => $value) {
            $name = null;
            foreach (array_keys($form->all()) as $candidate) {
                if (str_ends_with((string)$candidate, '[' . $suffix . ']')) {
                    $name = (string)$candidate;
                }
            }
            if ($name === null) {
                throw new RuntimeException('no field ' . $suffix . ' on ' . $url . ', have: ' . implode(', ', array_keys($form->all())));
            }
            $form[$name]->setValue($value);
        }
        $this->client->submit($form);

        return $this->client->getResponse()->getStatusCode();
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

    private function member(string $name, ?SoldierStatus $status): User
    {
        $em = $this->em();
        $role = new Role();
        $role->setTitle($name . ' role ' . $this->sfx);
        $role->setPermissions(['command-net.roster.view', 'command-net.operations.view', 'command-net.operations.rsvp', 'command-net.reportin.submit']);
        $em->persist($role);
        $created = $this->createUser($name . $this->sfx, $name . $this->sfx . '@example.org');
        $user = $em->find(User::class, $created->getId());
        $user->addRoleEntity($role);
        if ($status !== null) {
            $profile = new SoldierProfile($user);
            $profile->setEnlistmentDate(new DateTime('-100 days'));
            $profile->setStatus($status);
            if ($status === SoldierStatus::DISCHARGED) {
                $profile->setDischargeDate(new DateTime('-1 day'));
            }
            $em->persist($profile);
        }
        $em->flush();

        return $user;
    }

    /**
     * @return array<string, int|string>
     */
    private function seed(): array
    {
        $s = $this->sfx;
        $n = random_int(1000, 900000);
        $abbreviation = 'Z' . random_int(100, 999);

        $admin = $this->createAdmin('adm' . $s, 'adm' . $s . '@example.org');
        $active = $this->member('act', SoldierStatus::ACTIVE);
        $discharged = $this->member('dis', SoldierStatus::DISCHARGED);
        $em = $this->em();
        $subject = $this->createUser('sub' . $s, 'sub' . $s . '@example.org');
        $subject = $em->find(User::class, $subject->getId());
        $subject->setDisplayName(self::SCRIPT_NAME);
        $other = $this->createUser('oth' . $s, 'oth' . $s . '@example.org');
        $other = $em->find(User::class, $other->getId());
        $other->setDisplayName('Other Soldier ' . $s);

        $rank = new Rank();
        $rank->setName('Private' . $s);
        $rank->setAbbreviation('PVT');
        $rank->setPosition($n);
        $unitA = new Unit();
        $unitA->setName('UnitA' . $s);
        $unitA->setAbbreviation('UA');
        $unitA->setPosition(1);
        $unitB = new Unit();
        $unitB->setName('UnitB' . $s);
        $unitB->setAbbreviation('UB');
        $unitB->setPosition(2);
        $rifle = $this->equipment('Rifle' . $s, EquipmentType::PRIMARY_WEAPON);
        $pistol = $this->equipment('Pistol' . $s, EquipmentType::SECONDARY_WEAPON);
        $truck = $this->equipment('Truck' . $s, EquipmentType::VEHICLE);
        $unitA->addVehicle($truck);
        $position = new Position();
        $position->setTitle('Leader' . $s);
        $position->addPrimaryWeapon($rifle);
        $position->addSecondaryWeapon($pistol);
        $specialty = new Specialty();
        $specialty->setName('Medic' . $s);
        $specialty->setAbbreviation($abbreviation);
        $document = new Document();
        $document->setName('Citation' . $s);
        $document->setContent('<p>Issued to {user_name}</p>');
        $operation = new Operation();
        $operation->setTitle('Op' . $s);
        $operation->setStartDateTime(new DateTime('+2 days'));

        $subjectProfile = new SoldierProfile($subject);
        $subjectProfile->setRank($rank);
        $subjectProfile->setSpecialty($specialty);
        $subjectProfile->setSteamId('76561198000000001');
        $subjectProfile->setEnlistmentDate(new DateTime('-200 days'));
        $otherProfile = new SoldierProfile($other);
        $otherProfile->setSteamId('76561198000000002');
        $otherProfile->setEnlistmentDate(new DateTime('-200 days'));
        foreach ([$rank, $unitA, $unitB, $rifle, $pistol, $truck, $position, $specialty, $document, $operation, $subjectProfile, $otherProfile] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $assignment = new Assignment($subjectProfile, $unitA);
        $assignment->setPosition($position);
        $assignment->setIsPrimary(true);
        $assignment->setStartDate(new DateTime('-100 days'));
        $otherAssignment = new Assignment($otherProfile, $unitB);
        $otherAssignment->setIsPrimary(true);
        $otherAssignment->setStartDate(new DateTime('-100 days'));
        $awol = new ServiceRecord($subjectProfile, ServiceRecordType::AWOL, 'Flagged AWOL');
        $award = new ServiceRecord($subjectProfile, ServiceRecordType::AWARD, 'Medal');
        $award->setDocument($document);
        foreach ([$assignment, $otherAssignment, $awol, $award] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        /** @var SquadXmlSettings $squad */
        $squad = static::getContainer()->get(SquadXmlSettings::class);
        $squad->save(['enabled' => true, 'nick' => 'TST', 'name' => 'Test Squad']);

        $dischargedProfile = $em->getRepository(SoldierProfile::class)->findOneBy(['user' => $discharged->getId()]);
        $dischargedProfileId = $dischargedProfile->getId();
        // The first request reuses this entity manager, whose soldiers were built in memory without
        // their assignments; clearing it makes that request read them back from the database.
        $em->clear();

        return [
            'admin' => $admin->getId(), 'active' => $active->getId(), 'discharged' => $discharged->getId(),
            'dischargedProfile' => $dischargedProfileId,
            'username' => 'sub' . $s, 'otherUsername' => 'oth' . $s, 'otherName' => 'Other Soldier ' . $s,
            'rank' => $rank->getId(), 'unitA' => $unitA->getId(), 'unitB' => $unitB->getId(), 'operation' => $operation->getId(),
            'abbreviation' => $abbreviation, 'specialtyName' => 'Medic' . $s,
            'rifle' => 'Rifle' . $s, 'pistol' => 'Pistol' . $s, 'truck' => 'Truck' . $s,
        ];
    }

    private function equipment(string $name, EquipmentType $type): Equipment
    {
        $equipment = new Equipment();
        $equipment->setName($name);
        $equipment->setType($type);

        return $equipment;
    }
}
