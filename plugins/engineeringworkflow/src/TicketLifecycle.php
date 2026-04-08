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
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Engineeringworkflow;

final class TicketLifecycle
{
    public static function validate_before_update(\Ticket $ticket): void
    {
        if (!self::is_closing_transition($ticket)) {
            return;
        }

        if (self::should_skip_validation($ticket)) {
            return;
        }

        if (!self::is_engineering_flow_ticket($ticket)) {
            return;
        }

        if (self::has_deliverable_attachment((int) $ticket->fields['id'])) {
            return;
        }

        \Session::addMessageAfterRedirect(
            __s('At least one document must be attached before closing an engineering workflow ticket.'),
            false,
            ERROR
        );

        $ticket->input = false;
    }

    public static function link_child_ticket_after_add(\Ticket $ticket): void
    {
        $parent_tickets_id = self::extract_parent_tickets_id($ticket->input);

        if ($parent_tickets_id <= 0) {
            return;
        }

        $child_tickets_id = (int) ($ticket->fields['id'] ?? 0);
        if ($child_tickets_id <= 0 || $child_tickets_id === $parent_tickets_id) {
            return;
        }

        $parent_ticket = new \Ticket();
        if (!$parent_ticket->getFromDB($parent_tickets_id)) {
            \Session::addMessageAfterRedirect(
                __s('Parent ticket was not found. Child ticket was created without parent relation.'),
                false,
                WARNING
            );
            return;
        }

        $ticket_ticket = new \Ticket_Ticket();
        $relation_id = $ticket_ticket->add([
            'tickets_id_1' => $child_tickets_id,
            'tickets_id_2' => $parent_tickets_id,
            'link' => \CommonITILObject_CommonITILObject::SON_OF,
            '_disablenotif' => true,
        ]);

        if ($relation_id === false) {
            \Session::addMessageAfterRedirect(
                __s('Child ticket was created, but parent relation could not be added.'),
                false,
                WARNING
            );
        }
    }

    private static function should_skip_validation(\Ticket $ticket): bool
    {
        return (bool) ($ticket->input['_skip_engineeringworkflow_deliverable_check'] ?? false);
    }

    private static function is_closing_transition(\Ticket $ticket): bool
    {
        if (!isset($ticket->input['status'])) {
            return false;
        }

        $new_status = (int) $ticket->input['status'];
        $current_status = (int) ($ticket->fields['status'] ?? 0);

        if ($new_status === $current_status) {
            return false;
        }

        return in_array($new_status, $ticket->getClosedStatusArray(), true);
    }

    private static function is_engineering_flow_ticket(\Ticket $ticket): bool
    {
        $links = \Ticket_Ticket::getLinkedTo(\Ticket::class, (int) $ticket->fields['id']);

        foreach ($links as $link) {
            if (!isset($link['link'])) {
                continue;
            }

            if ((int) $link['link'] === \CommonITILObject_CommonITILObject::SON_OF) {
                return true;
            }

            if ((int) $link['link'] === \CommonITILObject_CommonITILObject::PARENT_OF) {
                return true;
            }
        }

        return false;
    }

    private static function has_deliverable_attachment(int $tickets_id): bool
    {
        return \countElementsInTable(
            \Document_Item::getTable(),
            [
                'itemtype' => \Ticket::class,
                'items_id' => $tickets_id,
            ]
        ) > 0;
    }

    private static function extract_parent_tickets_id(array $input): int
    {
        $candidate_fields = [
            '_engineeringworkflow_parent_tickets_id',
            'engineeringworkflow_parent_tickets_id',
            'parent_tickets_id',
        ];

        foreach ($candidate_fields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $parent_tickets_id = (int) $input[$field];
            if ($parent_tickets_id > 0) {
                return $parent_tickets_id;
            }
        }

        return 0;
    }
}
