<?php
/**
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

/**
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
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
	 * @param \Sabre\VObject\Component\VCalendar $oExpandedVCal
	 * @param \Sabre\VObject\Component\VCalendar $oVCal Default value is **null**.
	 *
	 * @return array
	 */
	public static function parseEvent($sUUID, $oCalendar, $oExpandedVCal, $oVCal = null)
	{
		$aResult = array();
		$aRules = array();
		$aExcludedRecurrences = array();

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUUID);

        $sComponent = 'VEVENT';
		if (isset($oExpandedVCal->{$sComponent}))
		{
			$sType = $sComponent;
		}
		else 
		{
			$sComponent = 'VTODO';
			if (isset($oExpandedVCal->{$sComponent}))
			{
				$sType = $sComponent;
			}
		}
        
        if (isset($oVCal))
		{
			$aRules = self::getRRules($sUUID, $oVCal, $sComponent);
			$aExcludedRecurrences = self::getExcludedRecurrences($oVCal);
		}
        
		if (isset($oExpandedVCal, $oExpandedVCal->{$sComponent}) && ($oUser instanceof \Aurora\Modules\Core\Classes\User || $oCalendar->IsPublic))
		{
			foreach ($oExpandedVCal->{$sComponent} as $oVComponent)
			{
				$sOwnerEmail = $oCalendar->Owner;
				$aEvent = array();
				
				if (isset($oVComponent, $oVComponent->UID))
				{
					$sUid = (string)$oVComponent->UID;
					$sRecurrenceId = \Aurora\Modules\Calendar\Classes\Helper::getRecurrenceId($oVComponent);

					$sId = $sUid . '-' . $sRecurrenceId;
					$aEvent['type'] = $sType;
					
					if (array_key_exists($sId, $aExcludedRecurrences) && isset($oVComponent->{'RECURRENCE-ID'}))
					{
						$oVComponent = $aExcludedRecurrences[$sId];
						$aEvent['excluded'] = true;
					}

					$bIsAppointment = false;
					$aEvent['attendees'] = array();
					if (isset($oVComponent->ATTENDEE))
					{
						$aEvent['attendees'] = self::parseAttendees($oVComponent);

						if (isset($oVComponent->ORGANIZER))
						{
							$sOwnerEmail = str_replace('mailto:', '', strtolower((string)$oVComponent->ORGANIZER));
						}
						$bIsAppointment = ($oUser instanceof \Aurora\Modules\Core\Classes\User && $sOwnerEmail !== $oUser->PublicId);
					}
					
					$oOwner = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sOwnerEmail);
					$sOwnerName = ($oOwner instanceof \Aurora\Modules\Core\Classes\User) ? $oOwner->Name : '';
					$bAllDay = (isset($oVComponent->DTSTART) && !$oVComponent->DTSTART->hasTime());
					if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
					{
						$sTimeZone = ($bAllDay) ? 'UTC' : $oUser->DefaultTimeZone;
					}
					
					$aEvent['appointment'] = $bIsAppointment;
					$aEvent['appointmentAccess'] = 0;
					
					$aEvent['alarms'] = self::parseAlarms($oVComponent);

					if (!isset($oVComponent->DTEND))
					{
						if (!isset($oVComponent->DTSTART) && isset($oVComponent->CREATED))
						{
							$oVComponent->DTSTART = $oVComponent->CREATED->getDateTime();
						}
						if (isset($oVComponent->DTSTART))
						{
							$dtStart = $oVComponent->DTSTART->getDateTime();
							if ($dtStart)
							{
								$dtStart = $dtStart->add(new \DateInterval('PT1H'));
								$oVComponent->DTEND = $dtStart;
							}
						}
					}
					
					$aEvent['calendarId'] = $oCalendar->Id;
					$aEvent['id'] = $sId;
					$aEvent['uid'] = $sUid;
					$aEvent['subject'] = $oVComponent->SUMMARY ? (string)$oVComponent->SUMMARY : '';
					$aDescription = $oVComponent->DESCRIPTION ? \Sabre\VObject\Parser\MimeDir::unescapeValue((string)$oVComponent->DESCRIPTION) : array('');
					$aEvent['description'] = $aDescription[0];
					$aEvent['location'] = $oVComponent->LOCATION ? (string)$oVComponent->LOCATION : '';
					$aEvent['start'] = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oVComponent->DTSTART, $sTimeZone);
					$aEvent['startTS'] = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVComponent->DTSTART, $sTimeZone);
					$aEvent['end'] = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oVComponent->DTEND, $sTimeZone);
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
					if ($oVComponent->STATUS)
					{
						$sStatus = (string)$oVComponent->STATUS;
						$bStatus = strtolower($sStatus) === 'completed' ? true : false; 
					}
					$aEvent['status'] = $bStatus;
					$aEvent['withDate'] = isset($oVComponent->DTSTART) && isset($oVComponent->DTEND);
				}
				
				$aResult[] = $aEvent;
			}
		}

		return $aResult;
	}

	/**
	 * @param \Sabre\VObject\Component $oVComponent
	 *
	 * @return array
	 */
	public static function parseAlarms($oVComponent)
	{
		$aResult = array();
		
		if ($oVComponent->VALARM)
		{
			foreach($oVComponent->VALARM as $oVAlarm)
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
	 * @param \Sabre\VObject\Component $oVComponent
	 *
	 * @return array
	 */
	public static function parseAttendees($oVComponent)
	{
		$aResult = array();
		
		if (isset($oVComponent->ATTENDEE))
		{
			foreach($oVComponent->ATTENDEE as $oAttendee)
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
	 * @param \Sabre\VObject\Component $oVComponentBase
	 *
	 * @return RRule|null
	 */
	public static function parseRRule($sUUID, $oVComponentBase)
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
		if (isset($oVComponentBase->RRULE, $oUser)  && $oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$oResult = new RRule($oUser);
			$aRules = $oVComponentBase->RRULE->getParts();
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
			
			$oResult->StartBase = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVComponentBase->DTSTART, $oUser->DefaultTimeZone);
			$oResult->EndBase = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVComponentBase->DTEND, $oUser->DefaultTimeZone);
		}

		return $oResult;
	}

	/**
	 * @param string $sUUID
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 * @param string $sComponent
	 *
	 * @return array
	 */
	public static function getRRules($sUUID, $oVCal, $sComponent = 'VEVENT')
	{
		$aResult = array();
		
		foreach($oVCal->getBaseComponents($sComponent) as $oVComponentBase)
		{
			if (isset($oVComponentBase->RRULE))
			{
				$oRRule = self::parseRRule($sUUID, $oVComponentBase);
				if ($oRRule)
				{
					$aResult[(string)$oVComponentBase->UID] = $oRRule;
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
        foreach($oVCal->children() as $oComponent) 
		{
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
