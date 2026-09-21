<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Application;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Testing\Traits\UserTrait;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Course;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\CourseClassStudent;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Entity\Enum\CourseResult;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\FormDefinition;
use MajesticDev\CommandNet\Entity\FormSubmission;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\Qualification;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\RankGroup;
use MajesticDev\CommandNet\Entity\ReportIn;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\SoldierQualification;
use MajesticDev\CommandNet\Entity\Specialty;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\AwolSettings;
use MajesticDev\CommandNet\Service\EnlistmentSettings;
use MajesticDev\CommandNet\Service\ReportInService;
use MajesticDev\CommandNet\Service\ReportInSettings;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Drives the state-changing flows through the real controllers, forms and CSRF-protected
 * buttons, then reads the database to see what actually happened. Every flow runs even if an
 * earlier one fails, and the failure message lists each check's result.
 */
class FlowTest extends WebTestCase
{
    use UserTrait;

    /** @var array<string> */
    private array $lines = [];
    private bool $failed = false;
    private EntityManagerInterface $em;
    private KernelBrowser $client;

    public function testStateChangingFlows(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->client->catchExceptions(false);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $s = substr(uniqid(), -6);
        $n = random_int(1000, 900000);

        $adminUser = $this->createAdmin('adm' . $s, $s . 'a@example.org');
        $applicant = $this->createUser('app' . $s, $s . 'b@example.org');
        $decliner = $this->createUser('dec' . $s, $s . 'c@example.org');
        $promotee = $this->createUser('pro' . $s, $s . 'd@example.org');
        $mover = $this->createUser('mov' . $s, $s . 'e@example.org');
        $leaver = $this->createUser('lev' . $s, $s . 'f@example.org');

        $role = function (string $title) use ($em, $s): Role {
            $r = new Role();
            $r->setTitle($title . ' ' . $s);
            $em->persist($r);
            return $r;
        };
        $rankRole1 = $role('R1');
        $rankRole2 = $role('R2');
        $unitRole1 = $role('U1');
        $unitRole2 = $role('U2');
        $specRole = $role('SP');
        $awolRole = $role('AWOL');

        $group = new RankGroup();
        $group->setName('G' . $s);
        $rank0 = $this->rank('Recruit' . $s, $n, $group, null);
        $rank1 = $this->rank('Private' . $s, $n + 10, $group, $rankRole1);
        $rank2 = $this->rank('Corporal' . $s, $n + 20, $group, $rankRole2);
        $rank9 = $this->rank('General' . $s, $n + 900, null, null);
        $u1 = $this->unit('Unit1' . $s, $unitRole1);
        $u2 = $this->unit('Unit2' . $s, $unitRole2);
        $spec = new Specialty();
        $spec->setName('Spec' . $s);
        $spec->setAbbreviation('SP');
        $spec->setRole($specRole);
        $qual = new Qualification();
        $qual->setName('Q' . $s);
        $qual->setPosition($n);
        $course1 = new Course();
        $course1->setName('Course1 ' . $s);
        $course1->setDescription('first');
        $course1->addQualification($qual);
        $course2 = new Course();
        $course2->setName('Course2 ' . $s);
        $course2->setDescription('second');
        $course2->addPrerequisite($course1);
        $course2->setMinimumRank($rank9);
        $class1 = new CourseClass();
        $class1->setCourse($course1);
        $class1->setStartsAt(new DateTime('+1 week'));
        $class2 = new CourseClass();
        $class2->setCourse($course2);
        $class2->setStartsAt(new DateTime('+1 week'));
        $op = new Operation();
        $op->setTitle('Op ' . $s);
        $op->setStartDateTime(new DateTime('-1 hour'));
        $form = new FormDefinition();
        $form->setName('Form ' . $s);
        $form->setFieldList("text | Reason | required\nselect | Kind | required | Short, Long\nboolean | Agree\ndate | From\nnumber | Days");
        foreach ([$group, $u1, $u2, $spec, $qual, $course1, $course2, $class1, $class2, $op, $form] as $e) {
            $em->persist($e);
        }
        $em->flush();

        $adminProfile = $this->profile($adminUser, $rank1);
        $promoProfile = $this->profile($promotee, $rank1);
        $moveProfile = $this->profile($mover, $rank1);
        $leaveProfile = $this->profile($leaver, $rank1);
        $leaveProfile->setSpecialty($spec);
        $leaver->addRoleEntity($rankRole1);
        $leaver->addRoleEntity($unitRole1);
        $leaver->addRoleEntity($specRole);
        $promotee->addRoleEntity($rankRole1);
        $mover->addRoleEntity($unitRole1);
        $em->flush();
        foreach ([$moveProfile, $leaveProfile] as $p) {
            $a = new Assignment($p, $u1);
            $a->setIsPrimary(true);
            $a->setStartDate(new DateTime('-30 days'));
            $em->persist($a);
        }
        $em->flush();

        /** @var EnlistmentSettings $settings */
        $settings = static::getContainer()->get(EnlistmentSettings::class);
        $settings->save(['enabled' => true, 'defaultRank' => $rank0->getId(), 'defaultUnit' => $u2->getId(), 'instructions' => 'Read the rules']);

        /** @var AwolSettings $awolSettings */
        $awolSettings = static::getContainer()->get(AwolSettings::class);
        $awolSettings->save(['enabled' => true, 'missThreshold' => 1, 'role' => $awolRole->getId()]);

        $ids = [
            'awolrole' => $awolRole->getId(),
            'admin' => $adminUser->getId(), 'applicant' => $applicant->getId(), 'decliner' => $decliner->getId(),
            'promotee' => $promotee->getId(), 'mover' => $mover->getId(), 'leaver' => $leaver->getId(),
            'r0' => $rank0->getId(), 'r1' => $rank1->getId(), 'r2' => $rank2->getId(),
            'role1' => $rankRole1->getId(), 'role2' => $rankRole2->getId(), 'urole1' => $unitRole1->getId(),
            'urole2' => $unitRole2->getId(), 'srole' => $specRole->getId(), 'u1' => $u1->getId(), 'u2' => $u2->getId(),
            'spec' => $spec->getId(), 'qual' => $qual->getId(), 'course1' => $course1->getId(), 'class1' => $class1->getId(),
            'class2' => $class2->getId(), 'op' => $op->getId(), 'form' => $form->getId(),
            'pAdmin' => $adminProfile->getId(), 'pPromo' => $promoProfile->getId(), 'pMove' => $moveProfile->getId(),
            'pLeave' => $leaveProfile->getId(),
        ];
        $em->clear();

        $this->flow('Enlistment: apply, accept', fn () => $this->enlistment($ids, true));
        $this->flow('Enlistment: apply, decline', fn () => $this->enlistment($ids, false));
        $this->flow('Promotion from /promotions', fn () => $this->promotion($ids));
        $this->flow('RSVP, attendance, report in', fn () => $this->operation($ids));
        $this->flow('Transfer and delete assignment (unit roles)', fn () => $this->transfer($ids, $s));
        $this->flow('Specialty change (role)', fn () => $this->specialty($ids));
        $this->flow('Discharge and re-enlist', fn () => $this->discharge($ids));
        $this->flow('Form: submit, review, edit definition', fn () => $this->forms($ids));
        $this->flow('Courses: enrol, refusal, results, no double processing', fn () => $this->courses($ids));
        $this->flow('Report In: command, warning, AWOL flag, report in', fn () => $this->reportInEnforcement($ids));

        $this->assertFalse($this->failed, "Some flows failed:\n" . implode("\n", $this->lines));
    }

