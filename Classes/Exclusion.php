<?php
/**
 * This code is licensed under Afterlogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

/**
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @property mixed $IdRecurrence;
 * @property mixed $IdRepeat
 * @property string $StartTime;
 * @property bool $Deleted;
 *
 * @package Calendar
 * @subpackage Classes
 */
class Exclusion
{
    public $IdRecurrence;
    public $IdRepeat;
    public $StartTime;
    public $Deleted;

    public function __construct()
    {
        $this->IdRecurrence = null;
        $this->IdRepeat   = null;
        $this->StartTime  = null;
        $this->Deleted    = null;
    }
}
