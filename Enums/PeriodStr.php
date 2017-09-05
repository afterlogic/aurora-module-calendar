<?php

namespace Aurora\Modules\Calendar\Enums;

class PeriodStr extends \Aurora\System\Enums\AbstractEnumeration
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
