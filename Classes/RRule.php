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
 * @package Calendar
 * @subpackage Classes
 */
class RRule
{
	public $StartBase;
	public $EndBase;
	public $Period;
	public $Count;
	public $Until;
	public $Interval;
	public $End;
	public $WeekNum;
	public $ByDays;
	protected $DefaultTimeZone;

	public function __construct($DefaultTimeZone)
	{
		$this->DefaultTimeZone = $DefaultTimeZone;
		$this->StartBase  = null;
		$this->EndBase    = null;
		$this->Period	  = null;
		$this->Count	  = null;
		$this->Until	  = null;
		$this->Interval	  = null;
		$this->End		  = null;
		$this->WeekNum	  = null;
		$this->ByDays	  = array();
	}

	public function Populate($aRRule)
	{
		$this->Period = isset($aRRule['period']) ? (int)$aRRule['period'] : null;
		$this->Count = isset($aRRule['count']) ? $aRRule['count'] : null;
		$this->Until = isset($aRRule['until']) ? $aRRule['until'] : null;
		$this->Interval = isset($aRRule['interval']) ? $aRRule['interval'] : null;
		$this->End = isset($aRRule['end']) ? $aRRule['end'] : null;
		$this->WeekNum = isset($aRRule['weekNum']) ? $aRRule['weekNum'] : null;
		$this->ByDays = isset($aRRule['byDays']) ? $aRRule['byDays'] : array();
	}

	public function toArray()
	{
		return array(
			'startBase' => $this->StartBase,
			'endBase' => $this->EndBase,
			'period' => $this->Period,
			'interval' => $this->Interval,
			'end' => !isset($this->End) ? 0 : $this->End,
			'until' => $this->Until,
			'weekNum' => $this->WeekNum,
			'count' => $this->Count,
			'byDays' => $this->ByDays
		);
	}

	public function toResponseArray($aParameters = array())
	{
		return $this->toArray();
	}

    public function __toString()
	{
		$aPeriods = array(
			\Aurora\Modules\Calendar\Enums\PeriodStr::Secondly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Minutely,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Hourly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Daily,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Weekly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Monthly,
			\Aurora\Modules\Calendar\Enums\PeriodStr::Yearly
		);

		$sRule = '';

		if (null !== $this->Period)
		{
			$iWeekNumber = null;
			if (($this->Period == \Aurora\Modules\Calendar\Enums\Period::Monthly || $this->Period == \Aurora\Modules\Calendar\Enums\Period::Yearly) && (null !== $this->WeekNum))
			{
				$iWeekNumber = ((int)$this->WeekNum < 0 || (int)$this->WeekNum > 4) ? 0 : (int)$this->WeekNum;
			}

			$sUntil = '';
			if (null !== $this->Until)
			{
				$oDTUntil = \Aurora\Modules\Calendar\Classes\Helper::prepareDateTime($this->Until, $this->GetTimeZone());
				$sUntil = $oDTUntil->format('Ymd\T235959\Z');
			}

			$iInterval = (null !== $this->Interval) ? (int)$this->Interval : 0;
			$iEnd = (null === $this->End || (int)$this->End < 0 || (int)$this->End > 3) ? 0 : (int)$this->End;

			$sFreq = strtoupper($aPeriods[$this->Period + 2]);
			$sRule = 'FREQ=' . $sFreq . ';INTERVAL=' . $iInterval;
			if ($iEnd === \Aurora\Modules\Calendar\Enums\RepeatEnd::Count)
			{
				$sRule .= ';COUNT=' . (null !== $this->Count) ? (int)$this->Count : 0;
			}
			else if ($iEnd === \Aurora\Modules\Calendar\Enums\RepeatEnd::Date)
			{
				$sRule .= ';UNTIL=' . $sUntil;
			}

			$sByDay = null;
			if (in_array($sFreq, array('WEEKLY', 'MONTHLY', 'YEARLY'))) {
				$aByDays = $this->ByDays;

				if (in_array($sFreq, array('MONTHLY', 'YEARLY')) && isset($iWeekNumber)) {
					if ($iWeekNumber >= 0 && $iWeekNumber < 4) {
						$iWeekNumber = (int) $iWeekNumber + 1;
					} else if ($iWeekNumber === 4) {
						$iWeekNumber = -1;
					}
					$aByDays = array_map(function($byDay) use ($iWeekNumber) {
						return $iWeekNumber . $byDay;
					}, $this->ByDays);
				}
				$sByDays = implode(',', $aByDays);
				if (!empty($sByDays)) {
					$sRule .= ';BYDAY=' . $sByDays;
				}
			}
		}
        return $sRule;
	}

	public function GetTimeZone()
	{
		return $this->DefaultTimeZone;
	}
}
