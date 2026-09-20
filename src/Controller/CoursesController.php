<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use DomainException;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\CourseClass;
use MajesticDev\CommandNet\Entity\Enum\CourseResult;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\CourseClassRepository;
use MajesticDev\CommandNet\Repository\CourseRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\CourseEnrollmentService;
use MajesticDev\CommandNet\Service\CourseResultsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CoursesController extends AbstractController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly CourseClassRepository $classRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly CourseEnrollmentService $enrollmentService,
        private readonly CourseResultsService $resultsService,
    ) {
    }

    #[Route('/courses', name: 'courses')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('command-net.courses.enroll');

        return $this->render('@CommandNetPlugin/frontend/courses/index.html.twig', [
            'courses' => $this->courseRepository->findBy([], ['name' => 'ASC']),
            'upcoming' => $this->classRepository->findUpcoming(),
            'profile' => $this->myProfile(),
        ]);
    }

    #[Route('/courses/class/{id}', name: 'course_class', requirements: ['id' => '\d+'])]
    public function view(CourseClass $class): Response
    {
        $this->denyAccessUnlessGranted('command-net.courses.enroll');

        $profile = $this->myProfile();

        return $this->render('@CommandNetPlugin/frontend/courses/class.html.twig', [
            'class' => $class,
            'profile' => $profile,
            'reason' => $profile !== null ? $this->enrollmentService->ineligibleReason($profile, $class) : 'Only enlisted personnel can enrol.',
            'results' => CourseResult::cases(),
        ]);
    }

    #[Route('/courses/class/{id}/enroll', name: 'course_class_enroll', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function enroll(CourseClass $class, Request $request): RedirectResponse
    {
        return $this->change($class, $request, 'course_enroll_', function (SoldierProfile $profile) use ($class): void {
            $this->enrollmentService->enroll($profile, $class);
            $this->addFlash('success', 'You are enrolled.');
        });
    }

    #[Route('/courses/class/{id}/withdraw', name: 'course_class_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function withdraw(CourseClass $class, Request $request): RedirectResponse
    {
        return $this->change($class, $request, 'course_withdraw_', function (SoldierProfile $profile) use ($class): void {
            $this->enrollmentService->withdraw($profile, $class);
            $this->addFlash('success', 'You have withdrawn.');
        });
    }

    #[Route('/courses/class/{id}/results', name: 'course_class_results', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function results(CourseClass $class, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.admin.courses.manage');

        if (!$this->isCsrfTokenValid('course_results_' . $class->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_course_class', ['id' => $class->getId()]);
        }

        $submitted = $request->request->all('result');
        $results = [];
        foreach ($class->getStudents() as $student) {
            $value = $submitted[(string)$student->getId()] ?? '';
            $results[$student->getId()] = is_string($value) ? CourseResult::tryFrom($value) : null;
        }

        try {
            $this->resultsService->process($class, $results);
            $this->addFlash('success', 'Results recorded.');
        } catch (DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('command_net_course_class', ['id' => $class->getId()]);
    }

    /**
     * Shared checks for enrolling and withdrawing: permission, CSRF, and an enlisted soldier.
     *
     * @param callable(SoldierProfile): void $action
     */
    private function change(CourseClass $class, Request $request, string $tokenPrefix, callable $action): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.courses.enroll');

        $back = $this->redirectToRoute('command_net_course_class', ['id' => $class->getId()]);
        if (!$this->isCsrfTokenValid($tokenPrefix . $class->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $back;
        }

        $profile = $this->myProfile();
        if ($profile === null) {
            $this->addFlash('error', 'Only enlisted personnel can enrol.');
            return $back;
        }

        try {
            $action($profile);
        } catch (DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $back;
    }

    private function myProfile(): ?SoldierProfile
    {
        $user = $this->getUser();

        return $user instanceof User ? $this->soldierProfileRepository->findOneBy(['user' => $user]) : null;
    }
}