    /**
     * @param array<string, int> $ids
     */
    private function enlistment(array $ids, bool $accept): void
    {
        $who = $accept ? 'applicant' : 'decliner';
        $this->as($ids[$who]);
        $status = $this->submit('/enlist', '[motivation]', [
            'callsign' => $accept ? 'Rook' : 'Nope',
            'steamId' => $accept ? '76561198000000009' : '76561198000000008',
            'motivation' => 'I want to join.',
        ]);
        $application = $this->em->getRepository(EnlistmentApplication::class)->findOneBy(['user' => $ids[$who]]);
        $this->check('application submitted (POST ' . $status . ')', $application?->getStatus() === ApplicationStatus::PENDING);
        if ($application === null) {
            return;
        }

        $this->as($ids['admin']);
        $this->submit('/admin/command-net/enlistment/' . $application->getId() . '/edit', '[decision]', ['decision' => $accept ? 'accept' : 'decline', 'note' => 'Welcome']);
        $this->em->clear();
        $application = $this->em->find(EnlistmentApplication::class, $application->getId());
        $this->check('application ' . ($accept ? 'accepted' : 'declined'), $application?->getStatus() === ($accept ? ApplicationStatus::ACCEPTED : ApplicationStatus::DECLINED));
        $profile = $this->em->getRepository(SoldierProfile::class)->findOneBy(['user' => $ids[$who]]);
        if (!$accept) {
            $this->check('no profile created on decline', $profile === null);
            $this->check('applicant notified of the decline', $this->notified($ids[$who], 'Application declined'));
            return;
        }
        $this->check('profile created and active', $profile?->getStatus() === SoldierStatus::ACTIVE);
        $this->check('starting rank applied', $profile?->getRank()?->getId() === $ids['r0']);
        $this->check('starting unit posting created', $profile?->getPrimaryAssignment()?->getUnit()->getId() === $ids['u2']);
        $this->check('unit role granted', $this->hasRole($ids[$who], $ids['urole2']));
        $this->check('enlistment record written', $this->recordCount($profile, ServiceRecordType::ENLISTMENT) > 0);
        $this->check('applicant notified of the acceptance', $this->notified($ids[$who], 'Application accepted'));
    }

