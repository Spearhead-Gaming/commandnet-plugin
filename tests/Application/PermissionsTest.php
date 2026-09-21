<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\CommandNetPlugin;
use MajesticDev\CommandNet\Entity\Award;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\Document;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Entity\Enum\EquipmentType;
use MajesticDev\CommandNet\Entity\Equipment;
use MajesticDev\CommandNet\Entity\FormDefinition;
use MajesticDev\CommandNet\Entity\FormSubmission;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\Position;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\Roster;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\EnlistmentSettings;
use MajesticDev\CommandNet\Service\SquadXmlSettings;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every other application test runs as an administrator. This one asks what a member without
 * admin rights can reach: for each endpoint it checks that a member with no permissions and a
 * member holding every command-net permission except the required one are refused (403), that
 * a member holding only the required permission gets in, and that an anonymous visitor does not
 * see the page.
 *
 * POSTs are sent without a CSRF token: every controller checks the permission first, so a
 * refused member gets 403 and a permitted one is redirected with an "expired" flash, and
 * nothing is changed either way.
 */
class PermissionsTest extends WebTestCase
{
    use UserTrait;

    private KernelBrowser $client;
    private string $sfx;
    /** @var array<string, User> */
    private array $members = [];

    public function testEndpointsHonourPermissions(): void
    {
        $this->client = static::createClient();
        $this->sfx = substr(uniqid(), -6);

        $ids = $this->seed();
        $everything = $this->allPermissions();
        $failures = [];

        foreach ($this->rows($ids) as $row) {
            [$alternatives, $method, $url] = $row;
            // Forumify's CRUD screens refuse by redirecting to the list with an error flash.
            $redirects = $row[3] ?? false;
            $refusal = $redirects ? 302 : 403;
            $label = $method . ' ' . $url;
            // /admin sits behind Forumify's own ROLE_ADMIN gate, so staff reach it through a role
            // flagged as administrator and are then narrowed by the command-net permissions.
            $admin = str_starts_with($url, '/admin');

            $none = $this->respond($this->member('none', [], $admin), $method, $url);
            if ($none !== $refusal) {
                $failures[] = $label . ': a ' . ($admin ? 'staff member' : 'member') . ' with no permissions got ' . $none . ', expected ' . $refusal;
            }

            if (!$admin) {
                $anonymous = $this->respond(null, $method, $url);
                if ($anonymous === 200) {
                    $failures[] = $label . ': an anonymous visitor got 200';
                }
            }

            foreach ($alternatives as $permission) {
                $status = $this->respond($this->member('only ' . $permission, [$permission], $admin), $method, $url);
                if ($status === 403 || $status === 401 || $status >= 500 || ($redirects && $status !== 200)) {
                    $failures[] = $label . ': a member with only ' . $permission . ' got ' . $status . ', expected access';
                }
            }

            $others = array_values(array_diff($everything, $alternatives));
            $status = $this->respond($this->member('all but ' . implode(',', $alternatives), $others, $admin), $method, $url);
            if ($status !== $refusal) {
                $failures[] = $label . ': a member with every permission except ' . implode(' / ', $alternatives) . ' got ' . $status . ', expected ' . $refusal;
            }
        }

        // The admin panel itself: a member without an administrator role, even one holding every
        // command-net permission, and an anonymous visitor, are kept out.
        $panel = '/admin/command-net/ranks';
        $status = $this->respond($this->member('every permission, not staff', $everything), 'GET', $panel);
        if ($status !== 403) {
            $failures[] = 'GET ' . $panel . ': a non-administrator holding every permission got ' . $status . ', expected 403';
        }
        if ($this->respond(null, 'GET', $panel) === 200) {
            $failures[] = 'GET ' . $panel . ': an anonymous visitor got 200';
        }

        $this->assertSame([], $failures, "Permission problems:\n" . implode("\n", $failures));
    }

    public function testOpenPages(): void
    {
        $this->client = static::createClient();
        $this->sfx = substr(uniqid(), -6);
        $this->seed();

        $this->assertSame(200, $this->respond($this->member('none', []), 'GET', '/enlist'), 'Any signed-in member can open the enlistment page.');
        $this->assertNotSame(200, $this->respond(null, 'GET', '/enlist'), 'Anonymous visitors cannot.');
        $this->assertSame(200, $this->respond(null, 'GET', '/squad.xml'), 'The squad file is public, an Arma client cannot log in.');
        $this->assertSame(200, $this->respond(null, 'GET', '/squad.dtd'));
    }

