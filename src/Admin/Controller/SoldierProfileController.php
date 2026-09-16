<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Admin\Form\SoldierProfileType;
use MajesticDev\CommandNet\Entity\Enum\ServiceRecordType;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\ServiceRecord;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;

/**
 * @extends AbstractCrudController<SoldierProfile>
 */
#[Route('/command-net/personnel', 'command_net_personnel')]
class SoldierProfileController extends AbstractCrudController
{
    // Adds an optional "Create/View ID Card" action when majesticdev/forumify-id-card-plugin
    // is installed; a no-op include on any install that doesn't have it.
    protected string $formTemplate = '@CommandNetPlugin/admin/soldier_profile/form.html.twig';

    protected ?string $permissionView = 'command-net.admin.personnel.view';
    protected ?string $permissionCreate = 'command-net.admin.personnel.manage';
    protected ?string $permissionEdit = 'command-net.admin.personnel.manage';
    protected ?string $permissionDelete = 'command-net.admin.personnel.manage';

    public function __construct(
        private readonly ServiceRecordRepository $serviceRecordRepository,
    ) {
    }

    protected function getEntityClass(): string
    {
        return SoldierProfile::class;
    }

    protected function getTableName(): string
    {
        return 'SoldierProfileTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(SoldierProfileType::class, $data);
    }

    /**
     * A rank edit doesn't go through the roster's award/qualification/assignment flow, so it
     * needs its own hook to keep the service record timeline complete. $profile is the same
     * Doctrine-managed instance the form mutates in place, so capturing its rank position
     * before calling the parent - which binds and saves the submitted data - gives the
     * "before" value, and re-reading it after gives the "after" one.
     */
    #[Route('/{identifier}/edit', '_edit')]
    public function edit(Request $request, string $identifier): Response
    {
        $profile = $this->repository->find($identifier);
        $previousRank = $profile?->getRank();

        $response = parent::edit($request, $identifier);

        if ($profile !== null && $response->isRedirect()) {
            $this->recordRankChange($profile, $previousRank);
        }

        return $response;
    }

    private function recordRankChange(SoldierProfile $profile, ?Rank $previousRank): void
    {
        $newRank = $profile->getRank();
        if ($newRank === null || $newRank === $previousRank) {
            // Clearing a rank entirely doesn't fit "promotion" or "demotion" - nothing to
            // record - and an unrelated field edit shouldn't write a record at all.
            return;
        }

        $isPromotion = $previousRank === null || $newRank->getPosition() > $previousRank->getPosition();
        $record = new ServiceRecord(
            $profile,
            $isPromotion ? ServiceRecordType::PROMOTION : ServiceRecordType::DEMOTION,
            (string) $newRank,
        );
        $this->serviceRecordRepository->save($record);
    }
}
