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

namespace GlpiPlugin\Engineeringworkflow;

use RuntimeException;

final class SapNotificationMapper
{
    /**
     * @param array<string, mixed> $notification
     * @return array<string, mixed>
     */
    public function map_to_bootstrap_payload(array $notification): array
    {
        $project_code = trim((string) ($notification['project_code'] ?? ''));
        $project_name = trim((string) ($notification['project_name'] ?? ''));
        $customer_name = trim((string) ($notification['customer_name'] ?? ''));

        if ($project_code === '' && $project_name === '') {
            throw new RuntimeException('SAP notification must include project_code or project_name.');
        }

        if ($project_name === '') {
            $project_name = $project_code;
        }

        $name_parts = [$project_name];
        if ($project_code !== '' && $project_code !== $project_name) {
            $name_parts[] = $project_code;
        }

        $project_title = implode(' - ', $name_parts);
        $project_content = sprintf(
            'Customer: %s | SAP reference: %s',
            $customer_name !== '' ? $customer_name : 'N/A',
            $project_code !== '' ? $project_code : 'N/A'
        );

        $phases = $notification['phases'] ?? [];
        if (!is_array($phases) || $phases === []) {
            $phases = [
                [
                    'name' => 'Phase 1',
                    'products' => [],
                ],
            ];
        }

        return [
            'project' => [
                'name' => $project_title,
                'content' => $project_content,
                'entities_id' => (int) ($notification['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0)),
                'priority' => (int) ($notification['priority'] ?? 3),
                'tonnage' => (float) ($notification['tonnage'] ?? 0),
                'is_export' => (bool) ($notification['is_export'] ?? false),
                'business_units' => (int) ($notification['business_units'] ?? 1),
            ],
            'phases' => $phases,
        ];
    }
}
