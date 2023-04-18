<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Calendar;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property bool $AddDescriptionToTitle
 * @property bool $AllowTasks
 * @property bool $AllowShare
 * @property int $DefaultTab
 * @property bool $HighlightWorkingDays 
 * @property bool $HighlightWorkingHours
 * @property int $WeekStartsOn
 * @property int $WorkdayEnds
 * @property int $WorkdayStarts
 * @property bool $AllowPrivateEvents
 * @property bool $AllowDefaultReminders
 * @property bool $AllowSubscribedCalendars
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "AddDescriptionToTitle" => new SettingsProperty(
                false,
                "bool",
                null,
                "If set to true, both title and description will be displayed for event in calendar grid",
            ),
            "AllowTasks" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true enables tasks-related features such as converting event to task and back",
            ),
            "AllowShare" => new SettingsProperty(
                true,
                "bool",
                null,
                "Setting to true enables calendar sharing capabilities (Aurora Corporate only)",
            ),
            "DefaultTab" => new SettingsProperty(
                3,
                "int",
                null,
                "Defines default calendar tab. All the settings starting from this one are applied to new accounts, and can be adjust by users in their settings.",
            ),
            "HighlightWorkingDays" => new SettingsProperty(
                true,
                "bool",
                null,
                "If true, 'Highlight working days' option is enabled",
            ),
            "HighlightWorkingHours" => new SettingsProperty(
                true,
                "bool",
                null,
                "If true, 'Highlight working hours' option is enabled",
            ),
            "WeekStartsOn" => new SettingsProperty(
                0,
                "int",
                null,
                "Defines first day of the week: 0 - Sunday, 1 - Monday, 6 - Saturday",
            ),
            "WorkdayEnds" => new SettingsProperty(
                18,
                "int",
                null,
                "Defines workday end time",
            ),
            "WorkdayStarts" => new SettingsProperty(
                9,
                "int",
                null,
                "Defines workday start time",
            ),
            "AllowPrivateEvents" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true enables private events features",
            ),
            "AllowDefaultReminders" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true enables default reminders features",
            ),
            "AllowSubscribedCalendars" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true enables external calendars features",
            ),
        ];
    }
}
