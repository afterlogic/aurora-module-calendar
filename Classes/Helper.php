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
class Helper
{

	/**
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent
	 * @param \DateTime $oNowDT
	 * @param \DateTime $oStartDT
	 *
	 * @return int|false
	 */
	public static function getActualReminderTime($oEvent, $oNowDT, $oStartDT, $iWorkDayStartsOffset = 0, $iOffset = 0)
	{
		$aReminders = \Aurora\Modules\Calendar\Classes\Parser::parseAlarms($oEvent);

		$iNowTS = $oNowDT->getTimestamp();

		if ($oStartDT)
		{
			$iStartEventTS = $oStartDT->getTimestamp();

			$aRemindersTime = array();
			foreach ($aReminders as $iReminder)
			{
				$aRemindersTime[] = $iStartEventTS + $iWorkDayStartsOffset - $iReminder * 60;
			}
			sort($aRemindersTime);
			foreach ($aRemindersTime as $iReminder)
			{
				if ($iReminder > $iNowTS + $iOffset)
				{
					return $iReminder;
				}
			}
		}
		return false;
	}

	/**
	 * @param \DateTimeImmutable $sDtStart
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 * @param string $sUid Default value is **null**.
	 *
	 * @return \DateTime
	 */
	public static function getNextRepeat(\DateTimeImmutable $sDtStart, $oVCal, $sUid = null)
	{
		$oRecur = new \Sabre\VObject\Recur\EventIterator($oVCal, $sUid);
		$oRecur->fastForward($sDtStart);
		return $oRecur->current();
	}

	/**
	 * @param DateTime $sDtStart
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 * @param string $sUid Default value is **null**.
	 *
	 * @return DateTime
	 */
	public static function getRRuleIteratorNextRepeat(\DateTimeImmutable $sDtStart, $oVCal, $sUid = null)
	{
		$oRecur = new \Sabre\VObject\Recur\EventIterator($oVCal, $sUid);
		$oRecur->fastForward($sDtStart);
		$oRecur->next();
		return $oRecur->current();
	}
	
	/**
	 * @param int $iData
	 * @param int $iMin
	 * @param int $iMax
	 *
	 * @return bool
	 */
	public static function validate($iData, $iMin, $iMax)
	{
		if (null === $iData)
		{
			return false;
		}
		$iData = round($iData);
		return (isset($iMin) && isset($iMax)) ? ($iMin <= $iData && $iData <= $iMax) : ($iData > 0);
	}

	/**
	 * @param \DateTime $dt
	 * @param string $sTimeZone
	 *
	 * @return int|null
	 */
	public static function getTimestamp($dt, $sTimeZone = 'UTC')
	{
		$iResult = null;

		$oDateTime = self::getDateTime($dt, $sTimeZone);
		if (null != $oDateTime)
		{
			$iResult = $oDateTime->getTimestamp();
		}

		return $iResult;
	}

	/**
	 * @param \DateTime $dt
	 * @param string $sTimeZone
	 *
	 * @return \DateTime|null
	 */
	public static function getDateTime($dt, $sTimeZone = 'UTC')
	{
		$result = null;
		if ($dt)
		{
			$result = $dt->getDateTime();
		}
		if (isset($result))
		{
			$sTimeZone = $sTimeZone === null ? 'UTC' : $sTimeZone;
			$result = $result->setTimezone(new \DateTimeZone($sTimeZone));
		}
		return $result;
	}

	/**
	 * @param \DateTime $dt
	 * @param string $format
	 *
	 * @return string
	 */
	public static function dateTimeToStr($dt, $format = 'Y-m-d H:i:s')
	{
		return $dt->format($format);
	}

