<?php

namespace Aurora\Modules\Calendar\Enums;

class Period extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Never   = 0;
    public const Daily	   = 1;
    public const Weekly   = 2;
    public const Monthly  = 3;
    public const Yearly   = 4;

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
