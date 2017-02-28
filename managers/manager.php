<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
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
 * CApiCalendarMainManager class summary
 * 
 * @package Calendar
 */
class CApiCalendarManager extends \Aurora\System\AbstractManagerWithStorage
{
	/*
	 * @type $ApiUsersManager CApiUsersManager
	 */
	protected $ApiUsersManager;

	/*
	 * @type CApiCapabilityManager
	 */
	protected $oApiCapabilityManager;

	/**
	 * @param \Aurora\System\GlobalManager &$oManager
	 */
	public function __construct(\Aurora\System\GlobalManager &$oManager, $sForcedStorage = '', \Aurora\System\AbstractModule $oModule = null)
	{
		parent::__construct('', $oManager, $sForcedStorage, $oModule);

		$this->ApiUsersManager =\Aurora\System\Api::GetSystemManager('users');
		$this->oApiCapabilityManager =\Aurora\System\Api::GetSystemManager('capability');
	}
	
	/**
	 * Determines whether read/write or read-only permissions are set for accessing calendar from this account. 
	 * 
	 * @param int $iUserId Account object 
	 * @param string $sCalendarId Calendar ID 
	 * 
	 * @return bool **ECalendarPermission::Write** or **ECalendarPermission::Read** accordingly.
	 */
	public function getCalendarAccess($iUserId, $sCalendarId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getCalendarAccess($iUserId, $sCalendarId);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 *
	 * @param CAccount $oAccount
	 *
	 * @return CAccount|false $oAccount
	 */
	public function getTenantAccount($oAccount)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getTenantAccount($oAccount);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * @param string $sHash
	 *
	 * @return CCalendar|false $oCalendar
	 */
	public function getPublicCalendarByHash($sHash)
	{
		return $this->getPublicCalendar($sHash);
	}

	/**
	 * @param string $sCalendarId
	 *
	 * @return CCalendar|false $oCalendar
	 */
	public function getPublicCalendar($sCalendarId)
	{
		return $this->getCalendar($this->getPublicAccount(), $sCalendarId);
	}

	/**
	 * Loads calendar.
	 *
	 * @param int $oUserId Account object
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return CCalendar|false $oCalendar
	 */
	public function getCalendar($oUserId, $sCalendarId)
	{
		$oCalendar = false;
		try
		{
			$oCalendar = $this->oStorage->getCalendar($oUserId, $sCalendarId);
			if ($oCalendar) {
				$oCalendar = $this->populateCalendarShares($oUserId, $oCalendar);
			}
		}
		catch (Exception $oException)
		{
			$oCalendar = false;
			$this->setLastException($oException);
		}
		return $oCalendar;
	}

	/**
	 * @param int $iUserId
	 * @param CCalendar $oCalendar
	 *
	 * @return CCalendar $oCalendar
	 */
	public function populateCalendarShares($iUserId, $oCalendar)
	{
		if (!$oCalendar->Shared || $oCalendar->Shared && 
				$oCalendar->Access === \ECalendarPermission::Write || $oCalendar->IsCalendarOwner($iUserId)) {
			$oCalendar->PubHash = $this->getPublicCalendarHash($oCalendar->Id);
			$aUsers = $this->getCalendarUsers($iUserId, $oCalendar);

			$aShares = array();
			if ($aUsers && is_array($aUsers)) {
				foreach ($aUsers as $aUser) {
					if ($aUser['email'] === $this->GetPublicUser()) {
						$oCalendar->IsPublic = true;
					} else if ($aUser['email'] === $this->getTenantUser($iUserId)) {
						$oCalendar->SharedToAll = true;
						$oCalendar->SharedToAllAccess = (int) $aUser['access'];
					} else {
						$aShares[] = $aUser;
					}
				}
			}
			$oCalendar->Shares = $aShares;
		} else {
			$oCalendar->IsDefault = false;
		}

		return $oCalendar;
	}

	/**
	 * @param string $sCalendarId
	 *
	 * @return string|false
	 */
	public function getPublicCalendarHash($sCalendarId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getPublicCalendarHash($sCalendarId);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * (Aurora only) Returns list of user account the calendar was shared with.
	 *
	 * @param int $iUserId
	 * @param CCalendar $oCalendar Calendar object
	 *
	 * @return array|bool
	 */
	public function getCalendarUsers($iUserId, $oCalendar)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->getCalendarUsers($iUserId, $oCalendar);
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}
	
	/**
	 *
	 * @return string|bool
	 */
	public function getPublicUser()
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getPublicUser();
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}
	
	/**
	 *
	 * @param CAccount $oAccount
	 *
	 * @return string|bool
	 */
	public function getTenantUser($oAccount)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getTenantUser($oAccount);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 *
	 * @return CAccount|false $oAccount
	 */
	public function getPublicAccount()
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getPublicAccount();
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Returns list of calendars for the account.
	 *
	 * @param int $iUserId
	 *
	 * @return array
	 */
	public function getUserCalendars($iUserId)
	{
		return $this->oStorage->getCalendars($iUserId);
	}

