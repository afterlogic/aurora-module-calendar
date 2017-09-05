<?php

namespace Aurora\Modules\Calendar\Enums;

class AttendeeStatus extends \Aurora\System\Enums\AbstractEnumeration
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