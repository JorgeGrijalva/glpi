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

use Glpi\Plugin\HookManager;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Engineeringworkflow\CentralWidget;
use GlpiPlugin\Engineeringworkflow\TicketLifecycle;
use GlpiPlugin\Engineeringworkflow\WorkflowConfig;

function plugin_version_engineeringworkflow(): array
{
    return [
        'name' => 'engineeringworkflow',
        'version' => '1.2.0',
        'author' => 'ESJ',
        'license' => 'GPL v2+',
        'requirements' => [
            'glpi' => [
                'min' => '11.0.0',
            ],
        ],
    ];
}

function plugin_init_engineeringworkflow(): void
{
    $GLOBALS['PLUGIN_HOOKS'][Hooks::CONFIG_PAGE]['engineeringworkflow'] = 'settings';

    $plugin = new Plugin();
    if (!$plugin->isActivated('engineeringworkflow')) {
        return;
    }

    $hook_manager = new HookManager('engineeringworkflow');
    $hook_manager->registerItemHook(
        Hooks::PRE_ITEM_ADD,
        Ticket::class,
        [TicketLifecycle::class, 'route_ticket_before_add']
    );

    $hook_manager->registerItemHook(
        Hooks::PRE_ITEM_UPDATE,
        Ticket::class,
        [TicketLifecycle::class, 'validate_before_update']
    );

    $hook_manager->registerItemHook(
        Hooks::ITEM_ADD,
        Ticket::class,
        [TicketLifecycle::class, 'link_child_ticket_after_add']
    );

    $hook_manager->registerItemHook(
        Hooks::ITEM_UPDATE,
        Ticket::class,
        [TicketLifecycle::class, 'track_status_transition_after_update']
    );

    $hook_manager->registerFunctionalHook(
        Hooks::DISPLAY_CENTRAL,
        [CentralWidget::class, 'render_widget']
    );
}

function plugin_engineeringworkflow_install(): bool
{
    WorkflowConfig::install_defaults();
    return true;
}

function plugin_engineeringworkflow_uninstall(): bool
{
    WorkflowConfig::uninstall_values();
    return true;
}
