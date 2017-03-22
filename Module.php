<?php


namespace Aurora\Modules\Calendar;

class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiCalendarManager = null;
	
	public function init() 
	{
		$this->incClass('helper');
		$this->incClass('calendar');
		$this->incClass('event');
		$this->incClass('parser');

		$this->oApiCalendarManager = $this->GetManager('', 'sabredav');
		$this->AddEntries(array(
				'invite' => 'EntryInvite',
				'calendar-pub' => 'EntryCalendarPub'
			)
		);
		
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
//		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
	}
	
	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return array(
			'AllowAppointments' => true, // AppData.User.CalendarAppointments
			'AllowShare' => true, // AppData.User.CalendarSharing
			'DefaultTab' => '3', // AppData.User.Calendar.CalendarDefaultTab
			'HighlightWorkingDays' => true, // AppData.User.Calendar.CalendarShowWeekEnds
			'HighlightWorkingHours' => true, // AppData.User.Calendar.CalendarShowWorkDay
			'PublicCalendarId' => '', // AppData.CalendarPubHash
			'WeekStartsOn' => '0', // AppData.User.Calendar.CalendarWeekStartsOn
			'WorkdayEnds' => '18', // AppData.User.Calendar.CalendarWorkDayEnds
			'WorkdayStarts' => '9' // AppData.User.Calendar.CalendarWorkDayStarts
		);
	}
	
	/**
	 * @return array
	 */
	public function GetCalendars($IsPublic = false, $PublicCalendarId = '')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mResult = false;
		$mCalendars = false;
		
		if ($IsPublic) 
		{
			$oCalendar = $this->oApiCalendarManager->getPublicCalendar($PublicCalendarId);
			$mCalendars = array($oCalendar);
		} else 
		{
			$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isCalendarSupported($iUserId)) 
			{
				
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
			}
	
			$mCalendars = $this->oApiCalendarManager->getCalendars($iUserId);
		}
		
		if ($mCalendars) 
		{
			
			$mResult['Calendars'] = $mCalendars;
		}
		
		return $mResult;
	}
	
	/**
	 * @return bool
	 */
	public function DownloadCalendar()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oAccount = $this->getDefaultAccountFromParam();
		if ($this->oApiCapabilityManager->isCalendarSupported($oAccount))
		{
			$sRawKey = (string) $this->getParamValue('RawKey', '');
			$aValues = \Aurora\System\Api::DecodeKeyValues($sRawKey);

			if (isset($aValues['CalendarId'])) {
				
				$sCalendarId = $aValues['CalendarId'];

				$sOutput = $this->oApiCalendarManager->exportCalendarToIcs($oAccount, $sCalendarId);
				if (false !== $sOutput) {
					
					header('Pragma: public');
					header('Content-Type: text/calendar');
					header('Content-Disposition: attachment; filename="'.$sCalendarId.'.ics";');
					header('Content-Transfer-Encoding: binary');

					echo $sOutput;
					return true;
				}
			}
		}

		return false;		
	}
	
	/**
	 * @return array
	 */
	public function CreateCalendar($Name, $Description, $Color)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		if (!$this->oApiCapabilityManager->isCalendarSupported($iUserId))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		$mCalendarId = $this->oApiCalendarManager->createCalendar($iUserId, $Name, $Description, 0, $Color);
		if ($mCalendarId)
		{
			$oCalendar = $this->oApiCalendarManager->getCalendar($iUserId, $mCalendarId);
			if ($oCalendar instanceof \CCalendar)
			{
				$mResult = $oCalendar->toResponseArray($iUserId);
			}
		}
		
		return $mResult;
	}	
	
	/**
	 * @return array
	 */
	public function UpdateCalendar($Name, $Description, $Color, $Id)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		if (!$this->oApiCapabilityManager->isCalendarSupported($iUserId))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		return $this->oApiCalendarManager->updateCalendar($iUserId, $Id, $Name, $Description, 0, $Color);
	}	

	/**
	 * @return array
	 */
	public function UpdateCalendarColor($Color, $Id)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		if (!$this->oApiCapabilityManager->isCalendarSupported($iUserId))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		return $this->oApiCalendarManager->updateCalendarColor($iUserId, $Id, $Color);
	}
	
	/**
	 * @return array
	 */
	public function UpdateCalendarShare()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$sCalendarId = $this->getParamValue('Id');
		$bIsPublic = (bool) $this->getParamValue('IsPublic');
		$aShares = @json_decode($this->getParamValue('Shares'), true);
		
		$bShareToAll = (bool) $this->getParamValue('ShareToAll', false);
		$iShareToAllAccess = (int) $this->getParamValue('ShareToAllAccess', \ECalendarPermission::Read);
		
		if (!$this->oApiCapabilityManager->isCalendarSupported($iUserId))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		// Share calendar to all users
		$aShares[] = array(
			'email' => $this->oApiCalendarManager->getTenantUser($iUserId),
			'access' => $bShareToAll ? $iShareToAllAccess : \ECalendarPermission::RemovePermission
		);
		
		// Public calendar
		$aShares[] = array(
			'email' => $this->oApiCalendarManager->getPublicUser(),
			'access' => $bIsPublic ? \ECalendarPermission::Read : \ECalendarPermission::RemovePermission
		);
		
		return $this->oApiCalendarManager->updateCalendarShares($iUserId, $sCalendarId, $aShares);
	}		
	
	/**
	 * @return array
	 */
	public function UpdateCalendarPublic()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();
		$sCalendarId = $this->getParamValue('Id');
		$bIsPublic = (bool) $this->getParamValue('IsPublic');
		
		if (!$this->oApiCapabilityManager->isCalendarSupported($oAccount))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		return $this->oApiCalendarManager->publicCalendar($oAccount, $sCalendarId, $bIsPublic);
	}		

	/**
	 * @return array
	 */
	public function DeleteCalendar()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();
		
		$sCalendarId = $this->getParamValue('Id');
		$mResult = $this->oApiCalendarManager->deleteCalendar($oAccount, $sCalendarId);
		
		return $this->DefaultResponse(__FUNCTION__, $mResult);
	}	
	
	/**
	 * @return array
	 */
	public function GetBaseEvent()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oAccount = $this->getDefaultAccountFromParam();
		if (!$this->oApiCapabilityManager->isCalendarSupported($oAccount))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		$sCalendarId = $this->getParamValue('calendarId');
		$sEventId = $this->getParamValue('uid');
		
		return $this->oApiCalendarManager->getBaseEvent($oAccount, $sCalendarId, $sEventId);
	}	
	
	
	/**
	 * @return array
	 */
	public function GetEvents($CalendarIds, $Start, $End, $IsPublic, $TimezoneOffset, $Timezone, $Expand = true)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mResult = false;
		
		if ($IsPublic)
		{
			$oPublicAccount = $this->oApiCalendarManager->getPublicAccount();
			$oPublicAccount->User->DefaultTimeZone = $TimezoneOffset;
			$mResult = $this->oApiCalendarManager->getEvents($oPublicAccount, $CalendarIds, $Start, $End, $Expand);
		}
		else
		{
			$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
			if (!$this->oApiCapabilityManager->isCalendarSupported($iUserId))
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
			}
			$mResult = $this->oApiCalendarManager->getEvents($iUserId, $CalendarIds, $Start, $End);
		}
		
		return $mResult;
	}	
	
	/**
	 * @return array
	 */
	public function CreateEvent()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();
		if (!$this->oApiCapabilityManager->isCalendarSupported($oAccount))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		$oEvent = new \CEvent();

		$oEvent->IdCalendar = $this->getParamValue('newCalendarId');
		$oEvent->Name = $this->getParamValue('subject');
		$oEvent->Description = $this->getParamValue('description');
		$oEvent->Location = $this->getParamValue('location');
		$oEvent->Start = $this->getParamValue('startTS');
		$oEvent->End = $this->getParamValue('endTS');
		$oEvent->AllDay = (bool) $this->getParamValue('allDay');
		$oEvent->Alarms = @json_decode($this->getParamValue('alarms'), true);
		$oEvent->Attendees = @json_decode($this->getParamValue('attendees'), true);

		$aRRule = @json_decode($this->getParamValue('rrule'), true);
		if ($aRRule)
		{
			$oRRule = new \CRRule($oAccount);
			$oRRule->Populate($aRRule);
			$oEvent->RRule = $oRRule;
		}

		$mResult = $this->oApiCalendarManager->createEvent($oAccount, $oEvent);
		if ($mResult)
		{
			$iStart = $this->getParamValue('selectStart'); 
			$iEnd = $this->getParamValue('selectEnd'); 

			$mResult = $this->oApiCalendarManager->getExpandedEvent($oAccount, $oEvent->IdCalendar, $mResult, $iStart, $iEnd);
		}
		
		return $mResult;
	}
	
	/**
	 * @return array
	 */
	public function UpdateEvent()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		$oAccount = $this->getDefaultAccountFromParam();
		if (!$this->oApiCapabilityManager->isCalendarSupported($oAccount))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}
		
		$sNewCalendarId = $this->getParamValue('newCalendarId'); 
		$oEvent = new \CEvent();

		$oEvent->IdCalendar = $this->getParamValue('calendarId');
		$oEvent->Id = $this->getParamValue('uid');
		$oEvent->Name = $this->getParamValue('subject');
		$oEvent->Description = $this->getParamValue('description');
		$oEvent->Location = $this->getParamValue('location');
		$oEvent->Start = $this->getParamValue('startTS');
		$oEvent->End = $this->getParamValue('endTS');
		$oEvent->AllDay = (bool) $this->getParamValue('allDay');
		$oEvent->Alarms = @json_decode($this->getParamValue('alarms'), true);
		$oEvent->Attendees = @json_decode($this->getParamValue('attendees'), true);
		
		$aRRule = @json_decode($this->getParamValue('rrule'), true);
		if ($aRRule)
		{
			$oRRule = new \CRRule($oAccount);
			$oRRule->Populate($aRRule);
			$oEvent->RRule = $oRRule;
		}
		
		$iAllEvents = (int) $this->getParamValue('allEvents');
		$sRecurrenceId = $this->getParamValue('recurrenceId');
		
		if ($iAllEvents && $iAllEvents === 1)
		{
			$mResult = $this->oApiCalendarManager->updateExclusion($oAccount, $oEvent, $sRecurrenceId);
		}
		else
		{
			$mResult = $this->oApiCalendarManager->updateEvent($oAccount, $oEvent);
			if ($mResult && $sNewCalendarId !== $oEvent->IdCalendar)
			{
				$mResult = $this->oApiCalendarManager->moveEvent($oAccount, $oEvent->IdCalendar, $sNewCalendarId, $oEvent->Id);
				$oEvent->IdCalendar = $sNewCalendarId;
			}
		}
		if ($mResult)
		{
			$iStart = $this->getParamValue('selectStart'); 
			$iEnd = $this->getParamValue('selectEnd'); 

			$mResult = $this->oApiCalendarManager->getExpandedEvent($oAccount, $oEvent->IdCalendar, $oEvent->Id, $iStart, $iEnd);
		}
			
		return $mResult;
	}	
	
	/**
	 * @return array
	 */
	public function DeleteEvent()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$mResult = false;
		$oAccount = $this->getDefaultAccountFromParam();
		
		$sCalendarId = $this->getParamValue('calendarId');
		$sId = $this->getParamValue('uid');

		$iAllEvents = (int) $this->getParamValue('allEvents');
		
		if ($iAllEvents && $iAllEvents === 1)
		{
			$oEvent = new \CEvent();
			$oEvent->IdCalendar = $sCalendarId;
			$oEvent->Id = $sId;
			
			$sRecurrenceId = $this->getParamValue('recurrenceId');

			$mResult = $this->oApiCalendarManager->updateExclusion($oAccount, $oEvent, $sRecurrenceId, true);
		}
		else
		{
			$mResult = $this->oApiCalendarManager->deleteEvent($oAccount, $sCalendarId, $sId);
		}
		
		return $mResult;
	}	
	
	/**
	 * @return array
	 */
	public function AddEventsFromFile()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		$mResult = false;

		if (!$this->oApiCapabilityManager->isCalendarSupported($oAccount))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CalendarsNotAllowed);
		}

		$sCalendarId = (string) $this->getParamValue('CalendarId', '');
		$sTempFile = (string) $this->getParamValue('File', '');

		if (empty($sCalendarId) || empty($sTempFile))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		$oApiFileCache = /* @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager */ \Aurora\System\Api::GetSystemManager('Filecache');
		$sData = $oApiFileCache->get($oAccount, $sTempFile);
		if (!empty($sData))
		{
			$mCreateEventResult = $this->oApiCalendarManager->createEventFromRaw($oAccount, $sCalendarId, null, $sData);
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
	 * @return array
	 */
	public function SetAppointmentAction()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = null; //$this->getAccountFromParam(); TODO:
		$oDefaultAccount = null; //$this->getDefaultAccountFromParam(); TODO
		
		$mResult = false;

		$sCalendarId = (string) $this->getParamValue('CalendarId', '');
		$sEventId = (string) $this->getParamValue('EventId', '');
		$sTempFile = (string) $this->getParamValue('File', '');
		$sAction = (string) $this->getParamValue('AppointmentAction', '');
		$sAttendee = (string) $this->getParamValue('Attendee', '');
		
		if (empty($sAction) || empty($sCalendarId))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}

		if ($this->oApiCapabilityManager->isCalendarAppointmentsSupported($oDefaultAccount))
		{
			$sData = '';
			if (!empty($sEventId))
			{
				$aEventData =  $this->oApiCalendarManager->getEvent($oDefaultAccount, $sCalendarId, $sEventId);
				if (isset($aEventData) && isset($aEventData['vcal']) && $aEventData['vcal'] instanceof \Sabre\VObject\Component\VCalendar)
				{
					$oVCal = $aEventData['vcal'];
					$oVCal->METHOD = 'REQUEST';
					$sData = $oVCal->serialize();
				}
			}
			else if (!empty($sTempFile))
			{
				$oApiFileCache = /* @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager */ \Aurora\System\Api::GetSystemManager('Filecache');
				$sData = $oApiFileCache->get($oAccount, $sTempFile);
			}
			if (!empty($sData))
			{
				$mProcessResult = $this->oApiCalendarManager->appointmentAction($oDefaultAccount, $sAttendee, $sAction, $sCalendarId, $sData);
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
								case \EDateFormat::DDMMYYYY:
									$sDateFormat = 'd/m/Y';
									break;
								case \EDateFormat::DD_MONTH_YYYY:
									$sDateFormat = 'd/m/Y';
									break;
								default:
									$sDateFormat = 'm/d/Y';
									break;
							}
							switch ($oAccountOrganizer->User->TimeFormat)
							{
								case \ETimeFormat::F24:
									$sTimeFormat = 'H:i';
									break;
								case \EDateFormat::DD_MONTH_YYYY:
									\ETimeFormat::F12;
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
							\Aurora\System\Api::Log('Empty template.', \ELogLevel::Error);
						}
					}
					else
					{
						\Aurora\System\Api::Log('Event not found.', \ELogLevel::Error);
					}
				}
				else
				{
					\Aurora\System\Api::Log('Calendar not found.', \ELogLevel::Error);
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
		
		$oApiIntegrator = \Aurora\System\Api::GetSystemManager('integrator');
		
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
	
	public function UpdateAttendeeStatus()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getAccountFromParam();
		
		$mResult = false;

		$sTempFile = (string) $this->getParamValue('File', '');
		$sFromEmail = (string) $this->getParamValue('FromEmail', '');
		
		if (empty($sTempFile) || empty($sFromEmail))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
		}
		if ($this->oApiCapabilityManager->isCalendarAppointmentsSupported($oAccount))
		{
			$oApiFileCache = /* @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager */ \Aurora\System\Api::GetSystemManager('Filecache');
			$sData = $oApiFileCache->get($oAccount, $sTempFile);
			if (!empty($sData))
			{
				$mResult = $this->oApiCalendarManager->processICS($oAccount, $sData, $sFromEmail, true);
			}
		}

		return $mResult;		
		
	}	
	
	public function ProcessICS()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getParamValue('Account', null);
		$sData = (string) $this->getParamValue('Data', '');
		$sFromEmail = (string) $this->getParamValue('FromEmail', '');

		return $this->oApiCalendarManager->processICS($oAccount, $sData, $sFromEmail);
	}
	
	/**
	 * @return array
	 */
	public function UploadCalendars()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();
		
		$aFileData = $this->getParamValue('FileData', null);
		$sAdditionalData = $this->getParamValue('AdditionalData', '{}');
		$aAdditionalData = @json_decode($sAdditionalData, true);
		
		$sCalendarId = isset($aAdditionalData['CalendarID']) ? $aAdditionalData['CalendarID'] : '';

		$sError = '';
		$aResponse = array(
			'ImportedCount' => 0
		);

		if (is_array($aFileData))
		{
			$bIsIcsExtension  = strtolower(pathinfo($aFileData['name'], PATHINFO_EXTENSION)) === 'ics';

			if ($bIsIcsExtension)
			{
				$oApiFileCacheManager = \Aurora\System\Api::GetSystemManager('Filecache');
				$sSavedName = 'import-post-' . md5($aFileData['name'] . $aFileData['tmp_name']);
				if ($oApiFileCacheManager->moveUploadedFile($oAccount, $sSavedName, $aFileData['tmp_name'])) {
					
					$iImportedCount = $this->oApiCalendarManager->importToCalendarFromIcs(
							$oAccount, 
							$sCalendarId, 
							$oApiFileCacheManager->generateFullFilePath($oAccount, $sSavedName)
					);

					if (false !== $iImportedCount && -1 !== $iImportedCount) {
						$aResponse['ImportedCount'] = $iImportedCount;
					} else {
						$sError = 'unknown';
					}

					$oApiFileCacheManager->clear($oAccount, $sSavedName);
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
	
//	public function onExtendMessageData($oAccount, &$oMessage, $aData)
//	{
//		$oApiCapa = /* @var \Aurora\System\Managers\Capability\Manager */ $this->oApiCapabilityManager;
//		$oApiFileCache = /* @var \Aurora\System\Managers\Filecache\Manager */\Aurora\System\Api::GetSystemManager('Filecache');
//		$sFromEmail = '';
//		$oFromCollection = $oMessage->getFrom();
//		if ($oFromCollection && 0 < $oFromCollection->Count())
//		{
//			$oFrom =& $oFromCollection->GetByIndex(0);
//			if ($oFrom)
//			{
//				$sFromEmail = trim($oFrom->GetEmail());
//			}
//		}
//		foreach ($aData as $aDataItem)
//		{
//			if ($aDataItem['Part'] instanceof \MailSo\Imap\BodyStructure && $aDataItem['Part']->ContentType() === 'text/calendar')
//			{
//				$sData = $aDataItem['Data'];
//				if (!empty($sData) && $oApiCapa->isCalendarSupported($oAccount))
//				{
//					$mResult = $this->oApiCalendarManager->processICS($oAccount, $sData, $sFromEmail);
//					if (is_array($mResult) && !empty($mResult['Action']) && !empty($mResult['Body']))
//					{
//						$sTemptFile = md5($mResult['Body']).'.ics';
//						if ($oApiFileCache && $oApiFileCache->put($oAccount, $sTemptFile, $mResult['Body']))
//						{
//							$oIcs = CApiMailIcs::createInstance();
//
//							$oIcs->Uid = $mResult['UID'];
//							$oIcs->Sequence = $mResult['Sequence'];
//							$oIcs->File = $sTemptFile;
//							$oIcs->Attendee = isset($mResult['Attendee']) ? $mResult['Attendee'] : null;
//							$oIcs->Type = ($oApiCapa->isCalendarAppointmentsSupported($oAccount)) ? $mResult['Action'] : 'SAVE';
//							$oIcs->Location = !empty($mResult['Location']) ? $mResult['Location'] : '';
//							$oIcs->Description = !empty($mResult['Description']) ? $mResult['Description'] : '';
//							$oIcs->When = !empty($mResult['When']) ? $mResult['When'] : '';
//							$oIcs->CalendarId = !empty($mResult['CalendarId']) ? $mResult['CalendarId'] : '';
//
//							$oMessage->addExtend('ICAL', $oIcs);
//						}
//						else
//						{
//							\Aurora\System\Api::Log('Can\'t save temp file "'.$sTemptFile.'"', ELogLevel::Error);
//						}
//					}
//				}				
//			}
//		}
//	}
	
    public function onGetMobileSyncInfo($aArgs, &$mResult)
	{
		$oDavModule = \Aurora\System\Api::GetModuleDecorator('Dav');
		$aCalendars = $this->GetCalendars();
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
