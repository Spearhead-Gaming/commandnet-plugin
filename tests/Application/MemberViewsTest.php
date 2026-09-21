<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Award;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\CourseClassStudent;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\ReportIn;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierAward;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\SoldierQualification;
use MajesticDev\CommandNet\Entity\Unit;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * PermissionsTest checks who may open a page or submit an action. This checks what the page then
 * shows. For each page it lists the buttons, forms and details that depend on a permission, and
 * loads the page as members holding the permission to open it plus exactly one more: that member
 * must see that control and none of the others.
 *
 * A control is a list of alternatives, each a set of permissions that reveals it. Its selector is
 * a CSS selector, or "text:" followed by a string that must appear in the page.
 */
class MemberViewsTest extends WebTestCase
{
    use UserTrait;

    private KernelBrowser $client;
    private string $sfx;
    /** @var array<string, User> */
    private array $viewers = [];
    /** @var array<string> */
    private array $failures = [];

    public function testEachControlAppearsOnlyForItsPermission(): void
    {
        $this->client = static::createClient();
        $this->sfx = substr(uniqid(), -6);
        $ids = $this->seed();
        $p = 'command-net.';
        $a = $p . 'admin.';
        $user = $ids['username'];

        $this->page('personnel file', [$p . 'roster.view'], '/roster/' . $user, [
            'award button' => [[[$a . 'awards.manage']], 'a[href$="/roster/' . $user . '/award"]'],
            'qualification button' => [[[$a . 'qualifications.manage']], 'a[href$="/roster/' . $user . '/qualification"]'],
            'assignment button' => [[[$a . 'personnel.manage']], 'a[href$="/roster/' . $user . '/assignment"]'],
            'remove award' => [[[$a . 'awards.manage']], 'form[action*="/award/"][action$="/delete"]'],
            'remove qualification' => [[[$a . 'qualifications.manage']], 'form[action*="/qualification/"][action$="/delete"]'],
            'remove assignment' => [[[$a . 'personnel.manage']], 'form[action*="/assignment/"][action$="/delete"]'],
            'remove manual entry' => [[[$a . 'personnel.manage']], 'form[action*="/service-record/"]'],
            'last report in' => [[[$a . 'reportin.view']], 'text:Last Report In'],
            'remove report in' => [[[$a . 'reportin.view', $a . 'reportin.manage']], 'form[action*="/roster/report-in/"]'],
        ]);

        $this->page('roster', [$p . 'roster.view'], '/roster', [
            'attendance link' => [[[$p . 'attendance.view_own'], [$p . 'attendance.view_all']], 'a[href$="/attendance"]'],
            'promotions link' => [[[$p . 'promotions.view']], 'a[href$="/promotions"]'],
            'report in button' => [[[$p . 'reportin.submit']], 'form[action$="/roster/report-in"]'],
            // "eported in" is deliberate: it is inside both "Reported in <date>" and "Never reported in".
            'last report in on the cards' => [[[$a . 'reportin.view']], 'text:eported in'],
        ]);

        $this->page('promotions', [$p . 'promotions.view'], '/promotions', [
            'promote button' => [[[$a . 'personnel.manage']], 'form[action*="/promote"]'],
        ]);

        $this->page('operation', [$p . 'operations.view'], '/operations/' . $ids['operation'], [
            'RSVP buttons' => [[[$p . 'operations.rsvp']], 'form[action$="/rsvp"]'],
            'submit report link' => [[[$p . 'operations.submit_aar']], 'a[href$="/operations/' . $ids['operation'] . '/aar"]'],
            'remove report' => [[[$a . 'operations.manage']], 'form[action*="/aar/"]'],
            'mark attendance' => [[[$a . 'operations.manage']], 'form[action$="/attendance"]'],
            'soldiers who have not responded' => [[[$a . 'operations.manage']], 'text:' . $ids['expected']],
        ]);

        $this->page('course class', [$p . 'courses.enroll'], '/courses/class/' . $ids['class'], [
            'record results' => [[[$a . 'courses.manage']], 'form[action$="/results"]'],
        ]);

        $this->page('attendance', [$p . 'attendance.view_own'], '/attendance', [
            'everyone\'s attendance' => [[[$p . 'attendance.view_all']], 'text:' . $ids['expected']],
        ]);

        $this->page('admin personnel record', [$a . 'personnel.manage'], '/admin/command-net/personnel/' . $ids['profile'] . '/edit', [
            'discharge button' => [[[$a . 'personnel.discharge']], 'a[href$="/discharge"]'],
        ], staff: true);

        // The author of a report can remove it without the manage permission.
        $em = $this->em();
        $role = new Role();
        $role->setTitle('Author role ' . $this->sfx);
        $role->setPermissions([$p . 'operations.view']);
        $em->persist($role);
        $author = $em->find(User::class, $ids['subject']);
        $author->addRoleEntity($role);
        $em->flush();
        $this->client->loginUser($author);
        $crawler = $this->client->request('GET', '/operations/' . $ids['operation']);
        if ($crawler->filter('form[action*="/aar/"]')->count() === 0) {
            $this->failures[] = 'operation: the author of a report cannot remove it';
        }

        $this->assertSame([], $this->failures, "Member views differ from the permissions:\n" . implode("\n", $this->failures));
    }

