<?php

namespace Aurora\Modules\Calendar\Enums;

class RepeatEnd extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Never		= 0;
    public const Count		= 1;
    public const Date		= 2;
    public const Infinity	= 3;

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
