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
    public static function route_ticket_before_add(\Ticket $ticket): void
    {
        if (!is_array($ticket->input)) {
            return;
        }

        $is_bootstrap_ticket = (bool) ($ticket->input['_engineeringworkflow_bootstrap'] ?? false);
        if (!$is_bootstrap_ticket) {
            return;
        }

        $small_project_tonnage = WorkflowConfig::get_int(
            WorkflowConfig::KEY_SMALL_PROJECT_TONNAGE,
            100
        );
        if ($small_project_tonnage <= 0) {
            $small_project_tonnage = 100;
        }

        $project_tonnage = (float) ($ticket->input['_engineeringworkflow_tonnage'] ?? 0);
        $is_export = (bool) ($ticket->input['_engineeringworkflow_is_export'] ?? false);
        $business_units = (int) ($ticket->input['_engineeringworkflow_business_units'] ?? 1);

        $target_group_id = WorkflowConfig::get_int(
            WorkflowConfig::KEY_PLANNING_GROUP_ID,
            0
        );

        if ($project_tonnage > $small_project_tonnage || $is_export || $business_units > 2) {
            $target_group_id = WorkflowConfig::get_int(
                WorkflowConfig::KEY_PM_GROUP_ID,
                0
            );
        }

        if ($target_group_id > 0) {
            $ticket->input['_groups_id_assign'] = $target_group_id;
        }
    }

    public static function validate_before_update(\Ticket $ticket): void
    {
        if (self::requires_pause_approval($ticket)) {
            return;
        }

        if (!self::is_closing_transition($ticket)) {
            return;
        }

        if (!WorkflowConfig::is_enabled(WorkflowConfig::KEY_ENABLE_DELIVERABLE_CHECK, true)) {
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

    public static function track_status_transition_after_update(\Ticket $ticket): void
    {
        if (!WorkflowConfig::is_enabled(WorkflowConfig::KEY_ENABLE_STATUS_TIMELINE, true)) {
            return;
        }

        if (!self::is_engineering_flow_ticket($ticket)) {
            return;
        }

        if (!in_array('status', $ticket->updates, true)) {
            return;
        }

        $old_status = (int) ($ticket->oldvalues['status'] ?? 0);
        $new_status = (int) ($ticket->fields['status'] ?? 0);

        if ($old_status === $new_status || $new_status <= 0) {
            return;
        }

        $message = sprintf(
            'Engineering workflow status changed from "%s" to "%s".',
            (string) \Ticket::getStatus($old_status),
            (string) \Ticket::getStatus($new_status)
        );

        $pause_type = (string) ($ticket->input['_engineeringworkflow_pause_type'] ?? '');
        $pause_reason = trim((string) ($ticket->input['_engineeringworkflow_pause_reason'] ?? ''));
        if ($pause_type !== '') {
            $message .= sprintf(' Pause type: %s.', $pause_type);
        }
        if ($pause_reason !== '') {
            $message .= sprintf(' Pause reason: %s.', $pause_reason);
        }

        $followup = new \ITILFollowup();
        $followup->add([
            'itemtype' => \Ticket::class,
            'items_id' => (int) $ticket->fields['id'],
            'content' => $message,
            'is_private' => 0,
        ]);
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

    private static function requires_pause_approval(\Ticket $ticket): bool
    {
        if (!WorkflowConfig::is_enabled(WorkflowConfig::KEY_ENABLE_PAUSE_APPROVAL, true)) {
            return false;
        }

        if (!self::is_engineering_flow_ticket($ticket)) {
            return false;
        }

        if (!isset($ticket->input['status']) || (int) $ticket->input['status'] !== \Ticket::WAITING) {
            return false;
        }

        $pause_type = (string) ($ticket->input['_engineeringworkflow_pause_type'] ?? '');
        if (!in_array($pause_type, ['internal', 'client'], true)) {
            return false;
        }

        $pause_reason = trim((string) ($ticket->input['_engineeringworkflow_pause_reason'] ?? ''));
        if ($pause_reason === '') {
            \Session::addMessageAfterRedirect(
                __s('A pause reason is required for engineering workflow tickets.'),
                false,
                ERROR
            );
            $ticket->input = false;
            return true;
        }

        $pause_approved = (bool) ($ticket->input['_engineeringworkflow_pause_approved'] ?? false);
        $can_approve = \Session::haveRight(\Ticket::$rightname, \Ticket::ASSIGN);
        if (!$pause_approved || !$can_approve) {
            \Session::addMessageAfterRedirect(
                __s('Pause requires approval by a coordinator or project manager.'),
                false,
                ERROR
            );
            $ticket->input = false;
            return true;
        }

        return false;
    }
}
