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
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractLicensedModule
{
	public $oManager = null;
	public $oFilecacheManager = null;
	
	public function getManager()
	{
		if ($this->oManager === null)
		{
			$this->oManager = new Manager($this);
		}

		return $this->oManager;
	}

	public function getFilecacheManager()
	{
		if ($this->oFilecacheManager === null)
		{
			$this->oFilecacheManager = new \Aurora\System\Managers\Filecache();
		}

		return $this->oFilecacheManager;
	}

	public function init() 
	{
		$this->AddEntries(array(
				'calendar-pub' => 'EntryCalendarPub',
				'calendar-download' => 'EntryCalendarDownload'
			)
		);

		\Aurora\Modules\Core\Classes\User::extend(
			self::GetName(),
			[
				'HighlightWorkingDays'	=> array('bool', $this->getConfig('HighlightWorkingDays', false)),
				'HighlightWorkingHours'	=> array('bool', $this->getConfig('HighlightWorkingHours', false)),
				'WorkdayStarts'			=> array('int', $this->getConfig('WorkdayStarts', 9)),
				'WorkdayEnds'			=> array('int', $this->getConfig('WorkdayEnds', 18)),
				'WeekStartsOn'			=> array('int', $this->getConfig('WeekStartsOn', 0)),
				'DefaultTab'			=> array('int', $this->getConfig('DefaultTab', 3)),
			]

		);

		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
	}
	
	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$aSettings = array(
			'AddDescriptionToTitle' => $this->getConfig('AddDescriptionToTitle', false),
			'AllowTasks' => $this->getConfig('AllowTasks', true),
			'DefaultTab' => $this->getConfig('DefaultTab', 3),
			'HighlightWorkingDays' => $this->getConfig('HighlightWorkingDays', true),
			'HighlightWorkingHours' => $this->getConfig('HighlightWorkingHours', true),
			'PublicCalendarId' => $this->oHttp->GetQuery('calendar-pub', ''),
			'WeekStartsOn' => $this->getConfig('WeekStartsOn', 0),
			'WorkdayEnds' => $this->getConfig('WorkdayEnds', 18),
			'WorkdayStarts' => $this->getConfig('WorkdayStarts', 9),
		);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
		{
			if (isset($oUser->{self::GetName().'::HighlightWorkingDays'}))
			{
				$aSettings['HighlightWorkingDays'] = $oUser->{self::GetName().'::HighlightWorkingDays'};
			}
			if (isset($oUser->{self::GetName().'::HighlightWorkingHours'}))
			{
				$aSettings['HighlightWorkingHours'] = $oUser->{self::GetName().'::HighlightWorkingHours'};
			}
			if (isset($oUser->{self::GetName().'::WorkdayStarts'}))
			{
				$aSettings['WorkdayStarts'] = $oUser->{self::GetName().'::WorkdayStarts'};
			}
			if (isset($oUser->{self::GetName().'::WorkdayEnds'}))
			{
				$aSettings['WorkdayEnds'] = $oUser->{self::GetName().'::WorkdayEnds'};
			}
			if (isset($oUser->{self::GetName().'::WeekStartsOn'}))
			{
				$aSettings['WeekStartsOn'] = $oUser->{self::GetName().'::WeekStartsOn'};
			}
			if (isset($oUser->{self::GetName().'::DefaultTab'}))
			{
				$aSettings['DefaultTab'] = $oUser->{self::GetName().'::DefaultTab'};
			}
		}
		
		return $aSettings;
	}
	
	public function UpdateSettings($HighlightWorkingDays, $HighlightWorkingHours, $WorkdayStarts, $WorkdayEnds, $WeekStartsOn, $DefaultTab)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser)
		{
			if ($oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
			{
				$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
				$oUser->{self::GetName().'::HighlightWorkingDays'} = $HighlightWorkingDays;
				$oUser->{self::GetName().'::HighlightWorkingHours'} = $HighlightWorkingHours;
				$oUser->{self::GetName().'::WorkdayStarts'} = $WorkdayStarts;
				$oUser->{self::GetName().'::WorkdayEnds'} = $WorkdayEnds;
				$oUser->{self::GetName().'::WeekStartsOn'} = $WeekStartsOn;
				$oUser->{self::GetName().'::DefaultTab'} = $DefaultTab;
				return $oCoreDecorator->UpdateUserObject($oUser);
			}
			if ($oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
			{
				$this->setConfig('HighlightWorkingDays', $HighlightWorkingDays);
				$this->setConfig('HighlightWorkingHours', $HighlightWorkingHours);
				$this->setConfig('WorkdayStarts', $WorkdayStarts);
				$this->setConfig('WorkdayEnds', $WorkdayEnds);
				$this->setConfig('WeekStartsOn', $WeekStartsOn);
				$this->setConfig('DefaultTab', $DefaultTab);
				return $this->saveModuleConfig();
			}
		}
		
		return false;
	}
	
	/**
	 * Loads calendar.
	 *
	 * @param int $UserId
	 * @param string sCalendarId Calendar ID
	 *
	 * @return \Aurora\Modules\Calendar\Classes\Calendar|false $oCalendar
	 */
	public function GetCalendar($UserId, $CalendarId)
	{
		$oCalendar = $this->getManager()->getCalendar($UserId, $CalendarId);
		if ($oCalendar) 
		{
//			$oCalendar = $this->getManager()->populateCalendarShares($UserId, $oCalendar);
		}
		return $oCalendar;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param boolean $IsPublic
	 * @param string $PublicCalendarId
	 * @return array|boolean
	 */
	public function GetCalendars($UserId, $IsPublic = false, $PublicCalendarId = '')
	{
		$mResult = false;
		$mCalendars = false;
		
		if ($IsPublic) 
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
			$oCalendar = $this->getManager()->getPublicCalendar($PublicCalendarId);
			$mCalendars = array($oCalendar);
		}
		else 
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$oUser = \Aurora\System\Api::getUserById($UserId);
			if ($oUser)
			{
				$mCalendars = $this->getManager()->getCalendars($oUser->PublicId);
			}
			
		}
		
		if ($mCalendars) 
		{
			$mResult = array(
				'Calendars' => $mCalendars
			);
		}
		
		return $mResult;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function EntryCalendarDownload()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$RawKey = \Aurora\System\Application::GetPathItemByIndex(1, '');
		$aValues = \Aurora\System\Api::DecodeKeyValues($RawKey);
		
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById(\Aurora\System\Api::getAuthenticatedUserId());

		if (isset($aValues['CalendarId']))
		{
			$sCalendarId = $aValues['CalendarId'];
			$sOutput = $this->getManager()->exportCalendarToIcs($sUserPublicId, $sCalendarId);
			if (false !== $sOutput)
			{
				header('Pragma: public');
				header('Content-Type: text/calendar');
				header('Content-Disposition: attachment; filename="' . $sCalendarId . '.ics";');
				header('Content-Transfer-Encoding: binary');
				echo $sOutput;
			}
		}
	}
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $Name
	 * @param string $Description
	 * @param string $Color
	 * @return array|boolean
	 */
	public function CreateCalendar($UserId, $Name, $Description, $Color, $UUID = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$mResult = false;
		
		$mCalendarId = $this->getManager()->createCalendar($sUserPublicId, $Name, $Description, 1, $Color, $UUID);
		if ($mCalendarId)
		{
			$oCalendar = $this->getManager()->getCalendar($sUserPublicId, $mCalendarId);
			if ($oCalendar instanceof \Aurora\Modules\Calendar\Classes\Calendar)
			{
				$mResult = $oCalendar->toResponseArray($sUserPublicId);
			}
		}
		
		return $mResult;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $Id
	 * @param string $Name
	 * @param string $Description
	 * @param string $Color
	 * @return array|boolean
	 */
	public function UpdateCalendar($UserId, $Id, $Name, $Description, $Color)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		return $this->getManager()->updateCalendar($sUserPublicId, $Id, $Name, $Description, 0, $Color);
	}	

	/**
	 * 
	 * @param int $UserId
	 * @param string $Id
	 * @param string $Color
	 * @return array|boolean
	 */
	public function UpdateCalendarColor($UserId, $Id, $Color)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		return $this->getManager()->updateCalendarColor($sUserPublicId, $Id, $Color);
	}
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $Id
	 * @param boolean $IsPublic
	 * @param array $Shares
	 * @param boolean $ShareToAll
	 * @param int $ShareToAllAccess
	 * @return array|boolean
	 */
	public function UpdateCalendarShare($UserId, $Id, $IsPublic, $Shares, $ShareToAll = false, $ShareToAllAccess = \Aurora\Modules\Calendar\Enums\Permission::Read)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$aShares = json_decode($Shares, true) ;
		$oUser = null;
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser->EntityId !== $UserId && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
		{
			$oUser = \Aurora\System\Api::getUserById($UserId);
		}
		else
		{
			$oUser = $oAuthenticatedUser;
		}
		$oCalendar = $this->getManager()->getCalendar($oUser->PublicId, $Id);
		if (!$oCalendar)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		//Calendar can be shared by owner or user with write access except SharedWithAll calendars
		if ($oCalendar->Owner !== $sUserPublicId
			&& $oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Write)
		{
			return false;
		}
		// Share calendar to all users
		if ($ShareToAll)
		{
			$aShares[] = array(
				'email' => $this->getManager()->getTenantUser($oUser),
				'access' => $ShareToAllAccess
			);
		}
		else
		{
			$aShares[] = array(
				'email' => $this->getManager()->getTenantUser($oUser),
				'access' => \Aurora\Modules\Calendar\Enums\Permission::RemovePermission
			);
		}

		return $this->getManager()->updateCalendarShares($sUserPublicId, $Id, $aShares);
	}		
	
	/**
	 * 
	 * @param string $Id User publicId
	 * @param boolean $IsPublic
	 * @param int $UserId
	 * @return array|boolean
	 */
	public function UpdateCalendarPublic($Id, $IsPublic, $UserId = null)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oUser = null;
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oAuthenticatedUser->PublicId !== $Id && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
		{
			$oUser = \Aurora\System\Api::getUserById($UserId);
		}
		return $this->getManager()->publicCalendar($Id, $IsPublic, $oUser);
	}		

	/**
	 * 
	 * @param int $UserId
	 * @param string $Id
	 * @return array|boolean
	 */
	public function DeleteCalendar($UserId, $Id)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		return $this->getManager()->deleteCalendar($sUserPublicId, $Id);
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $calendarId
	 * @param string $uid
	 * @return array|boolean
	 */
	public function GetBaseEvent($UserId, $calendarId, $uid)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		return $this->getManager()->getBaseEvent($sUserPublicId, $calendarId, $uid);
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param array $CalendarIds
	 * @param int $Start
	 * @param int $End
	 * @param boolean $IsPublic
	 * @param boolean $Expand
	 * @return array|boolean
	 */
	public function GetEvents($UserId, $CalendarIds, $Start, $End, $IsPublic, $Expand = true, $DefaultTimeZone = null)
	{
		$mResult = false;
		if ($IsPublic)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
			$mResult = $this->getManager()->getPublicEvents($CalendarIds, $Start, $End, $Expand, $DefaultTimeZone);
		}
		else
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
			$mResult = $this->getManager()->getEvents($sUserPublicId, $CalendarIds, $Start, $End);
		}
		
		return $mResult;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param array $CalendarIds
	 * @param int $Start
	 * @param int $End
	 * @param boolean $IsPublic
	 * @param boolean $Expand
	 * @return array|boolean
	 */
	public function GetTasks($UserId, $CalendarIds, $Completed = true, $Search = '', $Start = null, $End = null, $Expand = true)
	{
		$mResult = [];
		if ($this->getConfig('AllowTasks', true))
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

			$mResult = $this->getManager()->getTasks($sUserPublicId, $CalendarIds, $Completed, $Search, $Start, $End, $Expand);
		}
		
		return $mResult;
	}	
		
	/**
	 * 
	 * @param int $UserId
	 * @param string $newCalendarId
	 * @param string $subject
	 * @param string $description
	 * @param string $location
	 * @param int $startTS
	 * @param int $endTS
	 * @param boolean $allDay
	 * @param string $alarms
	 * @param string $attendees
	 * @param string $rrule
	 * @param int $selectStart
	 * @param int $selectEnd
	 * @return array|boolean
	 */
	public function CreateEvent($UserId, $newCalendarId, $subject, $description, $location, $startTS, 
			$endTS, $allDay, $alarms, $attendees, $rrule, $selectStart, $selectEnd, $type = 'VEVENT', $status = false, $withDate = true, $owner = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
		$oEvent->IdCalendar = $newCalendarId;
		$oEvent->Name = $subject;
		$oEvent->Description = $description;
		$oEvent->Location = $location;
		if ($withDate)
		{
			$oEvent->Start = $startTS;
			$oEvent->End = $endTS;
			$oEvent->AllDay = $allDay;
			$oEvent->Alarms = @json_decode($alarms, true);
			$aRRule = @json_decode($rrule, true);
			if ($aRRule)
			{
				$oUser = \Aurora\System\Api::getAuthenticatedUser();
				$oRRule = new \Aurora\Modules\Calendar\Classes\RRule($oUser->DefaultTimeZone);
				$oRRule->Populate($aRRule);
				$oEvent->RRule = $oRRule;
			}
		}
		$oEvent->Attendees = null;
		$oEvent->Type = $type;
		$oEvent->Status = $status && $type === 'VTODO';
		if ($type === 'VTODO')
		{
			$attendees = json_encode([]);
		}
		$aArgs = [
			'attendees'		=> $attendees,
			'owner'		=> $owner,
			'UserPublicId'	=> $sUserPublicId
		];
		$this->broadcastEvent(
			'UpdateEventAttendees',
			$aArgs,
			$oEvent
		);

		$mResult = $this->getManager()->createEvent($sUserPublicId, $oEvent);
		
		if ($mResult)
		{
			$mResult = $this->getManager()->getExpandedEvent($sUserPublicId, $oEvent->IdCalendar, $mResult, $selectStart, $selectEnd);
		}
		
		return $mResult;
	}
	
	/**
	 * 
	 * @param type $UserId
	 * @param type $CalendarId
	 * @param type $EventId
	 * @param type $Data
	 * @return type
	 */
	public function CreateEventFromData($UserId, $CalendarId, $EventId, $Data)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		
		return $this->getManager()->createEventFromRaw($sUserPublicId, $CalendarId, $EventId, $Data);
	}
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $CalendarId
	 * @param string $Subject
	 * @return array|boolean
	 */
	public function CreateTask($UserId, $CalendarId, $Subject)
	{
		$mResult = false;
		if ($this->getConfig('AllowTasks', true))
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
			$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
			$oEvent->IdCalendar = $CalendarId;
			$oEvent->Name = $Subject;
			$oEvent->Start = \time();
			$oEvent->End = \time();
			$oEvent->Type = 'VTODO';
			
			$mResult = $this->getManager()->createEvent($sUserPublicId, $oEvent);
		}
		
		return $mResult;
	}
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $CalendarId
	 * @param string $Subject
	 * @param string $Sescription
	 * @return array|boolean
	 */
	public function UpdateTask($UserId, $CalendarId, $TaskId, $Subject, $Status, $WithDate = false)
	{
		$bResult = false;
		if ($this->getConfig('AllowTasks', true))
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
			$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
			$oEvent->IdCalendar = $CalendarId;
			$oEvent->Id = $TaskId;
			$oEvent->Name = $Subject;
			$oEvent->Type = 'VTODO';
			$oEvent->Status = $Status ? 'COMPLETED' : '';
			
			if ($WithDate)
			{
				$aEvent = $this->GetBaseEvent($UserId, $CalendarId, $TaskId);
				if ($aEvent)
				{
					$oEvent->Start = $aEvent['startTS'];
					$oEvent->End = $aEvent['endTS'];
				}
			}

			if ($this->getManager()->updateEvent($sUserPublicId, $oEvent))
			{
				return $this->GetBaseEvent($UserId, $CalendarId, $TaskId);
			}
		}
		
		return $bResult;
	}
	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $newCalendarId
	 * @param string $calendarId
	 * @param string $uid
	 * @param string $subject
	 * @param string $description
	 * @param string $location
	 * @param int $startTS
	 * @param int $endTS
	 * @param boolean $allDay
	 * @param string $alarms
	 * @param string $attendees
	 * @param string $rrule
	 * @param int $allEvents
	 * @param string $recurrenceId
	 * @param int $selectStart
	 * @param int $selectEnd
	 * @return array|boolean
	 */
	public function UpdateEvent($UserId, $newCalendarId, $calendarId, $uid, $subject, $description, 
			$location, $startTS, $endTS, $allDay, $alarms, $attendees, $rrule, $allEvents, $recurrenceId,
			$selectStart, $selectEnd, $type = 'VEVENT', $status = false, $withDate = true, $owner = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$mResult = false;
		
		$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
		$oEvent->IdCalendar = $calendarId;
		$oEvent->Id = $uid;
		$oEvent->Name = $subject;
		$oEvent->Description = $description;
		$oEvent->Location = $location;
		if ($withDate)
		{
			$oEvent->Start = $startTS;
			$oEvent->End = $endTS;
			$oEvent->AllDay = $allDay;
			$oEvent->Alarms = @json_decode($alarms, true);
			$aRRule = @json_decode($rrule, true);
			if ($aRRule)
			{
				$oUser = \Aurora\System\Api::getAuthenticatedUser();
				$oRRule = new \Aurora\Modules\Calendar\Classes\RRule($oUser->DefaultTimeZone);
				$oRRule->Populate($aRRule);
				$oEvent->RRule = $oRRule;
			}
		}
		$oEvent->Attendees = null;
		$oEvent->Type = $type;
		if ($type === 'VTODO')
		{
			$attendees = json_encode([]);
		}
		$aArgs = [
			'attendees'		=> $attendees,
			'owner'		=> $owner,
			'UserPublicId'	=> $sUserPublicId
		];
		$this->broadcastEvent(
			'UpdateEventAttendees',
			$aArgs,
			$oEvent
		);
		if (!empty($status))
		{
			$oEvent->Status = $status && $type === 'VTODO';
		}
		
		if ($allEvents === 1)
		{
			$mResult = $this->getManager()->updateExclusion($sUserPublicId, $oEvent, $recurrenceId);
		}
		else
		{
			$mResult = $this->getManager()->updateEvent($sUserPublicId, $oEvent);
			if ($mResult && $newCalendarId !== $oEvent->IdCalendar)
			{
				$mResult = $this->getManager()->moveEvent($sUserPublicId, $oEvent->IdCalendar, $newCalendarId, $oEvent->Id);
				$oEvent->IdCalendar = $newCalendarId;
			}
		}
		if ($mResult)
		{
				$mResult = $this->getManager()->getExpandedEvent($sUserPublicId, $oEvent->IdCalendar, $oEvent->Id, $selectStart, $selectEnd);
		}
		return $mResult;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $calendarId
	 * @param string $uid
	 * @param boolean $allEvents
	 * @param string $recurrenceId
	 * @return array|boolean
	 */
	public function DeleteEvent($UserId, $calendarId, $uid, $allEvents, $recurrenceId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$mResult = false;
		if ($sUserPublicId)
		{
			if ($allEvents === 1)
			{
				$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
				$oEvent->IdCalendar = $calendarId;
				$oEvent->Id = $uid;
				$mResult = $this->getManager()->updateExclusion($sUserPublicId, $oEvent, $recurrenceId, true);
			}
			else
			{
				$mResult = $this->getManager()->deleteEvent($sUserPublicId, $calendarId, $uid);
			}
		}

		return $mResult;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $CalendarId
	 * @param string $File
	 * @return array|boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function AddEventsFromFile($UserId, $CalendarId, $File)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$mResult = false;

		if (empty($CalendarId) || empty($File))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$sData = $this->getFilecacheManager()->get($sUserPublicId, $File, '', self::GetName());
		if (!empty($sData))
		{
			$mCreateEventResult = $this->getManager()->createEventFromRaw($sUserPublicId, $CalendarId, null, $sData);
			if ($mCreateEventResult)
			{
				$mResult = array(
					'Uid' => (string) $mCreateEventResult
				);
			}
		}

		return $mResult;
	}	
	
	public function EntryCalendarPub()
	{
		$sResult = '';
		
		$oApiIntegrator = \Aurora\System\Managers\Integrator::getInstance();
		
		if ($oApiIntegrator)
		{
			@\header('Content-Type: text/html; charset=utf-8', true);
			
			if (!strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'firefox'))
			{
				@\header('Last-Modified: '.\gmdate('D, d M Y H:i:s').' GMT');
			}
			
			$oSettings =& \Aurora\System\Api::GetSettings();
			if (($oSettings->GetValue('CacheCtrl', true) && isset($_COOKIE['aft-cache-ctrl'])))
			{
				@\setcookie('aft-cache-ctrl', '', \strtotime('-1 hour'), \Aurora\System\Api::getCookiePath());
				\MailSo\Base\Http::NewInstance()->StatusHeader(304);
				exit();
			}
			$oCoreClientModule = \Aurora\System\Api::GetModule('CoreWebclient');
			if ($oCoreClientModule instanceof \Aurora\System\Module\AbstractModule) 
			{
				$sResult = file_get_contents($oCoreClientModule->GetPath().'/templates/Index.html');
				if (is_string($sResult)) 
				{
					$sFrameOptions = $oSettings->GetValue('XFrameOptions', '');
					if (0 < \strlen($sFrameOptions)) 
					{
						@\header('X-Frame-Options: '.$sFrameOptions);
					}
					
					$sAuthToken = isset($_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY]) ? $_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY] : '';
					$sResult = strtr($sResult, array(
						'{{AppVersion}}' => AU_APP_VERSION,
						'{{IntegratorDir}}' => $oApiIntegrator->isRtl() ? 'rtl' : 'ltr',
						'{{IntegratorLinks}}' => $oApiIntegrator->buildHeadersLink(),
						'{{IntegratorBody}}' => $oApiIntegrator->buildBody(
							array(
								'public_app' => true,
								'modules_list' => $oApiIntegrator->GetModulesForEntry('CalendarWebclient')
							)		
						)
					));
				}
			}
		}
		return $sResult;	
	}

	/**
	 * 
	 * @param int $UserId
	 * @param string $Data
	 * @param string $FromEmail
	 * @return boolean
	 */
	public function ProcessICS($UserId, $Data, $FromEmail)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		return $this->getManager()->processICS($UserId, $Data, $FromEmail);
	}

	/**
	 * 
	 * @param int $UserId
	 * @param array $UploadData
	 * @param string $AdditionalData
	 * @return array
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UploadCalendar($UserId,$UploadData, $CalendarID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$aAdditionalData = @json_decode($AdditionalData, true);
		
		$sCalendarId = isset($CalendarID) ? $CalendarID : '';

		$sError = '';
		$aResponse = array(
			'ImportedCount' => 0
		);

		if (is_array($UploadData))
		{
			$bIsIcsExtension  = strtolower(pathinfo($UploadData['name'], PATHINFO_EXTENSION)) === 'ics';

			if ($bIsIcsExtension)
			{
				$sSavedName = 'import-post-' . md5($UploadData['name'] . $UploadData['tmp_name']);
				if ($this->getFilecacheManager()->moveUploadedFile($sUserPublicId, $sSavedName, $UploadData['tmp_name'], '', self::GetName()))
				{
					$iImportedCount = $this->getManager()->importToCalendarFromIcs(
							$sUserPublicId,
							$sCalendarId, 
							$this->getFilecacheManager()->generateFullFilePath($sUserPublicId, $sSavedName, '', self::GetName())
					);

					if (false !== $iImportedCount && -1 !== $iImportedCount)
					{
						$aResponse['ImportedCount'] = $iImportedCount;
					}
					else
					{
						$sError = 'unknown';
					}

					$this->getFilecacheManager()->clear($sUserPublicId, $sSavedName, '', self::GetName());
				}
				else
				{
					$sError = 'unknown';
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::IncorrectFileExtension);
			}
		}
		else
		{
			$sError = 'unknown';
		}

		if (0 < strlen($sError))
		{
			$aResponse['Error'] = $sError;
		}		
		
		return $aResponse;
	}		
	
	public function onGetBodyStructureParts($aParts, &$aResult)
	{
		foreach ($aParts as $oPart) {
			if ($oPart instanceof \MailSo\Imap\BodyStructure && 
					$oPart->ContentType() === 'text/calendar'){
				
				$aResult[] = $oPart;
				break;
			}
		}
	}
	
	public function onExtendMessageData($aData, &$oMessage)
	{
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($oUser->EntityId);
		$sFromEmail = '';
		$oFromCollection = $oMessage->getFrom();
		if ($oFromCollection && 0 < $oFromCollection->Count())
		{
			$oFrom =& $oFromCollection->GetByIndex(0);
			if ($oFrom)
			{
				$sFromEmail = trim($oFrom->GetEmail());
			}
		}
		foreach ($aData as $aDataItem)
		{
			if ($aDataItem['Part'] instanceof \MailSo\Imap\BodyStructure && $aDataItem['Part']->ContentType() === 'text/calendar')
			{
				$sData = $aDataItem['Data'];
				if (!empty($sData))
				{
					try
					{
						$mResult = $this->getManager()->processICS($sUserPublicId, $sData, $sFromEmail);
					}
					catch (\Exception $oEx)
					{
						$mResult = false;
					}
					if (is_array($mResult) && !empty($mResult['Action']) && !empty($mResult['Body']))
					{
						$sTemptFile = md5($sFromEmail . $sData).'.ics';
						if ($this->getFilecacheManager()->put($sUserPublicId, $sTemptFile, $sData, '', self::GetName()))
						{
							$oIcs = \Aurora\Modules\Calendar\Classes\Ics::createInstance();

							$oIcs->Uid = $mResult['UID'];
							$oIcs->Sequence = $mResult['Sequence'];
							$oIcs->File = $sTemptFile;
							$oIcs->Type = 'SAVE';
							$oIcs->Attendee = null;
							$oIcs->Location = !empty($mResult['Location']) ? $mResult['Location'] : '';
							$oIcs->Description = !empty($mResult['Description']) ? $mResult['Description'] : '';
							$oIcs->When = !empty($mResult['When']) ? $mResult['When'] : '';
							$oIcs->CalendarId = !empty($mResult['CalendarId']) ? $mResult['CalendarId'] : '';
							$this->broadcastEvent(
								'CreateIcs',
								$mResult,
								$oIcs
							);

							$oMessage->addExtend('ICAL', $oIcs);
						}
						else
						{
							\Aurora\System\Api::Log('Can\'t save temp file "'.$sTemptFile.'"', \Aurora\System\Enums\LogLevel::Error);
						}
					}
				}				
			}
		}
	}
	
    public function onGetMobileSyncInfo($aArgs, &$mResult)
	{
		$oDavModule = \Aurora\Modules\Dav\Module::Decorator();
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$aCalendars = $this->Decorator()->GetCalendars($iUserId);
		
		if (isset($aCalendars['Calendars']) && is_array($aCalendars['Calendars']) && 0 < count($aCalendars['Calendars']))
		{
			foreach($aCalendars['Calendars'] as $oCalendar)
			{
				if ($oCalendar instanceof \Aurora\Modules\Calendar\Classes\Calendar)
				{
					$mResult['Dav']['Calendars'][] = array(
						'Name' => $oCalendar->DisplayName,
						'Url' => rtrim($oDavModule->GetServerUrl().$oCalendar->Url, "/")."/"
					);
				}
			}
		}	
	}

	public function onBeforeDeleteUser($aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUser($aArgs["UserId"]);
		$sUserPublicId = isset($oUser) ? $oUser->PublicId : null;
		if ($sUserPublicId)
		{
			$this->getManager()->deletePrincipalCalendars($sUserPublicId);
		}
	}
}
