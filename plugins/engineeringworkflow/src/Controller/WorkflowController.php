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

use Glpi\Controller\AbstractController;
use GlpiPlugin\Engineeringworkflow\SapNotificationMapper;
use GlpiPlugin\Engineeringworkflow\WorkflowBootstrapService;
use GlpiPlugin\Engineeringworkflow\WorkflowConfig;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorkflowController extends AbstractController
{
    #[Route('/bootstrap', name: 'engineeringworkflow_bootstrap', methods: ['GET', 'POST'])]
    public function bootstrap(Request $request): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Method not allowed.',
            ], 405);
        }

        if (!$this->can_bootstrap($request)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Missing right to create tickets or invalid API token.',
            ], 403);
        }

        try {
            $payload = $request->toArray();
            $service = new WorkflowBootstrapService();
            $result = $service->bootstrap_project($payload);

            return new JsonResponse([
                'status' => 'ok',
                'data' => $result,
            ], 201);
        } catch (RuntimeException $exception) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 400);
        }
    }

    #[Route('/sap-notification', name: 'engineeringworkflow_sap_notification', methods: ['GET', 'POST'])]
    public function ingest_sap_notification(Request $request): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Method not allowed.',
            ], 405);
        }

        if (!$this->can_bootstrap($request)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Missing right to create tickets or invalid API token.',
            ], 403);
        }

        try {
            $notification = $request->toArray();
            $mapper = new SapNotificationMapper();
            $payload = $mapper->map_to_bootstrap_payload($notification);

            $service = new WorkflowBootstrapService();
            $result = $service->bootstrap_project($payload);

            return new JsonResponse([
                'status' => 'ok',
                'data' => $result,
            ], 201);
        } catch (RuntimeException $exception) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 400);
        }
    }

    private function can_bootstrap(Request $request): bool
    {
        if (\Session::haveRight(\Ticket::$rightname, CREATE)) {
            return true;
        }

        $settings = WorkflowConfig::get_settings();
        $expected_token = trim((string) ($settings[WorkflowConfig::KEY_API_TOKEN] ?? ''));
        if ($expected_token === '') {
            return false;
        }

        $provided_token = trim((string) $request->headers->get('X-Engineeringworkflow-Token', ''));
        if ($provided_token === '') {
            return false;
        }

        return hash_equals($expected_token, $provided_token);
    }
}
