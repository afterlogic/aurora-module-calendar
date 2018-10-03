<?php
/**
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar;

/**
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractLicensedModule
{
	public $oApiCalendarManager = null;
	public $oApiFileCache = null;
	
	public function init() 
	{
		$this->oApiCalendarManager = new Manager($this);
		$this->oApiFileCache = new \Aurora\System\Managers\Filecache();

		$this->AddEntries(array(
				'calendar-pub' => 'EntryCalendarPub',
				'calendar-download' => 'EntryCalendarDownload'
			)
		);

		$this->extendObject(
			'Aurora\Modules\Core\Classes\User', 
			array(
				'HighlightWorkingDays'	=> array('bool', $this->getConfig('HighlightWorkingDays', false)),
				'HighlightWorkingHours'	=> array('bool', $this->getConfig('HighlightWorkingHours', false)),
				'WorkdayStarts'			=> array('int', $this->getConfig('WorkdayStarts', 9)),
				'WorkdayEnds'			=> array('int', $this->getConfig('WorkdayEnds', 18)),
				'WeekStartsOn'			=> array('int', $this->getConfig('WeekStartsOn', 0)),
				'DefaultTab'			=> array('int', $this->getConfig('DefaultTab', 3)),
			)
		);

		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('Core::AfterDeleteUser', array($this, 'onAfterDeleteUser'));
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
			if (isset($oUser->{$this->GetName().'::HighlightWorkingDays'}))
			{
				$aSettings['HighlightWorkingDays'] = $oUser->{$this->GetName().'::HighlightWorkingDays'};
			}
			if (isset($oUser->{$this->GetName().'::HighlightWorkingHours'}))
			{
				$aSettings['HighlightWorkingHours'] = $oUser->{$this->GetName().'::HighlightWorkingHours'};
			}
			if (isset($oUser->{$this->GetName().'::WorkdayStarts'}))
			{
				$aSettings['WorkdayStarts'] = $oUser->{$this->GetName().'::WorkdayStarts'};
			}
			if (isset($oUser->{$this->GetName().'::WorkdayEnds'}))
			{
				$aSettings['WorkdayEnds'] = $oUser->{$this->GetName().'::WorkdayEnds'};
			}
			if (isset($oUser->{$this->GetName().'::WeekStartsOn'}))
			{
				$aSettings['WeekStartsOn'] = $oUser->{$this->GetName().'::WeekStartsOn'};
			}
			if (isset($oUser->{$this->GetName().'::DefaultTab'}))
			{
				$aSettings['DefaultTab'] = $oUser->{$this->GetName().'::DefaultTab'};
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
				$oUser->{$this->GetName().'::HighlightWorkingDays'} = $HighlightWorkingDays;
				$oUser->{$this->GetName().'::HighlightWorkingHours'} = $HighlightWorkingHours;
				$oUser->{$this->GetName().'::WorkdayStarts'} = $WorkdayStarts;
				$oUser->{$this->GetName().'::WorkdayEnds'} = $WorkdayEnds;
				$oUser->{$this->GetName().'::WeekStartsOn'} = $WeekStartsOn;
				$oUser->{$this->GetName().'::DefaultTab'} = $DefaultTab;
				return $oCoreDecorator->UpdateUserObject($oUser);
			}
			if ($oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin)
			{
				return true;
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
		$oCalendar = $this->oApiCalendarManager->getCalendar($UserId, $CalendarId);
		if ($oCalendar) 
		{
//			$oCalendar = $this->oApiCalendarManager->populateCalendarShares($UserId, $oCalendar);
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
			$oCalendar = $this->oApiCalendarManager->getPublicCalendar($PublicCalendarId);
			$mCalendars = array($oCalendar);
		}
		else 
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$oUser = \Aurora\System\Api::getUserById($UserId);
			if ($oUser)
			{
				$mCalendars = $this->oApiCalendarManager->getCalendars($oUser->PublicId);
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
			$sOutput = $this->oApiCalendarManager->exportCalendarToIcs($sUserPublicId, $sCalendarId);
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
	public function CreateCalendar($UserId, $Name, $Description, $Color)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
		$mResult = false;
		
		$mCalendarId = $this->oApiCalendarManager->createCalendar($sUserPublicId, $Name, $Description, 1, $Color);
		if ($mCalendarId)
		{
			$oCalendar = $this->oApiCalendarManager->getCalendar($sUserPublicId, $mCalendarId);
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
		return $this->oApiCalendarManager->updateCalendar($sUserPublicId, $Id, $Name, $Description, 0, $Color);
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
		return $this->oApiCalendarManager->updateCalendarColor($sUserPublicId, $Id, $Color);
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
		// Share calendar to all users
		if ($ShareToAll)
		{
			$aShares[] = array(
				'email' => $this->oApiCalendarManager->getTenantUser($oUser),
				'access' => $ShareToAllAccess
			);
		}
		else
		{
			$aShares[] = array(
				'email' => $this->oApiCalendarManager->getTenantUser($oUser),
				'access' => \Aurora\Modules\Calendar\Enums\Permission::RemovePermission
			);
		}

		return $this->oApiCalendarManager->updateCalendarShares($sUserPublicId, $Id, $aShares);
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
		return $this->oApiCalendarManager->publicCalendar($Id, $IsPublic, $oUser);
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
		return $this->oApiCalendarManager->deleteCalendar($sUserPublicId, $Id);
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
		return $this->oApiCalendarManager->getBaseEvent($sUserPublicId, $calendarId, $uid);
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
			$mResult = $this->oApiCalendarManager->getPublicEvents($CalendarIds, $Start, $End, $Expand, $DefaultTimeZone);
		}
		else
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
			$mResult = $this->oApiCalendarManager->getEvents($sUserPublicId, $CalendarIds, $Start, $End);
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

			$mResult = $this->oApiCalendarManager->getTasks($sUserPublicId, $CalendarIds, $Completed, $Search, $Start, $End, $Expand);
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

		$mResult = $this->oApiCalendarManager->createEvent($sUserPublicId, $oEvent);
		
		if ($mResult)
		{
			$mResult = $this->oApiCalendarManager->getExpandedEvent($sUserPublicId, $oEvent->IdCalendar, $mResult, $selectStart, $selectEnd);
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
		
		return $this->oApiCalendarManager->createEventFromRaw($sUserPublicId, $CalendarId, $EventId, $Data);
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
			
			$mResult = $this->oApiCalendarManager->createEvent($sUserPublicId, $oEvent);
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

			if ($this->oApiCalendarManager->updateEvent($sUserPublicId, $oEvent))
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
			$mResult = $this->oApiCalendarManager->updateExclusion($sUserPublicId, $oEvent, $recurrenceId);
		}
		else
		{
			$mResult = $this->oApiCalendarManager->updateEvent($sUserPublicId, $oEvent);
			if ($mResult && $newCalendarId !== $oEvent->IdCalendar)
			{
				$mResult = $this->oApiCalendarManager->moveEvent($sUserPublicId, $oEvent->IdCalendar, $newCalendarId, $oEvent->Id);
				$oEvent->IdCalendar = $newCalendarId;
			}
		}
		if ($mResult)
		{
				$mResult = $this->oApiCalendarManager->getExpandedEvent($sUserPublicId, $oEvent->IdCalendar, $oEvent->Id, $selectStart, $selectEnd);
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
				$mResult = $this->oApiCalendarManager->updateExclusion($sUserPublicId, $oEvent, $recurrenceId, true);
			}
			else
			{
				$mResult = $this->oApiCalendarManager->deleteEvent($sUserPublicId, $calendarId, $uid);
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

		$sData = $this->oApiFileCache->get($sUserPublicId, $File, '', $this->GetName());
		if (!empty($sData))
		{
			$mCreateEventResult = $this->oApiCalendarManager->createEventFromRaw($sUserPublicId, $CalendarId, null, $sData);
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
		
		$oApiIntegrator = \Aurora\Modules\Core\Managers\Integrator::getInstance();
		
		if ($oApiIntegrator)
		{
			@\header('Content-Type: text/html; charset=utf-8', true);
			
			if (!strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'firefox'))
			{
				@\header('Last-Modified: '.\gmdate('D, d M Y H:i:s').' GMT');
			}
			
			$oSettings =& \Aurora\System\Api::GetSettings();
			if (($oSettings->GetConf('CacheCtrl', true) && isset($_COOKIE['aft-cache-ctrl'])))
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
					$sFrameOptions = $oSettings->GetConf('XFrameOptions', '');
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

		return $this->oApiCalendarManager->processICS($UserId, $Data, $FromEmail);
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
				if ($this->oApiFileCache->moveUploadedFile($sUserPublicId, $sSavedName, $UploadData['tmp_name'], '', $this->GetName()))
				{
					$iImportedCount = $this->oApiCalendarManager->importToCalendarFromIcs(
							$sUserPublicId,
							$sCalendarId, 
							$this->oApiFileCache->generateFullFilePath($sUserPublicId, $sSavedName, '', $this->GetName())
					);

					if (false !== $iImportedCount && -1 !== $iImportedCount)
					{
						$aResponse['ImportedCount'] = $iImportedCount;
					}
					else
					{
						$sError = 'unknown';
					}

					$this->oApiFileCache->clear($sUserPublicId, $sSavedName, '', $this->GetName());
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
						$mResult = $this->oApiCalendarManager->processICS($sUserPublicId, $sData, $sFromEmail);
					}
					catch (\Exception $oEx)
					{
						$mResult = false;
					}
					if (is_array($mResult) && !empty($mResult['Action']) && !empty($mResult['Body']))
					{
						$sTemptFile = md5($sFromEmail . $sData).'.ics';
						if ($this->oApiFileCache->put($sUserPublicId, $sTemptFile, $sData, '', $this->GetName()))
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

	public function onAfterDeleteUser($aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);
		$sUserPublicId = isset($aArgs["User"]) ? $aArgs["User"]->PublicId : null;
		if ($sUserPublicId)
		{
			$aUserCalendars = $this->oApiCalendarManager->getCalendars($sUserPublicId);
			if ($aUserCalendars)
			{
				foreach ($aUserCalendars as $oCalendar)
				{
					if ($oCalendar instanceof \Aurora\Modules\Calendar\Classes\Calendar)
					{
						$this->Decorator()->DeleteCalendar($aArgs["User"]->PublicId, $oCalendar->Id);
					}
				}
			}
		}
	}
}
