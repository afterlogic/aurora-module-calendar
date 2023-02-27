<?php

namespace Aurora\Modules\Calendar\Enums;

class DefaultTab extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Day = 1;
    public const Week = 2;
    public const Month = 3;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Day' => self::Day,
        'Week' => self::Week,
        'Month' => self::Month
    );
}
