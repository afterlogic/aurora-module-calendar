<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AfterLogic Software License
 *
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar;

/**
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
				'invite' => 'EntryInvite',
				'calendar-pub' => 'EntryCalendarPub',
				'calendar-download' => 'EntryCalendarDownload'
			)
		);

		$this->extendObject(
			'Aurora\Modules\Core\Classes\User', 
			array(
				'HighlightWorkingDays'	=> array('bool', $this->getConfig('HighlightWorkingDays', false)),
				'HighlightWorkingHours'	=> array('bool', $this->getConfig('HighlightWorkingHours', false)),
				'WorkdayStarts'			=> array('int', $this->getConfig('WorkdayStarts', false)),
				'WorkdayEnds'			=> array('int', $this->getConfig('WorkdayEnds', false)),
				'WeekStartsOn'			=> array('int', $this->getConfig('WeekStartsOn', false)),
				'DefaultTab'			=> array('int', $this->getConfig('DefaultTab', false)),
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
			'AllowAppointments' => $this->getConfig('AllowAppointments', true),
			'AllowShare' => $this->getConfig('AllowShare', true),
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
			$oCalendar = $this->oApiCalendarManager->populateCalendarShares($UserId, $oCalendar);
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;
		$mCalendars = false;
		
		if ($IsPublic) 
		{
			$oCalendar = $this->oApiCalendarManager->getPublicCalendar($PublicCalendarId);
			$mCalendars = array($oCalendar);
		}
		else 
		{
			$mCalendars = $this->oApiCalendarManager->getCalendars($UUID);
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
		
		$sUserUUID = \Aurora\System\Api::getUserUUIDById(\Aurora\System\Api::getAuthenticatedUserId());

		if (isset($aValues['CalendarId']))
		{
			$sCalendarId = $aValues['CalendarId'];
			$sOutput = $this->oApiCalendarManager->exportCalendarToIcs($sUserUUID, $sCalendarId);
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;
		
		$mCalendarId = $this->oApiCalendarManager->createCalendar($UUID, $Name, $Description, 0, $Color);
		if ($mCalendarId)
		{
			$oCalendar = $this->oApiCalendarManager->getCalendar($UUID, $mCalendarId);
			if ($oCalendar instanceof \Aurora\Modules\Calendar\Classes\Calendar)
			{
				$mResult = $oCalendar->toResponseArray($UUID);
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		return $this->oApiCalendarManager->updateCalendar($UUID, $Id, $Name, $Description, 0, $Color);
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		return $this->oApiCalendarManager->updateCalendarColor($UUID, $Id, $Color);
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$aShares = $Shares;
		
		// Share calendar to all users
		$aShares[] = array(
			'email' => $this->oApiCalendarManager->getTenantUser($UserId),
			'access' => $ShareToAll ? $ShareToAllAccess : \Aurora\Modules\Calendar\Enums\Permission::RemovePermission
		);
		
		// Public calendar
		$aShares[] = array(
			'email' => $this->oApiCalendarManager->getPublicUser(),
			'access' => $IsPublic ? \Aurora\Modules\Calendar\Enums\Permission::Read : \Aurora\Modules\Calendar\Enums\Permission::RemovePermission
		);
		
		return $this->oApiCalendarManager->updateCalendarShares($UUID, $Id, $aShares);
	}		
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $Id
	 * @param boolean $IsPublic
	 * @return array|boolean
	 */
	public function UpdateCalendarPublic($UserId, $Id, $IsPublic)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		return $this->oApiCalendarManager->publicCalendar($UUID, $Id, $IsPublic);
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		return $this->oApiCalendarManager->deleteCalendar($UUID, $Id);
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		return $this->oApiCalendarManager->getBaseEvent($UUID, $calendarId, $uid);
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
	public function GetEvents($UserId, $CalendarIds, $Start, $End, $IsPublic, $Expand = true)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;
		
		if ($IsPublic)
		{
			$oPublicAccount = $this->oApiCalendarManager->getPublicAccount();
			$mResult = $this->oApiCalendarManager->getEvents($oPublicAccount, $CalendarIds, $Start, $End, $Expand);
		}
		else
		{
			$mResult = $this->oApiCalendarManager->getEvents($UUID, $CalendarIds, $Start, $End);
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
	public function GetTasks($UserId, $CalendarIds)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		
		$mResult = $this->oApiCalendarManager->getTasks($UUID, $CalendarIds);
		
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
			$endTS, $allDay, $alarms, $attendees, $rrule, $selectStart, $selectEnd, $type = 'event', $status = false)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
		$oEvent->IdCalendar = $newCalendarId;
		$oEvent->Name = $subject;
		$oEvent->Description = $description;
		$oEvent->Location = $location;
		$oEvent->Start = $startTS;
		$oEvent->End = $endTS;
		$oEvent->AllDay = $allDay;
		$oEvent->Alarms = @json_decode($alarms, true);
		$oEvent->Attendees = @json_decode($attendees, true);
		$oEvent->Type = $type;
		$oEvent->Status = $status;
		
		$aRRule = @json_decode($rrule, true);
		if ($aRRule)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$oRRule = new \Aurora\Modules\Calendar\Classes\RRule($oUser);
			$oRRule->Populate($aRRule);
			$oEvent->RRule = $oRRule;
		}

		$mResult = $this->oApiCalendarManager->createEvent($UUID, $oEvent);
		if ($mResult)
		{
			$mResult = $this->oApiCalendarManager->getExpandedEvent($UUID, $oEvent->IdCalendar, $mResult, $selectStart, $selectEnd);
		}
		
		return $mResult;
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
		$oEvent->IdCalendar = $CalendarId;
		$oEvent->Name = $Subject;
		$oEvent->Start = \time();
		$oEvent->End = \time();
		$oEvent->Type = 'todo';
		
		return $this->oApiCalendarManager->createEvent($UUID, $oEvent);
	}
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $CalendarId
	 * @param string $Subject
	 * @param string $Sescription
	 * @return array|boolean
	 */
	public function UpdateTask($UserId, $CalendarId, $TaskId, $Subject, $Status)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
		$oEvent->IdCalendar = $CalendarId;
		$oEvent->Id = $TaskId;
		$oEvent->Name = $Subject;
		$oEvent->Start = \time();
		$oEvent->End = \time();
		$oEvent->Type = 'todo';
		$oEvent->Status = $Status ? 'COMPLETED' : '';
		
		return $this->oApiCalendarManager->updateEvent($UUID, $oEvent);
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
			$selectStart, $selectEnd, $type, $status)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;
		
		$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
		$oEvent->IdCalendar = $calendarId;
		$oEvent->Id = $uid;
		$oEvent->Name = $subject;
		$oEvent->Description = $description;
		$oEvent->Location = $location;
		$oEvent->Start = $startTS;
		$oEvent->End = $endTS;
		$oEvent->AllDay = $allDay;
		$oEvent->Alarms = @json_decode($alarms, true);
		$oEvent->Attendees = @json_decode($attendees, true);
		$oEvent->Type = $type;
		if (!empty($status))
		{
			$oEvent->Status = $status;
		}
		
		$aRRule = @json_decode($rrule, true);
		if ($aRRule)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$oRRule = new \Aurora\Modules\Calendar\Classes\RRule($oUser);
			$oRRule->Populate($aRRule);
			$oEvent->RRule = $oRRule;
		}
		
		if ($allEvents === 1)
		{
			$mResult = $this->oApiCalendarManager->updateExclusion($UUID, $oEvent, $recurrenceId);
		}
		else
		{
			$mResult = $this->oApiCalendarManager->updateEvent($UUID, $oEvent);
			if ($mResult && $newCalendarId !== $oEvent->IdCalendar)
			{
				$mResult = $this->oApiCalendarManager->moveEvent($UUID, $oEvent->IdCalendar, $newCalendarId, $oEvent->Id);
				$oEvent->IdCalendar = $newCalendarId;
			}
		}
		if ($mResult)
		{
			$mResult = $this->oApiCalendarManager->getExpandedEvent($UUID, $oEvent->IdCalendar, $oEvent->Id, $selectStart, $selectEnd);
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;
		
		if ($allEvents === 1)
		{
			$oEvent = new \Aurora\Modules\Calendar\Classes\Event();
			$oEvent->IdCalendar = $calendarId;
			$oEvent->Id = $uid;
			$mResult = $this->oApiCalendarManager->updateExclusion($UUID, $oEvent, $recurrenceId, true);
		}
		else
		{
			$mResult = $this->oApiCalendarManager->deleteEvent($UUID, $calendarId, $uid);
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;

		if (empty($CalendarId) || empty($File))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$sData = $this->oApiFileCache->get($UUID, $File);
		if (!empty($sData))
		{
			$mCreateEventResult = $this->oApiCalendarManager->createEventFromRaw($UUID, $CalendarId, null, $sData);
			if ($mCreateEventResult)
			{
				$mResult = array(
					'Uid' => (string) $mCreateEventResult
				);
			}
		}

		return $mResult;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $CalendarId
	 * @param string $EventId
	 * @param string $File
	 * @param string $AppointmentAction
	 * @param string $Attendee
	 * @return array|boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function SetAppointmentAction($UserId, $CalendarId, $EventId, $File, $AppointmentAction, $Attendee)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;

		if (empty($AppointmentAction) || empty($CalendarId))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		if (/*$this->oApiCapabilityManager->isCalendarAppointmentsSupported($UserId)*/ true) // TODO
		{
			$sData = '';
			if (!empty($EventId))
			{
				$aEventData =  $this->oApiCalendarManager->getEvent($UUID, $CalendarId, $EventId);
				if (isset($aEventData) && isset($aEventData['vcal']) && $aEventData['vcal'] instanceof \Sabre\VObject\Component\VCalendar)
				{
					$oVCal = $aEventData['vcal'];
					$oVCal->METHOD = 'REQUEST';
					$sData = $oVCal->serialize();
				}
			}
			else if (!empty($File))
			{
				$sData = $this->oApiFileCache->get($UUID, $File);
			}
			if (!empty($sData))
			{
				$mProcessResult = $this->oApiCalendarManager->appointmentAction($UUID, $Attendee, $AppointmentAction, $CalendarId, $sData);
				if ($mProcessResult)
				{
					$mResult = array(
						'Uid' => $mProcessResult
					);
				}
			}
		}

		return $mResult;
	}	
	
	public function EntryInvite()
	{
		$sResult = '';
		$aInviteValues = \Aurora\System\Api::DecodeKeyValues($this->oHttp->GetQuery('invite'));

//		$oApiUsersManager = \Aurora\System\Api::GetSystemManager('users');
		if (isset($aInviteValues['organizer']))
		{
			$oAccountOrganizer = $oApiUsersManager->getAccountByEmail($aInviteValues['organizer']);
			if (isset($oAccountOrganizer, $aInviteValues['attendee'], $aInviteValues['calendarId'], $aInviteValues['eventId'], $aInviteValues['action']))
			{
				$oCalendar = $this->oApiCalendarManager->getCalendar($oAccountOrganizer, $aInviteValues['calendarId']);
				if ($oCalendar)
				{
					$oEvent = $this->oApiCalendarManager->getEvent($oAccountOrganizer, $aInviteValues['calendarId'], $aInviteValues['eventId']);
					if ($oEvent && is_array($oEvent) && 0 < count ($oEvent) && isset($oEvent[0]))
					{
						if (is_string($sResult))
						{
							$sResult = file_get_contents($this->GetPath().'/templates/CalendarEventInviteExternal.html');

							$dt = new \DateTime();
							$dt->setTimestamp($oEvent[0]['startTS']);
							if (!$oEvent[0]['allDay'])
							{
								$sDefaultTimeZone = new \DateTimeZone($oAccountOrganizer->getDefaultStrTimeZone());
								$dt->setTimezone($sDefaultTimeZone);
							}

							$sAction = $aInviteValues['action'];
							$sActionColor = 'green';
							$sActionText = '';
							switch (strtoupper($sAction))
							{
								case 'ACCEPTED':
									$sActionColor = 'green';
									$sActionText = 'Accepted';
									break;
								case 'DECLINED':
									$sActionColor = 'red';
									$sActionText = 'Declined';
									break;
								case 'TENTATIVE':
									$sActionColor = '#A0A0A0';
									$sActionText = 'Tentative';
									break;
							}

							$sDateFormat = 'm/d/Y';
							$sTimeFormat = 'h:i A';
							switch ($oAccountOrganizer->User->DateFormat)
							{
								case \Aurora\System\Enums\DateFormat::DDMMYYYY:
									$sDateFormat = 'd/m/Y';
									break;
								case \Aurora\System\Enums\DateFormat::DD_MONTH_YYYY:
									$sDateFormat = 'd/m/Y';
									break;
								default:
									$sDateFormat = 'm/d/Y';
									break;
							}
							switch ($oAccountOrganizer->User->TimeFormat)
							{
								case \Aurora\System\Enums\TimeFormat::F24:
									$sTimeFormat = 'H:i';
									break;
								case \Aurora\System\Enums\DateFormat::DD_MONTH_YYYY:
									\Aurora\System\Enums\TimeFormat::F12;
									$sTimeFormat = 'h:i A';
									break;
								default:
									$sTimeFormat = 'h:i A';
									break;
							}
							$sDateTime = $dt->format($sDateFormat.' '.$sTimeFormat);

							$mResult = array(
								'{{COLOR}}' => $oCalendar->Color,
								'{{EVENT_NAME}}' => $oEvent[0]['subject'],
								'{{EVENT_BEGIN}}' => ucfirst(\Aurora\System\Api::ClientI18N('REMINDERS/EVENT_BEGIN', $oAccountOrganizer)),
								'{{EVENT_DATE}}' => $sDateTime,
								'{{CALENDAR}}' => ucfirst(\Aurora\System\Api::ClientI18N('REMINDERS/CALENDAR', $oAccountOrganizer)),
								'{{CALENDAR_NAME}}' => $oCalendar->DisplayName,
								'{{EVENT_DESCRIPTION}}' => $oEvent[0]['description'],
								'{{EVENT_ACTION}}' => $sActionText,
								'{{ACTION_COLOR}}' => $sActionColor,
							);

							$sResult = strtr($sResult, $mResult);
						}
						else
						{
							\Aurora\System\Api::Log('Empty template.', \Aurora\System\Enums\LogLevel::Error);
						}
					}
					else
					{
						\Aurora\System\Api::Log('Event not found.', \Aurora\System\Enums\LogLevel::Error);
					}
				}
				else
				{
					\Aurora\System\Api::Log('Calendar not found.', \Aurora\System\Enums\LogLevel::Error);
				}
				$sAttendee = $aInviteValues['attendee'];
				if (!empty($sAttendee))
				{
					$this->oApiCalendarManager->updateAppointment($oAccountOrganizer, $aInviteValues['calendarId'], $aInviteValues['eventId'], $sAttendee, $aInviteValues['action']);
				}
			}
		}
		return $sResult;
	}
	
	public function EntryCalendarPub()
	{
		$sResult = '';
		
		$oApiIntegrator = new \Aurora\Modules\Core\Managers\Integrator();
		
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
				setcookie('aft-cache-ctrl', '', time() - 3600);
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
	 * @param string $File
	 * @param string $FromEmail
	 * @return boolean
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateAttendeeStatus($UserId, $File, $FromEmail)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;

		if (empty($File) || empty($FromEmail))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		
		if (/*$this->oApiCapabilityManager->isCalendarAppointmentsSupported($UserId)*/ true) // TODO
		{
			$sData = $this->oApiFileCache->get($UUID, $File);
			if (!empty($sData))
			{
				$mResult = $this->oApiCalendarManager->processICS($UserId, $sData, $FromEmail, true);
			}
		}

		return $mResult;		
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
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
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
				if ($this->oApiFileCache->moveUploadedFile($UUID, $sSavedName, $UploadData['tmp_name']))
				{
					$iImportedCount = $this->oApiCalendarManager->importToCalendarFromIcs(
							$UUID,
							$sCalendarId, 
							$this->oApiFileCache->generateFullFilePath($UUID, $sSavedName)
					);

					if (false !== $iImportedCount && -1 !== $iImportedCount)
					{
						$aResponse['ImportedCount'] = $iImportedCount;
					}
					else
					{
						$sError = 'unknown';
					}

					$this->oApiFileCache->clear($UUID, $sSavedName);
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
			}
		}
	}
	
	public function onExtendMessageData($aData, &$oMessage)
	{
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$UUID = \Aurora\System\Api::getUserUUIDById($oUser->EntityId);
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
					$mResult = $this->oApiCalendarManager->processICS($UUID, $sData, $sFromEmail);
					if (is_array($mResult) && !empty($mResult['Action']) && !empty($mResult['Body']))
					{
						$sTemptFile = md5($mResult['Body']).'.ics';
						if ($this->oApiFileCache->put($UUID, $sTemptFile, $mResult['Body']))
						{
							$oIcs = \Aurora\Modules\Mail\Classes\Ics::createInstance();

							$oIcs->Uid = $mResult['UID'];
							$oIcs->Sequence = $mResult['Sequence'];
							$oIcs->File = $sTemptFile;
							$oIcs->Attendee = isset($mResult['Attendee']) ? $mResult['Attendee'] : null;
							
							// TODO
							$oIcs->Type = (/*$oApiCapa->isCalendarAppointmentsSupported($oUser->EntityId)*/ true) ? $mResult['Action'] : 'SAVE';
							
							$oIcs->Location = !empty($mResult['Location']) ? $mResult['Location'] : '';
							$oIcs->Description = !empty($mResult['Description']) ? $mResult['Description'] : '';
							$oIcs->When = !empty($mResult['When']) ? $mResult['When'] : '';
							$oIcs->CalendarId = !empty($mResult['CalendarId']) ? $mResult['CalendarId'] : '';

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
		$aCalendars = $this->GetCalendars($iUserId);
		if (isset($aCalendars['Calendars']) && is_array($aCalendars['Calendars']) && 0 < count($aCalendars['Calendars']))
		{
			foreach($aCalendars['Calendars'] as $oCalendar)
			{
				if ($oCalendar instanceof \Aurora\Modules\Calendar\Classes\Calendar)
				{
					$mResult['Dav']['Calendars'][] = array(
						'Name' => $oCalendar->DisplayName,
						'Url' => $oDavModule->GetServerUrl().$oCalendar->Url
					);
				}
			}
		}	
	}

	public function onAfterDeleteUser($aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

		$aUserCalendars = isset($aArgs["UUID"]) ? $this->GetCalendars($aArgs["UUID"]) : [];
		if (isset($aUserCalendars["Calendars"]))
		{
			foreach ($aUserCalendars["Calendars"] as $oCalendar)
			{
				if ($oCalendar instanceof \Aurora\Modules\Calendar\Classes\Calendar)
				{
					$this->DeleteCalendar($aArgs["UUID"], $oCalendar->Id);
				}
			}
		}
	}
}