    /**
     * @param array<string> $base permissions needed to open the page
     * @param array<string, array{0: array<array<string>>, 1: string}> $controls
     */
    private function page(string $name, array $base, string $url, array $controls, bool $staff = false): void
    {
        $sets = [['base only', $base]];
        foreach ($controls as $control => [$alternatives]) {
            foreach ($alternatives as $needs) {
                $sets[] = ['with ' . implode(' + ', $needs) . ' (' . $control . ')', array_values(array_unique([...$base, ...$needs]))];
            }
        }

        foreach ($sets as [$label, $permissions]) {
            $seen = $this->seen($name, $label, $permissions, $staff, $url, $controls);
            foreach ($controls as $control => [$alternatives]) {
                $expected = false;
                foreach ($alternatives as $needs) {
                    $expected = $expected || array_diff($needs, $permissions) === [];
                }
                if (isset($seen[$control]) && $seen[$control] !== $expected) {
                    $this->failures[] = sprintf(
                        '%s, %s: "%s" is %s but should be %s',
                        $name,
                        $label,
                        $control,
                        $seen[$control] ? 'shown' : 'hidden',
                        $expected ? 'shown' : 'hidden',
                    );
                }
            }
        }
    }

    /**
     * @param array<string> $permissions
     * @param array<string, array{0: array<array<string>>, 1: string}> $controls
     * @return array<string, bool>
     */
    private function seen(string $name, string $label, array $permissions, bool $staff, string $url, array $controls): array
    {
        $this->client->loginUser($this->viewer($permissions, $staff));
        $crawler = $this->client->request('GET', $url);
        $status = $this->client->getResponse()->getStatusCode();
        if ($status !== 200) {
            $this->failures[] = sprintf('%s, %s: the page returned %d', $name, $label, $status);

            return [];
        }

        $html = (string)$this->client->getResponse()->getContent();
        $seen = [];
        foreach ($controls as $control => [, $selector]) {
            $seen[$control] = str_starts_with($selector, 'text:')
                ? str_contains($html, substr($selector, 5))
                : $crawler->filter($selector)->count() > 0;
        }

        return $seen;
    }

    /**
     * An enlisted member whose only role carries exactly these permissions (and is flagged as an
     * administrator role for the admin panel when $staff is set). Reused between pages.
     *
     * @param array<string> $permissions
     */
    private function viewer(array $permissions, bool $staff): User
    {
        sort($permissions);
        $key = implode(',', $permissions) . ($staff ? ' staff' : '');
        if (isset($this->viewers[$key])) {
            return $this->viewers[$key];
        }

        $n = count($this->viewers);
        $em = $this->em();
        $role = new Role();
        $role->setTitle('View role ' . $n . ' ' . $this->sfx);
        $role->setPermissions($permissions);
        $role->setAdministrator($staff);
        $em->persist($role);
        $created = $this->createUser('vw' . $n . $this->sfx, 'vw' . $n . $this->sfx . '@example.org');
        $user = $em->find(User::class, $created->getId());
        $user->addRoleEntity($role);
        $profile = new SoldierProfile($user);
        $profile->setEnlistmentDate(new DateTime('-100 days'));
        $em->persist($profile);
        $em->flush();

        return $this->viewers[$key] = $user;
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
        $expected = $this->createUser('exp' . $s, 'exp' . $s . '@example.org');

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
        $award = new Award();
        $award->setName('Medal' . $s);
        $award->setPosition($n);
        $qualification = new Qualification();
        $qualification->setName('Marksman' . $s);
        $qualification->setPosition($n);
        $course = new Course();
        $course->setName('Course' . $s);
        $course->setDescription('Basics');
        $class = new CourseClass();
        $class->setCourse($course);
        $class->setStartsAt(new DateTime('-1 day'));
        $operation = new Operation();
        $operation->setTitle('Op' . $s);
        $operation->setStartDateTime(new DateTime('-1 day'));
        $profile = new SoldierProfile($subject);
        $profile->setRank($rank);
        $profile->setEnlistmentDate(new DateTime('-200 days'));
        $expectedProfile = new SoldierProfile($expected);
        $expectedProfile->setEnlistmentDate(new DateTime('-200 days'));
        foreach ([$group, $rank, $next, $unit, $award, $qualification, $course, $class, $operation, $profile, $expectedProfile] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $assignment = new Assignment($profile, $unit);
        $assignment->setIsPrimary(true);
        $assignment->setStartDate(new DateTime('-100 days'));
        $reportIn = new ReportIn($profile);
        $profile->setLastReportIn($reportIn->getReportedAt());
        $report = new OperationAAR($operation, $subject);
        $report->setSummary('Summary');
        $report->setObjectivesMet(true);
        foreach ([
            $assignment, $reportIn, $report,
            new SoldierAward($profile, $award), new SoldierQualification($profile, $qualification),
            new ServiceRecord($profile, ServiceRecordType::NOTE, 'A manual note'),
            new CourseClassStudent($class, $expectedProfile),
        ] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [
            'username' => 'sub' . $s, 'expected' => 'exp' . $s, 'subject' => $subject->getId(), 'profile' => $profile->getId(),
            'operation' => $operation->getId(), 'class' => $class->getId(),
        ];
    }
}