	/**
	 * @param \Sabre\VObject\Component $oVComponent
	 * @param string $sRecurrenceId
	 *
	 * @return mixed
	 */
	public static function isRecurrenceExists($oVComponent, $sRecurrenceId)
	{
		$mResult = false;
		foreach($oVComponent as $mKey => $oComponent)
		{
			if (isset($oComponent->{'RECURRENCE-ID'}))
			{
				if (self::getRecurrenceId($oComponent) === $sRecurrenceId)
				{
					$mResult = $mKey;
					break;
				}
			}
		}

		return $mResult;
	}

	/**
	 * @param \Sabre\VObject\Component $oComponent
	 *
	 * @return int
	 */
	public static function getRecurrenceId($oComponent)
	{
		$iTimestamp = 0;
		$oRecurrenceId = $oComponent->DTSTART;
		if ($oComponent->{'RECURRENCE-ID'})
		{
			$oRecurrenceId = $oComponent->{'RECURRENCE-ID'};
		}
		if (isset($oRecurrenceId))
		{
			$dRecurrence = $oRecurrenceId->getDateTime();
			$iTimestamp = $dRecurrence->getTimestamp();
		}
		return $iTimestamp;
	}

    /**
	 * @param \DateInterval $oInterval
	 *
	 * @return int
	 */
	public static function getOffsetInMinutes($oInterval)
	{
		$iMinutes = 0;
		try
		{
			$iMinutes = $oInterval->i + $oInterval->h*60 + $oInterval->d*24*60;
		}
		catch (Exception $ex)
		{
			$iMinutes = 15;
		}

		return $iMinutes;
	}

	public static function getBaseVComponentIndex($oVComponent)
	{
		$iIndex = -1;
		if (isset($oVComponent))
		{
			foreach($oVComponent as $oComponent)
			{
				$iIndex++;
				if (empty($oComponent->{'RECURRENCE-ID'}))
				{
					break;
				}
			}
		}
		return ($iIndex >= 0) ? $iIndex : false;
	}

	/**
	 * @param string $sUserPublicId
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent
	 * @param \Sabre\VObject\Component\VEvent $oVEvent
	 */
	public static function populateVCalendar($sUserPublicId, $oEvent, &$oVCal, &$oVEvent)
	{
		$oVEvent->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
		$oVEvent->{'SEQUENCE'} = isset($oVEvent->{'SEQUENCE'}) ? $oVEvent->{'SEQUENCE'}->getValue() + 1 : 1;
		
		if ($oEvent->Type === 'VTODO')
		{
			if ($oEvent->Status)
			{
				$oVEvent->STATUS = 'COMPLETED';
				$oVEvent->{'PERCENT-COMPLETE'} = 100;
				$oVEvent->COMPLETED = new \DateTime('now', new \DateTimeZone('UTC'));
			}
			else 
			{
				$oVEvent->STATUS = 'NEEDS-ACTION';
				unset($oVEvent->{'PERCENT-COMPLETE'});
				unset($oVEvent->COMPLETED);
			}
		}
		else if ($oEvent->Type === 'VEVENT')
		{
			unset($oVEvent->STATUS);
			unset($oVEvent->COMPLETED);
			unset($oVEvent->{'PERCENT-COMPLETE'});
		}
		
//		$oVCal =& $oVEvent->parent;

		$oVEvent->UID = $oEvent->Id;

		if (!empty($oEvent->Start) && !empty($oEvent->End))
		{
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($sUserPublicId);
			$oDTStart = self::prepareDateTime($oEvent->Start, $oUser->DefaultTimeZone);
			if (isset($oDTStart))
			{
				$oVEvent->DTSTART = $oDTStart;
				if ($oEvent->AllDay)
				{
					$oVEvent->DTSTART->offsetSet('VALUE', 'DATE');
				}
			}
			$oDTEnd = self::prepareDateTime($oEvent->End, $oUser->DefaultTimeZone);
			if (isset($oDTEnd))
			{
				if ($oEvent->Type === 'VTODO')
				{
					$oVEvent->DUE = $oDTEnd;
					if ($oEvent->AllDay)
					{
						$oVEvent->DUE->offsetSet('VALUE', 'DATE');
					}
				}
				else
				{
					$oVEvent->DTEND = $oDTEnd;
					if ($oEvent->AllDay)
					{
						$oVEvent->DTEND->offsetSet('VALUE', 'DATE');
					}
				}
			}
		}
		else
		{
			unset($oVEvent->DTSTART);
			unset($oVEvent->DTEND);
		}

		if (isset($oEvent->Name))
		{
			$oVEvent->SUMMARY = $oEvent->Name;
		}
		if (isset($oEvent->Description))
		{
			$oVEvent->DESCRIPTION = $oEvent->Description;
		}
		if (isset($oEvent->Location))
		{
			$oVEvent->LOCATION = $oEvent->Location;
		}

		unset($oVEvent->RRULE);
		if (isset($oEvent->RRule))
		{
			$sRRULE = '';
			if (isset($oVEvent->RRULE) && null === $oEvent->RRule)
			{
				$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
				$oRRule = false;
				if ($oUser instanceof \Aurora\Modules\Core\Models\User)
				{
					$oRRule = \Aurora\Modules\Calendar\Classes\Parser::parseRRule($oUser->DefaultTimeZone, $oVCal, (string)$oVEvent->UID);
				}
				if ($oRRule && $oRRule instanceof \Aurora\Modules\Calendar\Classes\RRule)
				{
					$sRRULE = (string) $oRRule;
				}
			}
			else
			{
				$sRRULE = (string)$oEvent->RRule;
			}
			if (trim($sRRULE) !== '')
			{
				$oVEvent->add('RRULE', $sRRULE);
			}
		}

		unset($oVEvent->VALARM);
		if (isset($oEvent->Alarms))
		{
			foreach ($oEvent->Alarms as $sOffset)
			{
				$oVEvent->add('VALARM', array(
					'TRIGGER' => self::getOffsetInStr($sOffset),
					'DESCRIPTION' => 'Alarm',
					'ACTION' => 'DISPLAY'
				));
			}
		}
	}

