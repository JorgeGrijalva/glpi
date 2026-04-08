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

final class WorkflowConfig
{
    public const CONTEXT = 'plugin_engineeringworkflow';

    public const KEY_ENABLE_DELIVERABLE_CHECK = 'enable_deliverable_check';
    public const KEY_ENABLE_PAUSE_APPROVAL = 'enable_pause_approval';
    public const KEY_ENABLE_STATUS_TIMELINE = 'enable_status_timeline';
    public const KEY_PLANNING_GROUP_ID = 'planning_group_id';
    public const KEY_PM_GROUP_ID = 'pm_group_id';
    public const KEY_SMALL_PROJECT_TONNAGE = 'small_project_tonnage';
    public const KEY_API_TOKEN = 'api_token';

    public static function install_defaults(): void
    {
        \Config::setConfigurationValues(self::CONTEXT, [
            self::KEY_ENABLE_DELIVERABLE_CHECK => '1',
            self::KEY_ENABLE_PAUSE_APPROVAL => '1',
            self::KEY_ENABLE_STATUS_TIMELINE => '1',
            self::KEY_PLANNING_GROUP_ID => '0',
            self::KEY_PM_GROUP_ID => '0',
            self::KEY_SMALL_PROJECT_TONNAGE => '100',
            self::KEY_API_TOKEN => '',
        ]);
    }

    public static function uninstall_values(): void
    {
        \Config::deleteConfigurationValues(self::CONTEXT, [
            self::KEY_ENABLE_DELIVERABLE_CHECK,
            self::KEY_ENABLE_PAUSE_APPROVAL,
            self::KEY_ENABLE_STATUS_TIMELINE,
            self::KEY_PLANNING_GROUP_ID,
            self::KEY_PM_GROUP_ID,
            self::KEY_SMALL_PROJECT_TONNAGE,
            self::KEY_API_TOKEN,
        ]);
    }

    public static function is_enabled(string $key, bool $default = false): bool
    {
        $values = \Config::getConfigurationValues(self::CONTEXT, [$key]);
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return (string) $values[$key] === '1';
    }

    public static function get_int(string $key, int $default = 0): int
    {
        $values = \Config::getConfigurationValues(self::CONTEXT, [$key]);
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return (int) $values[$key];
    }

    public static function save_settings(array $settings): void
    {
        $normalized_settings = [
            self::KEY_ENABLE_DELIVERABLE_CHECK => !empty($settings[self::KEY_ENABLE_DELIVERABLE_CHECK]) ? '1' : '0',
            self::KEY_ENABLE_PAUSE_APPROVAL => !empty($settings[self::KEY_ENABLE_PAUSE_APPROVAL]) ? '1' : '0',
            self::KEY_ENABLE_STATUS_TIMELINE => !empty($settings[self::KEY_ENABLE_STATUS_TIMELINE]) ? '1' : '0',
            self::KEY_PLANNING_GROUP_ID => (string) max(0, (int) ($settings[self::KEY_PLANNING_GROUP_ID] ?? 0)),
            self::KEY_PM_GROUP_ID => (string) max(0, (int) ($settings[self::KEY_PM_GROUP_ID] ?? 0)),
            self::KEY_SMALL_PROJECT_TONNAGE => (string) max(1, (int) ($settings[self::KEY_SMALL_PROJECT_TONNAGE] ?? 100)),
            self::KEY_API_TOKEN => trim((string) ($settings[self::KEY_API_TOKEN] ?? '')),
        ];

        \Config::setConfigurationValues(self::CONTEXT, $normalized_settings);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_settings(): array
    {
        $keys = [
            self::KEY_ENABLE_DELIVERABLE_CHECK,
            self::KEY_ENABLE_PAUSE_APPROVAL,
            self::KEY_ENABLE_STATUS_TIMELINE,
            self::KEY_PLANNING_GROUP_ID,
            self::KEY_PM_GROUP_ID,
            self::KEY_SMALL_PROJECT_TONNAGE,
            self::KEY_API_TOKEN,
        ];

        $values = \Config::getConfigurationValues(self::CONTEXT, $keys);

        return [
            self::KEY_ENABLE_DELIVERABLE_CHECK => (string) ($values[self::KEY_ENABLE_DELIVERABLE_CHECK] ?? '1') === '1',
            self::KEY_ENABLE_PAUSE_APPROVAL => (string) ($values[self::KEY_ENABLE_PAUSE_APPROVAL] ?? '1') === '1',
            self::KEY_ENABLE_STATUS_TIMELINE => (string) ($values[self::KEY_ENABLE_STATUS_TIMELINE] ?? '1') === '1',
            self::KEY_PLANNING_GROUP_ID => (int) ($values[self::KEY_PLANNING_GROUP_ID] ?? 0),
            self::KEY_PM_GROUP_ID => (int) ($values[self::KEY_PM_GROUP_ID] ?? 0),
            self::KEY_SMALL_PROJECT_TONNAGE => (int) ($values[self::KEY_SMALL_PROJECT_TONNAGE] ?? 100),
            self::KEY_API_TOKEN => (string) ($values[self::KEY_API_TOKEN] ?? ''),
        ];
    }
}
