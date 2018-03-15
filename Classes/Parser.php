<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AfterLogic Software License
 *
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

/**
 * @internal
 * 
 * @package Calendar
 * @subpackage Classes
 */
class Parser
{
	/**
	 * @param string $sUUID
	 * @param \Aurora\Modules\Calendar\Classes\Calendar $oCalendar
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 * @param \Sabre\VObject\Component\VCalendar $oVCalOriginal Default value is **null**.
	 *
	 * @return array
	 */
	public static function parseEvent($sUUID, $oCalendar, $oVCal, $oVCalOriginal = null)
	{
		$aResult = array();
		$aRules = array();
		$aExcludedRecurrences = array();

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUUID);
		if (isset($oVCalOriginal))
		{
			$aRules = \Aurora\Modules\Calendar\Classes\Parser::getRRules($sUUID, $oVCalOriginal);
			$aExcludedRecurrences = \Aurora\Modules\Calendar\Classes\Parser::getExcludedRecurrences($oVCalOriginal);
		}

		$VComponent = null;
		$sType = 'event';
		if (isset($oVCal->VEVENT))
		{
			$VComponent = $oVCal->VEVENT;
		}
		else if (isset($oVCal->VTODO))
		{
			$VComponent = $oVCal->VTODO;
			$sType = 'todo';
		}
		
