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

final class WorkflowBootstrapService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function bootstrap_project(array $payload): array
    {
        $project = $payload['project'] ?? null;
        if (!is_array($project)) {
            throw new RuntimeException('Missing "project" payload.');
        }

        $project_name = trim((string) ($project['name'] ?? ''));
        if ($project_name === '') {
            throw new RuntimeException('Project name is required.');
        }

        $project_content = trim((string) ($project['content'] ?? ''));
        $entities_id = (int) ($project['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
        $project_priority = (int) ($project['priority'] ?? 3);
        $project_tonnage = (float) ($project['tonnage'] ?? 0);
        $is_export = (bool) ($project['is_export'] ?? false);
        $business_units = (int) ($project['business_units'] ?? 1);

        $project_ticket_id = $this->create_ticket([
            'name' => $project_name,
            'content' => $project_content !== '' ? $project_content : 'Engineering project bootstrap',
            'entities_id' => $entities_id,
            'priority' => $project_priority,
            'type' => \Ticket::DEMAND_TYPE,
            'status' => \Ticket::INCOMING,
            '_engineeringworkflow_bootstrap' => true,
            '_engineeringworkflow_tonnage' => $project_tonnage,
            '_engineeringworkflow_is_export' => $is_export,
            '_engineeringworkflow_business_units' => $business_units,
        ]);

        $phase_items = $payload['phases'] ?? [];
        if (!is_array($phase_items)) {
            $phase_items = [];
        }

        $created_phases = [];
        foreach ($phase_items as $phase_index => $phase_item) {
            if (!is_array($phase_item)) {
                continue;
            }

            $phase_name = trim((string) ($phase_item['name'] ?? ''));
            if ($phase_name === '') {
                $phase_name = 'Phase ' . ((int) $phase_index + 1);
            }

            $phase_ticket_id = $this->create_ticket([
                'name' => sprintf('[Phase] %s - %s', $project_name, $phase_name),
                'content' => 'Engineering phase',
                'entities_id' => $entities_id,
                'priority' => $project_priority,
                'type' => \Ticket::DEMAND_TYPE,
                'status' => \Ticket::INCOMING,
                '_engineeringworkflow_parent_tickets_id' => $project_ticket_id,
            ]);

            $product_items = $phase_item['products'] ?? [];
            if (!is_array($product_items)) {
                $product_items = [];
            }

            $created_products = [];
            foreach ($product_items as $product_index => $product_item) {
                if (!is_array($product_item)) {
                    continue;
                }

                $product_name = trim((string) ($product_item['name'] ?? ''));
                if ($product_name === '') {
                    $product_name = 'Product ' . ((int) $product_index + 1);
                }

                $product_ticket_id = $this->create_ticket([
                    'name' => sprintf('[Product] %s - %s - %s', $project_name, $phase_name, $product_name),
                    'content' => 'Engineering product task',
                    'entities_id' => $entities_id,
                    'priority' => $project_priority,
                    'type' => \Ticket::DEMAND_TYPE,
                    'status' => \Ticket::INCOMING,
                    '_engineeringworkflow_parent_tickets_id' => $phase_ticket_id,
                ]);

                $created_products[] = [
                    'id' => $product_ticket_id,
                    'name' => $product_name,
                ];
            }

            $created_phases[] = [
                'id' => $phase_ticket_id,
                'name' => $phase_name,
                'products' => $created_products,
            ];
        }

        return [
            'project_ticket_id' => $project_ticket_id,
            'phases' => $created_phases,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function create_ticket(array $input): int
    {
        $ticket = new \Ticket();
        $ticket_id = $ticket->add($input);
        if ($ticket_id === false) {
            throw new RuntimeException('Unable to create ticket during engineering workflow bootstrap.');
        }

        return (int) $ticket_id;
    }
}
