<?php

namespace Aurora\Modules\Calendar\Enums;

class WeekStartOn extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Saturday = 6;
    public const Sunday = 0;
    public const Monday = 1;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Saturday' => self::Saturday,
        'Sunday' => self::Sunday,
        'Monday' => self::Monday
    );
}
