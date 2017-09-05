<?php

namespace Aurora\Modules\Calendar\Enums;

class Period extends \Aurora\System\Enums\AbstractEnumeration
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