    /**
     * @param array<string, int> $ids
     */
    private function promotion(array $ids): void
    {
        $this->as($ids['admin']);
        $before = $this->recordCount($this->em->find(SoldierProfile::class, $ids['pPromo']), ServiceRecordType::PROMOTION);
        $status = $this->post('/promotions', '/promotions/' . $ids['pPromo'] . '/promote');
        $this->em->clear();
        $profile = $this->em->find(SoldierProfile::class, $ids['pPromo']);
        $this->check('promote POST redirected (' . $status . ')', $status === 302);
        $this->check('rank is now the next one', $profile?->getRank()?->getId() === $ids['r2']);
        $this->check('promotion record written', $this->recordCount($profile, ServiceRecordType::PROMOTION) === $before + 1);
        $this->check('new rank role granted', $this->hasRole($ids['promotee'], $ids['role2']));
        $this->check('old rank role revoked', !$this->hasRole($ids['promotee'], $ids['role1']));
        $this->check('soldier notified of the promotion', $this->notified($ids['promotee'], 'Promoted'));
    }

    /**
     * @param array<string, int> $ids
     */
    private function operation(array $ids): void
    {
        $this->as($ids['admin']);
        $url = '/operations/' . $ids['op'];
        $this->post($url, '/operations/' . $ids['op'] . '/rsvp', ['status' => 'attending']);
        $this->em->clear();
        $rsvp = $this->em->getRepository(OperationRSVP::class)->findOneBy(['operation' => $ids['op'], 'soldier' => $ids['pAdmin']]);
        $this->check('RSVP saved as attending', $rsvp?->getStatus()->value === 'attending');

        $this->post($url, '/operations/' . $ids['op'] . '/attendance', ['soldier_id' => (string)$ids['pAdmin'], 'attended' => '1']);
        $this->post($url, '/operations/' . $ids['op'] . '/attendance', ['soldier_id' => (string)$ids['pMove'], 'attended' => '0']);
        $this->em->clear();
        $repo = $this->em->getRepository(OperationRSVP::class);
        $this->check('marked attended', $repo->findOneBy(['operation' => $ids['op'], 'soldier' => $ids['pAdmin']])?->getAttended() === true);
        $this->check('never-RSVP\'d soldier marked absent (row created)', $repo->findOneBy(['operation' => $ids['op'], 'soldier' => $ids['pMove']])?->getAttended() === false);

        // AWOL detection is on with a threshold of one missed operation, so the absence above flags them.
        $mover = $this->em->find(SoldierProfile::class, $ids['pMove']);
        $this->check('missing the operation flagged the soldier AWOL', $mover?->getStatus() === SoldierStatus::AWOL);
        $this->check('AWOL record written', $this->recordCount($mover, ServiceRecordType::AWOL) === 1);
        $this->check('AWOL role granted', $this->hasRole($ids['mover'], $ids['awolrole']));
        $this->check('soldier notified of the AWOL flag', $this->notified($ids['mover'], 'Flagged AWOL'));

        $this->post($url, '/operations/' . $ids['op'] . '/attendance', ['soldier_id' => (string)$ids['pMove'], 'attended' => '1']);
        $this->em->clear();
        $mover = $this->em->find(SoldierProfile::class, $ids['pMove']);
        $this->check('attending clears the AWOL flag', $mover?->getStatus() === SoldierStatus::ACTIVE);
        $this->check('AWOL role revoked again', !$this->hasRole($ids['mover'], $ids['awolrole']));
        $this->check('soldier notified of the return to Active', $this->notified($ids['mover'], 'Returned to Active'));

        $this->post('/roster', '/roster/report-in');
        $this->em->clear();
        $count = $this->em->getRepository(ReportIn::class)->count(['soldier' => $ids['pAdmin']]);
        $this->check('report in recorded', $count === 1);
        $this->check('lastReportIn cached on profile', $this->em->find(SoldierProfile::class, $ids['pAdmin'])?->getLastReportIn() !== null);
    }

