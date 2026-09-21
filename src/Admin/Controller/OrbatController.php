<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use MajesticDev\CommandNet\Repository\UnitRepository;
use MajesticDev\CommandNet\Service\OrbatGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The unit tree as an Arma 3 CfgORBAT block, to copy or download as orbat.hpp and #include from a
 * mission's description.ext. Not published anywhere: a mission maker fetches it here when needed.
 */
#[Route('/command-net/orbat', 'command_net_orbat')]
class OrbatController extends AbstractController
{
    public function __invoke(Request $request, UnitRepository $units, OrbatGenerator $generator): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.units.view');

        $side = (string)$request->query->get('side', 'West');
        $config = $generator->generate($units->findBy(['parent' => null], ['position' => 'ASC']), $side);

        if ($request->query->getBoolean('download')) {
            return new Response($config, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="orbat.hpp"',
            ]);
        }

        return $this->render('@CommandNetPlugin/admin/orbat.html.twig', [
            'config' => $config,
            'side' => isset(OrbatGenerator::SIDES[$side]) ? $side : 'West',
            'sides' => OrbatGenerator::SIDES,
        ]);
    }
}
