<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

/**
 * @internal
 * 
 * @package Calendar
 * @subpackage Storages
 */
class CApiCalendarStorage extends \Aurora\System\AbstractManagerStorage
{
	/**
	 * @param \Aurora\System\GlobalManager &$oManager
	 */
	public function __construct($sStorageName, \Aurora\System\AbstractManager &$oManager)
	{
		parent::__construct('calendar', $sStorageName, $oManager);
	}

	/**
	 * @param int $iUserId
	 */
	public function init($iUserId)
	{
	}

	/**
	 * @param CalendarInfo  $oCalendar
	 */
	public function initCalendar(&$oCalendar)
	{
	}

	public function getCalendarAccess($iUserId, $sCalendarId)
	{
		return ECalendarPermission::Write;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 *
	 * @return null
	 */
	public function getCalendar($iUserId, $sCalendarId)
	{
		return null;
	}

	/*
	 * @param string $sCalendar
	 *
	 * @return false
	 */
	public function getPublicCalendar($sCalendar)
	{

		return false;
	}

	/*
	 * @param string $sHash
	 *
	 * @return false
	 */
	public function getPublicCalendarByHash($sHash)
	{
		return false;
	}

	/*
	 * @param string $sCalendarId
	 *
	 * @return false
	 */
	public function getPublicCalendarHash($sCalendarId) //TODO
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 *
	 * @return array
	 */
	public function GetCalendarsSharedToAll($iUserId)
	{
		return array();
	}

	/**
	}
	 * @param int $iUserId
	 *
	 * @return array
	 */
	public function getCalendars($iUserId)
	{
		return array();
	}

	/**
	 * @param int $iUserId
	 *
     * @return array
	 */
	public function GetCalendarNames($iUserId)
	{
		return array();
	}	

	/**
	 * @param int $iUserId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function createCalendar($iUserId, $sName, $sDescription, $iOrder, $sColor)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function updateCalendar($iUserId, $sCalendarId, $sName, $sDescription, $iOrder,
			$sColor)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function updateCalendarColor($iUserId, $sCalendarId, $sColor)
	{
		return false;
	}

	/**
	 * @param string $sCalendarId
	 * @param int $iVisible
	 */
	public function updateCalendarVisible($sCalendarId, $iVisible)
	{
		@setcookie($sCalendarId, $iVisible, time() + 86400);
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 *
	 * @return false
	 */
	public function deleteCalendar($iUserId, $sCalendarId)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sUserId
	 * @param int $iPerms
	 *
	 * @return false
	 */
	public function updateCalendarShare($iUserId, $sCalendarId, $sUserId, $iPerms = ECalendarPermission::RemovePermission)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param bool $bIsPublic
	 *
	 * @return false
	 */
	public function publicCalendar($iUserId, $sCalendarId, $bIsPublic)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $oCalendar
	 *
	 * @return array
	 */
	public function getCalendarUsers($iUserId, $oCalendar)
	{
		return array();
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dFinish
	 *
	 * @return array
	 */
	public function getEvents($iUserId, $sCalendarId, $dStart, $dFinish)
	{
		return array();
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return array
	 */
	public function getEvent($iUserId, $sCalendarId, $sEventId)
	{
		return array();
	}

	/**
	}
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param \Sabre\VObject\Component\VCalendar $vCal
	 *
	 * @return null
	 */
	public function createEvent($iUserId, $sCalendarId, $sEventId, $vCal)
	{
		return null;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return true
	 */
	public function updateEventRaw($iUserId, $sCalendarId, $sEventId, $sData)
	{
		return true;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $aArgs
	 *
	 * @return false
	 */
	public function updateEvent($iUserId, $sCalendarId, $sEventId, $aArgs)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sNewCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return false
	 */
	public function moveEvent($iUserId, $sCalendarId, $sNewCalendarId, $sEventId, $sData)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return false
	 */
	public function deleteEvent($iUserId, $sCalendarId, $sEventId)
	{
		return false;
	}

	public function getReminders($start, $end)
	{
		return false;
	}

	public function AddReminder($sEmail, $calendarUri, $eventid, $time = null)
	{
		return false;
	}

	public function updateReminder($sEmail, $calendarUri, $eventId, $sData)
	{
		return false;
	}

	public function deleteReminder($eventId)
	{
		return false;
	}

	public function deleteReminderByCalendar($calendarUri)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 *
	 * @return bool
	 */
	public function clearAllCalendars($iUserId)
	{
		return true;
	}
}