    /**
     * @param array<string, int> $ids
     */
    private function transfer(array $ids, string $s): void
    {
        $this->as($ids['admin']);
        $this->submit('/roster/mov' . $s . '/assignment', '[unit]', ['unit' => (string)$ids['u2'], 'isPrimary' => true, 'startDate' => date('Y-m-d')]);
        $this->em->clear();
        $this->check('role of new unit granted', $this->hasRole($ids['mover'], $ids['urole2']));
        $this->check('role of old unit revoked', !$this->hasRole($ids['mover'], $ids['urole1']));

        $profile = $this->em->find(SoldierProfile::class, $ids['pMove']);
        $new = $profile?->getPrimaryAssignment();
        $this->check('new posting is the primary one', $new?->getUnit()->getId() === $ids['u2']);
        if ($new === null) {
            return;
        }
        $this->post('/roster/mov' . $s, '/roster/mov' . $s . '/assignment/' . $new->getId() . '/delete');
        $this->em->clear();
        $this->check('deleting the assignment removes its unit role', !$this->hasRole($ids['mover'], $ids['urole2']));
    }

    /**
     * @param array<string, int> $ids
     */
    private function specialty(array $ids): void
    {
        $this->as($ids['admin']);
        $this->submit('/admin/command-net/personnel/' . $ids['pLeave'] . '/edit', '[specialty]', ['specialty' => '']);
        $this->em->clear();
        $this->check('clearing the specialty revokes its role', !$this->hasRole($ids['leaver'], $ids['srole']));
        $this->as($ids['admin']);
        $this->submit('/admin/command-net/personnel/' . $ids['pLeave'] . '/edit', '[specialty]', ['specialty' => (string)$ids['spec']]);
        $this->em->clear();
        $this->check('setting the specialty grants its role', $this->hasRole($ids['leaver'], $ids['srole']));
    }

    /**
     * @param array<string, int> $ids
     */
    private function discharge(array $ids): void
    {
        $this->as($ids['admin']);
        $this->submit('/admin/command-net/personnel/' . $ids['pLeave'] . '/discharge', '[kind]', [
            'kind' => 'honorable', 'reason' => 'Time served',
        ]);
        $this->em->clear();
        $profile = $this->em->find(SoldierProfile::class, $ids['pLeave']);
        $this->check('status discharged / not enlisted', $profile?->getStatus() === SoldierStatus::DISCHARGED && $profile->isEnlisted() === false);
        $this->check('discharge record written', $this->recordCount($profile, ServiceRecordType::DISCHARGE) === 1);
        $this->check('rank role removed', !$this->hasRole($ids['leaver'], $ids['role1']));
        $this->check('unit role removed', !$this->hasRole($ids['leaver'], $ids['urole1']));
        $this->check('specialty role removed', !$this->hasRole($ids['leaver'], $ids['srole']));
        $records = $profile?->getServiceRecords()->count() ?? 0;

        $this->as($ids['leaver']);
        $this->submit('/enlist', '[motivation]', ['motivation' => 'Coming back.']);
        $application = $this->em->getRepository(EnlistmentApplication::class)->findOneBy(['user' => $ids['leaver']]);
        $this->check('discharged soldier can apply again', $application?->getStatus() === ApplicationStatus::PENDING);
        if ($application === null) {
            return;
        }
        $this->as($ids['admin']);
        $this->submit('/admin/command-net/enlistment/' . $application->getId() . '/edit', '[decision]', ['decision' => 'accept']);
        $this->em->clear();
        $again = $this->em->getRepository(SoldierProfile::class)->findBy(['user' => $ids['leaver']]);
        $this->check('same profile restored (' . count($again) . ' profile)', count($again) === 1 && $again[0]->getId() === $ids['pLeave']);
        $this->check('active again', ($again[0] ?? null)?->getStatus() === SoldierStatus::ACTIVE);
        $this->check('history kept', ($again[0] ?? null) !== null && $again[0]->getServiceRecords()->count() >= $records);
    }

