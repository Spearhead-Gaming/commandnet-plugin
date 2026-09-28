<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use MajesticDev\CommandNet\Admin\Form\OrbatImportType;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Service\OrbatImporter;
use MajesticDev\CommandNet\Service\PositionCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lets an admin paste a whole unit structure (see OrbatImporter) instead of creating each
 * Unit one at a time through the standard CRUD form.
 */
class OrbatImportController extends AbstractController
{
    /**
     * A ready-to-paste example, commented with the outline syntax itself (OrbatImporter
     * ignores "#" lines) so it can be handed out as-is and pasted straight into the form
     * below it - see downloadTemplate().
     */
    private const string TEMPLATE = <<<'TXT'
        # ORBAT import template
        #
        # Paste this whole file into "Import ORBAT" (admin) or a unit's "Import structure"
        # (My Units) - comment lines like this one are ignored, so you can keep this file as
        # reference and paste from it directly without editing anything out first.
        #
        # Syntax:
        #   - A bare line creates a Unit - a real command (division, regiment, detachment...).
        #     It can only nest under another Unit, not under a squad/team.
        #   - A line starting with "+ Name" creates a Squad - a squad or team within a Unit.
        #     Squads/teams aren't commands in their own right (no commander, insignia, Discord
        #     role, vehicles, or ORBAT export of their own), so they're never Unit rows.
        #   - A line starting with "= Title" links that billet to whatever it's indented
        #     under (a unit or a squad/team), creating it in the shared Position catalog first
        #     if it doesn't already exist.
        #   - A line starting with "@Preset" expands to a standard billet set instead of typing
        #     it out every time. Built in: @Team (Team Lead, Medic, Team Member),
        #     @Squad (Squad Leader, Squad Medic).
        #   - A line starting with "#" is a comment, ignored regardless of indentation.
        #
        # This example follows the real chain of command down to Detachment 7, then its
        # squads (with @Squad) and teams (with @Team) below that. Rename anything below,
        # duplicate the Squad/Team blocks as needed, and delete what you don't need - only
        # non-comment lines are imported.

        HQ
          3rd Infantry Division
            75th Ranger Regiment
              1st Air Cavalry Brigade
                Detachment 7
                  = Commanding Officer
                  = Executive Officer

                  + Squad 1
                    @Squad
                    + Team 1
                      @Team
                    + Team 2
                      @Team
                    + Team 3
                      @Team

                  + Squad 2
                    @Squad
                    + Team 1
                      @Team
                    + Team 2
                      @Team
                    + Team 3
                      @Team

                  + Weapons Detachment
                    @Squad
                    + Team 1
                      @Team
                    + Team 2
                      @Team
                    + Team 3
                      @Team
        TXT;

    public function __construct(private readonly OrbatImporter $importer)
    {
    }

    #[Route('/command-net/units/import', 'command_net_units_import', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.units.manage');

        $form = $this->createForm(OrbatImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var Unit|null $parent */
                $parent = $form->get('parent')->getData();
                $result = $this->importer->import((string)$form->get('outline')->getData(), $parent);
                $this->addFlash('success', "Imported {$result['units']} unit(s), {$result['squads']} squad/team(s), and linked {$result['positions']} position(s).");
                return $this->redirectToRoute('forumify_admin_command_net_units_list');
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('@CommandNetPlugin/admin/orbat_import.html.twig', [
            'form' => $form,
            'presets' => PositionCatalog::PRESETS,
        ]);
    }

    #[Route('/command-net/units/import/template', 'command_net_units_import_template', methods: ['GET'])]
    public function downloadTemplate(): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.units.view');

        return new Response(self::TEMPLATE, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="orbat-template.txt"',
        ]);
    }
}
