<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AfterLogic Software License
 *
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

/**
 * @package Api
 * @subpackage Enum
 */
class ECalendarDefaultWorkDay
{
	const Starts = 9;
	const Ends = 17;
}

/**
 * @package Api
 * @subpackage Enum
 */
class ECalendarWeekStartOn extends \Aurora\System\Enums\AbstractEnumeration
{
	const Saturday = 6;
	const Sunday = 0;
	const Monday = 1;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Saturday' => self::Saturday,
		'Sunday' => self::Sunday,
		'Monday' => self::Monday
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class ECalendarDefaultTab extends \Aurora\System\Enums\AbstractEnumeration
{
	const Day = 1;
	const Week = 2;
	const Month = 3;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Day' => self::Day,
		'Week' => self::Week,
		'Month' => self::Month
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class ECalendarPermission extends \Aurora\System\Enums\AbstractEnumeration
{
	const RemovePermission = -1;
	const Write = 1;
	const Read = 2;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'RemovePermission' => self::RemovePermission,
		'Write' => self::Write,
		'Read' => self::Read

	);
}


/**
 * @package Api
 * @subpackage Enum
 */
class EPeriodStr extends \Aurora\System\Enums\AbstractEnumeration
{
	const Secondly = 'secondly';
	const Minutely = 'minutely';
	const Hourly   = 'hourly';
	const Daily	   = 'daily';
	const Weekly   = 'weekly';
	const Monthly  = 'monthly';
	const Yearly   = 'yearly';

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Secondly' => self::Secondly,
		'Minutely' => self::Minutely,
		'Hourly'   => self::Hourly,
		'Daily'	   => self::Daily,
		'Weekly'   => self::Weekly,
		'Monthly'  => self::Monthly,
		'Yearly'   => self::Yearly
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class EPeriod extends \Aurora\System\Enums\AbstractEnumeration
{
	const Never   = 0;
	const Daily	   = 1;
	const Weekly   = 2;
	const Monthly  = 3;
	const Yearly   = 4;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Never'		=> self::Never,
		'Daily'		=> self::Daily,
		'Weekly'	=> self::Weekly,
		'Monthly'	=> self::Monthly,
		'Yearly'	=> self::Yearly
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class ERepeatEnd extends \Aurora\System\Enums\AbstractEnumeration
{
	const Never		= 0;
	const Count		= 1;
	const Date		= 2;
	const Infinity	= 3;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Never'		=> self::Never,
		'Count'		=> self::Count,
		'Date'		=> self::Date,
		'Infinity'	=> self::Infinity
	);
}

/**
 * @package Api
 * @subpackage Enum
 */
class EAttendeeStatus extends \Aurora\System\Enums\AbstractEnumeration
{
	const Unknown = 0;
	const Accepted = 1;
	const Declined = 2;
	const Tentative = 3;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Unknown' => self::Unknown,
		'Accepted' => self::Accepted,
		'Declined' => self::Declined,
		'Tentative'   => self::Tentative
	);
}