    /**
     * @param array<string, int> $ids
     */
    private function forms(array $ids): void
    {
        $this->as($ids['admin']);
        $this->submit('/forms/' . $ids['form'], '_reason]', [
            '_reason' => 'Holiday', '_kind' => 'Long', '_agree' => true, '_from' => '2030-01-01', '_days' => '5',
        ]);
        $submission = $this->em->getRepository(FormSubmission::class)->findOneBy(['user' => $ids['admin']], ['id' => 'DESC']);
        $this->check('submission stored as pending', $submission?->getStatus() === ApplicationStatus::PENDING);
        $this->check('answers snapshot holds the answer', str_contains($submission?->getAnswersAsText() ?? '', 'Holiday'));
        if ($submission === null) {
            return;
        }

        $this->as($ids['admin']);
        $this->submit('/admin/command-net/form-submissions/' . $submission->getId() . '/edit', '[decision]', ['decision' => 'accept', 'note' => 'Approved']);
        $this->em->clear();
        $this->check('submission accepted', $this->em->find(FormSubmission::class, $submission->getId())?->getStatus() === ApplicationStatus::ACCEPTED);
        $this->check('submitter notified of the decision', $this->notified($ids['admin'], ': accepted'));

        $definition = $this->em->find(FormDefinition::class, $ids['form']);
        $definition?->setFieldList('text | Something else entirely | required');
        $this->em->flush();
        $this->em->clear();
        $again = $this->em->find(FormSubmission::class, $submission->getId());
        $this->check('editing the form leaves the old answers readable', str_contains($again?->getAnswersAsText() ?? '', 'Reason'));
    }

    /**
     * @param array<string, int> $ids
     */
    private function courses(array $ids): void
    {
        $this->as($ids['admin']);
        $this->post('/courses/class/' . $ids['class1'], '/courses/class/' . $ids['class1'] . '/enroll');
        $this->client->request('GET', '/courses/class/' . $ids['class2']);
        $this->check('no enrol button shown for the ineligible class', $this->client->getCrawler()->filter('form[action$="/enroll"]')->count() === 0);
        $this->em->clear();
        $students = $this->em->getRepository(CourseClassStudent::class);
        $this->check('enrolled in the open class', $students->count(['courseClass' => $ids['class1']]) === 1);
        $this->check('refused for the course with an unmet prerequisite and rank', $students->count(['courseClass' => $ids['class2']]) === 0);

        $class = $this->em->find(CourseClass::class, $ids['class1']);
        $class?->setStartsAt(new DateTime('-1 day'));
        $this->em->flush();
        $this->em->clear();

        $student = $this->em->getRepository(CourseClassStudent::class)->findOneBy(['courseClass' => $ids['class1']]);
        $page = '/courses/class/' . $ids['class1'];
        $this->post($page, $page . '/results', ['result[' . $student?->getId() . ']' => 'passed']);
        $this->em->clear();
        $student = $this->em->getRepository(CourseClassStudent::class)->findOneBy(['courseClass' => $ids['class1']]);
        $this->check('result recorded as passed', $student?->getResult() === CourseResult::PASSED);
        $this->check('student notified of the result', $this->notified($ids['admin'], ': Passed'));
        $profile = $this->em->find(SoldierProfile::class, $ids['pAdmin']);
        $this->check('course record written', $this->recordCount($profile, ServiceRecordType::COURSE) === 1);
        $this->check('qualification granted', $this->em->getRepository(SoldierQualification::class)->count(['soldier' => $ids['pAdmin'], 'qualification' => $ids['qual']]) === 1);
        $records = $this->em->getRepository(ServiceRecord::class)->count(['soldier' => $ids['pAdmin']]);

        $this->as($ids['admin']);
        $this->client->request('GET', $page);
        $this->check('results form no longer offered once processed', $this->client->getCrawler()->filter('form[action$="/results"]')->count() === 0);
        $this->check('service records unchanged', $this->em->getRepository(ServiceRecord::class)->count(['soldier' => $ids['pAdmin']]) === $records);
    }

