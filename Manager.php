<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AfterLogic Software License
 *
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar;

class Manager extends \Aurora\System\Managers\AbstractManagerWithStorage
{
	/*
	 * @type $ApiUsersManager CApiUsersManager
	 */
	protected $ApiUsersManager;

	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct($sForcedStorage = '', \Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule, new Storages\Sabredav($this));
	}
	
	protected function isCalendarSharingSupported($sUserUUID)
	{
		return false; // TODO
	}
	
	/**
	 * Determines whether read/write or read-only permissions are set for accessing calendar from this account. 
	 * 
	 * @param string $sUserUUID Account object 
	 * @param string $sCalendarId Calendar ID 
	 * 
	 * @return bool **ECalendarPermission::Write** or **ECalendarPermission::Read** accordingly.
	 */
	public function getCalendarAccess($sUserUUID, $sCalendarId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getCalendarAccess($sUserUUID, $sCalendarId);
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
	 * @param string $sUserUUID
	 * @param CCalendar $oCalendar
	 *
	 * @return CCalendar $oCalendar
	 */
	public function populateCalendarShares($sUserUUID, $oCalendar)
	{
		// TODO
/*		
		if (!$oCalendar->Shared || $oCalendar->Shared && 
				$oCalendar->Access === \ECalendarPermission::Write || $oCalendar->IsCalendarOwner($sUserUUID)) {
			$oCalendar->PubHash = $this->getPublicCalendarHash($oCalendar->Id);
			$aUsers = $this->getCalendarUsers($sUserUUID, $oCalendar);

			$aShares = array();
			if ($aUsers && is_array($aUsers)) {
				foreach ($aUsers as $aUser) {
					if ($aUser['email'] === $this->GetPublicUser()) {
						$oCalendar->IsPublic = true;
					} else if ($aUser['email'] === $this->getTenantUser($sUserUUID)) {
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
*/
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
	 * @param string $sUserUUID
	 * @param CCalendar $oCalendar Calendar object
	 *
	 * @return array|bool
	 */
	public function getCalendarUsers($sUserUUID, $oCalendar)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->getCalendarUsers($sUserUUID, $oCalendar);
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
	 * @param string $sUserUUID
	 *
	 * @return array
	 */
	public function getUserCalendars($sUserUUID)
	{
		return $this->oStorage->getCalendars($sUserUUID);
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
	 * @param string $sUserUUID
	 * @param string $sName Name of the calendar
	 * @param string $sDescription Description of the calendar
	 * @param int $iOrder Ordinal number of the calendar in calendars list
	 * @param string $sColor Color code
	 *
	 * @return CCalendar|false
	 */
	public function createCalendar($sUserUUID, $sName, $sDescription, $iOrder, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->createCalendar($sUserUUID, $sName, $sDescription, $iOrder, $sColor);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sName Name of the calendar
	 * @param string $sDescription Description of the calendar
	 * @param int $iOrder Ordinal number of the calendar in calendars list
	 * @param string $sColor Color code
	 *
	 * @return CCalendar|false
	 */
	public function updateCalendar($sUserUUID, $sCalendarId, $sName, $sDescription, $iOrder, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->updateCalendar($sUserUUID, $sCalendarId, $sName, $sDescription, $iOrder, $sColor);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sColor New color code
	 *
	 * @return bool
	 */
	public function updateCalendarColor($sUserUUID, $sCalendarId, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->updateCalendarColor($sUserUUID, $sCalendarId, $sColor);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return bool
	 */
	public function deleteCalendar($sUserUUID, $sCalendarId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->deleteCalendar($sUserUUID, $sCalendarId);
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
	 * @param string $sUserUUID Account object
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return bool
	 */
	public function unsubscribeCalendar($sUserUUID, $sCalendarId)
	{
		$oResult = null;
		if ($this->isCalendarSharingSupported($sUserUUID))
		{
			try
			{
				$oResult = $this->oStorage->unsubscribeCalendar($sUserUUID, $sCalendarId);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param bool $bShareToAll If set to **true**, add sharing; if **false**, sharing is removed
	 * @param int $iPermission Permissions set for the account. Accepted values:
	 *		- **ECalendarPermission::Read** (read-only access);
	 *		- **ECalendarPermission::Write** (read/write access);
	 *		- **ECalendarPermission::RemovePermission** (effectively removes sharing with the account).
	 *
	 * @return bool
	 */
	public function updateCalendarShareToAll($sUserUUID, $sCalendarId, $bShareToAll, $iPermission)
	{
		$sUserId = $this->getTenantUser($sUserUUID);
		$aShares[] = array(
			'name' => $sUserId,
			'email' => $sUserId,
			'access' => $bShareToAll ? $iPermission : \ECalendarPermission::RemovePermission
		);

		return $this->updateCalendarShares($sUserUUID, $sCalendarId, $aShares);
	}

	/**
	 * (Aurora only) Share or remove sharing calendar with the listed users.
	 *
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param array $aShares Array defining list of users and permissions. Each array item needs to have the following keys:
	 *		["email"] - email which denotes user the calendar is shared to;
	 *		["access"] - permission settings equivalent to those used in updateCalendarShare method.
	 *
	 * @return bool
	 */
	public function updateCalendarShares($sUserUUID, $sCalendarId, $aShares)
	{
		$oResult = null;
		if ($this->isCalendarSharingSupported($sUserUUID)) {
			try
			{
				$oResult = $this->oStorage->updateCalendarShares($sUserUUID, $sCalendarId, $aShares);
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
	 * @param string $sUserUUID Account object
	 * @param string $sCalendarId Calendar ID
	 * @param bool $bIsPublic If set to **true**, calendar is made public; if **false**, setting as public gets cancelled
	 *
	 * @return bool
	 */
	public function publicCalendar($sUserUUID, $sCalendarId, $bIsPublic = false)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->publicCalendar($sUserUUID, $sCalendarId, $bIsPublic);
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
	 * @param string $sUserUUID Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sUserId User ID
	 *
	 * @return bool
	 */
	public function deleteCalendarShare($sUserUUID, $sCalendarId, $sUserId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->updateCalendarShare($sUserUUID, $sCalendarId, $sUserId);
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
	 * @param string $sUserUUID Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sUserId User Id
	 * @param int $iPermission Permissions set for the account. Accepted values:
	 *		- **ECalendarPermission::Read** (read-only access);
	 *		- **ECalendarPermission::Write** (read/write access);
	 *		- **ECalendarPermission::RemovePermission** (effectively removes sharing with the account).
	 *
	 * @return bool
	 */
	public function updateCalendarShare($sUserUUID, $sCalendarId, $sUserId, $iPermission)
	{
		$oResult = null;
		if ($this->isCalendarSharingSupported($sUserUUID)) {
			try
			{
				$oResult = $this->oStorage->updateCalendarShare($sUserUUID, $sCalendarId, $sUserId, $iPermission);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return string|bool
	 */
	public function exportCalendarToIcs($sUserUUID, $sCalendarId)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->exportCalendarToIcs($sUserUUID, $sCalendarId);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sTempFileName .ICS file name data are imported from
	 *
	 * @return int|bool integer (number of events added)
	 */
	public function importToCalendarFromIcs($sUserUUID, $sCalendarId, $sTempFileName)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->importToCalendarFromIcs($sUserUUID, $sCalendarId, $sTempFileName);
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
	 *
	 * @return array|bool
	 */
	public function getPublicEvents($sCalendarId, $dStart = null, $dFinish = null)
	{
		return $this->getEvents($this->getPublicAccount(), $sCalendarId, $dStart, $dFinish);
	}

	/**
	 * Account object
	 *
	 * @param string $sUserUUID
	 * @param array | string $mCalendarId Calendar ID
	 * @param string $dStart Date range start
	 * @param string $dFinish Date range end
	 *
	 * @return array|bool
	 */
	public function getEvents($sUserUUID, $mCalendarId, $dStart = null, $dFinish = null)
	{
		$aResult = array();
		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart  - 86400) : null;
			$dFinish = ($dFinish != null) ? date('Ymd\T235959\Z', $dFinish) : null;
			$mCalendarId = !is_array($mCalendarId) ? array($mCalendarId) : $mCalendarId;

			foreach ($mCalendarId as $sCalendarId) {
				$aEvents = $this->oStorage->getEvents($sUserUUID, $sCalendarId, $dStart, $dFinish);
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
	 * @param string $sUserUUID Account object
	 * @param mixed $mCalendarId
	 * @param object $dStart
	 * @param object $dEnd
	 * @param bool $bGetData
	 *
	 * @return string
	 */
	public function getEventsInfo($sUserUUID, $mCalendarId, $dStart = null, $dEnd = null, $bGetData = false)
	{
		$aResult = array();
		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart  - 86400) : null;
			$dEnd = ($dEnd != null) ? date('Ymd\T235959\Z', $dEnd) : null;
			$mCalendarId = !is_array($mCalendarId) ? array($mCalendarId) : $mCalendarId;

			foreach ($mCalendarId as $sCalendarId)
			{
				$aEvents = $this->oStorage->getEventsInfo($sUserUUID, $sCalendarId, $dStart, $dEnd, $bGetData);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return array|bool
	 */
	public function getEvent($sUserUUID, $sCalendarId, $sEventId)
	{
		$mResult = null;
		try
		{
			$mResult = array();
			$aData = $this->oStorage->getEvent($sUserUUID, $sCalendarId, $sEventId);
			if ($aData !== false) {
				if (isset($aData['vcal'])) {
					$oVCal = $aData['vcal'];
					$oCalendar = $this->oStorage->getCalendar($sUserUUID, $sCalendarId);
					$mResult = \CalendarParser::parseEvent($sUserUUID, $oCalendar, $oVCal);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return array|bool
	 */
	public function getBaseEvent($sUserUUID, $sCalendarId, $sEventId)
	{
		$mResult = null;
		try
		{
			$mResult = array();
			$aData = $this->oStorage->getEvent($sUserUUID, $sCalendarId, $sEventId);
			if ($aData !== false) {
				if (isset($aData['vcal'])) {
					$oVCal = $aData['vcal'];
					$oVCalOriginal = clone $oVCal;
					$oCalendar = $this->oStorage->getCalendar($sUserUUID, $sCalendarId);
					$oVEvent = $oVCal->getBaseComponents('VEVENT');
					if (isset($oVEvent[0])) {
						unset($oVCal->VEVENT);
						$oVCal->VEVENT = $oVEvent[0];
					}
					$oEvent = \CalendarParser::parseEvent($sUserUUID, $oCalendar, $oVCal, $oVCalOriginal);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 * @param string $dStart Date range start
	 * @param string $dEnd Date range end
	 *
	 * @return array|bool
	 */
	public function getExpandedEvent($sUserUUID, $sCalendarId, $sEventId, $dStart = null, $dEnd = null)
	{
		$mResult = null;

		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart/*  + 86400*/) : null;
			$dEnd = ($dEnd != null) ? date('Ymd\T235959\Z', $dEnd) : null;
			$mResult = $this->oStorage->getExpandedEvent($sUserUUID, $sCalendarId, $sEventId, $dStart, $dEnd);
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $sData
	 *
	 * @return mixed
	 */
	public function createEventFromRaw($sUserUUID, $sCalendarId, $sEventId, $sData)
	{
		$oResult = null;
		$aEvents = array();
		try
		{
			$oVCal = \Sabre\VObject\Reader::read($sData);
			if ($oVCal && $oVCal->VEVENT) {
				if (!empty($sEventId)) {
					$oResult = $this->oStorage->createEvent($sUserUUID, $sCalendarId, $sEventId, $oVCal);
				} else {
					foreach ($oVCal->VEVENT as $oVEvent) {
						$sUid = (string)$oVEvent->UID;
						if (!isset($aEvents[$sUid])) {
							$aEvents[$sUid] = new \Sabre\VObject\Component\VCalendar();
						}
						$aEvents[$sUid]->add($oVEvent);
					}

					foreach ($aEvents as $sUid => $oVCalNew) {
						$this->oStorage->createEvent($sUserUUID, $sCalendarId, $sUid, $oVCalNew);
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
	 * @param string $sUserUUID
	 * @param CEvent $oEvent Event object
	 *
	 * @return mixed
	 */
	public function createEvent($sUserUUID, $oEvent)
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

			\CCalendarHelper::populateVCalendar($sUserUUID, $oEvent, $oVCal->VEVENT);

			$oResult = $this->oStorage->createEvent($sUserUUID, $oEvent->IdCalendar, $oEvent->Id, $oVCal);

			if ($oResult) {
				$this->updateEventGroups($sUserUUID, $oEvent);
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
	 * @param string $sUserUUID
	 * @param CEvent $oEvent
	 */
	public function updateEventGroups($sUserUUID, $oEvent)
	{
		$aGroups = \CCalendarHelper::findGroupsHashTagsFromString($oEvent->Name);
		$aGroupsDescription = \CCalendarHelper::findGroupsHashTagsFromString($oEvent->Description);
		$aGroups = array_merge($aGroups, $aGroupsDescription);
		$aGroupsLocation = \CCalendarHelper::findGroupsHashTagsFromString($oEvent->Location);
		$aGroups = array_merge($aGroups, $aGroupsLocation);


		$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
		if ($oContactsModule) {
			foreach ($aGroups as $sGroup) {
				$sGroupName = ltrim($sGroup, '#');
				$oGroup = $oContactsModule->CallMethod('getGroupByName', array($sUserUUID->IdUser, $sGroupName));
				if (!$oGroup) {
					$oGroup = new \CGroup();
					$oGroup->IdUser = $sUserUUID->IdUser;
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
	 * @param string $sUserUUID
	 * @param CEvent $oEvent Event object
	 *
	 * @return bool
	 */
	public function updateEvent($sUserUUID, $oEvent)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($sUserUUID, $oEvent->IdCalendar, $oEvent->Id);
			if ($aData !== false) {
				$oVCal = $aData['vcal'];

				if ($oVCal) {
					$iIndex = \CCalendarHelper::getBaseVEventIndex($oVCal->VEVENT);
					if ($iIndex !== false) {
						\CCalendarHelper::populateVCalendar($sUserUUID, $oEvent, $oVCal->VEVENT[$iIndex]);
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

					$oResult = $this->oStorage->updateEvent($sUserUUID, $oEvent->IdCalendar, $oEvent->Id, $oVCalCopy);
					if ($oResult) {
						$this->updateEventGroups($sUserUUID, $oEvent);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventUrl
	 * @param string $sData
	 * 
	 * @return bool
	 */
	public function updateEventRaw($sUserUUID, $sCalendarId, $sEventUrl, $sData)
	{
		return $this->oStorage->updateEventRaw($sUserUUID, $sCalendarId, $sEventUrl, $sData);
	}
	
	/**
	 * Moves event to a different calendar.
	 *
	 * @param string $sUserUUID
	 * @param string $sCalendarId Current calendar ID
	 * @param string $sCalendarIdNew New calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return bool
	 */
	public function moveEvent($sUserUUID, $sCalendarId, $sCalendarIdNew, $sEventId)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($sUserUUID, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) {
				$oResult = $this->oStorage->moveEvent($sUserUUID, $sCalendarId, $sCalendarIdNew, $sEventId, $aData['vcal']->serialize());
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
	 * @param string $sUserUUID
	 * @param CEvent $oEvent Event object
	 * @param string $sRecurrenceId Recurrence ID
	 * @param bool $bDelete If **true**, exclusion is deleted
	 *
	 * @return bool
	 */
	public function updateExclusion($sUserUUID, $oEvent, $sRecurrenceId, $bDelete = false)
	{
		$oResult = null;
		try
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$aData = $this->oStorage->getEvent($sUserUUID, $oEvent->IdCalendar, $oEvent->Id);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) {
				$oVCal = $aData['vcal'];
				$iIndex = \CCalendarHelper::getBaseVEventIndex($oVCal->VEVENT);
				if ($iIndex !== false) {
					$oVCal->VEVENT[$iIndex]->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));

					$oDTExdate = \CCalendarHelper::prepareDateTime($sRecurrenceId, $oUser->DefaultTimeZone);
					$oDTStart = $oVCal->VEVENT[$iIndex]->DTSTART->getDatetime();

					$mIndex = \CCalendarHelper::isRecurrenceExists($oVCal->VEVENT, $sRecurrenceId);
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
									$iRecurrenceId = \CCalendarHelper::getStrDate($oVEvent->{'RECURRENCE-ID'}, $oUser->DefaultTimeZone, 'Ymd');
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
							\CCalendarHelper::populateVCalendar($sUserUUID, $oEvent, $oVEventRecur);
						}
					}

					return $this->oStorage->updateEvent($sUserUUID, $oEvent->IdCalendar, $oEvent->Id, $oVCal);

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
	 * @param string $sUserUUID Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 * @param string $iRecurrenceId Recurrence ID
	 *
	 * @return bool
	 */
	public function deleteExclusion($sUserUUID, $sCalendarId, $sEventId, $iRecurrenceId)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($sUserUUID, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) {
				$oVCal = $aData['vcal'];

				$aVEvents = $oVCal->VEVENT;
				unset($oVCal->VEVENT);

				foreach($aVEvents as $oVEvent) {
					if (isset($oVEvent->{'RECURRENCE-ID'})) {
						$iServerRecurrenceId = \CCalendarHelper::getStrDate($oVEvent->{'RECURRENCE-ID'}, $oUser->DefaultTimeZone, 'Ymd');
						if ($iRecurrenceId == $iServerRecurrenceId) {
							continue;
						}
					}
					$oVCal->add($oVEvent);
				}
				return $this->oStorage->updateEvent($sUserUUID, $sCalendarId, $sEventId, $oVCal);
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
	 * @param string $sUserUUID
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
	public function updateAppointment($sUserUUID, $sCalendarId, $sEventId, $sAttendee, $sAction)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($sUserUUID, $sCalendarId, $sEventId);
			if ($aData !== false) {
				$oVCal = $aData['vcal'];
				$oVCal->METHOD = 'REQUEST';
				return $this->appointmentAction($sUserUUID, $sAttendee, $sAction, $sCalendarId, $oVCal->serialize());
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
	 * @param int|string $sUserUUID Account object
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
	public function appointmentAction($sUserUUID, $sAttendee, $sAction, $sCalendarId, $sData, $bExternal = false)
	{
		$oDefaultAccount = null;
		$oAttendeeAccount = null;
		$bDefaultAccountAsEmail = false;
		$bIsDefaultAccount = false;
				
		if (isset($sUserUUID) && is_int($sUserUUID)) {
			$bDefaultAccountAsEmail = false;
			/* @var $oDefaultAccount CAccount */
			$oDefaultAccount = $this->ApiUsersManager->getDefaultAccount($sUserUUID);
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
									$oAttendeeAccount = $this->getAccountFromAccountList($sUserUUID, $sAttendee);
								}
								if (!($oAttendeeAccount instanceof \CAccount)) {
									$oAttendeeAccount = $oDefaultAccount;
								}
								$bResult = \CCalendarHelper::sendAppointmentMessage($oAttendeeAccount, $sTo, $sSubject, $sBody, $sMethod);
							}
						}
					} else {
						$bResult = true;
					}
				}
			}

			if (!$bResult) {
				\Aurora\System\Api::Log('Ics Appointment Action FALSE result!', \Aurora\System\Enums\LogLevel::Error);
				if ($sUserUUID) {
					\Aurora\System\Api::Log('Email: '.$sUserUUID->Email.', Action: '. $sAction.', Data:', \Aurora\System\Enums\LogLevel::Error);
				}
				\Aurora\System\Api::Log($sData, \Aurora\System\Enums\LogLevel::Error);
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
	 * @param string $sUserUUID
	 *
	 * @return CCalendar|false $oCalendar
	 */
	public function getDefaultCalendar($sUserUUID)
	{
		$aCalendars = $this->getCalendars($sUserUUID);
		return (is_array($aCalendars) && isset($aCalendars[0])) ? $aCalendars[0] : false;
	}

	/**
	 * @param string $sUserUUID
	 *
	 * @return bool
	 */
	public function getCalendars($sUserUUID)
	{
		$oResult = array();
		try
		{
			$oCalendars = array();
			$oCalendarsOwn = $this->oStorage->getCalendars($sUserUUID);

			if ($this->isCalendarSharingSupported($sUserUUID)) {
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
				if (!$bDefault && $oCalendar->Access !== \ECalendarPermission::Read) {
					$oCalendar->IsDefault = $bDefault = true;
				}
				$oCalendar = $this->populateCalendarShares($sUserUUID, $oCalendar);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return bool
	 */
	public function deleteEvent($sUserUUID, $sCalendarId, $sEventId)
	{
		$oResult = false;
		try
		{
			$aData = $this->oStorage->getEvent($sUserUUID, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && 
					$aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar)
			{
				$oVCal = $aData['vcal'];

				$iIndex = \CCalendarHelper::getBaseVEventIndex($oVCal->VEVENT);
				if ($iIndex !== false)
				{
					$oVEvent = $oVCal->VEVENT[$iIndex];

					$sOrganizer = (isset($oVEvent->ORGANIZER)) ?
							str_replace('mailto:', '', strtolower((string)$oVEvent->ORGANIZER)) : null;

					if (isset($sOrganizer))
					{
						if ($sOrganizer === $sUserUUID->Email)
						{
							$oDateTimeNow = new DateTime("now");
							$oDateTimeEvent = $oVEvent->DTSTART->getDateTime();
							$oDateTimeRepeat = \CCalendarHelper::getNextRepeat($oDateTimeNow, $oVEvent);
							$bRrule = isset($oVEvent->RRULE);
							$bEventFore = $oDateTimeEvent ? $oDateTimeEvent > $oDateTimeNow : false;
							$bNextRepeatFore = $oDateTimeRepeat ? $oDateTimeRepeat > $oDateTimeNow : false;

							if (isset($oVEvent->ATTENDEE) && ($bRrule ? $bNextRepeatFore : $bEventFore))
							{
								foreach($oVEvent->ATTENDEE as $oAttendee)
								{
									$sEmail = str_replace('mailto:', '', strtolower((string)$oAttendee));

									$oVCal->METHOD = 'CANCEL';
									$sSubject = (string)$oVEvent->SUMMARY . ': Canceled';

									\CCalendarHelper::sendAppointmentMessage($sUserUUID, $sEmail, $sSubject, $oVCal->serialize(), 'REQUEST');
									unset($oVCal->METHOD);
								}
							}
						}
					}
				}
				$oResult = $this->oStorage->deleteEvent($sUserUUID, $sCalendarId, $sEventId);
				if ($oResult)
				{
					// TODO realise 'removeEventFromAllGroups' method in 'Contacts' module
					//$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
					//$oContactsModule->CallMethod('removeEventFromAllGroups', array($sCalendarId, $sEventId));
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventUrl Event URL
	 *
	 * @return bool
	 */
	public function deleteEventByUrl($sUserUUID, $sCalendarId, $sEventUrl)
	{
		return $this->oStorage->deleteEventByUrl($sUserUUID, $sCalendarId, $sEventUrl);
	}


	/**
	 * @param string $sUserUUID
	 * @param string $sData
	 * @param string $mFromEmail
	 * @param bool $bUpdateAttendeeStatus
	 *
	 * @return array|bool
	 */
	public function processICS($sUserUUID, $sData, $mFromEmail, $bUpdateAttendeeStatus = false)
	{
		$mResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$aAccountEmails = array($oUser->PublicId);
		//TODO get list user emails

//		$aAccountEmails = array();
//		$aUserAccounts = $this->ApiUsersManager->getUserAccounts($sUserUUID->IdUser);
//		foreach ($aUserAccounts as $aUserAccount) {
//			if (isset($aUserAccount) && isset($aUserAccount[1])) {
//				$aAccountEmails[] = $aUserAccount[1];
//			}
//		}
//
//		$aFetchers = \Aurora\System\Api::ExecuteMethod('Mail::GetFetchers', array('Account' => $oDefaultAccount));
//		if (is_array($aFetchers) && 0 < count($aFetchers)) {
//			foreach ($aFetchers as /* @var $oFetcher \CFetcher */ $oFetcher) {
//				if ($oFetcher) {
//					$aAccountEmails[] = !empty($oFetcher->Email) ? $oFetcher->Email : $oFetcher->IncomingLogin;
//				}
//			}
//		}
//
//		$aIdentities = $this->ApiUsersManager->getUserIdentities($sUserUUID->IdUser);
//		if (is_array($aIdentities) && 0 < count($aIdentities)) {
//			foreach ($aIdentities as /* @var $oIdentity \CIdentity */ $oIdentity) {
//				if ($oIdentity) {
//					$aAccountEmails[] = $oIdentity->Email;
//				}
//			}
//		}

		try
		{
			$oVCal = \Sabre\VObject\Reader::read($sData);
			if ($oVCal)
			{
				$oVCalResult = $oVCal;

				$oMethod = isset($oVCal->METHOD) ? $oVCal->METHOD : null;
				$sMethod = isset($oMethod) ? (string) $oMethod : 'SAVE';

				if (!in_array($sMethod, array('REQUEST', 'REPLY', 'CANCEL', 'PUBLISH', 'SAVE')))
				{
					return false;
				}

				$aVEvents = $oVCal->getBaseComponents('VEVENT');
				$oVEvent = (isset($aVEvents) && count($aVEvents) > 0) ? $aVEvents[0] : null;

				if (isset($oVEvent))
				{
					$sCalendarId = '';
					$oVEventResult = $oVEvent;

					$sEventId = (string)$oVEventResult->UID;

					$aCalendars = $this->oStorage->GetCalendarNames($sUserUUID);
					$aCalendarIds = $this->oStorage->findEventInCalendars($sUserUUID, $sEventId, $aCalendars);

					if (is_array($aCalendarIds) && isset($aCalendarIds[0]))
					{
						$sCalendarId = $aCalendarIds[0];
						$aDataServer = $this->oStorage->getEvent($sUserUUID, $sCalendarId, $sEventId);
						if ($aDataServer !== false)
						{
							$oVCalServer = $aDataServer['vcal'];
							if (isset($oMethod))
							{
								$oVCalServer->METHOD = $oMethod;
							}
							$aVEventsServer = $oVCalServer->getBaseComponents('VEVENT');
							if (count($aVEventsServer) > 0)
							{
								$oVEventServer = $aVEventsServer[0];

								if (isset($oVEvent->{'LAST-MODIFIED'}) && 
									isset($oVEventServer->{'LAST-MODIFIED'}))
								{
									$lastModified = $oVEvent->{'LAST-MODIFIED'}->getDateTime();
									$lastModifiedServer = $oVEventServer->{'LAST-MODIFIED'}->getDateTime();

									$sequence = isset($oVEvent->{'SEQUENCE'}) && $oVEvent->{'SEQUENCE'}->getValue() ? $oVEvent->{'SEQUENCE'}->getValue() : 0 ; // current sequence value
									$sequenceServer = isset($oVEventServer->{'SEQUENCE'}) && $oVEventServer->{'SEQUENCE'}->getValue() ? $oVEventServer->{'SEQUENCE'}->getValue() : 0; // accepted sequence value

									if ($sequenceServer >= $sequence)
									{
										$oVCalResult = $oVCalServer;
										$oVEventResult = $oVEventServer;
									}
									if (isset($sMethod) && !($lastModifiedServer >= $lastModified))
									{
										if ($sMethod === 'REPLY')
										{
											$oVCalResult = $oVCalServer;
											$oVEventResult = $oVEventServer;

											if (isset($oVEvent->ATTENDEE) && $sequenceServer >= $sequence)
											{
												$oAttendee = $oVEvent->ATTENDEE[0];
												$sAttendee = str_replace('mailto:', '', strtolower((string)$oAttendee));
												if (isset($oVEventResult->ATTENDEE))
												{
													foreach ($oVEventResult->ATTENDEE as $oAttendeeResult)
													{
														$sEmailResult = str_replace('mailto:', '', strtolower((string)$oAttendeeResult));
														if ($sEmailResult === $sAttendee)
														{
															if (isset($oAttendee['PARTSTAT']))
															{
																$oAttendeeResult['PARTSTAT'] = $oAttendee['PARTSTAT']->getValue();
															}
															break;
														}
													}
												}
											}
											if ($bUpdateAttendeeStatus)
											{
												unset($oVCalResult->METHOD);
												$oVEventResult->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
												$mResult = $this->oStorage->updateEventRaw($sUserUUID, $sCalendarId, $sEventId, $oVCalResult->serialize());
												$oVCalResult->METHOD = $sMethod;
											}
										}
									}
								}
							}
						}

						if ($sMethod === 'CANCEL' && $bUpdateAttendeeStatus)
						{
							if ($this->deleteEvent($sUserUUID, $sCalendarId, $sEventId))
							{
								$mResult = true;
							}
						}

					}

					if (!$bUpdateAttendeeStatus)
					{
						$sTimeFormat = (isset($oVEventResult->DTSTART) && !$oVEventResult->DTSTART->hasTime()) ? 'D, M d' : 'D, M d, Y, H:i';
						$mResult = array(
							'Calendars' => $aCalendars,
							'CalendarId' => $sCalendarId,
							'UID' => $sEventId,
							'Body' => $oVCalResult->serialize(),
							'Action' => $sMethod,
							'Location' => isset($oVEventResult->LOCATION) ? (string)$oVEventResult->LOCATION : '',
							'Description' => isset($oVEventResult->DESCRIPTION) ? (string)$oVEventResult->DESCRIPTION : '',
							'When' => \CCalendarHelper::getStrDate($oVEventResult->DTSTART, $oUser->DefaultTimeZone, $sTimeFormat),
							'Sequence' => isset($sequence) ? $sequence : 1
						);

						$aAccountEmails = ($sMethod === 'REPLY') ? array($mFromEmail) : $aAccountEmails;
						if (isset($oVEventResult->ATTENDEE) && isset($sequenceServer) && isset($sequence) && $sequenceServer >= $sequence)
						{
							foreach($oVEventResult->ATTENDEE as $oAttendee)
							{
								$sAttendee = str_replace('mailto:', '', strtolower((string)$oAttendee));
								if (in_array($sAttendee, $aAccountEmails) && isset($oAttendee['PARTSTAT']))
								{
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
	 * @param string $sUserUUID
	 *
	 * @return bool
	 */
	public function clearAllCalendars($sUserUUID)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->clearAllCalendars($sUserUUID);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}
}