    /**
     * @param array<string, int|string> $ids
     * @return array<int, array{0: array<string>, 1: string, 2: string, 3?: bool}> permissions (any one grants access), method, url, and true where a refusal is a redirect to the list
     */
    private function rows(array $ids): array
    {
        $p = 'command-net.';
        $a = $p . 'admin.';
        $rows = [
            [[$p . 'roster.view'], 'GET', '/roster'],
            [[$p . 'roster.view'], 'GET', '/roster/' . $ids['username']],
            [[$p . 'roster.view'], 'GET', '/units'],
            [[$p . 'qualifications.view'], 'GET', '/qualifications'],
            [[$p . 'operations.view'], 'GET', '/operations'],
            [[$p . 'operations.view'], 'GET', '/operations/' . $ids['operation']],
            [[$p . 'operations.rsvp'], 'POST', '/operations/' . $ids['operation'] . '/rsvp'],
            [[$p . 'operations.submit_aar'], 'GET', '/operations/' . $ids['operation'] . '/aar'],
            [[$a . 'operations.manage'], 'POST', '/operations/' . $ids['operation'] . '/attendance'],
            [[$p . 'attendance.view_own', $p . 'attendance.view_all'], 'GET', '/attendance'],
            [[$p . 'promotions.view'], 'GET', '/promotions'],
            [[$a . 'personnel.manage'], 'POST', '/promotions/' . $ids['profile'] . '/promote'],
            [[$p . 'forms.submit'], 'GET', '/forms'],
            [[$p . 'forms.submit'], 'GET', '/forms/' . $ids['form']],
            [[$p . 'courses.enroll'], 'GET', '/courses'],
            [[$p . 'courses.enroll'], 'GET', '/courses/class/' . $ids['class']],
            [[$p . 'courses.enroll'], 'POST', '/courses/class/' . $ids['class'] . '/enroll'],
            [[$p . 'courses.enroll'], 'POST', '/courses/class/' . $ids['class'] . '/withdraw'],
            [[$a . 'courses.manage'], 'POST', '/courses/class/' . $ids['class'] . '/results'],
            [[$p . 'reportin.submit'], 'POST', '/roster/report-in'],
            [[$a . 'reportin.manage'], 'POST', '/roster/report-in/999999/delete'],
            [[$a . 'personnel.manage'], 'GET', '/roster/' . $ids['username'] . '/assignment'],
            [[$a . 'personnel.manage'], 'POST', '/roster/' . $ids['username'] . '/assignment/999999/delete'],
            [[$a . 'personnel.manage'], 'POST', '/roster/' . $ids['username'] . '/service-record/999999/delete'],
            [[$a . 'awards.manage'], 'GET', '/roster/' . $ids['username'] . '/award'],
            [[$a . 'awards.manage'], 'POST', '/roster/' . $ids['username'] . '/award/999999/delete'],
            [[$a . 'qualifications.manage'], 'GET', '/roster/' . $ids['username'] . '/qualification'],
            [[$a . 'qualifications.manage'], 'POST', '/roster/' . $ids['username'] . '/qualification/999999/delete'],
            [[$a . 'personnel.discharge'], 'GET', '/admin/command-net/personnel/' . $ids['profile'] . '/discharge'],
            [[$a . 'awol.manage'], 'GET', '/admin/command-net/awol-settings'],
            [[$a . 'reportin.manage'], 'GET', '/admin/command-net/report-in-settings'],
            [[$a . 'enlistment.manage'], 'GET', '/admin/command-net/enlistment-settings'],
            [[$a . 'squadxml.manage'], 'GET', '/admin/command-net/squad-xml-settings'],
        ];

        // Admin catalogues: the list needs "view", opening a record needs "manage" (on the
        // enlistment and form review queues, opening a record is the review).
        $catalogues = [
            'ranks' => ['ranks', 'rank'], 'rank-groups' => ['ranks', 'group'], 'units' => ['units', 'unit'],
            'positions' => ['units', 'position'], 'awards' => ['awards', 'award'],
            'qualifications' => ['qualifications', 'qualification'], 'operations' => ['operations', 'operation'],
            'specialties' => ['specialties', 'specialty'], 'equipment' => ['equipment', 'equipment'],
            'documents' => ['documents', 'document'], 'forms' => ['forms', 'form'],
            'form-submissions' => ['forms', 'submission'], 'courses' => ['courses', 'course'],
            'course-classes' => ['courses', 'class'], 'rosters' => ['rosters', 'roster'],
            'personnel' => ['personnel', 'profile'], 'enlistment' => ['enlistment', 'application'],
        ];
        foreach ($catalogues as $path => [$area, $key]) {
            $rows[] = [[$a . $area . '.view'], 'GET', '/admin/command-net/' . $path];
            $rows[] = [[$a . $area . '.manage'], 'GET', '/admin/command-net/' . $path . '/' . $ids[$key] . '/edit', true];
        }

        return $rows;
    }

    /**
     * @return array<string>
     */
    private function allPermissions(): array
    {
        /** @var CommandNetPlugin $plugin */
        $plugin = (new ReflectionClass(CommandNetPlugin::class))->newInstanceWithoutConstructor();
        $all = [];
        $walk = function (array $tree, string $prefix) use (&$walk, &$all): void {
            foreach ($tree as $key => $value) {
                if (!is_array($value)) {
                    continue;
                }
                if (array_is_list($value)) {
                    foreach ($value as $action) {
                        $all[] = $prefix . $key . '.' . $action;
                    }
                    continue;
                }
                $walk($value, $prefix . $key . '.');
            }
        };
        $walk($plugin->getPermissions(), 'command-net.');

        return $all;
    }

