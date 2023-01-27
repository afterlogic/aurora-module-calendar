<?php

namespace Aurora\Modules\Calendar\Enums;

class Permission extends \Aurora\System\Enums\AbstractEnumeration
{
    public const RemovePermission = -1;
    public const Write = 1;
    public const Read = 2;

    /**
     * @var array
     */
    protected $aConsts = array(
        'RemovePermission' => self::RemovePermission,
        'Write' => self::Write,
        'Read' => self::Read

    );
}
