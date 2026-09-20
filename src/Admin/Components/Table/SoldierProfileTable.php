<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Components\Table;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Core\Entity\User;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\SoldierProfile;

#[AsLiveComponent('SoldierProfileTable', '@CommandNetPlugin/admin/personnel/table.html.twig')]
#[IsGranted('command-net.admin.personnel.view')]
class SoldierProfileTable extends AbstractDoctrineTable
{
    /**
     * Every checkbox in the select column binds to this same array prop via its
     * `value` attribute - LiveComponent's checkbox handling appends/removes that value
     * on check/uncheck, the same way a native multi-select checkbox group works.
     *
     * @var array<string>
     */
    #[LiveProp(writable: true)]
    public array $selected = [];

    #[LiveProp(writable: true)]
    public string $bulkStatus = '';

    protected function getEntityClass(): string
    {
        return SoldierProfile::class;
    }

    /**
     * @return array<SoldierStatus>
     */
    public function getSoldierStatuses(): array
    {
        return SoldierStatus::cases();
    }

    /**
     * Mass status change after a roll call is the concrete case this exists for - marking
     * everyone who no-showed as AWOL, or a batch of departures as discharged, without
     * opening each personnel file individually.
     */
    #[LiveAction]
    public function bulkApplyStatus(): void
    {
        if (!$this->isGranted('command-net.admin.personnel.manage')) {
            return;
        }

        $status = SoldierStatus::tryFrom($this->bulkStatus);
        $ids = array_map('intval', $this->selected);
        if ($status === null || $ids === []) {
            return;
        }

        /** @var array<SoldierProfile> $soldiers */
        $soldiers = $this->repository->findBy(['id' => $ids]);
        foreach ($soldiers as $soldier) {
            $soldier->setStatus($status);
        }
        $this->repository->saveAll($soldiers);

        $this->selected = [];
        $this->bulkStatus = '';
    }

    protected function buildTable(): void
    {
        if ($this->isGranted('command-net.admin.personnel.manage')) {
            $this->addColumn('select', [
                'label' => '',
                'field' => 'id',
                'searchable' => false,
                'sortable' => false,
                'renderer' => static fn (int $id): string => '<input type="checkbox" value="' . $id . '" data-model="selected">',
            ]);
        }

        $this
            // "user" is a real single-level association, safe to sort/search on.
            ->addColumn('user', [
                'label' => 'Name',
                'field' => 'user',
                'renderer' => fn (User $user) => $user->getDisplayName(),
            ])
            ->addColumn('rank', [
                'field' => 'rank',
                'searchable' => false,
                'renderer' => fn (?Rank $rank) => $rank !== null ? (string)$rank : 'Unranked',
            ])
            // primaryAssignment is a derived getter, not a mapped association — kept as
            // a single-segment field so the table never tries to build a DQL join on it.
            ->addColumn('unit', [
                'field' => 'primaryAssignment',
                'searchable' => false,
                'sortable' => false,
                'renderer' => fn (?Assignment $assignment) => $assignment !== null ? (string)$assignment->getUnit() : '—',
            ])
            ->addColumn('status', [
                'field' => 'status',
                'searchable' => false,
                'renderer' => fn (SoldierStatus $status) => $status->label(),
            ])
            ->addActionColumn($this->renderActionColumn(...));
    }

    protected function renderActionColumn(int $id): string
    {
        $actions = '';
        $actions .= $this->renderAction('forumify_admin_command_net_personnel_edit', ['identifier' => $id], 'pencil-simple-line');

        if ($this->security->isGranted('command-net.admin.personnel.manage')) {
            $actions .= $this->renderAction('forumify_admin_command_net_personnel_delete', ['identifier' => $id], 'x');
        }

        return $actions;
    }
}
