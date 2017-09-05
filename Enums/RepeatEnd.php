<?php

namespace Aurora\Modules\Calendar\Enums;

class RepeatEnd extends \Aurora\System\Enums\AbstractEnumeration
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