    /**
     * A signed-in member whose only role carries exactly these permissions, and is flagged as an
     * administrator role when $admin is set. Reused between rows.
     *
     * @param array<string> $permissions
     */
    private function member(string $tag, array $permissions, bool $admin = false): User
    {
        $tag .= $admin ? ' (staff)' : '';
        if (isset($this->members[$tag])) {
            return $this->members[$tag];
        }

        $n = count($this->members);
        $em = $this->em();
        $role = new Role();
        $role->setTitle('Perm role ' . $n . ' ' . $this->sfx);
        $role->setPermissions($permissions);
        $role->setAdministrator($admin);
        $em->persist($role);
        $user = $this->createUser('pm' . $n . $this->sfx, 'pm' . $n . $this->sfx . '@example.org');
        $user = $em->find(User::class, $user->getId());
        $user->addRoleEntity($role);
        $em->flush();

        return $this->members[$tag] = $user;
    }

    private function respond(?User $user, string $method, string $url): int
    {
        if ($user === null) {
            $this->client->restart();
        } else {
            $this->client->loginUser($user);
        }
        $this->client->request($method, $url);

        return $this->client->getResponse()->getStatusCode();
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em;
    }

    /**
     * @return array<string, int|string>
     */
    private function seed(): array
    {
        $em = $this->em();
        $s = $this->sfx;
        $n = random_int(1000, 900000);

        $subject = $this->createUser('sub' . $s, 'sub' . $s . '@example.org');
        $applicant = $this->createUser('apl' . $s, 'apl' . $s . '@example.org');

        $group = new RankGroup();
        $group->setName('G' . $s);
        $rank = new Rank();
        $rank->setName('Private' . $s);
        $rank->setAbbreviation('PVT');
        $rank->setPosition($n);
        $rank->setGroup($group);
        $next = new Rank();
        $next->setName('Corporal' . $s);
        $next->setAbbreviation('CPL');
        $next->setPosition($n + 10);
        $next->setGroup($group);
        $unit = new Unit();
        $unit->setName('Unit' . $s);
        $unit->setAbbreviation('UN');
        $unit->setPosition(1);
        $equipment = new Equipment();
        $equipment->setName('Rifle' . $s);
        $equipment->setType(EquipmentType::PRIMARY_WEAPON);
        $position = new Position();
        $position->setTitle('Leader' . $s);
        $specialty = new Specialty();
        $specialty->setName('Medic' . $s);
        $specialty->setAbbreviation('MED');
        $document = new Document();
        $document->setName('Citation' . $s);
        $document->setContent('<p>{user_name}</p>');
        $qualification = new Qualification();
        $qualification->setName('Marksman' . $s);
        $qualification->setPosition($n);
        $award = new Award();
        $award->setName('Medal' . $s);
        $award->setPosition($n);
        $course = new Course();
        $course->setName('Course' . $s);
        $course->setDescription('Basics');
        $class = new CourseClass();
        $class->setCourse($course);
        $class->setStartsAt(new DateTime('+1 week'));
        $form = new FormDefinition();
        $form->setName('Form' . $s);
        $form->setFieldList('text | Reason | required');
        $roster = new Roster();
        $roster->setName('Roster' . $s);
        $roster->setPosition($n);
        $roster->addUnit($unit);
        $operation = new Operation();
        $operation->setTitle('Op' . $s);
        $operation->setStartDateTime(new DateTime('+2 days'));
        $profile = new SoldierProfile($subject);
        $profile->setRank($rank);
        $profile->setEnlistmentDate(new DateTime('-100 days'));

        foreach ([$group, $rank, $next, $unit, $equipment, $position, $specialty, $document, $qualification, $award, $course, $class, $form, $roster, $operation, $profile] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $application = new EnlistmentApplication($applicant);
        $application->setMotivation('I want to join.');
        $em->persist($application);
        $submission = new FormSubmission($form, $applicant, [['label' => 'Reason', 'value' => 'Holiday']]);
        $em->persist($submission);
        $em->flush();

        $container = static::getContainer();
        $container->get(SquadXmlSettings::class)->save(['enabled' => true, 'nick' => 'TST', 'name' => 'Test Squad']);
        $container->get(EnlistmentSettings::class)->save(['enabled' => true, 'instructions' => 'Read the rules']);

        return [
            'username' => 'sub' . $s, 'rank' => $rank->getId(), 'group' => $group->getId(), 'unit' => $unit->getId(),
            'position' => $position->getId(), 'award' => $award->getId(), 'qualification' => $qualification->getId(),
            'operation' => $operation->getId(), 'specialty' => $specialty->getId(), 'equipment' => $equipment->getId(),
            'document' => $document->getId(), 'form' => $form->getId(), 'submission' => $submission->getId(),
            'course' => $course->getId(), 'class' => $class->getId(), 'roster' => $roster->getId(),
            'profile' => $profile->getId(), 'application' => $application->getId(),
        ];
    }
}
