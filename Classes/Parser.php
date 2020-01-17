<?php
/**
 * This code is licensed under Afterlogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

/**
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
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
	public static function parseEvent($sUserPublicId, $oCalendar, $oExpandedVCal, $oVCal = null, $sDefaultTimeZone = null)
	{
		$aResult = array();
		$aRules = array();
		$aExcludedRecurrences = array();

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$sTimeZone = $oUser->DefaultTimeZone;
		}
		else if ($sDefaultTimeZone)
		{
			$sTimeZone = $sDefaultTimeZone;
		}
		else
		{
			$sTimeZone = 'UTC';
		}

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
			$aRules = self::getRRules($sTimeZone, $oVCal, $sComponent);
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

					$aArgs = [
						'oVComponent'	=> $oVComponent,
						'sOwnerEmail'	=> $sOwnerEmail,
						'oUser'		=> $oUser
					];
					\Aurora\System\Api::GetModule('Calendar')->broadcastEvent(
						'parseEvent',
						$aArgs,
						$aEvent
					);
					$sOwnerEmail = $aArgs['sOwnerEmail'];
					$oOwner = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sOwnerEmail);
					$sOwnerName = ($oOwner instanceof \Aurora\Modules\Core\Classes\User) ? $oOwner->Name : '';
					$bAllDay = (isset($oVComponent->DTSTART) && !$oVComponent->DTSTART->hasTime());
					$sCurrentTimeZone = ($bAllDay) ? 'UTC' : $sTimeZone;
					$aEvent['alarms'] = self::parseAlarms($oVComponent);

					$oDTEND = null;
					if ($sComponent === 'VTODO')
					{
						if (isset($oVComponent->DUE))
						{
							$oDTEND = $oVComponent->DUE;
						}
					}
					else if (isset($oVComponent->DTEND))
					{
						$oDTEND = $oVComponent->DTEND;
					}
					
					if (isset($oDTEND))
					{
						if (!isset($oVComponent->DTSTART))
						{
							$oVComponent->DTSTART = $oDTEND->getDateTime();
						}
					}
					else
					{
						if (isset($oVComponent->DTSTART))
						{
							$dtStart = $oVComponent->DTSTART->getDateTime();
							if ($dtStart)
							{
								$oVComponent->DTEND = $dtStart->add(new \DateInterval('PT1H'));
								$oDTEND = $oVComponent->DTEND;
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
					$aEvent['start'] = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oVComponent->DTSTART, $sCurrentTimeZone);
					$aEvent['startTS'] = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVComponent->DTSTART, $sCurrentTimeZone);
					$aEvent['end'] = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oDTEND, $sCurrentTimeZone);
					$aEvent['endTS'] = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oDTEND, $sCurrentTimeZone);
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
					$aEvent['withDate'] = isset($oVComponent->DTSTART) && isset($oDTEND);
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
	 * @param string $DefaultTimeZone
	 * @param \Sabre\VObject\Component $oVComponentBase
	 *
	 * @return RRule|null
	 */
	public static function parseRRule($DefaultTimeZone, $oVComponentBase)
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

		if (isset($oVComponentBase->RRULE, $DefaultTimeZone))
		{
			$oResult = new RRule($DefaultTimeZone);
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

			if (isset($aRules['BYDAY']))
			{
				if (!is_array($aRules['BYDAY']))
				{
					$aByDay[] = $aRules['BYDAY'];
				}
				else
				{
					$aByDay = $aRules['BYDAY'];
				}
					
				foreach ($aByDay as $sDay)
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
			
			$oResult->StartBase = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVComponentBase->DTSTART, $DefaultTimeZone);
			$oResult->EndBase = \Aurora\Modules\Calendar\Classes\Helper::getTimestamp($oVComponentBase->DTEND, $DefaultTimeZone);
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
	public static function getRRules($DefaultTimeZone, $oVCal, $sComponent = 'VEVENT')
	{
		$aResult = array();
		
		foreach($oVCal->getBaseComponents($sComponent) as $oVComponentBase)
		{
			if (isset($oVComponentBase->RRULE))
			{
				$oRRule = self::parseRRule($DefaultTimeZone, $oVComponentBase);
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