	/**
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	public function ___qSortCallback ($a, $b)
	{
		return ($a['is_default'] === '1' ? -1 : 1);
	}

	/**
	 * Creates new calendar.
	 *
	 * @param int $iUserId
	 * @param string $sName Name of the calendar
	 * @param string $sDescription Description of the calendar
	 * @param int $iOrder Ordinal number of the calendar in calendars list
	 * @param string $sColor Color code
	 *
	 * @return CCalendar|false
	 */
	public function createCalendar($iUserId, $sName, $sDescription, $iOrder, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->createCalendar($iUserId, $sName, $sDescription, $iOrder, $sColor);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Updates calendar properties.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sName Name of the calendar
	 * @param string $sDescription Description of the calendar
	 * @param int $iOrder Ordinal number of the calendar in calendars list
	 * @param string $sColor Color code
	 *
	 * @return CCalendar|false
	 */
	public function updateCalendar($iUserId, $sCalendarId, $sName, $sDescription, $iOrder, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->updateCalendar($iUserId, $sCalendarId, $sName, $sDescription, $iOrder, $sColor);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * @param string $sCalendarId
	 * @param int $iVisible
	 *
	 * @return bool
	 */
	public function updateCalendarVisible($sCalendarId, $iVisible)
	{
		$oResult = null;
		try
		{
			$this->oStorage->updateCalendarVisible($sCalendarId, $iVisible);
			$oResult = true;
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Change color of the calendar.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sColor New color code
	 *
	 * @return bool
	 */
	public function updateCalendarColor($iUserId, $sCalendarId, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->updateCalendarColor($iUserId, $sCalendarId, $sColor);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Deletes calendar.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return bool
	 */
	public function deleteCalendar($iUserId, $sCalendarId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->deleteCalendar($iUserId, $sCalendarId);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Removes calendar from list of those shared with the specific account. [Aurora only.](http://dev.afterlogic.com/aurora)
	 *
	 * @param int $iUserId Account object
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return bool
	 */
	public function unsubscribeCalendar($iUserId, $sCalendarId)
	{
		$oResult = null;
		if ($this->oApiCapabilityManager->isCalendarSharingSupported($iUserId))
		{
			try
			{
				$oResult = $this->oStorage->unsubscribeCalendar($iUserId, $sCalendarId);
			}
			catch (Exception $oException)
			{
				$oResult = false;
				$this->setLastException($oException);
			}
		}
		return $oResult;
	}

	/**
	 * (Aurora only) Share or remove sharing calendar with all users.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param bool $bShareToAll If set to **true**, add sharing; if **false**, sharing is removed
	 * @param int $iPermission Permissions set for the account. Accepted values:
	 *		- **ECalendarPermission::Read** (read-only access);
	 *		- **ECalendarPermission::Write** (read/write access);
	 *		- **ECalendarPermission::RemovePermission** (effectively removes sharing with the account).
	 *
	 * @return bool
	 */
	public function updateCalendarShareToAll($iUserId, $sCalendarId, $bShareToAll, $iPermission)
	{
		$sUserId = $this->getTenantUser($iUserId);
		$aShares[] = array(
			'name' => $sUserId,
			'email' => $sUserId,
			'access' => $bShareToAll ? $iPermission : \ECalendarPermission::RemovePermission
		);

		return $this->updateCalendarShares($iUserId, $sCalendarId, $aShares);
	}

	/**
	 * (Aurora only) Share or remove sharing calendar with the listed users.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param array $aShares Array defining list of users and permissions. Each array item needs to have the following keys:
	 *		["email"] - email which denotes user the calendar is shared to;
	 *		["access"] - permission settings equivalent to those used in updateCalendarShare method.
	 *
	 * @return bool
	 */
	public function updateCalendarShares($iUserId, $sCalendarId, $aShares)
	{
		$oResult = null;
		if ($this->oApiCapabilityManager->isCalendarSharingSupported($iUserId)) {
			try
			{
				$oResult = $this->oStorage->updateCalendarShares($iUserId, $sCalendarId, $aShares);
			}
			catch (Exception $oException)
			{
				$oResult = false;
				$this->setLastException($oException);
			}
		}
		return $oResult;
	}

	/**
	 * Set/unset calendar as public.
	 *
	 * @param int $iUserId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param bool $bIsPublic If set to **true**, calendar is made public; if **false**, setting as public gets cancelled
	 *
	 * @return bool
	 */
	public function publicCalendar($iUserId, $sCalendarId, $bIsPublic = false)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->publicCalendar($iUserId, $sCalendarId, $bIsPublic);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}
	
	/**
	 * (Aurora only) Removes sharing calendar with the specific user account.
	 *
	 * @param int $iUserId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sUserId User ID
	 *
	 * @return bool
	 */
	public function deleteCalendarShare($iUserId, $sCalendarId, $sUserId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->updateCalendarShare($iUserId, $sCalendarId, $sUserId);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Share or remove sharing calendar with the specific user account. [Aurora only.](http://dev.afterlogic.com/aurora)
	 *
	 * @param int $iUserId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sUserId User Id
	 * @param int $iPermission Permissions set for the account. Accepted values:
	 *		- **ECalendarPermission::Read** (read-only access);
	 *		- **ECalendarPermission::Write** (read/write access);
	 *		- **ECalendarPermission::RemovePermission** (effectively removes sharing with the account).
	 *
	 * @return bool
	 */
	public function updateCalendarShare($iUserId, $sCalendarId, $sUserId, $iPermission)
	{
		$oResult = null;
		if ($this->oApiCapabilityManager->isCalendarSharingSupported($iUserId)) {
			try
			{
				$oResult = $this->oStorage->updateCalendarShare($iUserId, $sCalendarId, $sUserId, $iPermission);
			}
			catch (Exception $oException)
			{
				$oResult = false;
				$this->setLastException($oException);
			}
		}
		return $oResult;
	}
	
	/**
	 * Returns calendar data as ICS data.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return string|bool
	 */
	public function exportCalendarToIcs($iUserId, $sCalendarId)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->exportCalendarToIcs($iUserId, $sCalendarId);
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * Populates calendar from .ICS file.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sTempFileName .ICS file name data are imported from
	 *
	 * @return int|bool integer (number of events added)
	 */
	public function importToCalendarFromIcs($iUserId, $sCalendarId, $sTempFileName)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->importToCalendarFromIcs($iUserId, $sCalendarId, $sTempFileName);
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * Returns list of events of public calendar within date range.
	 *
	 * @param string $sCalendarId Calendar ID
	 * @param string $dStart Date range start
	 * @param string $dFinish Date range end
	 * @param string $sTimezone Timezone identifier
	 * @param int $iTimezoneOffset Offset value for timezone
	 *
	 * @return array|bool
	 */
	public function getPublicEvents($sCalendarId, $dStart = null, $dFinish = null, $sTimezone = 'UTC', $iTimezoneOffset = 0)
	{
		return $this->getEvents($this->getPublicAccount(), $sCalendarId, $dStart, $dFinish);
	}

	/**
	 * Account object
	 *
	 * @param int $iUserId
	 * @param array | string $mCalendarId Calendar ID
	 * @param string $dStart Date range start
	 * @param string $dFinish Date range end
	 *
	 * @return array|bool
	 */
	public function getEvents($iUserId, $mCalendarId, $dStart = null, $dFinish = null)
	{
		$aResult = array();
		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart  - 86400) : null;
			$dFinish = ($dFinish != null) ? date('Ymd\T235959\Z', $dFinish) : null;
			$mCalendarId = !is_array($mCalendarId) ? array($mCalendarId) : $mCalendarId;

			foreach ($mCalendarId as $sCalendarId) {
				$aEvents = $this->oStorage->getEvents($iUserId, $sCalendarId, $dStart, $dFinish);
				if ($aEvents && is_array($aEvents)) {
					$aResult = array_merge($aResult, $aEvents);
				}
			}
		}
		catch (Exception $oException)
		{
			$aResult = false;
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param int $iUserId Account object
	 * @param mixed $mCalendarId
	 * @param object $dStart
	 * @param object $dEnd
	 * @param bool $bGetData
	 *
	 * @return string
	 */
	public function getEventsInfo($iUserId, $mCalendarId, $dStart = null, $dEnd = null, $bGetData = false)
	{
		$aResult = array();
		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart  - 86400) : null;
			$dEnd = ($dEnd != null) ? date('Ymd\T235959\Z', $dEnd) : null;
			$mCalendarId = !is_array($mCalendarId) ? array($mCalendarId) : $mCalendarId;

			foreach ($mCalendarId as $sCalendarId)
			{
				$aEvents = $this->oStorage->getEventsInfo($iUserId, $sCalendarId, $dStart, $dEnd, $bGetData);
				if ($aEvents && is_array($aEvents))
				{
					$aResult = array_merge($aResult, $aEvents);
				}
			}
		}
		catch (Exception $oException)
		{
			$aResult = false;
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * Return specific event.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return array|bool
	 */
	public function getEvent($iUserId, $sCalendarId, $sEventId)
	{
		$mResult = null;
		try
		{
			$mResult = array();
			$aData = $this->oStorage->getEvent($iUserId, $sCalendarId, $sEventId);
			if ($aData !== false) {
				if (isset($aData['vcal'])) {
					$oVCal = $aData['vcal'];
					$oCalendar = $this->oStorage->getCalendar($iUserId, $sCalendarId);
					$mResult = CalendarParser::parseEvent($iUserId, $oCalendar, $oVCal);
					$mResult['vcal'] = $oVCal;
				}
			}
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}
	
	
	// Events

	/**
	 * For recurring event, gets a base one.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return array|bool
	 */
	public function getBaseEvent($iUserId, $sCalendarId, $sEventId)
	{
		$mResult = null;
		try
		{
			$mResult = array();
			$aData = $this->oStorage->getEvent($iUserId, $sCalendarId, $sEventId);
			if ($aData !== false) {
				if (isset($aData['vcal'])) {
					$oVCal = $aData['vcal'];
					$oVCalOriginal = clone $oVCal;
					$oCalendar = $this->oStorage->getCalendar($iUserId, $sCalendarId);
					$oVEvent = $oVCal->getBaseComponents('VEVENT');
					if (isset($oVEvent[0])) {
						unset($oVCal->VEVENT);
						$oVCal->VEVENT = $oVEvent[0];
					}
					$oEvent = CalendarParser::parseEvent($iUserId, $oCalendar, $oVCal, $oVCalOriginal);
					if (isset($oEvent[0])) {
						$mResult = $oEvent[0];
					}
				}
			}
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * For recurring event, gets all occurences within a date range.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 * @param string $dStart Date range start
	 * @param string $dEnd Date range end
	 *
	 * @return array|bool
	 */
	public function getExpandedEvent($iUserId, $sCalendarId, $sEventId, $dStart = null, $dEnd = null)
	{
		$mResult = null;

		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart/*  + 86400*/) : null;
			$dEnd = ($dEnd != null) ? date('Ymd\T235959\Z', $dEnd) : null;
			$mResult = $this->oStorage->getExpandedEvent($iUserId, $sCalendarId, $sEventId, $dStart, $dEnd);
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $sData
	 *
	 * @return mixed
	 */
	public function createEventFromRaw($iUserId, $sCalendarId, $sEventId, $sData)
	{
		$oResult = null;
		$aEvents = array();
		try
		{
			$oVCal = \Sabre\VObject\Reader::read($sData);
			if ($oVCal && $oVCal->VEVENT) {
				if (!empty($sEventId)) {
					$oResult = $this->oStorage->createEvent($iUserId, $sCalendarId, $sEventId, $oVCal);
				} else {
					foreach ($oVCal->VEVENT as $oVEvent) {
						$sUid = (string)$oVEvent->UID;
						if (!isset($aEvents[$sUid])) {
							$aEvents[$sUid] = new \Sabre\VObject\Component\VCalendar();
						}
						$aEvents[$sUid]->add($oVEvent);
					}

					foreach ($aEvents as $sUid => $oVCalNew) {
						$this->oStorage->createEvent($iUserId, $sCalendarId, $sUid, $oVCalNew);
					}
					$oResult = true;
				}
			}
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}
	
	/**
	 * Creates event from event object.
	 *
	 * @param int $iUserId
	 * @param CEvent $oEvent Event object
	 *
	 * @return mixed
	 */
	public function createEvent($iUserId, $oEvent)
	{
		$oResult = null;
		try
		{
			$oEvent->Id = \Sabre\DAV\UUIDUtil::getUUID();

			$oVCal = new \Sabre\VObject\Component\VCalendar();

			$oVCal->add('VEVENT', array(
				'SEQUENCE' => 0,
				'TRANSP' => 'OPAQUE',
				'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
			));

			CCalendarHelper::populateVCalendar($iUserId, $oEvent, $oVCal->VEVENT);

			$oResult = $this->oStorage->createEvent($iUserId, $oEvent->IdCalendar, $oEvent->Id, $oVCal);

			if ($oResult) {
				$this->updateEventGroups($iUserId, $oEvent);
			}
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * @param int $iUserId
	 * @param CEvent $oEvent
	 */
	public function updateEventGroups($iUserId, $oEvent)
	{
		$aGroups = CCalendarHelper::findGroupsHashTagsFromString($oEvent->Name);
		$aGroupsDescription = CCalendarHelper::findGroupsHashTagsFromString($oEvent->Description);
		$aGroups = array_merge($aGroups, $aGroupsDescription);
		$aGroupsLocation = CCalendarHelper::findGroupsHashTagsFromString($oEvent->Location);
		$aGroups = array_merge($aGroups, $aGroupsLocation);


		$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
		if ($oContactsModule) {
			foreach ($aGroups as $sGroup) {
				$sGroupName = ltrim($sGroup, '#');
				$oGroup = $oContactsModule->CallMethod('getGroupByName', array($iUserId->IdUser, $sGroupName));
				if (!$oGroup) {
					$oGroup = new \CGroup();
					$oGroup->IdUser = $iUserId->IdUser;
					$oGroup->Name = $sGroupName;
					$oContactsModule->CallMethod('createGroup', array($oGroup));
				}

				$oContactsModule->CallMethod('removeEventFromGroup', array($oGroup->IdGroup, $oEvent->IdCalendar, $oEvent->Id));
				$oContactsModule->CallMethod('addEventToGroup', array($oGroup->IdGroup, $oEvent->IdCalendar, $oEvent->Id));
			}
		}
	}

	/**
	 * Update events using event object.
	 *
	 * @param int $iUserId
	 * @param CEvent $oEvent Event object
	 *
	 * @return bool
	 */
	public function updateEvent($iUserId, $oEvent)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($iUserId, $oEvent->IdCalendar, $oEvent->Id);
			if ($aData !== false) {
				$oVCal = $aData['vcal'];

				if ($oVCal) {
					$iIndex = CCalendarHelper::getBaseVEventIndex($oVCal->VEVENT);
					if ($iIndex !== false) {
						CCalendarHelper::populateVCalendar($iUserId, $oEvent, $oVCal->VEVENT[$iIndex]);
					}
					$oVCalCopy = clone $oVCal;
					if (!isset($oEvent->RRule)) {
						unset($oVCalCopy->VEVENT);
						foreach ($oVCal->VEVENT as $oVEvent) {
                            $oVEvent->SEQUENCE = (int) $oVEvent->SEQUENCE->getValue() + 1;
							if (!isset($oVEvent->{'RECURRENCE-ID'})) {
								$oVCalCopy->add($oVEvent);
							}
						}
					}

					$oResult = $this->oStorage->updateEvent($iUserId, $oEvent->IdCalendar, $oEvent->Id, $oVCalCopy);
					if ($oResult) {
						$this->updateEventGroups($iUserId, $oEvent);
					}

				}
			}
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventUrl
	 * @param string $sData
	 * 
	 * @return bool
	 */
	public function updateEventRaw($iUserId, $sCalendarId, $sEventUrl, $sData)
	{
		return $this->oStorage->updateEventRaw($iUserId, $sCalendarId, $sEventUrl, $sData);
	}
	
	/**
	 * Moves event to a different calendar.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Current calendar ID
	 * @param string $sCalendarIdNew New calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return bool
	 */
	public function moveEvent($iUserId, $sCalendarId, $sCalendarIdNew, $sEventId)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($iUserId, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) {
				$oResult = $this->oStorage->moveEvent($iUserId, $sCalendarId, $sCalendarIdNew, $sEventId, $aData['vcal']->serialize());
				$this->updateEventGroupByMoving($sCalendarId, $sEventId, $sCalendarIdNew);
				return true;
			}
			return false;
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $sNewCalendarId
	 */
	public function updateEventGroupByMoving($sCalendarId, $sEventId, $sNewCalendarId)
	{
		$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
		if ($oContactsModule) {
			$aEvents = $oContactsModule->CallMethod('getGroupEvent', array($sCalendarId, $sEventId));
			if (is_array($aEvents) && 0 < count($aEvents)) {
				foreach ($aEvents as $aEvent) {
					if (isset($aEvent['id_group'])) {
						$oContactsModule->CallMethod('removeEventFromGroup', array($aEvent['id_group'], $sCalendarId, $sEventId));
						$oContactsModule->CallMethod('addEventToGroup', array($aEvent['id_group'], $sNewCalendarId, $sEventId));
					}
				}
			}
		}
	}

	/**
	 * Updates or deletes exclusion from recurring event.
	 *
	 * @param int $iUserId
	 * @param CEvent $oEvent Event object
	 * @param string $sRecurrenceId Recurrence ID
	 * @param bool $bDelete If **true**, exclusion is deleted
	 *
	 * @return bool
	 */
	public function updateExclusion($iUserId, $oEvent, $sRecurrenceId, $bDelete = false)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($iUserId, $oEvent->IdCalendar, $oEvent->Id);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) {
				$oVCal = $aData['vcal'];
				$iIndex = CCalendarHelper::getBaseVEventIndex($oVCal->VEVENT);
				if ($iIndex !== false) {
					$oVCal->VEVENT[$iIndex]->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));

					$oDTExdate = CCalendarHelper::prepareDateTime($sRecurrenceId, $iUserId->getDefaultStrTimeZone());
					$oDTStart = $oVCal->VEVENT[$iIndex]->DTSTART->getDatetime();

					$mIndex = CCalendarHelper::isRecurrenceExists($oVCal->VEVENT, $sRecurrenceId);
					if ($bDelete) {
						// if exclude first event in occurrence
						if ($oDTExdate == $oDTStart) {
							$it = new \Sabre\VObject\RecurrenceIterator($oVCal, (string) $oVCal->VEVENT[$iIndex]->UID);
							$it->fastForward($oDTStart);
							$it->next();

							if ($it->valid()) {
								$oEventObj = $it->getEventObject();
							}

							$oVCal->VEVENT[$iIndex]->DTSTART = $oEventObj->DTSTART;
							$oVCal->VEVENT[$iIndex]->DTEND = $oEventObj->DTEND;
						}

						$oVCal->VEVENT[$iIndex]->add('EXDATE', $oDTExdate);

						if (false !== $mIndex) {
							$aVEvents = $oVCal->VEVENT;
							unset($oVCal->VEVENT);

							foreach($aVEvents as $oVEvent) {
								if ($oVEvent->{'RECURRENCE-ID'}) {
									$iRecurrenceId = CCalendarHelper::getStrDate($oVEvent->{'RECURRENCE-ID'}, $iUserId->getDefaultStrTimeZone(), 'Ymd');
									if ($iRecurrenceId == (int) $sRecurrenceId) {
										continue;
									}
								}
								$oVCal->add($oVEvent);
							}
						}
					} else {
						$oVEventRecur = null;
						if ($mIndex === false) {
							$oVEventRecur = $oVCal->add('VEVENT', array(
								'SEQUENCE' => 1,
								'TRANSP' => 'OPAQUE',
								'RECURRENCE-ID' => $oDTExdate
							));
						} else if (isset($oVCal->VEVENT[$mIndex])) {
							$oVEventRecur = $oVCal->VEVENT[$mIndex];
						}
						if ($oVEventRecur) {
							$oEvent->RRule = null;
							CCalendarHelper::populateVCalendar($iUserId, $oEvent, $oVEventRecur);
						}
					}

					return $this->oStorage->updateEvent($iUserId, $oEvent->IdCalendar, $oEvent->Id, $oVCal);

				}
			}
			return false;
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * deleteExclusion
	 *
	 * @param int $iUserId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 * @param string $iRecurrenceId Recurrence ID
	 *
	 * @return bool
	 */
	public function deleteExclusion($iUserId, $sCalendarId, $sEventId, $iRecurrenceId)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($iUserId, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) {
				$oVCal = $aData['vcal'];

				$aVEvents = $oVCal->VEVENT;
				unset($oVCal->VEVENT);

				foreach($aVEvents as $oVEvent) {
					if (isset($oVEvent->{'RECURRENCE-ID'})) {
						$iServerRecurrenceId = CCalendarHelper::getStrDate($oVEvent->{'RECURRENCE-ID'}, $iUserId->getDefaultStrTimeZone(), 'Ymd');
						if ($iRecurrenceId == $iServerRecurrenceId) {
							continue;
						}
					}
					$oVCal->add($oVEvent);
				}
				return $this->oStorage->updateEvent($iUserId, $sCalendarId, $sEventId, $oVCal);
			}
			return false;
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 *
	 * @param int $start
	 * @param int $end
	 *
	 * @return array|bool
	 */
	public function getReminders($start = null, $end = null)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getReminders($start, $end);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 *
	 * @param string $sEventId
	 * @return bool
	 */
	public function deleteReminder($sEventId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->deleteReminder($sEventId);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 *
	 * @param string $sCalendarUri
	 *
	 * @return bool
	 */
	public function deleteReminderByCalendar($sCalendarUri)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->deleteReminderByCalendar($sCalendarUri);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 *
	 * @param string $sEmail
	 * @param string $sCalendarUri
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return bool
	 */
	public function updateReminder($sEmail, $sCalendarUri, $sEventId, $sData)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->updateReminder($sEmail, $sCalendarUri, $sEventId, $sData);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Processing response to event invitation. [Aurora only.](http://dev.afterlogic.com/aurora)
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 * @param string $sAttendee Attendee identified by email address
	 * @param string $sAction Appointment actions. Accepted values:
	 *		- "ACCEPTED"
	 *		- "DECLINED"
	 *		- "TENTATIVE"
	 *
	 * @return bool
	 */
	public function updateAppointment($iUserId, $sCalendarId, $sEventId, $sAttendee, $sAction)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($iUserId, $sCalendarId, $sEventId);
			if ($aData !== false) {
				$oVCal = $aData['vcal'];
				$oVCal->METHOD = 'REQUEST';
				return $this->appointmentAction($iUserId, $sAttendee, $sAction, $sCalendarId, $oVCal->serialize());
			}
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Allows for responding to event invitation (accept / decline / tentative). [Aurora only.](http://dev.afterlogic.com/aurora)
	 *
	 * @param int|string $iUserId Account object
	 * @param string $sAttendee Attendee identified by email address
	 * @param string $sAction Appointment actions. Accepted values:
	 *		- "ACCEPTED"
	 *		- "DECLINED"
	 *		- "TENTATIVE"
	 * @param string $sCalendarId Calendar ID
	 * @param string $sData ICS data of the response
	 * @param bool $bExternal If **true**, it is assumed attendee is external to the system
	 *
	 * @return bool
	 */
	public function appointmentAction($iUserId, $sAttendee, $sAction, $sCalendarId, $sData, $bExternal = false)
	{
		$oDefaultAccount = null;
		$oAttendeeAccount = null;
		$bDefaultAccountAsEmail = false;
		$bIsDefaultAccount = false;
				
		if (isset($iUserId) && is_int($iUserId)) {
			$bDefaultAccountAsEmail = false;
			/* @var $oDefaultAccount CAccount */
			$oDefaultAccount = $this->ApiUsersManager->getDefaultAccount($iUserId);
			$bIsDefaultAccount = true;
		} else {
			$oAttendeeAccount = $this->ApiUsersManager->getAccountByEmail($sAttendee);
			if ($oAttendeeAccount) 	{
				$bDefaultAccountAsEmail = false;
				$oDefaultAccount = $oAttendeeAccount;
			} else {
				$bDefaultAccountAsEmail = true;
			}
		}
		if (!$bDefaultAccountAsEmail && !$bIsDefaultAccount) {
			$oCalendar = $this->getDefaultCalendar($oDefaultAccount);
			if ($oCalendar) {
				$sCalendarId = $oCalendar['Id'];
			}
		}

		$bResult = false;
		$sEventId = null;
		try
		{
			$sTo = $sSubject = $sBody = $sSummary = '';

			$oVCal = \Sabre\VObject\Reader::read($sData);
			if ($oVCal) {
				$sMethod = $sMethodOriginal = (string) $oVCal->METHOD;
				$aVEvents = $oVCal->getBaseComponents('VEVENT');

				if (isset($aVEvents) && count($aVEvents) > 0) {
					$oVEvent = $aVEvents[0];
					$sEventId = (string)$oVEvent->UID;
					$bAllDay = (isset($oVEvent->DTSTART) && !$oVEvent->DTSTART->hasTime());

					if (isset($oVEvent->SUMMARY)) {
						$sSummary = (string)$oVEvent->SUMMARY;
					}
					if (isset($oVEvent->ORGANIZER)) {
						$sTo = str_replace('mailto:', '', strtolower((string)$oVEvent->ORGANIZER));
					}
					if (strtoupper($sMethod) === 'REQUEST') {
						$sMethod = 'REPLY';
						$sSubject = $sSummary;

//						unset($oVEvent->ATTENDEE);
						$sPartstat = strtoupper($sAction);
						switch ($sPartstat)
						{
							case 'ACCEPTED':
								$sSubject = 'Accepted: '. $sSubject;
								break;
							case 'DECLINED':
								$sSubject = 'Declined: '. $sSubject;
								break;
							case 'TENTATIVE':
								$sSubject = 'Tentative: '. $sSubject;
								break;
						}

						$sCN = '';
						if (isset($oDefaultAccount) && $sAttendee ===  $oDefaultAccount->Email) {
							if (!empty($oDefaultAccount->FriendlyName)) {
								$sCN = $oDefaultAccount->FriendlyName;
							} else {
								$sCN = $sAttendee;
							}
						}
						
						foreach($oVEvent->ATTENDEE as $oAttendee) {
							$sEmail = str_replace('mailto:', '', strtolower((string)$oAttendee));
							if (strtolower($sEmail) === strtolower($sAttendee)) {
								$oAttendee['CN'] = $sCN;
								$oAttendee['PARTSTAT'] = $sPartstat;
								$oAttendee['RESPONDED-AT'] = gmdate("Ymd\THis\Z");
							}
						}
						
/*
						$oVEvent->add('ATTENDEE', 'mailto:'.$sAttendee, array(
							'CN' => $sCN,
							'PARTSTAT' => $sPartstat,
							'RESPONDED-AT' => gmdate("Ymd\THis\Z")
						));
 * 
 */
					}

					$oVCal->METHOD = $sMethod;
					$oVEvent->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));

					$sBody = $oVCal->serialize();

					if ($sCalendarId !== false && $bExternal === false && !$bDefaultAccountAsEmail) {
						unset($oVCal->METHOD);
						
						if ($sAttendee === $oDefaultAccount->Email) {
							if (strtoupper($sAction) == 'DECLINED' || strtoupper($sMethod) == 'CANCEL') {
								$this->deleteEvent($oDefaultAccount, $sCalendarId, $sEventId);
							}
						} else {
							$this->oStorage->updateEventRaw($oDefaultAccount, $sCalendarId, $sEventId, $oVCal->serialize());
						}
					}

					if (strtoupper($sMethodOriginal) == 'REQUEST'/* && (strtoupper($sAction) !== 'DECLINED')*/) {
						if (!empty($sTo) && !empty($sBody)) {
							$oToAccount = $this->ApiUsersManager->getAccountByEmail($sTo);
							if ($oToAccount) {
								$bResult = ($this->processICS($oToAccount, $sBody, $sAttendee, true) !== false);
							}
							if ((!$oToAccount || !$bResult) && $oDefaultAccount instanceof \CAccount) {
								if (!$oAttendeeAccount) {
									$oAttendeeAccount = $this->getAccountFromAccountList($iUserId, $sAttendee);
								}
								if (!($oAttendeeAccount instanceof \CAccount)) {
									$oAttendeeAccount = $oDefaultAccount;
								}
								$bResult = CCalendarHelper::sendAppointmentMessage($oAttendeeAccount, $sTo, $sSubject, $sBody, $sMethod);
							}
						}
					} else {
						$bResult = true;
					}
				}
			}

			if (!$bResult) {
				\Aurora\System\Api::Log('Ics Appointment Action FALSE result!', ELogLevel::Error);
				if ($iUserId) {
					\Aurora\System\Api::Log('Email: '.$iUserId->Email.', Action: '. $sAction.', Data:', ELogLevel::Error);
				}
				\Aurora\System\Api::Log($sData, ELogLevel::Error);
			} else {
				$bResult = $sEventId;
			}

			return $bResult;
		}
		catch (Exception $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}
	
	/**
	 * Returns default calendar of the account.
	 *
	 * @param int $iUserId
	 *
	 * @return CCalendar|false $oCalendar
	 */
	public function getDefaultCalendar($iUserId)
	{
		$aCalendars = $this->getCalendars($iUserId);
		return (is_array($aCalendars) && isset($aCalendars[0])) ? $aCalendars[0] : false;
	}

	/**
	 * @param int $iUserId
	 *
	 * @return bool
	 */
	public function getCalendars($iUserId)
	{
		$oResult = array();
		try
		{
			$oCalendars = array();
			$oCalendarsOwn = $this->oStorage->getCalendars($iUserId);

			if ($this->oApiCapabilityManager->isCalendarSharingSupported($iUserId)) {
				$oCalendarsSharedToAll = array();

				$aCalendarsSharedToAllIds = array_map(
					function($oCalendar) {
						if ($oCalendar->SharedToAll) {
							return $oCalendar->IntId;
						}
					},
					$oCalendarsOwn
				);

				foreach ($oCalendarsOwn as $oCalendarOwn) {
					if (in_array($oCalendarOwn->IntId, $aCalendarsSharedToAllIds)) {
						$oCalendarOwn->Shared = true;
						$oCalendarOwn->SharedToAll = true;
					}
					$oCalendarsSharedToAll[$oCalendarOwn->IntId] = $oCalendarOwn;
				}
				$oCalendars = $oCalendarsSharedToAll;
			} else {
				$oCalendars = $oCalendarsOwn;
			}

			$bDefault = false;
			foreach ($oCalendars as $oCalendar) {
				if (!$bDefault && $oCalendar->Access !== ECalendarPermission::Read) {
					$oCalendar->IsDefault = $bDefault = true;
				}
				$oCalendar = $this->populateCalendarShares($iUserId, $oCalendar);
				$oResult[] = $oCalendar;
			}
		}
		catch (Exception $oException)
		{
			$oResult = false;
//			$this->setLastException($oException);
		}

		return $oResult;
	}
	
	/**
	 * Deletes event.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return bool
	 */
	public function deleteEvent($iUserId, $sCalendarId, $sEventId)
	{
		$oResult = false;
		try
		{
			$aData = $this->oStorage->getEvent($iUserId, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) {
				$oVCal = $aData['vcal'];

				$iIndex = CCalendarHelper::getBaseVEventIndex($oVCal->VEVENT);
				if ($iIndex !== false) {
					$oVEvent = $oVCal->VEVENT[$iIndex];

					$sOrganizer = (isset($oVEvent->ORGANIZER)) ?
							str_replace('mailto:', '', strtolower((string)$oVEvent->ORGANIZER)) : null;

					if (isset($sOrganizer)) {
						if ($sOrganizer === $iUserId->Email) {
							$oDateTimeNow = new DateTime("now");
							$oDateTimeEvent = $oVEvent->DTSTART->getDateTime();
							$oDateTimeRepeat = \CCalendarHelper::getNextRepeat($oDateTimeNow, $oVEvent);
							$bRrule = isset($oVEvent->RRULE);
							$bEventFore = $oDateTimeEvent ? $oDateTimeEvent > $oDateTimeNow : false;
							$bNextRepeatFore = $oDateTimeRepeat ? $oDateTimeRepeat > $oDateTimeNow : false;

							if (isset($oVEvent->ATTENDEE) && ($bRrule ? $bNextRepeatFore : $bEventFore)) {
								foreach($oVEvent->ATTENDEE as $oAttendee) {
									$sEmail = str_replace('mailto:', '', strtolower((string)$oAttendee));

									$oVCal->METHOD = 'CANCEL';
									$sSubject = (string)$oVEvent->SUMMARY . ': Canceled';

									CCalendarHelper::sendAppointmentMessage($iUserId, $sEmail, $sSubject, $oVCal->serialize(), 'REQUEST');
									unset($oVCal->METHOD);
								}
							}
						}
					}
				}
				$oResult = $this->oStorage->deleteEvent($iUserId, $sCalendarId, $sEventId);
				if ($oResult) {
					$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
					$oContactsModule->CallMethod('removeEventFromAllGroups', array($sCalendarId, $sEventId));
				}
			}
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	/**
	 * Deletes event.
	 *
	 * @param int $iUserId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventUrl Event URL
	 *
	 * @return bool
	 */
	public function deleteEventByUrl($iUserId, $sCalendarId, $sEventUrl)
	{
		return $this->oStorage->deleteEventByUrl($iUserId, $sCalendarId, $sEventUrl);
	}


	/**
	 * @param int $iUserId
	 * @param string $sData
	 * @param string $mFromEmail
	 * @param bool $bUpdateAttendeeStatus
	 *
	 * @return array|bool
	 */
	public function processICS($iUserId, $sData, $mFromEmail, $bUpdateAttendeeStatus = false)
	{
		$mResult = false;

		/* @var $oDefaultAccount CAccount */
		$oDefaultAccount = $iUserId->UseToAuthorize ? $iUserId : $this->ApiUsersManager->getDefaultAccount($iUserId->IdUser);

		$aAccountEmails = array();
		$aUserAccounts = $this->ApiUsersManager->getUserAccounts($iUserId->IdUser);
		foreach ($aUserAccounts as $aUserAccount) {
			if (isset($aUserAccount) && isset($aUserAccount[1])) {
				$aAccountEmails[] = $aUserAccount[1];
			}
		}
		
		$aFetchers = \Aurora\System\Api::ExecuteMethod('Mail::GetFetchers', array('Account' => $oDefaultAccount));
		if (is_array($aFetchers) && 0 < count($aFetchers)) {
			foreach ($aFetchers as /* @var $oFetcher \CFetcher */ $oFetcher) {
				if ($oFetcher) {
					$aAccountEmails[] = !empty($oFetcher->Email) ? $oFetcher->Email : $oFetcher->IncomingLogin;
				}
			}
		}
			
		$aIdentities = $this->ApiUsersManager->getUserIdentities($iUserId->IdUser);
		if (is_array($aIdentities) && 0 < count($aIdentities)) {
			foreach ($aIdentities as /* @var $oIdentity \CIdentity */ $oIdentity) {
				if ($oIdentity) {
					$aAccountEmails[] = $oIdentity->Email;
				}
			}
		}

		try
		{
			$oVCal = \Sabre\VObject\Reader::read($sData);
			if ($oVCal) {
				$oVCalResult = $oVCal;

				$oMethod = isset($oVCal->METHOD) ? $oVCal->METHOD : null;
				$sMethod = isset($oMethod) ? (string) $oMethod : 'SAVE';

				if (!in_array($sMethod, array('REQUEST', 'REPLY', 'CANCEL', 'PUBLISH', 'SAVE'))) {
					return false;
				}

				$aVEvents = $oVCal->getBaseComponents('VEVENT');
				$oVEvent = (isset($aVEvents) && count($aVEvents) > 0) ? $aVEvents[0] : null;

				if (isset($oVEvent)) {
					$sCalendarId = '';
					$oVEventResult = $oVEvent;

					$sEventId = (string)$oVEventResult->UID;

					$aCalendars = $this->oStorage->GetCalendarNames($oDefaultAccount);
					$aCalendarIds = $this->oStorage->findEventInCalendars($oDefaultAccount, $sEventId, $aCalendars);

					if (is_array($aCalendarIds) && isset($aCalendarIds[0])) {
						$sCalendarId = $aCalendarIds[0];
						$aDataServer = $this->oStorage->getEvent($oDefaultAccount, $sCalendarId, $sEventId);
						if ($aDataServer !== false) {
							$oVCalServer = $aDataServer['vcal'];
							if (isset($oMethod)) {
								$oVCalServer->METHOD = $oMethod;
							}
							$aVEventsServer = $oVCalServer->getBaseComponents('VEVENT');
							if (count($aVEventsServer) > 0) {
								$oVEventServer = $aVEventsServer[0];

								if (isset($oVEvent->{'LAST-MODIFIED'}) && 
									isset($oVEventServer->{'LAST-MODIFIED'})) {
									$lastModified = $oVEvent->{'LAST-MODIFIED'}->getDateTime();
									$lastModifiedServer = $oVEventServer->{'LAST-MODIFIED'}->getDateTime();

                                    $sequence = isset($oVEvent->{'SEQUENCE'}) && $oVEvent->{'SEQUENCE'}->getValue() ? $oVEvent->{'SEQUENCE'}->getValue() : 0 ; // current sequence value
                                    $sequenceServer = isset($oVEventServer->{'SEQUENCE'}) && $oVEventServer->{'SEQUENCE'}->getValue() ? $oVEventServer->{'SEQUENCE'}->getValue() : 0; // accepted sequence value

                                    if ($sequenceServer >= $sequence) {
										$oVCalResult = $oVCalServer;
										$oVEventResult = $oVEventServer;
									}
									if (isset($sMethod) && !($lastModifiedServer >= $lastModified)) {
										if ($sMethod === 'REPLY') {
											$oVCalResult = $oVCalServer;
											$oVEventResult = $oVEventServer;

											if (isset($oVEvent->ATTENDEE) && $sequenceServer >= $sequence) {
												$oAttendee = $oVEvent->ATTENDEE[0];
												$sAttendee = str_replace('mailto:', '', strtolower((string)$oAttendee));
												if (isset($oVEventResult->ATTENDEE)) {
													foreach ($oVEventResult->ATTENDEE as $oAttendeeResult) {
														$sEmailResult = str_replace('mailto:', '', strtolower((string)$oAttendeeResult));
														if ($sEmailResult === $sAttendee) {
															if (isset($oAttendee['PARTSTAT'])) {
																$oAttendeeResult['PARTSTAT'] = $oAttendee['PARTSTAT']->getValue();
															}
															break;
														}
													}
												}
											}
											if ($bUpdateAttendeeStatus) {
												unset($oVCalResult->METHOD);
												$oVEventResult->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
												$mResult = $this->oStorage->updateEventRaw($oDefaultAccount, $sCalendarId, $sEventId, $oVCalResult->serialize());
												$oVCalResult->METHOD = $sMethod;
											}
                                        }
									}
								}
							}
						}

                        if ($sMethod === 'CANCEL' && $bUpdateAttendeeStatus) {
                            if ($this->deleteEvent($oDefaultAccount, $sCalendarId, $sEventId)) {
                                $mResult = true;
                            }
                        }

					}

					if (!$bUpdateAttendeeStatus) {
						$sTimeFormat = (isset($oVEventResult->DTSTART) && !$oVEventResult->DTSTART->hasTime()) ? 'D, M d' : 'D, M d, Y, H:i';
						$mResult = array(
							'Calendars' => $aCalendars,
							'CalendarId' => $sCalendarId,
							'UID' => $sEventId,
							'Body' => $oVCalResult->serialize(),
							'Action' => $sMethod,
							'Location' => isset($oVEventResult->LOCATION) ? (string)$oVEventResult->LOCATION : '',
							'Description' => isset($oVEventResult->DESCRIPTION) ? (string)$oVEventResult->DESCRIPTION : '',
							'When' => CCalendarHelper::getStrDate($oVEventResult->DTSTART, $oDefaultAccount->getDefaultStrTimeZone(), $sTimeFormat),
							'Sequence' => isset($sequence) ? $sequence : 1
						);

						$aAccountEmails = ($sMethod === 'REPLY') ? array($mFromEmail) : $aAccountEmails;
						if (isset($oVEventResult->ATTENDEE) && isset($sequenceServer) && isset($sequence) && $sequenceServer >= $sequence) {
							foreach($oVEventResult->ATTENDEE as $oAttendee) {
								$sAttendee = str_replace('mailto:', '', strtolower((string)$oAttendee));
								if (in_array($sAttendee, $aAccountEmails) && isset($oAttendee['PARTSTAT'])) {
									$mResult['Attendee'] = $sAttendee;
									$mResult['Action'] = $sMethod . '-' . $oAttendee['PARTSTAT']->getValue();
								}
							}
						}
					}
				}
			}
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}

		return $mResult;
	}
	
	/**
	 *
	 * @param CAccount $oAccount
	 * @param string $sEmail
	 *
	 * @return CAccount|false $oAccount
	 */
	public function getAccountFromAccountList($oAccount, $sEmail)
	{
		$oResult = null;
		$iResultAccountId = 0;

		try
		{
			if ($oAccount) {
				$aUserAccounts = $this->ApiUsersManager->getUserAccounts($oAccount->IdUser);
				foreach ($aUserAccounts as $iAccountId => $aUserAccount) {
					if (isset($aUserAccount) && isset($aUserAccount[1]) &&
							strtolower($aUserAccount[1]) === strtolower($sEmail)) {
						$iResultAccountId = $iAccountId;
						break;
					}
				}
				if (0 < $iResultAccountId) {
					$oResult = $this->ApiUsersManager->getAccountById($iResultAccountId);
				}
			}
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}
	
	/**
	 * @param int $iUserId
	 *
	 * @return bool
	 */
	public function clearAllCalendars($iUserId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->clearAllCalendars($iUserId);
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}
}