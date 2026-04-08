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

use Glpi\Application\View\TemplateRenderer;

final class CentralWidget
{
    public static function render_widget(): void
    {
        if (!\Session::haveRight(\Ticket::$rightname, READ)) {
            return;
        }

        $related_ticket_ids = self::get_related_ticket_ids();
        if ($related_ticket_ids === []) {
            return;
        }

        $status_counts = [
            \Ticket::INCOMING => self::count_by_status($related_ticket_ids, \Ticket::INCOMING),
            \Ticket::ASSIGNED => self::count_by_status($related_ticket_ids, \Ticket::ASSIGNED),
            \Ticket::PLANNED => self::count_by_status($related_ticket_ids, \Ticket::PLANNED),
            \Ticket::WAITING => self::count_by_status($related_ticket_ids, \Ticket::WAITING),
            \Ticket::SOLVED => self::count_by_status($related_ticket_ids, \Ticket::SOLVED),
            \Ticket::CLOSED => self::count_by_status($related_ticket_ids, \Ticket::CLOSED),
        ];

        $rows = [];
        foreach ($status_counts as $status => $count) {
            $rows[] = [
                'status_label' => (string) \Ticket::getStatus($status),
                'count' => $count,
            ];
        }

        TemplateRenderer::getInstance()->display('@engineeringworkflow/central/widget.html.twig', [
            'rows' => $rows,
        ]);
    }

    /**
     * @return int[]
     */
    private static function get_related_ticket_ids(): array
    {
        $relations = (new \Ticket_Ticket())->find([
            'link' => [
                \CommonITILObject_CommonITILObject::SON_OF,
                \CommonITILObject_CommonITILObject::PARENT_OF,
            ],
        ]);

        $ticket_ids = [];
        foreach ($relations as $relation) {
            $ticket_ids[] = (int) $relation['tickets_id_1'];
            $ticket_ids[] = (int) $relation['tickets_id_2'];
        }

        $ticket_ids = array_values(array_filter(array_unique($ticket_ids)));
        return $ticket_ids;
    }

    /**
     * @param int[] $ticket_ids
     */
    private static function count_by_status(array $ticket_ids, int $status): int
    {
        return \countElementsInTable(
            \Ticket::getTable(),
            [
                'id' => $ticket_ids,
                'status' => $status,
            ]
        );
    }
}
