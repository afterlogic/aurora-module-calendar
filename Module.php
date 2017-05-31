<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Calendar;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiCalendarManager = null;
	public $oApiFileCache = null;
	
	public function init() 
	{
		$this->incClass('helper');
		$this->incClass('enum');
		$this->incClass('calendar');
		$this->incClass('event');
		$this->incClass('parser');

		$this->oApiCalendarManager = new Manager('', $this);
		$this->oApiFileCache = new \Aurora\System\Managers\Filecache\Manager();
		
		$this->AddEntries(array(
				'invite' => 'EntryInvite',
				'calendar-pub' => 'EntryCalendarPub'
			)
		);
		
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
	}
	
	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		return array(
			'AllowAppointments' => $this->getConfig('AllowAppointments', true),
			'AllowShare' => $this->getConfig('AllowShare', true),
			'DefaultTab' => $this->getConfig('DefaultTab', 3),
			'HighlightWorkingDays' => $this->getConfig('HighlightWorkingDays', true),
			'HighlightWorkingHours' => $this->getConfig('HighlightWorkingHours', true),
			'PublicCalendarId' => '',
			'WeekStartsOn' => $this->getConfig('WeekStartsOn', 0),
			'WorkdayEnds' => $this->getConfig('WorkdayEnds', 18),
			'WorkdayStarts' => $this->getConfig('WorkdayStarts', 9),
		);
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
	 * @param int $UserId
	 * @param string $RawKey
	 * @return boolean
	 */
	public function DownloadCalendar($UserId, $RawKey)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$aValues = \Aurora\System\Api::DecodeKeyValues($RawKey);

		if (isset($aValues['CalendarId']))
		{
			$sCalendarId = $aValues['CalendarId'];
			$sOutput = $this->oApiCalendarManager->exportCalendarToIcs($UserId, $sCalendarId);
			if (false !== $sOutput)
			{
				header('Pragma: public');
				header('Content-Type: text/calendar');
				header('Content-Disposition: attachment; filename="' . $sCalendarId . '.ics";');
				header('Content-Transfer-Encoding: binary');
				echo $sOutput;
				return true;
			}
		}

		return false;		
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
			if ($oCalendar instanceof \CCalendar)
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
	public function UpdateCalendarShare($UserId, $Id, $IsPublic, $Shares, $ShareToAll = false, $ShareToAllAccess = \ECalendarPermission::Read)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$aShares = $Shares;
		
		// Share calendar to all users
		$aShares[] = array(
			'email' => $this->oApiCalendarManager->getTenantUser($UserId),
			'access' => $ShareToAll ? $ShareToAllAccess : \ECalendarPermission::RemovePermission
		);
		
		// Public calendar
		$aShares[] = array(
			'email' => $this->oApiCalendarManager->getPublicUser(),
			'access' => $IsPublic ? \ECalendarPermission::Read : \ECalendarPermission::RemovePermission
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
			$endTS, $allDay, $alarms, $attendees, $rrule, $selectStart, $selectEnd)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$oEvent = new \CEvent();
		$oEvent->IdCalendar = $newCalendarId;
		$oEvent->Name = $subject;
		$oEvent->Description = $description;
		$oEvent->Location = $location;
		$oEvent->Start = $startTS;
		$oEvent->End = $endTS;
		$oEvent->AllDay = $allDay;
		$oEvent->Alarms = @json_decode($alarms, true);
		$oEvent->Attendees = @json_decode($attendees, true);

		$aRRule = @json_decode($rrule, true);
		if ($aRRule)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$oRRule = new \CRRule($oUser);
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
			$selectStart, $selectEnd)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$UUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$mResult = false;
		
		$oEvent = new \CEvent();
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
		
		$aRRule = @json_decode($rrule, true);
		if ($aRRule)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$oRRule = new \CRRule($oUser);
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
			$oEvent = new \CEvent();
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
			if ($oCoreClientModule instanceof \Aurora\System\Module\AbstractModule) {
				$sResult = file_get_contents($oCoreClientModule->GetPath().'/templates/Index.html');
				if (is_string($sResult)) {
					$oSettings =& \Aurora\System\Api::GetSettings();
					$sFrameOptions = $oSettings->GetConf('XFrameOptions', '');
					if (0 < \strlen($sFrameOptions)) {
						@\header('X-Frame-Options: '.$sFrameOptions);
					}
					
					$sAuthToken = isset($_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY]) ? $_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY] : '';
					$sResult = strtr($sResult, array(
						'{{AppVersion}}' => AURORA_APP_VERSION,
						'{{IntegratorDir}}' => $oApiIntegrator->isRtl() ? 'rtl' : 'ltr',
						'{{IntegratorLinks}}' => $oApiIntegrator->buildHeadersLink(),
						'{{IntegratorBody}}' => $oApiIntegrator->buildBody('-calendar-pub')
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
							$oIcs = \CApiMailIcs::createInstance();

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
				if ($oCalendar instanceof \CCalendar)
				{
					$mResult['Dav']['Calendars'][] = array(
						'Name' => $oCalendar->DisplayName,
						'Url' => $oDavModule->GetServerUrl().$oCalendar->Url
					);
				}
			}
		}	
	}	
	
}
