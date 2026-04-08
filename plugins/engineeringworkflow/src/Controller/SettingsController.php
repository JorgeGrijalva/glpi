<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Engineeringworkflow\Controller;

use Config;
use Glpi\Controller\AbstractController;
use GlpiPlugin\Engineeringworkflow\WorkflowConfig;
use Group;
use Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'engineeringworkflow_settings', methods: ['GET'])]
    public function index(): Response
    {
        if (!Config::canUpdate()) {
            Session::addMessageAfterRedirect(
                __s('You do not have rights to configure this plugin.'),
                false,
                ERROR
            );

            return new RedirectResponse('/front/central.php');
        }

        $settings = WorkflowConfig::get_settings();
        $groups = (new Group())->find([], ['name']);

        return $this->render('@engineeringworkflow/pages/settings.html.twig', [
            'settings' => $settings,
            'groups' => $groups,
        ]);
    }

    #[Route('/settings', name: 'engineeringworkflow_settings_update', methods: ['POST'])]
    public function update(Request $request): RedirectResponse
    {
        if (!Config::canUpdate()) {
            Session::addMessageAfterRedirect(
                __s('You do not have rights to configure this plugin.'),
                false,
                ERROR
            );

            return new RedirectResponse('/plugins/engineeringworkflow/settings');
        }

        $payload = $request->request->all();
        WorkflowConfig::save_settings($payload);

        Session::addMessageAfterRedirect(
            __s('Engineering workflow settings updated.'),
            false,
            INFO
        );

        return new RedirectResponse('/plugins/engineeringworkflow/settings');
    }
}
