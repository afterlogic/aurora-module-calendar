<?php

namespace Aurora\Modules\Calendar\Enums;

class AttendeeStatus extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Unknown = 0;
    public const Accepted = 1;
    public const Declined = 2;
    public const Tentative = 3;

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
