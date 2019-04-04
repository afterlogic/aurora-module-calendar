<?php
/**
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar;

/**
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Calendar
 * @subpackage Managers
 */
class Manager extends \Aurora\System\Managers\AbstractManagerWithStorage
{
	/**
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule, new Storages\Sabredav($this));
	}
	
	protected function isCalendarSharingSupported($sUserUUID)
	{
		return true; // TODO
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
		$oUser = \Aurora\System\Api::getUserById($sUserUUID);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$oResult = $this->oStorage->getCalendarAccess($oUser->PublicId, $sCalendarId);
		}
		return $oResult;
	}

	/**
	 *
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 *
	 * @return \Aurora\Modules\StandardAuth\Classes\Account|false $oAccount
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
	 * @return \Aurora\Modules\Calendar\Classes\Calendar|false $oCalendar
	 */
	public function getPublicCalendarByHash($sHash)
	{
		return $this->getPublicCalendar($sHash);
	}

	/**
	 * @param string $sCalendarId
	 *
	 * @return \Aurora\Modules\Calendar\Classes\Calendar|false $oCalendar
	 */
	public function getPublicCalendar($sCalendarId)
	{
		$oCalendar = false;
		try
		{
			$oCalDAVCalendar = $this->oStorage->getPublicCalendar($sCalendarId);
			if ($oCalDAVCalendar)
			{
				$oCalendar = $this->oStorage->parseCalendar($oCalDAVCalendar);
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
	 * Loads calendar.
	 *
	 * @param int $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return \Aurora\Modules\Calendar\Classes\Calendar|false $oCalendar
	 */
	public function getCalendar($sUserPublicId, $sCalendarId)
	{
		$oCalendar = false;
		try
		{
			$oCalendar = $this->oStorage->getCalendar($sUserPublicId, $sCalendarId);
		}
		catch (Exception $oException)
		{
			$oCalendar = false;
			$this->setLastException($oException);
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
	 * @param string $sUserPublicId
	 * @param \Aurora\Modules\Calendar\Classes\Calendar $oCalendar Calendar object
	 *
	 * @return array|bool
	 */
	public function getCalendarUsers($sUserPublicId, $oCalendar)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->getCalendarUsers($sUserPublicId, $oCalendar);
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
	 * @return string|bool
	 */
	public function getTenantUser($oUser = null)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->getTenantUser($oUser);
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
	 * @return \Aurora\Modules\StandardAuth\Classes\Account|false $oAccount
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
	 * @param string $sUserPublicId
	 *
	 * @return array
	 */
	public function getUserCalendars($sUserPublicId)
	{
		return $this->oStorage->getCalendars($sUserPublicId);
	}

	/**
	 * Creates new calendar.
	 *
	 * @param string $sUserPublicId
	 * @param string $sName Name of the calendar
	 * @param string $sDescription Description of the calendar
	 * @param int $iOrder Ordinal number of the calendar in calendars list
	 * @param string $sColor Color code
	 *
	 * @return \Aurora\Modules\Calendar\Classes\Calendar|false
	 */
	public function createCalendar($sUserPublicId, $sName, $sDescription, $iOrder, $sColor, $sUUID = null)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->createCalendar($sUserPublicId, $sName, $sDescription, $iOrder, $sColor, $sUUID);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sName Name of the calendar
	 * @param string $sDescription Description of the calendar
	 * @param int $iOrder Ordinal number of the calendar in calendars list
	 * @param string $sColor Color code
	 *
	 * @return \Aurora\Modules\Calendar\Classes\Calendar|false
	 */
	public function updateCalendar($sUserPublicId, $sCalendarId, $sName, $sDescription, $iOrder, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->updateCalendar($sUserPublicId, $sCalendarId, $sName, $sDescription, $iOrder, $sColor);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sColor New color code
	 *
	 * @return bool
	 */
	public function updateCalendarColor($sUserPublicId, $sCalendarId, $sColor)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->updateCalendarColor($sUserPublicId, $sCalendarId, $sColor);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
		}
		return $oResult;
	}

	public function deletePrincipalCalendars($sUserPublicId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->deletePrincipalCalendars($sUserPublicId);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return bool
	 */
	public function deleteCalendar($sUserPublicId, $sCalendarId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->deleteCalendar($sUserPublicId, $sCalendarId);
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
	 * @param string $sUserPublicId Account object
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return bool
	 */
	public function unsubscribeCalendar($sUserPublicId, $sCalendarId)
	{
		$oResult = null;
		if ($this->isCalendarSharingSupported($sUserPublicId))
		{
			try
			{
				$oResult = $this->oStorage->unsubscribeCalendar($sUserPublicId, $sCalendarId);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param bool $bShareToAll If set to **true**, add sharing; if **false**, sharing is removed
	 * @param int $iPermission Permissions set for the account. Accepted values:
	 *		- **ECalendarPermission::Read** (read-only access);
	 *		- **ECalendarPermission::Write** (read/write access);
	 *		- **ECalendarPermission::RemovePermission** (effectively removes sharing with the account).
	 *
	 * @return bool
	 */
	public function updateCalendarShareToAll($sUserPublicId, $sCalendarId, $bShareToAll, $iPermission)
	{
		$sUserId = $this->getTenantUser($sUserPublicId);
		$aShares[] = array(
			'name' => $sUserId,
			'email' => $sUserId,
			'access' => $bShareToAll ? $iPermission : \Aurora\Modules\Calendar\Enums\Permission::RemovePermission
		);

		return $this->updateCalendarShares($sUserPublicId, $sCalendarId, $aShares);
	}

	/**
	 * (Aurora only) Share or remove sharing calendar with the listed users.
	 *
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param array $aShares Array defining list of users and permissions. Each array item needs to have the following keys:
	 *		["email"] - email which denotes user the calendar is shared to;
	 *		["access"] - permission settings equivalent to those used in updateCalendarShare method.
	 *
	 * @return bool
	 */
	public function updateCalendarShares($sUserPublicId, $sCalendarId, $aShares)
	{
		$oResult = false;
		if ($this->isCalendarSharingSupported($sUserPublicId))
		{
			$aProcessedEmails = [];
			$oUser = \Aurora\System\Api::GetModule('Core')->getUsersManager()->getUserByPublicId($sUserPublicId);
			foreach ($aShares as $aShare)
			{
				if (in_array($aShare['email'], $aProcessedEmails))
				{//duplicated shares
					return $oResult;
				}
				$aProcessedEmails[] = $aShare['email'];
				if ($aShare['email'] !== $this->getTenantUser($oUser) &&
					$aShare['email'] !== $this->getPublicUser())
				{
					$oSharedUser = \Aurora\System\Api::GetModule('Core')->getUsersManager()->getUserByPublicId($aShare['email']);
					if (!$oSharedUser instanceof \Aurora\Modules\Core\Classes\User)
					{
						return $oResult;
					}
					unset($oSharedUser);
				}
			}
			$oResult = $this->oStorage->updateCalendarShares($sUserPublicId, $sCalendarId, $aShares);
		}
		return $oResult;
	}

	/**
	 * Set/unset calendar as public.
	 *
	 * @param string $sUserPublicId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param bool $bIsPublic If set to **true**, calendar is made public; if **false**, setting as public gets cancelled
	 *
	 * @return bool
	 */
	public function publicCalendar($sUserPublicId, $sCalendarId, $bIsPublic = false, $oUser = null)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->publicCalendar($sUserPublicId, $sCalendarId, $bIsPublic, $oUser);
		}
		catch (Exception $oException)
		{
			$oResult = false;
			$this->setLastException($oException);
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
	public function getPublishStatus($sCalendarId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->oStorage->getPublishStatus($sCalendarId);
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
	 * @param string $sUserPublicId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sUserId User ID
	 *
	 * @return bool
	 */
	public function deleteCalendarShare($sUserPublicId, $sCalendarId, $sUserId)
	{
		$oResult = null;
		try
		{
			$oResult = $this->updateCalendarShare($sUserPublicId, $sCalendarId, $sUserId);
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
	 * @param string $sUserPublicId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sUserId User Id
	 * @param int $iPermission Permissions set for the account. Accepted values:
	 *		- **ECalendarPermission::Read** (read-only access);
	 *		- **ECalendarPermission::Write** (read/write access);
	 *		- **ECalendarPermission::RemovePermission** (effectively removes sharing with the account).
	 *
	 * @return bool
	 */
	public function updateCalendarShare($sUserPublicId, $sCalendarId, $sUserId, $iPermission)
	{
		$oResult = null;
		if ($this->isCalendarSharingSupported($sUserPublicId))
		{
			try
			{
				$oResult = $this->oStorage->updateCalendarShare($sUserPublicId, $sCalendarId, $sUserId, $iPermission);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 *
	 * @return string|bool
	 */
	public function exportCalendarToIcs($sUserPublicId, $sCalendarId)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->exportCalendarToIcs($sUserPublicId, $sCalendarId);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sTempFileName .ICS file name data are imported from
	 *
	 * @return int|bool integer (number of events added)
	 */
	public function importToCalendarFromIcs($sUserPublicId, $sCalendarId, $sTempFileName)
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->importToCalendarFromIcs($sUserPublicId, $sCalendarId, $sTempFileName);
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
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

			foreach ($mCalendarId as $sCalendarId) 
			{
				$aEvents = $this->oStorage->getEvents($sUserUUID, $sCalendarId, $dStart, $dFinish);
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
	 * @param array | string $mCalendarId Calendar ID
	 * @param string $dStart Date range start
	 * @param string $dFinish Date range end
	 *
	 * @return array|bool
	 */
	public function getPublicEvents($mCalendarId, $dStart = null, $dFinish = null, $bExpand = true, $sDefaultTimeZone = null)
	{
		$aResult = array();

		$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart  - 86400) : null;
		$dFinish = ($dFinish != null) ? date('Ymd\T235959\Z', $dFinish) : null;
		$mCalendarId = !is_array($mCalendarId) ? array($mCalendarId) : $mCalendarId;

		foreach ($mCalendarId as $sCalendarId)
		{
			$aEvents = $this->oStorage->getPublicEvents($sCalendarId, $dStart, $dFinish, $bExpand, $sDefaultTimeZone);
			if ($aEvents && is_array($aEvents))
			{
				$aResult = array_merge($aResult, $aEvents);
			}
		}

		return $aResult;
	}	
	
	/**
	 * Account object
	 *
	 * @param string $sUserPublicId
	 * @param array | string $mCalendarId Calendar ID
	 * @param string $dStart Date range start
	 * @param string $dFinish Date range end
	 *
	 * @return array|bool
	 */
	public function getTasks($sUserPublicId, $mCalendarId, $bCompeted = true, $sSearch = '', $dStart = null, $dFinish = null, $bExpand = true)
	{
		$aResult = array();
		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart  - 86400) : null;
			$dFinish = ($dFinish != null) ? date('Ymd\T235959\Z', $dFinish) : null;
			$mCalendarId = !is_array($mCalendarId) ? array($mCalendarId) : $mCalendarId;

			foreach ($mCalendarId as $sCalendarId) 
			{
				$aTasks = $this->oStorage->getTasks($sUserPublicId, $sCalendarId, $bCompeted, $sSearch, $dStart, $dFinish, $bExpand);

				if ($aTasks && is_array($aTasks)) 
				{
					$aResult = array_merge($aResult, $aTasks);
				}
			}

			// usort($aResult, function ($left, $right) {
			// 	if (isset($left['startTS'], $right['startTS'])) 
			// 	{
			// 		if ($left['startTS'] == $right['startTS'] ) 
			// 		{
			// 			return 0;
			// 		}
			// 		return ($left['startTS'] > $right['startTS']) ? 1 : -1;
			// 	}
			// 	else
			// 	{
			// 		if (!isset($left['startTS']) && !isset($right['startTS']))
			// 		{
			// 			return 0;
			// 		}
			// 		else
			// 		{
			// 			if (!isset($left['startTS']))
			// 			{
			// 				return 1;
			// 			}
			// 			if (!isset($right['startTS']))
			// 			{
			// 				return -1;
			// 			}
			// 		}
			// 	}
			// });
		}
		catch (Exception $oException)
		{
			$aResult = false;
			$this->setLastException($oException);
		}
		return $aResult;
	}	

	/**
	 * @param string $sUserPublicId Account object
	 * @param mixed $mCalendarId
	 * @param object $dStart
	 * @param object $dEnd
	 * @param bool $bGetData
	 *
	 * @return string
	 */
	public function getEventsInfo($sUserPublicId, $mCalendarId, $dStart = null, $dEnd = null, $bGetData = false)
	{
		$aResult = array();
		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart  - 86400) : null;
			$dEnd = ($dEnd != null) ? date('Ymd\T235959\Z', $dEnd) : null;
			$mCalendarId = !is_array($mCalendarId) ? array($mCalendarId) : $mCalendarId;

			foreach ($mCalendarId as $sCalendarId)
			{
				$aEvents = $this->oStorage->getEventsInfo($sUserPublicId, $sCalendarId, $dStart, $dEnd, $bGetData);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return array|bool
	 */
	public function getEvent($sUserPublicId, $sCalendarId, $sEventId)
	{
		$mResult = null;
		try
		{
			$mResult = array();
			$aData = $this->oStorage->getEvent($sUserPublicId, $sCalendarId, $sEventId);
			if ($aData !== false) 
			{
				if (isset($aData['vcal'])) 
				{
					$oVCal = $aData['vcal'];
					$oCalendar = $this->oStorage->getCalendar($sUserPublicId, $sCalendarId);
					$mResult = \Aurora\Modules\Calendar\Classes\Parser::parseEvent($sUserPublicId, $oCalendar, $oVCal, $oVCal);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return array|bool
	 */
	public function getBaseEvent($sUserPublicId, $sCalendarId, $sEventId)
	{
		$mResult = null;
		try
		{
			$mResult = array();
			$aData = $this->oStorage->getEvent($sUserPublicId, $sCalendarId, $sEventId);
			if ($aData !== false) 
			{
				if (isset($aData['vcal'])) 
				{
					$oVCal = $aData['vcal'];
					$oVCalOriginal = clone $oVCal;
					$oCalendar = $this->oStorage->getCalendar($sUserPublicId, $sCalendarId);
					$oVEvent = $oVCal->getBaseComponents('VEVENT');
					if (isset($oVEvent[0])) 
					{
						unset($oVCal->VEVENT);
						$oVCal->VEVENT = $oVEvent[0];
					}
					$oEvent = \Aurora\Modules\Calendar\Classes\Parser::parseEvent($sUserPublicId, $oCalendar, $oVCal, $oVCalOriginal);
					if (isset($oEvent[0])) 
					{
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 * @param string $dStart Date range start
	 * @param string $dEnd Date range end
	 *
	 * @return array|bool
	 */
	public function getExpandedEvent($sUserPublicId, $sCalendarId, $sEventId, $dStart = null, $dEnd = null)
	{
		$mResult = null;

		try
		{
			$dStart = ($dStart != null) ? date('Ymd\T000000\Z', $dStart/*  + 86400*/) : null;
			$dEnd = ($dEnd != null) ? date('Ymd\T235959\Z', $dEnd) : null;
			$mResult = $this->oStorage->getExpandedEvent($sUserPublicId, $sCalendarId, $sEventId, $dStart, $dEnd);
		}
		catch (Exception $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $sData
	 *
	 * @return mixed
	 */
	public function createEventFromRaw($sUserPublicId, $sCalendarId, $sEventId, $sData)
	{
		$oResult = null;
		$aEvents = array();
		try
		{
			$oVCal = \Sabre\VObject\Reader::read($sData);
			if ($oVCal && ($oVCal->VEVENT || $oVCal->VTODO)) 
			{
				if (!empty($sEventId)) 
				{
					$oResult = $this->oStorage->createEvent($sUserPublicId, $sCalendarId, $sEventId, $oVCal);
				} 
				else 
				{
					foreach ($oVCal->VEVENT as $oVEvent) 
					{
						$sUid = (string)$oVEvent->UID;
						if (!isset($aEvents[$sUid])) 
						{
							$aEvents[$sUid] = new \Sabre\VObject\Component\VCalendar();
						}
						$aEvents[$sUid]->add($oVEvent);
					}
					foreach ($oVCal->VTODO as $oVTodo) 
					{
						$sUid = (string)$oVTodo->UID;
						if (!isset($aEvents[$sUid])) 
						{
							$aEvents[$sUid] = new \Sabre\VObject\Component\VCalendar();
						}
						$aEvents[$sUid]->add($oVTodo);
					}

					foreach ($aEvents as $sUid => $oVCalNew) 
					{
						$this->oStorage->createEvent($sUserPublicId, $sCalendarId, $sUid, $oVCalNew);
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
	 * @param string $sUserPublicId
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent Event object
	 *
	 * @return mixed
	 */
	public function createEvent($sUserPublicId, $oEvent)
	{
		$oResult = null;
		try
		{
			$oEvent->Id = \Sabre\DAV\UUIDUtil::getUUID();

			$oVCal = new \Sabre\VObject\Component\VCalendar();
			$sComponentName = !empty($oEvent->Type) ? $oEvent->Type : 'VEVENT';
			$oComponent = new \Sabre\VObject\Component\VEvent(
				$oVCal,
				$sComponentName,
				[
					'SEQUENCE' => 0,
					'TRANSP' => 'OPAQUE',
					'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
				],
				true
			);
			$oVCal->add($oComponent);

			\Aurora\Modules\Calendar\Classes\Helper::populateVCalendar($sUserPublicId, $oEvent, $oVCal->$sComponentName);
			$aArgs = [
				'sUserPublicId' => $sUserPublicId,
				'oEvent' => $oEvent
			];
			$this->GetModule()->broadcastEvent(
				'populateVCalendar',
				$aArgs,
				$oVCal->$sComponentName
			);
			$oResult = $this->oStorage->createEvent($sUserPublicId, $oEvent->IdCalendar, $oEvent->Id, $oVCal);

			if ($oResult) 
			{
				$this->updateEventGroups($sUserPublicId, $oEvent);
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
	 * @param string $sUserPublicId
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent
	 */
	public function updateEventGroups($sUserPublicId, $oEvent)
	{
		$aGroups = \Aurora\Modules\Calendar\Classes\Helper::findGroupsHashTagsFromString($oEvent->Name);
		$aGroupsDescription = \Aurora\Modules\Calendar\Classes\Helper::findGroupsHashTagsFromString($oEvent->Description);
		$aGroups = array_merge($aGroups, $aGroupsDescription);
		$aGroupsLocation = \Aurora\Modules\Calendar\Classes\Helper::findGroupsHashTagsFromString($oEvent->Location);
		$aGroups = array_merge($aGroups, $aGroupsLocation);
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);

		$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
		if ($oContactsModule && $oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			foreach ($aGroups as $sGroup) 
			{
				$sGroupName = ltrim($sGroup, '#');
				$oGroup = $oContactsModule->CallMethod('getGroupByName', array($sGroupName, $oUser->IdUser));
				if (!$oGroup) 
				{
					$oGroup = new \Aurora\Modules\Contacts\Classes\Group();
					$oGroup->IdUser = $oUser->IdUser;
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
	 * @param string $sUserPublicId
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent Event object
	 *
	 * @return bool
	 */
	public function updateEvent($sUserPublicId, $oEvent)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($sUserPublicId, $oEvent->IdCalendar, $oEvent->Id);
			if ($aData !== false) 
			{
				$oVCal = $aData['vcal'];

				if ($oEvent->Type === 'VTODO' && isset($oVCal->VEVENT))
				{
					$sRawEventData = $oVCal->serialize();
					$sRawEventData = str_replace('VEVENT', 'VTODO', $sRawEventData);
					$oVCal = \Sabre\VObject\Reader::read($sRawEventData);
				}
				
				if ($oEvent->Type === 'VEVENT' && isset($oVCal->VTODO))
				{
					$sRawEventData = $oVCal->serialize();
					$sRawEventData = str_replace('VTODO', 'VEVENT', $sRawEventData);
					$oVCal = \Sabre\VObject\Reader::read($sRawEventData);
				}
				
				if ($oVCal) 
				{
					$sComponent = $oEvent->Type;
					
					$iIndex = \Aurora\Modules\Calendar\Classes\Helper::getBaseVComponentIndex($oVCal->{$sComponent});
					if ($iIndex !== false) 
					{
						\Aurora\Modules\Calendar\Classes\Helper::populateVCalendar($sUserPublicId, $oEvent, $oVCal->{$sComponent}[$iIndex]);
						$aArgs = [
							'sUserPublicId' => $sUserPublicId,
							'oEvent' => $oEvent
						];
						$this->GetModule()->broadcastEvent(
							'populateVCalendar',
							$aArgs,
							$oVCal->{$sComponent}[$iIndex]
						);
					}
					
					$oVCalResult = clone $oVCal;
					if (!isset($oEvent->RRule)) 
                    {
						unset($oVCalResult->{$sComponent});
						if (isset($oVCal->{$sComponent}))
						{
							foreach ($oVCal->{$sComponent} as $oVComponent) 
							{
								$oVComponent->SEQUENCE = (int) $oVComponent->SEQUENCE->getValue() + 1;
								if (!isset($oVComponent->{'RECURRENCE-ID'})) 
								{
									$oVCalResult->add($oVComponent);
								}
							}
						}
					}

					$oResult = $this->oStorage->updateEvent($sUserPublicId, $oEvent->IdCalendar, $aData['url'], $oVCalResult);
					if ($oResult) 
					{
						$this->updateEventGroups($sUserPublicId, $oEvent);
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
	 * @param string $sUserPublicId
	 * @param string $sCalendarId
	 * @param string $sEventUrl
	 * @param string $sData
	 * 
	 * @return bool
	 */
	public function updateEventRaw($sUserPublicId, $sCalendarId, $sEventUrl, $sData)
	{
		return $this->oStorage->updateEventRaw($sUserPublicId, $sCalendarId, $sEventUrl, $sData);
	}
	
	/**
	 * Moves event to a different calendar.
	 *
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Current calendar ID
	 * @param string $sCalendarIdNew New calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return bool
	 */
	public function moveEvent($sUserPublicId, $sCalendarId, $sCalendarIdNew, $sEventId)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($sUserPublicId, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && $aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) 
			{
				$oResult = $this->oStorage->moveEvent($sUserPublicId, $sCalendarId, $sCalendarIdNew, $sEventId, $aData['vcal']->serialize());
//				$this->updateEventGroupByMoving($sCalendarId, $sEventId, $sCalendarIdNew);
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
		if ($oContactsModule) 
		{
			$aEvents = $oContactsModule->CallMethod('getGroupEvent', array($sCalendarId, $sEventId));
			if (is_array($aEvents) && 0 < count($aEvents)) 
			{
				foreach ($aEvents as $aEvent) 
				{
					if (isset($aEvent['id_group'])) 
					{
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
	 * @param string $sUserPublicId
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent Event object
	 * @param string $sRecurrenceId Recurrence ID
	 * @param bool $bDelete If **true**, exclusion is deleted
	 *
	 * @return bool
	 */
	public function updateExclusion($sUserPublicId, $oEvent, $sRecurrenceId, $bDelete = false)
	{
		$oResult = null;
		try
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$aData = $this->oStorage->getEvent($sUserPublicId, $oEvent->IdCalendar, $oEvent->Id);
			if ($aData !== false && isset($aData['vcal']) && $aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) 
			{
				$oVCal = $aData['vcal'];
				$sComponent = isset($oVCal->VEVENT) ? 'VEVENT' : 'VTODO';
				
				$iIndex = \Aurora\Modules\Calendar\Classes\Helper::getBaseVComponentIndex($oVCal->{$sComponent});
				if ($iIndex !== false) 
				{
					$oVCal->{$sComponent}[$iIndex]->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));

					$oDTExdate = \Aurora\Modules\Calendar\Classes\Helper::prepareDateTime($sRecurrenceId, $oUser->DefaultTimeZone);
					$oDTStart = $oVCal->{$sComponent}[$iIndex]->DTSTART->getDatetime();

					$mIndex = \Aurora\Modules\Calendar\Classes\Helper::isRecurrenceExists($oVCal->{$sComponent}, $sRecurrenceId);
					if ($bDelete) 
					{
						// if exclude first event in occurrence
						if ($oDTExdate === $oDTStart) 
						{
							$it = new \Sabre\VObject\RecurrenceIterator($oVCal, (string) $oVCal->{$sComponent}[$iIndex]->UID);
							$it->fastForward($oDTStart);
							$it->next();

							if ($it->valid()) 
							{
								$oEventObj = $it->getEventObject();
							}

							$oVCal->{$sComponent}[$iIndex]->DTSTART = $oEventObj->DTSTART;
							$oVCal->{$sComponent}[$iIndex]->DTEND = $oEventObj->DTEND;
						}

						if (isset($oVCal->{$sComponent}[$iIndex]->EXDATE))
						{
							$oEXDATE = clone $oVCal->{$sComponent}[$iIndex]->EXDATE;
							unset($oVCal->{$sComponent}[$iIndex]->EXDATE);
							foreach ($oEXDATE as $oExDate)
							{
								if ($oExDate->getDateTime() !== $oDTExdate)
								{
									$oVCal->{$sComponent}[$iIndex]->EXDATE = $oExDate;
								}
							}
						}
						$oVCal->{$sComponent}[$iIndex]->add('EXDATE', $oDTExdate);

						if (false !== $mIndex) 
						{
							$aVEvents = $oVCal->{$sComponent};
							unset($oVCal->{$sComponent});

							foreach($aVEvents as $oVEvent) 
							{
								if ($oVEvent->{'RECURRENCE-ID'}) 
								{
									$iRecurrenceId = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVEvent->{'RECURRENCE-ID'}, $oUser->DefaultTimeZone);
									if ((int)$iRecurrenceId == (int) $sRecurrenceId) 
									{
										continue;
									}
								}
								$oVCal->add($oVEvent);
							}
						}
					} 
					else 
					{
						$oVEventRecur = null;
						if ($mIndex === false) 
						{
							$oVEventRecur = $oVCal->add($sComponent, array(
								'SEQUENCE' => 1,
								'TRANSP' => 'OPAQUE',
								'RECURRENCE-ID' => $oDTExdate
							));
						} 
						else if (isset($oVCal->{$sComponent}[$mIndex])) 
						{
							$oVEventRecur = $oVCal->{$sComponent}[$mIndex];
						}
						if ($oVEventRecur) 
						{
							$oEvent->RRule = null;
							\Aurora\Modules\Calendar\Classes\Helper::populateVCalendar($sUserPublicId, $oEvent, $oVEventRecur);
							$aArgs = [
								'sUserPublicId' => $sUserPublicId,
								'oEvent' => $oEvent
							];
							$this->GetModule()->broadcastEvent(
								'populateVCalendar',
								$aArgs,
								$oVEventRecur
							);
						}
					}
					$this->oStorage->updateEvent($sUserPublicId, $oEvent->IdCalendar, $oEvent->Id, $oVCal);
					return true;
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
	 * @param string $sUserPublicId Account object
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 * @param string $iRecurrenceId Recurrence ID
	 *
	 * @return bool
	 */
	public function deleteExclusion($sUserPublicId, $sCalendarId, $sEventId, $iRecurrenceId)
	{
		$oResult = null;
		try
		{
			$aData = $this->oStorage->getEvent($sUserPublicId, $sCalendarId, $sEventId);
			$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
			if ($oUser && $aData !== false && isset($aData['vcal']) && $aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar) 
			{
				
				$oVCal = $aData['vcal'];

				$sComponent = 'VEVENT';
				if ($oVCal->VTODO)
				{
					$sComponent = 'VTODO';
				}
				
				$aVComponents = $oVCal->{$sComponent};
				unset($oVCal->{$sComponent});

				foreach($aVComponents as $oVComponent) 
				{
					if (isset($oVComponent->{'RECURRENCE-ID'})) 
					{
						$iServerRecurrenceId = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oVComponent->{'RECURRENCE-ID'}, $oUser->DefaultTimeZone, 'Ymd');
						if ($iRecurrenceId == $iServerRecurrenceId) 
						{
							continue;
						}
					}
					$oVCal->add($oVComponent);
				}
				return $this->oStorage->updateEvent($sUserPublicId, $sCalendarId, $sEventId, $oVCal);
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
	 * Returns default calendar of the account.
	 *
	 * @param string $sUserPublicId
	 *
	 * @return \Aurora\Modules\Calendar\Classes\Calendar|false $oCalendar
	 */
	public function getDefaultCalendar($sUserPublicId)
	{
		$aCalendars = $this->getCalendars($sUserPublicId);
		return (is_array($aCalendars) && isset($aCalendars[\Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID])) ? $aCalendars[\Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID] : false;
	}

	/**
	 * @param string $sUserPublicId
	 *
	 * @return bool
	 */
	public function getCalendars($sUserPublicId)
	{
		$aCalendars = [];
		try
		{
			$aCalendars = $this->oStorage->getCalendars($sUserPublicId);
		}
		catch (Exception $oException)
		{
			$aCalendars = false;
		}

		return $aCalendars;
	}

	public function getSharedCalendars($sUserPublicId)
	{
		$aCalendars = [];
		try
		{
			$aCalendars = $this->oStorage->getSharedCalendars($sUserPublicId);
		}
		catch (Exception $oException)
		{
			$aCalendars = false;
		}

		return $aCalendars;
	}

	/**
	 * Deletes event.
	 *
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventId Event ID
	 *
	 * @return bool
	 */
	public function deleteEvent($sUserPublicId, $sCalendarId, $sEventId)
	{
		$oResult = false;

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$aData = $this->oStorage->getEvent($oUser->PublicId, $sCalendarId, $sEventId);
			if ($aData !== false && isset($aData['vcal']) && $aData['vcal'] instanceof \Sabre\VObject\Component\VCalendar)
			{
				$oVCal = $aData['vcal'];
				$aArgs = [
					'sUserPublicId' => $oUser->PublicId
				];
				$this->GetModule()->broadcastEvent(
					'DeleteEvent',
					$aArgs,
					$oVCal
				);
				$oResult = $this->oStorage->deleteEvent($oUser->PublicId, $sCalendarId, $aData['url']);
				if ($oResult)
				{
					// TODO realise 'removeEventFromAllGroups' method in 'Contacts' module
					//$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
					//$oContactsModule->CallMethod('removeEventFromAllGroups', array($sCalendarId, $sEventId));
				}
			}
		}

		return $oResult;
	}

	/**
	 * Deletes event.
	 *
	 * @param string $sUserPublicId
	 * @param string $sCalendarId Calendar ID
	 * @param string $sEventUrl Event URL
	 *
	 * @return bool
	 */
	public function deleteEventByUrl($sUserPublicId, $sCalendarId, $sEventUrl)
	{
		return $this->oStorage->deleteEventByUrl($sUserPublicId, $sCalendarId, $sEventUrl);
	}


	/**
	 * @param string $sUserPublicId
	 * @param string $sData
	 * @param string $mFromEmail
	 * @param bool $bUpdateAttendeeStatus
	 *
	 * @return array|bool
	 */
	public function processICS($sUserPublicId, $sData, $mFromEmail, $bUpdateAttendeeStatus = false)
	{
		$mResult = false;
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		$aAccountEmails = [$oAuthenticatedUser->PublicId];

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$aUserAccounts = \Aurora\System\Api::GetModuleDecorator('Mail')->GetAccounts($oUser->EntityId);
			foreach ($aUserAccounts as $oMailAccount)
			{
				if ($oMailAccount instanceof \Aurora\Modules\Mail\Classes\Account)
				{
					$aAccountEmails[] = $oMailAccount->Email;
				}
			}

			//TODO get fetchers list
	//
	//		$aFetchers = \Aurora\System\Api::ExecuteMethod('Mail::GetFetchers', array('Account' => $oDefaultAccount));
	//		if (is_array($aFetchers) && 0 < count($aFetchers)) {
	//			foreach ($aFetchers as /* @var $oFetcher \Aurora\Modules\Mail\Classes\Fetcher */ $oFetcher) {
	//				if ($oFetcher) {
	//					$aAccountEmails[] = !empty($oFetcher->Email) ? $oFetcher->Email : $oFetcher->IncomingLogin;
	//				}
	//			}
	//		}

//			$aIdentities = \Aurora\System\Api::GetModuleDecorator('Mail')->GetIdentities($oUser->EntityId);
//			if (is_array($aIdentities) && 0 < count($aIdentities))
//			{
//				foreach ($aIdentities as $oIdentity)
//				{
//					if ($oIdentity instanceof \Aurora\Modules\Mail\Classes\Identity)
//					{
//						$aAccountEmails[] = $oIdentity->Email;
//					}
//				}
//			}
//			$aAccountEmails = array_unique($aAccountEmails);

			$oVCal = \Sabre\VObject\Reader::read($sData);
			if ($oVCal)
			{
				$oVCalResult = $oVCal;

				$oMethod = isset($oVCal->METHOD) ? $oVCal->METHOD : null;
				$sMethod = isset($oMethod) ? (string) $oMethod : 'SAVE';

				if (!in_array($sMethod, ['REQUEST', 'REPLY', 'CANCEL', 'PUBLISH', 'SAVE']))
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

					$aCalendars = $this->oStorage->GetCalendarNames($sUserPublicId);
					$aCalendarIds = $this->oStorage->findEventInCalendars($sUserPublicId, $sEventId, $aCalendars);

					if (is_array($aCalendarIds) && isset($aCalendarIds[0]))
					{
						$sCalendarId = $aCalendarIds[0];
						$aDataServer = $this->oStorage->getEvent($sUserPublicId, $sCalendarId, $sEventId);
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
								if (!isset($oVEvent->{'LAST-MODIFIED'}) && isset($oVEvent->{'DTSTAMP'}))
								{
									$oVEvent->add('LAST-MODIFIED', $oVEvent->{'DTSTAMP'}->getDateTime());
								}

								if (isset($oVEvent->{'LAST-MODIFIED'}) && 
									isset($oVEventServer->{'LAST-MODIFIED'}))
								{
									$lastModified = $oVEvent->{'LAST-MODIFIED'}->getDateTime();
									$lastModifiedServer = '';
									//Checking if current user's appointment was parsed earlier
									foreach ($oVEventServer->ATTENDEE as $oAttendee)
									{
										if ($mFromEmail === str_replace('mailto:', '', strtolower((string) $oAttendee->getValue())))
										{
											if (isset($oAttendee['RESPONDED-AT']))
											{
												$lastModifiedServer = new \DateTime($oAttendee['RESPONDED-AT'], new \DateTimeZone('UTC'));
											}
											break;
										}
									}

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
											if ($bUpdateAttendeeStatus)
											{
												$aArgs = [
													'oVCalResult'			=> $oVCalResult,
													'oVEventResult'			=> $oVEventResult,
													'sUserPublicId'			=> $oUser->PublicId,
													'sCalendarId'			=> $sCalendarId,
													'sEventId'				=> $sEventId,
													'sMethod'				=> $sMethod,
													'sequence'				=> $sequence,
													'sequenceServer'		=> $sequenceServer,
													'oVEvent'				=> $oVEvent,
													'mFromEmail'			=> $mFromEmail
												];
												$this->GetModule()->broadcastEvent(
													'processICS::UpdateEvent',
													$aArgs,
													$mResult
												);
											}
										}
									}
								}
							}
						}

						if ($sMethod === 'CANCEL' && $bUpdateAttendeeStatus)
						{
							$aArgs = [
								'sUserPublicId'			=> $oUser->PublicId,
								'sCalendarId'			=> $sCalendarId,
								'sEventId'				=> $sEventId
							];
							$this->GetModule()->broadcastEvent(
								'processICS::Cancel',
								$aArgs,
								$mResult
							);
						}
					}

					if (!$bUpdateAttendeeStatus)
					{
						$sWhen = '';
						if (isset($oVEventResult->DTSTART))
						{
							if (!$oVEventResult->DTSTART->hasTime())
							{
								$oDateTime = \Aurora\Modules\Calendar\Classes\Helper::getDateTime($oVEventResult->DTSTART, $oUser->DefaultTimeZone);
								$sWhen = $oDateTime->format('D, M d, Y');
							}
							else
							{
								$sWhen = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oVEventResult->DTSTART, $oUser->DefaultTimeZone, 'D, M d, Y, H:i');
							}
						}
						$mResult = [
							'Calendars' => $aCalendars,
							'CalendarId' => $sCalendarId,
							'UID' => $sEventId,
							'Body' => $oVCalResult->serialize(),
							'Action' => $sMethod,
							'Location' => isset($oVEventResult->LOCATION) ? (string)$oVEventResult->LOCATION : '',
							'Description' => isset($oVEventResult->DESCRIPTION) ? (string)$oVEventResult->DESCRIPTION : '',
							'When' => $sWhen,
							'Sequence' => isset($sequence) ? $sequence : 1
						];

						$aAccountEmails = ($sMethod === 'REPLY') ? [$mFromEmail] : $aAccountEmails;
						if (isset($sequenceServer) && isset($sequence) && $sequenceServer >= $sequence)
						{
							$aArgs = [
								'oVEventResult'		=> $oVEventResult,
								'sMethod'			=> $sMethod,
								'aAccountEmails'	=> $aAccountEmails
							];
							$this->GetModule()->broadcastEvent(
								'processICS::AddAttendeesToResult',
								$aArgs,
								$mResult
							);
						}
					}
				}
			}
		}

		return $mResult;
	}
	
	/**
	 *
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @param string $sEmail
	 *
	 * @return \Aurora\Modules\StandardAuth\Classes\Account|false $oAccount
	 */
	public function getAccountFromAccountList($oAccount, $sEmail)
	{
		$oResult = null;
		$iResultAccountId = 0;

		try
		{
			if ($oAccount) 
			{
				$aUserAccounts = \Aurora\System\Api::GetModule('Core')->getUsersManager()->getUserAccounts($oAccount->IdUser);
				foreach ($aUserAccounts as $iAccountId => $aUserAccount) 
				{
					if (isset($aUserAccount) && isset($aUserAccount[1]) && strtolower($aUserAccount[1]) === strtolower($sEmail)) 
					{
						$iResultAccountId = $iAccountId;
						break;
					}
				}
				if (0 < $iResultAccountId) 
				{
					$oResult = \Aurora\System\Api::GetModule('Core')->getUsersManager()->getAccountById($iResultAccountId);
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
	 * @param string $sUserPublicId
	 *
	 * @return bool
	 */
	public function clearAllCalendars($sUserPublicId)
	{
		$bResult = false;

		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$bResult = $this->oStorage->clearAllCalendars($sUserPublicId);
		}

		return $bResult;
	}
}