		if (isset($oVCal, $VComponent) && ($oUser instanceof \Aurora\Modules\Core\Classes\User || $oCalendar->IsPublic))
		{
			foreach ($VComponent as $oVEvent)
			{
				$sOwnerEmail = $oCalendar->Owner;
				$aEvent = array();
				
				if (isset($oVEvent, $oVEvent->UID))
				{
					$sUid = (string)$oVEvent->UID;
					$sRecurrenceId = \Aurora\Modules\Calendar\Classes\Helper::getRecurrenceId($oVEvent);

					$sId = $sUid . '-' . $sRecurrenceId;
					$aEvent['type'] = $sType;
					
					if (array_key_exists($sId, $aExcludedRecurrences))
					{
						$oVEvent = $aExcludedRecurrences[$sId];
						$aEvent['excluded'] = true;
					}

					$bIsAppointment = false;
					$aEvent['attendees'] = array();
					// TODO
					if (1 != 1 && isset($oVEvent->ATTENDEE))
					{
						$aEvent['attendees'] = self::parseAttendees($oVEvent);

						if (isset($oVEvent->ORGANIZER))
						{
							$sOwnerEmail = str_replace('mailto:', '', strtolower((string)$oVEvent->ORGANIZER));
						}
						$bIsAppointment = ($oUser instanceof \Aurora\Modules\Core\Classes\User && $sOwnerEmail !== $oUser->PublicId);
					}
					
//					$oOwner = $ApiUsersManager->getAccountByEmail($sOwnerEmail);
//					$sOwnerName = ($oOwner) ? $oOwner->FriendlyName : '';
					$sOwnerName = '';
					$bAllDay = (isset($oVEvent->DTSTART) && !$oVEvent->DTSTART->hasTime());
					if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
					{
						$sOwnerName  = !empty($oUser->Name) ? $oUser->Name : $oUser->PublicId;
						$sTimeZone = ($bAllDay) ? 'UTC' : $oUser->DefaultTimeZone;
					}
					
					$aEvent['appointment'] = $bIsAppointment;
					$aEvent['appointmentAccess'] = 0;
					
					$aEvent['alarms'] = self::parseAlarms($oVEvent);


					if (!isset($oVEvent->DTEND))
					{
						if (!isset($oVEvent->DTSTART) && isset($oVEvent->CREATED))
						{
							$oVEvent->DTSTART = $oVEvent->CREATED->getDateTime();
						}
						if (isset($oVEvent->DTSTART))
						{
							$dtStart = $oVEvent->DTSTART->getDateTime();
							if ($dtStart)
							{
								$dtStart = $dtStart->add(new \DateInterval('PT1H'));
								$oVEvent->DTEND = $dtStart;
							}
						}
					}
					
					$aEvent['calendarId'] = $oCalendar->Id;
					$aEvent['id'] = $sId;
					$aEvent['uid'] = $sUid;
					$aEvent['subject'] = $oVEvent->SUMMARY ? (string)$oVEvent->SUMMARY : '';
					$aDescription = $oVEvent->DESCRIPTION ? \Sabre\VObject\Parser\MimeDir::unescapeValue((string)$oVEvent->DESCRIPTION) : array('');
					$aEvent['description'] = $aDescription[0];
					$aEvent['location'] = $oVEvent->LOCATION ? (string)$oVEvent->LOCATION : '';
					$aEvent['start'] = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oVEvent->DTSTART, $sTimeZone);
					$aEvent['startTS'] = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVEvent->DTSTART, $sTimeZone);
					$aEvent['end'] = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oVEvent->DTEND, $sTimeZone);
					$aEvent['allDay'] = $bAllDay;
					$aEvent['owner'] = $sOwnerEmail;
					$aEvent['ownerName'] = $sOwnerName;
					$aEvent['modified'] = false;
					$aEvent['recurrenceId'] = $sRecurrenceId;
					if (isset($aRules[$sUid]) && $aRules[$sUid] instanceof RRule)
					{
						$aEvent['rrule'] = $aRules[$sUid]->toArray();
					}
					$bStatus = false;
					if ($oVEvent->STATUS)
					{
						$sStatus = (string)$oVEvent->STATUS;
						$bStatus = strtolower($sStatus) === 'completed' ? true : false; 
					}
					$aEvent['status'] = $bStatus;
					$aEvent['withDate'] = isset($oVEvent->DTSTART) && isset($oVEvent->DTEND);
				}
				
				$aResult[] = $aEvent;
			}
		}

		return $aResult;
	}

	/**
	 * @param \Sabre\VObject\Component\VEvent $oVEvent
	 *
	 * @return array
	 */
	public static function parseAlarms($oVEvent)
	{
		$aResult = array();
		
		if ($oVEvent->VALARM)
		{
			foreach($oVEvent->VALARM as $oVAlarm)
			{
				if (isset($oVAlarm->TRIGGER) && $oVAlarm->TRIGGER instanceof \Sabre\VObject\Property\ICalendar\Duration)
				{
					$aResult[] = \Aurora\Modules\Calendar\Classes\Helper::getOffsetInMinutes($oVAlarm->TRIGGER->getDateInterval());
				}
			}
			rsort($aResult);
		}	
		
		return $aResult;
	}

	/**
	 * @param \Sabre\VObject\Component\VEvent $oVEvent
	 *
	 * @return array
	 */
	public static function parseAttendees($oVEvent)
	{
		$aResult = array();
		
		if (isset($oVEvent->ATTENDEE))
		{
			foreach($oVEvent->ATTENDEE as $oAttendee)
			{
				$iStatus = \Aurora\Modules\Calendar\Enums\AttendeeStatus::Unknown;
				if (isset($oAttendee['PARTSTAT']))
				{
					switch (strtoupper((string)$oAttendee['PARTSTAT']))
					{
						case 'ACCEPTED':
							$iStatus = \Aurora\Modules\Calendar\Enums\AttendeeStatus::Accepted;
							break;
						case 'DECLINED':
							$iStatus = \Aurora\Modules\Calendar\Enums\AttendeeStatus::Declined;
							break;
						case 'TENTATIVE':
							$iStatus = \Aurora\Modules\Calendar\Enums\AttendeeStatus::Tentative;;
							break;
					}
				}

				$aResult[] = array(
					'access' => 0,
					'email' => isset($oAttendee['EMAIL']) ? (string)$oAttendee['EMAIL'] : str_replace('mailto:', '', strtolower($oAttendee->getValue())),
					'name' => isset($oAttendee['CN']) ? (string)$oAttendee['CN'] : '',
					'status' => $iStatus
				);
			}
		}

		return $aResult;
	}

	/**
	 * @param string $sUUID
	 * @param \Sabre\VObject\Component\VEvent $oVEventBase
	 *
	 * @return RRule|null
	 */
	public static function parseRRule($sUUID, $oVEventBase)
	{
		$oResult = null;

		$aWeekDays = array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA');
		$aPeriods = array(
			\Aurora\Modules\Calendar\Enums\PeriodStr::Secondly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Minutely,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Hourly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Daily,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Weekly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Monthly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Yearly
		);
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUUID);
		if (isset($oVEventBase->RRULE, $oUser)  && $oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$oResult = new RRule($oUser);
			$aRules = $oVEventBase->RRULE->getParts();
			if (isset($aRules['FREQ']))
			{
				$bIsPosiblePeriod = array_search(strtolower($aRules['FREQ']), array_map('strtolower', $aPeriods));
				if ($bIsPosiblePeriod !== false)
				{
					$oResult->Period = $bIsPosiblePeriod - 2;
				}
			}
			if (isset($aRules['INTERVAL']))
			{
				$oResult->Interval = $aRules['INTERVAL'];
			}
			if (isset($aRules['COUNT']))
			{
				$oResult->Count = $aRules['COUNT'];
			}
			if (isset($aRules['UNTIL']))
			{
				$oResult->Until = date_format(date_create($aRules['UNTIL']), 'U');
			}
			if (isset($oResult->Count))
			{
				$oResult->End = \Aurora\Modules\Calendar\Enums\RepeatEnd::Count;
			}
			else if (isset($oResult->Until))
			{
				$oResult->End = \Aurora\Modules\Calendar\Enums\RepeatEnd::Date;
			}
			else
			{
				$oResult->End = \Aurora\Modules\Calendar\Enums\RepeatEnd::Infinity;
			}

			if (isset($aRules['BYDAY']) && is_array($aRules['BYDAY']))
			{
				foreach ($aRules['BYDAY'] as $sDay)
				{
					if (strlen($sDay) > 2)
					{
						$iNum = (int)substr($sDay, 0, -2);

						if ($iNum === 1) $oResult->WeekNum = 0;
						if ($iNum === 2) $oResult->WeekNum = 1;
						if ($iNum === 3) $oResult->WeekNum = 2;
						if ($iNum === 4) $oResult->WeekNum = 3;
						if ($iNum === -1) $oResult->WeekNum = 4;
					}

					foreach ($aWeekDays as $sWeekDay)
					{
						if (strpos($sDay, $sWeekDay) !== false) 
						{
							$oResult->ByDays[] = $sWeekDay;
						}
					}
				}
			}
			
			$oResult->StartBase = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVEventBase->DTSTART, $oUser->DefaultTimeZone);
			$oResult->EndBase = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVEventBase->DTEND, $oUser->DefaultTimeZone);
		}

		return $oResult;
	}

	/**
	 * @param string $sUUID
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 *
	 * @return array
	 */
	public static function getRRules($sUUID, $oVCal)
	{
		$aResult = array();
		
		foreach($oVCal->getBaseComponents('VEVENT') as $oVEventBase)
		{
			if (isset($oVEventBase->RRULE))
			{
				$oRRule = Parser::parseRRule($sUUID, $oVEventBase);
				if ($oRRule)
				{
					$aResult[(string)$oVEventBase->UID] = $oRRule;
				}
			}
		}
		
		return $aResult;
	}

	/**
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 *
	 * @return array
	 */
	public static function getExcludedRecurrences($oVCal)
	{
        $aRecurrences = array();
        foreach($oVCal->children() as $oComponent) {

            if (!$oComponent instanceof \Sabre\VObject\Component)
			{
                continue;
			}

            if (isset($oComponent->{'RECURRENCE-ID'}))
			{
				$iRecurrenceId = \Aurora\Modules\Calendar\Classes\Helper::getRecurrenceId($oComponent);
				$aRecurrences[(string)$oComponent->UID . '-' . $iRecurrenceId] = $oComponent;
			}
        }

        return $aRecurrences;
	}
	
}