	/**
	 * @param mixed $mDateTime
	 * @param string $sTimeZone
	 *
	 * @return \DateTime
	 */
	public static function prepareDateTime($mDateTime, $sTimeZone)
	{
		$oDateTime = new \DateTime();
		if (is_numeric($mDateTime) && strlen($mDateTime) !== 8)
		{
			$oDateTime->setTimestamp($mDateTime);
			$oDateTime->setTimezone(new \DateTimeZone($sTimeZone));
		}
		else
		{
			$oDateTime = \Sabre\VObject\DateTimeParser::parse($mDateTime, new \DateTimeZone($sTimeZone));
		}

		return $oDateTime;
	}

    /**
	 * @param string $iMinutes
	 *
	 * @return string
	 */
	public static function getOffsetInStr($iMinutes)
	{
		return '-PT' . $iMinutes . 'M';
	}

	/**
	 * @param \DateTime $dt
	 * @param string $sTimeZone
	 * @param string $format
	 *
	 * @return string
	 */
	public static function getStrDate($dt, $sTimeZone, $format = 'Y-m-d H:i:s')
	{
		$result = null;
		$oDateTime = self::getDateTime($dt, $sTimeZone);
		if ($oDateTime)
		{
			if (!$dt->hasTime())
			{
				$format = 'Y-m-d';
			}
			$result = $oDateTime->format($format);
		}
		return $result;
	}

	/**
	 * @param string $sString
	 *
	 * @return array
	 */
	public static function findGroupsHashTagsFromString($sString)
	{
		$aResult = array();
		
		preg_match_all("/[#]([^#\s]+)/", $sString, $aMatches);
		
		if (\is_array($aMatches) && isset($aMatches[0]) && \is_array($aMatches[0]) && 0 < \count($aMatches[0]))
		{
			$aResult = $aMatches[0];
		}
		
		return $aResult;
	}
	
}