    /**
     * Report In enforcement, driven through the scheduled command and then by moving the clock
     * forward. It flags every active soldier, so it runs after the flows that need active people.
     *
     * @param array<string, int> $ids
     */
    private function reportInEnforcement(array $ids): void
    {
        /** @var ReportInSettings $settings */
        $settings = static::getContainer()->get(ReportInSettings::class);
        $settings->save(['enabled' => true, 'periodDays' => 30, 'warningDays' => 7]);
        /** @var ReportInService $service */
        $service = static::getContainer()->get(ReportInService::class);

        $tester = new CommandTester((new Application(static::$kernel))->find('command-net:report-in:run-checks'));
        $exit = $tester->execute([]);
        $this->em->clear();
        $this->check('the run-checks command succeeds (exit ' . $exit . ')', $exit === 0);
        $this->check('nobody is flagged on the first run', $this->em->find(SoldierProfile::class, $ids['pAdmin'])?->getStatus() === SoldierStatus::ACTIVE);

        $service->runChecks(new DateTimeImmutable('+25 days'));
        $this->em->clear();
        $this->check('a soldier close to the deadline is warned', $this->notified($ids['admin'], 'Report in soon'));
        $this->check('and is not flagged yet', $this->em->find(SoldierProfile::class, $ids['pAdmin'])?->getStatus() === SoldierStatus::ACTIVE);

        $service->runChecks(new DateTimeImmutable('+40 days'));
        $this->em->clear();
        $profile = $this->em->find(SoldierProfile::class, $ids['pAdmin']);
        $this->check('past the deadline the soldier is flagged AWOL', $profile?->getStatus() === SoldierStatus::AWOL && $profile->isReportInFlagged());
        $this->check('soldier notified of the AWOL flag', $this->notified($ids['admin'], 'Flagged AWOL'));

        $this->as($ids['admin']);
        $this->post('/roster', '/roster/report-in');
        $this->em->clear();
        $this->check('reporting in clears it', $this->em->find(SoldierProfile::class, $ids['pAdmin'])?->getStatus() === SoldierStatus::ACTIVE);
        $this->check('soldier notified of the return to Active', $this->notified($ids['admin'], 'Returned to Active'));
    }

    private function notified(int $userId, string $titleContains): bool
    {
        foreach ($this->em->getRepository(Notification::class)->findBy(['recipient' => $userId]) as $notification) {
            if (str_contains((string)($notification->getContext()['title'] ?? ''), $titleContains)) {
                $this->renders($notification);

                return true;
            }
        }

        return false;
    }

