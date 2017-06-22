<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Calendar\Storages;

class Storage extends \Aurora\System\Managers\AbstractManagerStorage
{
	/**
	 * @param string $sUserUUID
	 */
	public function init($sUserUUID)
	{
	}

	/**
	 * @param CalendarInfo  $oCalendar
	 */
	public function initCalendar(&$oCalendar)
	{
	}

	public function getCalendarAccess($sUserUUID, $sCalendarId)
	{
		return \ECalendarPermission::Write;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 *
	 * @return null
	 */
	public function getCalendar($sUserUUID, $sCalendarId)
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
	 * @param string $sUserUUID
	 *
	 * @return array
	 */
	public function GetCalendarsSharedToAll($sUserUUID)
	{
		return array();
	}

	/**
	}
	 * @param string $sUserUUID
	 *
	 * @return array
	 */
	public function getCalendars($sUserUUID)
	{
		return array();
	}

	/**
	 * @param string $sUserUUID
	 *
     * @return array
	 */
	public function GetCalendarNames($sUserUUID)
	{
		return array();
	}	

	/**
	 * @param string $sUserUUID
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function createCalendar($sUserUUID, $sName, $sDescription, $iOrder, $sColor)
	{
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function updateCalendar($sUserUUID, $sCalendarId, $sName, $sDescription, $iOrder,
			$sColor)
	{
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function updateCalendarColor($sUserUUID, $sCalendarId, $sColor)
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 *
	 * @return false
	 */
	public function deleteCalendar($sUserUUID, $sCalendarId)
	{
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sUserId
	 * @param int $iPerms
	 *
	 * @return false
	 */
	public function updateCalendarShare($sUserUUID, $sCalendarId, $sUserId, $iPerms = \ECalendarPermission::RemovePermission)
	{
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param bool $bIsPublic
	 *
	 * @return false
	 */
	public function publicCalendar($sUserUUID, $sCalendarId, $bIsPublic)
	{
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $oCalendar
	 *
	 * @return array
	 */
	public function getCalendarUsers($sUserUUID, $oCalendar)
	{
		return array();
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dFinish
	 *
	 * @return array
	 */
	public function getEvents($sUserUUID, $sCalendarId, $dStart, $dFinish)
	{
		return array();
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return array
	 */
	public function getEvent($sUserUUID, $sCalendarId, $sEventId)
	{
		return array();
	}

	/**
	}
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param \Sabre\VObject\Component\VCalendar $vCal
	 *
	 * @return null
	 */
	public function createEvent($sUserUUID, $sCalendarId, $sEventId, $vCal)
	{
		return null;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return true
	 */
	public function updateEventRaw($sUserUUID, $sCalendarId, $sEventId, $sData)
	{
		return true;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $aArgs
	 *
	 * @return false
	 */
	public function updateEvent($sUserUUID, $sCalendarId, $sEventId, $aArgs)
	{
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sNewCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return false
	 */
	public function moveEvent($sUserUUID, $sCalendarId, $sNewCalendarId, $sEventId, $sData)
	{
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return false
	 */
	public function deleteEvent($sUserUUID, $sCalendarId, $sEventId)
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
	 * @param string $sUserUUID
	 *
	 * @return bool
	 */
	public function clearAllCalendars($sUserUUID)
	{
		return true;
	}
}

