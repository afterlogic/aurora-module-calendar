<?php
/**
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

/**
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
 * @property mixed  $Id
 * @property mixed  $IdCalendar
 * @property string $Start
 * @property string $End
 * @property bool   $AllDay
 * @property string $Name
 * @property string $Description
 * @property RRule $RRule
 * @property array  $Alarms
 * @property array  $Attendees;
 * @property bool $Deleted;
 * @property bool $Modified;
 * @property int $sequence
 *
 * @package Calendar
 * @subpackage Classes
 */
class Event
{
	public $Id;
	public $IdCalendar;
	public $Start;
	public $End;
	public $AllDay;
	public $Name;
	public $Description;
	public $Location;
	public $RRule;
	public $Alarms;
	public $Attendees;
    public $Deleted;
	public $Modified;
    public $Sequence;
	public $Type;
	public $Status;

	public function __construct()
	{
		$this->Id			  = null;
		$this->IdCalendar	  = null;
		$this->Start		  = null;
		$this->End			  = null;
		$this->AllDay		  = false;
		$this->Name			  = null;
		$this->Description	  = null;
		$this->Location		  = null;
		$this->RRule		  = null;
		$this->Alarms		  = array();
		$this->Attendees	  = array();
		$this->Deleted		  = null;
		$this->Modified		  = false;
        $this->Sequence       = 0;
		$this->Type			  = 'VEVENT';
		$this->Status		  =	false;
	}
}
