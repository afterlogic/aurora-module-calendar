<?php
/**
 * This code is licensed under Afterlogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

use Sabre\VObject\Property\ICalendar\DateTime as VDateTime;
use Sabre\VObject\Property\ICalendar\Duration as VDuration;

/**
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
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
 * @property int $Sequence
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
    public $IsPrivate;

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
        $this->IsPrivate	  =	false;
    }

    /**
     * Populate event object from Sabre VEvent component.
     *
     * @param string $sUserPublicId
     * @param \Sabre\VObject\Component $oVEvent
     * @return $this
     */
    public function populateFromVEvent($sUserPublicId, $oVEvent)
    {
        // UID / Id
        if (isset($oVEvent->UID)) {
            $this->Id = (string)$oVEvent->UID;
        }

        // Description / Body
        $this->Description = isset($oVEvent->DESCRIPTION) ? (string)$oVEvent->DESCRIPTION : '';

        // Location
        $this->Location = isset($oVEvent->LOCATION) ? (string)$oVEvent->LOCATION : '';

        // Summary / Name
        $this->Name = isset($oVEvent->SUMMARY) ? (string)$oVEvent->SUMMARY : '';

        // Sequence
        if (isset($oVEvent->SEQUENCE)) {
            $this->Sequence = $oVEvent->SEQUENCE->getValue();
        }

        // Last modified
        if (isset($oVEvent->{'LAST-MODIFIED'}) && $oVEvent->{'LAST-MODIFIED'} instanceof VDateTime) {
            try {
                $dt = $oVEvent->{'LAST-MODIFIED'}->getDateTime();
                $this->Modified = $dt->getTimestamp();
            } catch (\Exception $e) {
                // ignore
            }
        }

        // DTSTART / DTEND / All day
        if (isset($oVEvent->DTSTART) && $oVEvent->DTSTART instanceof VDateTime) {
            try {
                $dtStartObj = $oVEvent->DTSTART->getDateTime();
                $this->Start = $dtStartObj->getTimestamp();

                $dtStartValueParam = $oVEvent->DTSTART['VALUE'] ?? null;

                $this->AllDay =
                    $dtStartValueParam instanceof \Sabre\VObject\Parameter &&
                    $dtStartValueParam->getValue() === 'DATE';

            } catch (\Exception $e) {
                $this->Start = null;
                $this->AllDay = false;
            }
        }

        if (isset($oVEvent->DTEND) && $oVEvent->DTEND instanceof VDateTime) {
            try {
                $dtEnd = $oVEvent->DTEND->getDateTime();
                $this->End = $dtEnd->getTimestamp();
            } catch (\Exception $e) {
                $this->End = null;
            }
        } elseif (isset($this->Start) && isset($oVEvent->DURATION) && $oVEvent->DURATION instanceof VDuration) {
            try {
                $interval = $oVEvent->DURATION->getDateInterval();
                $dt = (new \DateTime())->setTimestamp($this->Start);
                $dt->add($interval);
                $this->End = $dt->getTimestamp();
            } catch (\Exception $e) {
                $this->End = null;
            }
        }

        $oUser = \Aurora\Api::getUserByPublicId($sUserPublicId);
        $sDefaultTimeZone = '';
        if ($oUser) {
            $sDefaultTimeZone = $oUser->getDefaultTimeZone();
        }
        // Recurrence rule
        $this->RRule = Parser::parseRRule($sDefaultTimeZone, $oVEvent);

        // Attendees
        $this->Attendees = [];
        if (isset($oVEvent->ATTENDEE)) {
            foreach ($oVEvent->ATTENDEE as $att) {
                $sVal = (string)$att;
                $sEmail = str_ireplace('mailto:', '', $sVal);
                $this->Attendees[] = $sEmail;
            }
        }

        return $this;
    }
}