    /**
     * What the member is shown for a notification comes from the platform's generic type reading the
     * context we filled in, so a misspelt key would leave the title, text or link blank.
     */
    private function renders(Notification $notification): void
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get('translator');
        $type = new GenericNotificationType($translator);
        $shown = ['title' => $type->getTitle($notification), 'text' => $type->getDescription($notification), 'link' => $type->getUrl($notification)];
        foreach ($shown as $what => $value) {
            if (trim($value) === '') {
                $this->check('notification "' . ($notification->getContext()['title'] ?? '') . '" shows a ' . $what, false);
            }
        }
        if (!str_starts_with($shown['link'], '/')) {
            $this->check('notification "' . ($notification->getContext()['title'] ?? '') . '" links to a page on the site (got "' . $shown['link'] . '")', false);
        }
    }

    private function rank(string $name, int $position, ?RankGroup $group, ?Role $role): Rank
    {
        $rank = new Rank();
        $rank->setName($name);
        $rank->setAbbreviation(substr($name, 0, 3));
        $rank->setPosition($position);
        $rank->setGroup($group);
        $rank->setRole($role);
        $this->em->persist($rank);

        return $rank;
    }

    private function unit(string $name, Role $role): Unit
    {
        $unit = new Unit();
        $unit->setName($name);
        $unit->setAbbreviation(substr($name, 0, 3));
        $unit->setPosition(1);
        $unit->setRole($role);

        return $unit;
    }

    private function profile(User $user, Rank $rank): SoldierProfile
    {
        $profile = new SoldierProfile($user);
        $profile->setRank($rank);
        $profile->setEnlistmentDate(new DateTime('-200 days'));
        $this->em->persist($profile);
        $this->em->flush();

        return $profile;
    }

    private function hasRole(int $userId, int $roleId): bool
    {
        $user = $this->em->find(User::class, $userId);
        foreach ($user?->getRoleEntities() ?? [] as $role) {
            if ($role->getId() === $roleId) {
                return true;
            }
        }

        return false;
    }

    private function recordCount(?SoldierProfile $profile, ServiceRecordType $type): int
    {
        return $profile === null ? 0 : $this->em->getRepository(ServiceRecord::class)->count(['soldier' => $profile->getId(), 'type' => $type]);
    }

    private function as(int $userId): void
    {
        $this->em->clear();
        $user = $this->em->find(User::class, $userId);
        $this->client->loginUser($user);
    }

    /**
     * Loads a page, finds the form holding the field whose name contains $anchor, fills it in by
     * field-name suffix (true/false ticks or unticks a checkbox) and submits it.
     *
     * @param array<string, string|bool> $values
     */
    private function submit(string $url, string $anchor, array $values): int
    {
        $crawler = $this->client->request('GET', $url);
        $node = $crawler->filterXPath('//*[self::input or self::textarea or self::select][contains(@name,"' . $anchor . '")]/ancestor::form[1]');
        if ($node->count() === 0) {
            throw new \RuntimeException('no form with a ' . $anchor . ' field on ' . $url . ' (status ' . $this->client->getResponse()->getStatusCode() . ')');
        }
        $form = $node->form();
        foreach ($values as $suffix => $value) {
            $name = null;
            foreach (array_keys($form->all()) as $candidate) {
                if (str_ends_with((string)$candidate, $suffix . ']')) {
                    $name = (string)$candidate;
                }
            }
            if ($name === null) {
                throw new \RuntimeException('no field ' . $suffix . ' on ' . $url . ', have: ' . implode(', ', array_keys($form->all())));
            }
            if (is_bool($value)) {
                $value ? $form[$name]->tick() : $form[$name]->untick();
            } else {
                $form[$name]->setValue($value);
            }
        }
        $this->client->submit($form);

        return $this->client->getResponse()->getStatusCode();
    }

    /**
     * Loads a page and presses the button whose form posts to $action, as a real click would, so
     * the CSRF token comes from the page. $override changes hidden fields such as the status.
     *
     * @param array<string, string> $override
     */
    private function post(string $url, string $action, array $override = []): int
    {
        $crawler = $this->client->request('GET', $url);
        $node = $crawler->filter('form[action$="' . $action . '"]');
        if ($node->count() === 0) {
            throw new \RuntimeException('no form posting to ' . $action . ' on ' . $url . ' (status ' . $this->client->getResponse()->getStatusCode() . ')');
        }
        $this->client->submit($node->first()->form(), $override);

        return $this->client->getResponse()->getStatusCode();
    }

    private function check(string $name, bool $ok): void
    {
        $this->lines[] = ($ok ? 'PASS  ' : 'FAIL  ') . $name;
        $this->failed = $this->failed || !$ok;
    }

    private function flow(string $title, callable $flow): void
    {
        $this->lines[] = '== ' . $title;
        try {
            $flow();
        } catch (Throwable $e) {
            $this->lines[] = 'FAIL  exception ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 300) . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
            $this->failed = true;
        }
    }
}
