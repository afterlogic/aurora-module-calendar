<?php

namespace Aurora\Modules\Calendar\Enums;

class WeekStartOn extends \Aurora\System\Enums\AbstractEnumeration
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
