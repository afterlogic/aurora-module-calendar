<?php

namespace Aurora\Modules\Calendar\Enums;

class Permission extends \Aurora\System\Enums\AbstractEnumeration
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
