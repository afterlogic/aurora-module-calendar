<?php
/**
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Storages;

/**
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @internal
 */
class Storage extends \Aurora\System\Managers\AbstractStorage
{
	/**
	 * @param string $sUserPublicId
	 */
	public function init($sUserPublicId)
	{
	}

	/**
	 * @param CalendarInfo  $oCalendar
	 */
	public function initCalendar(&$oCalendar)
	{
	}

	public function getCalendarAccess($sUserPublicId, $sCalendarId)
	{
		return \Aurora\Modules\Calendar\Enums\Permission::Write;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 *
	 * @return null
	 */
	public function getCalendar($sUserPublicId, $sCalendarId)
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
	 * @param string $sUserPublicId
	 *
	 * @return array
	 */
	public function GetCalendarsSharedToAll($sUserPublicId)
	{
		return array();
	}

	/**
	}
	 * @param string $sUserPublicId
	 *
	 * @return array
	 */
	public function getCalendars($sUserPublicId)
	{
		return array();
	}

	/**
	 * @param string $sUserPublicId
	 *
     * @return array
	 */
	public function GetCalendarNames($sUserPublicId)
	{
		return array();
	}	

	/**
	 * @param string $sUserPublicId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function createCalendar($sUserPublicId, $sName, $sDescription, $iOrder, $sColor)
	{
		return false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function updateCalendar($sUserPublicId, $sCalendarId, $sName, $sDescription, $iOrder,
			$sColor)
	{
		return false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sColor
	 *
	 * @return false
	 */
	public function updateCalendarColor($sUserPublicId, $sCalendarId, $sColor)
	{
		return false;
	}

	/**
	 * @param string $sCalendarId
	 * @param int $iVisible
	 */
	public function updateCalendarVisible($sCalendarId, $iVisible)
	{
		@\setcookie($sCalendarId, $iVisible, \strtotime('+1 day'), \Aurora\System\Api::getCookiePath());
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 *
	 * @return false
	 */
	public function deleteCalendar($sUserPublicId, $sCalendarId)
	{
		return false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sUserId
	 * @param int $iPerms
	 *
	 * @return false
	 */
	public function updateCalendarShare($sUserPublicId, $sCalendarId, $sUserId, $iPerms = \Aurora\Modules\Calendar\Enums\Permission::RemovePermission)
	{
		return false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param bool $bIsPublic
	 *
	 * @return false
	 */
	public function publicCalendar($sCalendarId, $bIsPublic)
	{
		return false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $oCalendar
	 *
	 * @return array
	 */
	public function getCalendarUsers($sUserPublicId, $oCalendar)
	{
		return array();
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dFinish
	 *
	 * @return array
	 */
	public function getEvents($sUserPublicId, $sCalendarId, $dStart, $dFinish)
	{
		return array();
	}

	/**
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dFinish
	 *
	 * @return array
	 */
	public function getPublicEvents($sCalendarId, $dStart, $dFinish)
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
	public function getEvent($sUserPublicId, $sCalendarId, $sEventId)
	{
		return array();
	}
	
	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 *
	 * @return array
	 */
	public function getTasks($sUserPublicId, $sCalendarId, $bCompeted, $sSearch)
	{
		return array();
	}
	

	/**
	}
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param \Sabre\VObject\Component\VCalendar $vCal
	 *
	 * @return null
	 */
	public function createEvent($sUserPublicId, $sCalendarId, $sEventId, $vCal)
	{
		return null;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return true
	 */
	public function updateEventRaw($sUserPublicId, $sCalendarId, $sEventId, $sData)
	{
		return true;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $aArgs
	 *
	 * @return false
	 */
	public function updateEvent($sUserPublicId, $sCalendarId, $sEventId, $aArgs)
	{
		return false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sNewCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return false
	 */
	public function moveEvent($sUserPublicId, $sCalendarId, $sNewCalendarId, $sEventId, $sData)
	{
		return false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return false
	 */
	public function deleteEvent($sUserPublicId, $sCalendarId, $sEventId)
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
	 * @param string $sUserPublicId
	 *
	 * @return bool
	 */
	public function clearAllCalendars($sUserPublicId)
	{
		return true;
	}
}

