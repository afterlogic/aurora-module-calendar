<?php

namespace Aurora\Modules\Calendar\Enums;

class PeriodStr extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Secondly = 'secondly';
    public const Minutely = 'minutely';
    public const Hourly   = 'hourly';
    public const Daily	   = 'daily';
    public const Weekly   = 'weekly';
    public const Monthly  = 'monthly';
    public const Yearly   = 'yearly';

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
