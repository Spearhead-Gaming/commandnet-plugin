<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Award;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\FormDefinition;
use MajesticDev\CommandNet\Entity\FormSubmission;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\EnlistmentSettings;
use MajesticDev\CommandNet\Service\SquadXmlSettings;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Throwable;

/**
 * Seeds one of everything, logs in as an administrator and GETs every page, recording the status
 * of each and the exception behind any failure.
 */
class SmokeTest extends WebTestCase
{
    use UserTrait;

    public function testEveryPageRendersForAnAdministrator(): void
    {
        $client = static::createClient();
        $client->catchExceptions(false);
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $sfx = substr(uniqid(), -6);
        $admin = $this->createAdmin('adm' . $sfx, $sfx . 'a@example.org');
        $applicant = $this->createUser('app' . $sfx, $sfx . 'b@example.org');

        $group = new RankGroup();
        $group->setName('Enlisted');
        $rank = new Rank();
        $rank->setName('Sergeant');
        $rank->setAbbreviation('SGT');
        $rank->setPosition(1);
        $rank->setGroup($group);
        $higher = new Rank();
        $higher->setName('Captain');
        $higher->setAbbreviation('CPT');
        $higher->setPosition(2);
        $unit = new Unit();
        $unit->setName('Alpha');
        $unit->setAbbreviation('A');
        $unit->setPosition(1);
        $rifle = $this->equipment('M4A1', EquipmentType::PRIMARY_WEAPON);
        $pistol = $this->equipment('M9', EquipmentType::SECONDARY_WEAPON);
        $truck = $this->equipment('HMMWV', EquipmentType::VEHICLE);
        $unit->addVehicle($truck);
        $position = new Position();
        $position->setTitle('Squad Leader');
        $position->addPrimaryWeapon($rifle);
        $position->addSecondaryWeapon($pistol);
        $specialty = new Specialty();
        $specialty->setName('Combat Medic');
        $specialty->setAbbreviation('MED');
        $document = new Document();
        $document->setName('Citation');
        $document->setContent('<p>{user_name} received {record_title} on {record_date}</p>');
        $qualification = new Qualification();
        $qualification->setName('Marksman');
        $qualification->setPosition(1);
        $award = new Award();
        $award->setName('Medal');
        $award->setPosition(1);
        $course = new Course();
        $course->setName('Rifle Course');
        $course->setDescription('Basics');
        $course->addQualification($qualification);
        $class = new CourseClass();
        $class->setCourse($course);
        $class->setStartsAt(new DateTime('+1 week'));
        $form = new FormDefinition();
        $form->setName('Leave request');
        $form->setFieldList("text | Reason | required\nselect | Type | required | Short, Long\nboolean | Agree\ndate | From\nnumber | Days");
        $roster = new Roster();
        $roster->setName('Combat units');
        $roster->setPosition(1);
        $roster->addUnit($unit);
        $operation = new Operation();
        $operation->setTitle('Op Thunder');
        $operation->setStartDateTime(new DateTime('+2 days'));
        $operation->setUnit($unit);

        $profile = new SoldierProfile($admin);
        $profile->setRank($rank);
        $profile->setSpecialty($specialty);
        $profile->setCallsign('Viper');
        $profile->setSteamId('76561198000000001');
        $profile->setEnlistmentDate(new DateTime('-120 days'));

        foreach ([$group, $rank, $higher, $unit, $rifle, $pistol, $truck, $position, $specialty, $document, $qualification, $award, $course, $class, $form, $roster, $operation, $profile] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $assignment = new Assignment($profile, $unit);
        $assignment->setPosition($position);
        $profile->addAssignment($assignment);
        $em->persist($assignment);
        $record = new ServiceRecord($profile, ServiceRecordType::AWARD, 'Medal of Honor');
        $record->setDescription('Valor');
        $record->setDocument($document);
        $em->persist($record);
        $application = new EnlistmentApplication($applicant);
        $application->setMotivation('I want to join.');
        $em->persist($application);
        $submission = new FormSubmission($form, $applicant, [['label' => 'Reason', 'value' => 'Holiday']]);
        $em->persist($submission);
        $em->flush();

        $container->get(SquadXmlSettings::class)->save(['enabled' => true, 'nick' => 'TST', 'name' => 'Test Squad']);
        $container->get(EnlistmentSettings::class)->save(['enabled' => true, 'instructions' => 'Read the rules first.']);

        $ids = [
            'rank' => $rank->getId(), 'group' => $group->getId(), 'unit' => $unit->getId(), 'position' => $position->getId(),
            'award' => $award->getId(), 'qualification' => $qualification->getId(), 'operation' => $operation->getId(),
            'specialty' => $specialty->getId(), 'equipment' => $rifle->getId(), 'document' => $document->getId(),
            'form' => $form->getId(), 'submission' => $submission->getId(), 'course' => $course->getId(),
            'class' => $class->getId(), 'roster' => $roster->getId(), 'profile' => $profile->getId(),
            'application' => $application->getId(),
        ];

        $urls = [
            '/roster', '/roster?roster=' . $ids['roster'], '/roster/adm' . $sfx . '', '/roster/adm' . $sfx . '/award', '/roster/adm' . $sfx . '/qualification',
            '/roster/adm' . $sfx . '/assignment', '/units', '/qualifications', '/attendance', '/promotions', '/operations',
            '/operations/' . $ids['operation'], '/operations/' . $ids['operation'] . '/aar', '/enlist', '/forms',
            '/forms/' . $ids['form'], '/courses', '/courses/class/' . $ids['class'], '/squad.xml', '/squad.dtd',
        ];

        $edits = [
            'ranks' => 'rank', 'rank_groups' => 'group', 'units' => 'unit', 'positions' => 'position', 'awards' => 'award',
            'qualifications' => 'qualification', 'operations' => 'operation', 'specialties' => 'specialty',
            'equipment' => 'equipment', 'documents' => 'document', 'forms' => 'form', 'form_submissions' => 'submission',
            'courses' => 'course', 'course_classes' => 'class', 'rosters' => 'roster', 'personnel' => 'profile',
            'enlistment' => 'application',
        ];
        foreach ($edits as $name => $key) {
            $urls[] = '/admin/command-net/' . str_replace('_', '-', $name) . '/' . $ids[$key] . '/edit';
        }
        $urls[] = '/admin/command-net/personnel/' . $ids['profile'] . '/discharge';

        $router = $container->get('router');
        foreach ($router->getRouteCollection() as $name => $route) {
            if (str_starts_with($name, 'forumify_admin_command_net_') && !str_contains($route->getPath(), '{')
                && (in_array('GET', $route->getMethods(), true) || $route->getMethods() === [])) {
                $urls[] = $route->getPath();
            }
        }
        $urls = array_values(array_unique($urls));

        $client->loginUser($admin);
        $results = [];
        foreach ($urls as $url) {
            try {
                $client->request('GET', $url);
                $status = $client->getResponse()->getStatusCode();
                $note = '';
                if ($url === '/squad.xml' && $status === 200) {
                    $note = str_contains((string)$client->getResponse()->getContent(), '<!DOCTYPE squad SYSTEM "squad.dtd">') ? 'xml ok' : 'NO DOCTYPE';
                }
                if ($url === '/roster/adm' . $sfx . '' && $status === 200) {
                    $note = str_contains((string)$client->getResponse()->getContent(), 'Medal of Honor') ? 'record shown' : 'RECORD MISSING';
                }
            } catch (Throwable $e) {
                $status = 500;
                $note = get_class($e) . ': ' . substr($e->getMessage(), 0, 260) . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
            }
            $results[$url] = [$status, $note];
        }

        $lines = [];
        $bad = [];
        foreach ($results as $url => [$status, $note]) {
            $lines[] = sprintf('%d  %s  %s', $status, $url, $note);
            if ($status >= 400 || str_contains($note, 'MISSING') || str_contains($note, 'NO DOCTYPE')) {
                $bad[] = end($lines);
            }
        }
        $this->assertSame([], $bad, "Pages with problems:\n" . implode("\n", $bad));
    }

    private function equipment(string $name, EquipmentType $type): Equipment
    {
        $equipment = new Equipment();
        $equipment->setName($name);
        $equipment->setType($type);

        return $equipment;
    }
}
