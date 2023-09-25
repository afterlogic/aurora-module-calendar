<?php
/**
 * This code is licensed under Afterlogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar;

use Aurora\System\Exceptions\ApiException;

/**
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractLicensedModule
{
    public $oManager = null;
    public $oFilecacheManager = null;
    protected $oUserForDelete = null;

    public const DEFAULT_PERIOD_IN_DAYS = 30;

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     *
     * @return Manager
     */
    public function getManager()
    {
        if ($this->oManager === null) {
            $this->oManager = new Manager($this);
        }

        return $this->oManager;
    }

    public function getFilecacheManager()
    {
        if ($this->oFilecacheManager === null) {
            $this->oFilecacheManager = new \Aurora\System\Managers\Filecache();
        }

        return $this->oFilecacheManager;
    }

    public function init()
    {
        $this->aErrors = [
            Enums\ErrorCodes::CannotFindCalendar => $this->i18N('ERROR_NO_CALENDAR'),
            Enums\ErrorCodes::InvalidSubscribedIcs => $this->i18N('ERROR_INVALID_SUBSCRIBED_ICS')
        ];

        $this->AddEntries(
            array(
                'calendar-pub' => 'EntryCalendarPub',
                'calendar-download' => 'EntryCalendarDownload'
            )
        );

        $this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
        $this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
        $this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
        $this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
        $this->subscribeEvent('Core::DeleteUser::after', array($this, 'onAfterDeleteUser'));
    }

    /**
     * Obtains list of module settings for authenticated user.
     *
     * @return array
     */
    public function GetSettings()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

        $aSettings = array(
            'AddDescriptionToTitle' => $this->oModuleSettings->AddDescriptionToTitle,
            'AllowTasks' => $this->oModuleSettings->AllowTasks,
            'DefaultTab' => $this->oModuleSettings->DefaultTab,
            'HighlightWorkingDays' => $this->oModuleSettings->HighlightWorkingDays,
            'HighlightWorkingHours' => $this->oModuleSettings->HighlightWorkingHours,
            'ShowWeekNumbers' => $this->oModuleSettings->ShowWeekNumbers,
            'PublicCalendarId' => $this->oHttp->GetQuery('calendar-pub', ''),
            'WeekStartsOn' => $this->oModuleSettings->WeekStartsOn,
            'WorkdayEnds' => $this->oModuleSettings->WorkdayEnds,
            'WorkdayStarts' => $this->oModuleSettings->WorkdayStarts,
            'AllowSubscribedCalendars' => $this->oModuleSettings->AllowSubscribedCalendars,
            'AllowPrivateEvents' => $this->oModuleSettings->AllowPrivateEvents,
            'AllowDefaultReminders' => $this->oModuleSettings->AllowDefaultReminders,
            'DefaultReminders' => [],
            'CalendarColors' => $this->oModuleSettings->CalendarColors,
        );

        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oUser && $oUser->isNormalOrTenant()) {
            if (null !== $oUser->getExtendedProp(self::GetName() . '::HighlightWorkingDays')) {
                $aSettings['HighlightWorkingDays'] = $oUser->getExtendedProp(self::GetName() . '::HighlightWorkingDays');
            }
            if (null !== $oUser->getExtendedProp(self::GetName() . '::HighlightWorkingHours')) {
                $aSettings['HighlightWorkingHours'] = $oUser->getExtendedProp(self::GetName() . '::HighlightWorkingHours');
            }
            if (null !== $oUser->getExtendedProp(self::GetName() . '::ShowWeekNumbers')) {
                $aSettings['ShowWeekNumbers'] = $oUser->getExtendedProp(self::GetName() . '::ShowWeekNumbers');
            }
            if (null !== $oUser->getExtendedProp(self::GetName() . '::WorkdayStarts')) {
                $aSettings['WorkdayStarts'] = $oUser->getExtendedProp(self::GetName() . '::WorkdayStarts');
            }
            if (null !== $oUser->getExtendedProp(self::GetName() . '::WorkdayEnds')) {
                $aSettings['WorkdayEnds'] = $oUser->getExtendedProp(self::GetName() . '::WorkdayEnds');
            }
            if (null !== $oUser->getExtendedProp(self::GetName() . '::WeekStartsOn')) {
                $aSettings['WeekStartsOn'] = $oUser->getExtendedProp(self::GetName() . '::WeekStartsOn');
            }
            if (null !== $oUser->getExtendedProp(self::GetName() . '::DefaultTab')) {
                $aSettings['DefaultTab'] = $oUser->getExtendedProp(self::GetName() . '::DefaultTab');
            }
            if (null !== $oUser->getExtendedProp(self::GetName() . '::DefaultReminders')) {
                $aSettings['DefaultReminders'] = $oUser->getExtendedProp(self::GetName() . '::DefaultReminders');
            }

            $oUser->save();
        }

        return $aSettings;
    }

    public function UpdateSettings($HighlightWorkingDays, $HighlightWorkingHours, $WorkdayStarts, $WorkdayEnds, $WeekStartsOn, $DefaultTab, $DefaultReminders, $ShowWeekNumbers)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oUser) {
            if ($oUser->isNormalOrTenant()) {
                $oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
                $oUser->setExtendedProps([
                    self::GetName() . '::HighlightWorkingDays' => $HighlightWorkingDays,
                    self::GetName() . '::HighlightWorkingHours' => $HighlightWorkingHours,
                    self::GetName() . '::ShowWeekNumbers' => $ShowWeekNumbers,
                    self::GetName() . '::WorkdayStarts' => $WorkdayStarts,
                    self::GetName() . '::WorkdayEnds' => $WorkdayEnds,
                    self::GetName() . '::WeekStartsOn' => $WeekStartsOn,
                    self::GetName() . '::DefaultTab' => $DefaultTab,
                    self::GetName() . '::DefaultReminders' => $DefaultReminders,
                ]);
                return $oCoreDecorator->UpdateUserObject($oUser);
            }
            if ($oUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin) {
                $this->setConfig('HighlightWorkingDays', $HighlightWorkingDays);
                $this->setConfig('HighlightWorkingHours', $HighlightWorkingHours);
                $this->setConfig('ShowWeekNumbers', $ShowWeekNumbers);
                $this->setConfig('WorkdayStarts', $WorkdayStarts);
                $this->setConfig('WorkdayEnds', $WorkdayEnds);
                $this->setConfig('WeekStartsOn', $WeekStartsOn);
                $this->setConfig('DefaultTab', $DefaultTab);
                return $this->saveModuleConfig();
            }
        }

        return false;
    }

    /**
     * Loads calendar.
     *
     * @param int $UserId
     * @param string $CalendarId Calendar ID
     *
     * @return Classes\Calendar|false $oCalendar
     */
    public function GetCalendar($UserId, $CalendarId)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $oUser = \Aurora\System\Api::getUserById($UserId);

        $oCalendar = $this->getManager()->getCalendar($oUser->PublicId, $CalendarId);
        if ($oCalendar) {
            //			$oCalendar = $this->getManager()->populateCalendarShares($UserId, $oCalendar);
        }
        return $oCalendar;
    }

    /**
     *
     * @param string $CalendarId
     *
     * @return array|false
     */
    public function GetPublicCalendar($CalendarId)
    {
        $mResult = false;

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
        $oPublicCalendar = $this->getManager()->getPublicCalendar($CalendarId);
        if ($oPublicCalendar) {
            $mResult = [
                'Calendars' => [$oPublicCalendar]
            ];
        }

        return $mResult;
    }

    /**
     *
     * @param int $UserId
     * @return array|boolean
     */
    public function GetCalendars($UserId)
    {
        $mResult = false;
        $mCalendars = false;

        \Aurora\System\Api::CheckAccess($UserId);
        $oUser = \Aurora\System\Api::getUserById($UserId);
        if ($oUser) {
            $mCalendars = $this->getManager()->getCalendars($oUser->PublicId);
        }

        // When $mCalendars is an empty array with condition "if ($mCalendars)" $mResult will be false
        if (is_array($mCalendars)) {
            $mResult = array(
                'Calendars' => $mCalendars
            );
        }

        return $mResult;
    }

    /**
     *
     * @return void
     */
    public function EntryCalendarDownload()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $RawKey = \Aurora\System\Router::getItemByIndex(1, '');
        $aValues = \Aurora\System\Api::DecodeKeyValues($RawKey);

        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById(\Aurora\System\Api::getAuthenticatedUserId());

        if (isset($aValues['CalendarId'])) {
            $sCalendarId = $aValues['CalendarId'];
            $sOutput = $this->getManager()->exportCalendarToIcs($sUserPublicId, $sCalendarId);
            if (false !== $sOutput) {
                header('Pragma: public');
                header('Content-Type: text/calendar');
                header('Content-Disposition: attachment; filename="' . $sCalendarId . '.ics";');
                header('Content-Transfer-Encoding: binary');
                echo $sOutput;
            }
        }
    }

    /**
     *
     * @param int $UserId
     * @param string $Name
     * @param string $Description
     * @param string $Color
     * @return array|boolean
     */
    public function CreateCalendar($UserId, $Name, $Description, $Color, $UUID = null)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        $mResult = false;

        $mCalendarId = $this->getManager()->createCalendar($sUserPublicId, $Name, $Description, 1, $Color, $UUID);
        if ($mCalendarId) {
            $oCalendar = $this->getManager()->getCalendar($sUserPublicId, $mCalendarId);
            if ($oCalendar instanceof Classes\Calendar) {
                $mResult = $oCalendar->toResponseArray($sUserPublicId);
            }
        }

        return $mResult;
    }

    public function CreateSubscribedCalendar($UserId, $Name, $Source, $Color, $UUID = null)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        $mResult = false;

        if (!$this->getManager()->validateSubscribedCalebdarSource($Source)) {
            throw new ApiException(Enums\ErrorCodes::InvalidSubscribedIcs);
        }

        $mCalendarId = $this->getManager()->createSubscribedCalendar($sUserPublicId, $Name, $Source, 1, $Color, $UUID);
        if ($mCalendarId) {
            $oCalendar = $this->getManager()->getCalendar($sUserPublicId, $mCalendarId);
            if ($oCalendar instanceof Classes\Calendar) {
                $mResult = $oCalendar->toResponseArray($sUserPublicId);
            }
        }

        return $mResult;
    }

    /**
     *
     * @param int $UserId
     * @param string $Id
     * @param string $Name
     * @param string $Description
     * @param string $Color
     * @return array|boolean
     */
    public function UpdateCalendar($UserId, $Id, $Name, $Description, $Color)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        return $this->getManager()->updateCalendar($sUserPublicId, $Id, $Name, $Description, 0, $Color);
    }

    /**
     *
     * @param int $UserId
     * @param string $Id
     * @param string $Name
     * @param string $Source
     * @param string $Color
     * @return array|boolean
     */
    public function UpdateSubscribedCalendar($UserId, $Id, $Name, $Source, $Color)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

        if (!$this->getManager()->validateSubscribedCalebdarSource($Source)) {
            throw new ApiException(Enums\ErrorCodes::InvalidSubscribedIcs);
        }

        return $this->getManager()->updateSubscribedCalendar($sUserPublicId, $Id, $Name, $Source, 0, $Color);
    }

    /**
     *
     * @param int $UserId
     * @param string $Id
     * @param string $Color
     * @return array|boolean
     */
    public function UpdateCalendarColor($UserId, $Id, $Color)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        return $this->getManager()->updateCalendarColor($sUserPublicId, $Id, $Color);
    }

    /**
     *
     * @param int $UserId
     * @param string $Id
     * @param boolean $IsPublic
     * @param string $Shares
     * @param boolean $ShareToAll
     * @param int $ShareToAllAccess
     * @return array|boolean
     */
    public function UpdateCalendarShare($UserId, $Id, $IsPublic, $Shares, $ShareToAll = false, $ShareToAllAccess = Enums\Permission::Read)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        $aShares = json_decode($Shares, true) ;
        $oUser = null;
        $oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oAuthenticatedUser->Id !== $UserId && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin) {
            $oUser = \Aurora\System\Api::getUserById($UserId);
        } else {
            $oUser = $oAuthenticatedUser;
        }
        $oCalendar = $this->getManager()->getCalendar($oUser->PublicId, $Id);
        if (!$oCalendar) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }
        //Calendar can be shared by owner or user with write access except SharedWithAll calendars
        if ($oCalendar->Owner !== $sUserPublicId
            && $oCalendar->Access !== Enums\Permission::Write) {
            return false;
        }
        // Share calendar to all users
        if ($ShareToAll) {
            $aShares[] = array(
                'email' => $this->getManager()->getTenantUser($oUser),
                'access' => $ShareToAllAccess
            );
        } else {
            $aShares[] = array(
                'email' => $this->getManager()->getTenantUser($oUser),
                'access' => Enums\Permission::RemovePermission
            );
        }

        return $this->getManager()->updateCalendarShares($sUserPublicId, $Id, $aShares);
    }

    /**
     *
     * @param string $Id User publicId
     * @param boolean $IsPublic
     * @param int $UserId
     * @return array|boolean
     */
    public function UpdateCalendarPublic($Id, $IsPublic, $UserId = null)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $oUser = null;
        $oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oAuthenticatedUser->PublicId !== $Id && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::SuperAdmin) {
            $oUser = \Aurora\System\Api::getUserById($UserId);
        }
        return $this->getManager()->publicCalendar($Id, $IsPublic, $oUser);
    }

    /**
     *
     * @param int $UserId
     * @param string $Id
     * @return array|boolean
     */
    public function DeleteCalendar($UserId, $Id)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        return $this->getManager()->deleteCalendar($sUserPublicId, $Id);
    }

    /**
     *
     * @param int $UserId
     * @param string $calendarId
     * @param string $uid
     * @return array|boolean
     */
    public function GetBaseEvent($UserId, $calendarId, $uid)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        return $this->getManager()->getBaseEvent($sUserPublicId, $calendarId, $uid);
    }

    /**
     *
     * @param int $UserId
     * @param array $CalendarIds
     * @param int $Start
     * @param int $End
     * @param boolean $IsPublic
     * @param boolean $Expand
     * @return array|boolean
     */
    public function GetEvents($UserId, $CalendarIds, $Start, $End, $IsPublic, $Expand = true, $DefaultTimeZone = null)
    {
        $mResult = false;
        if ($IsPublic) {
            \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
            $mResult = $this->getManager()->getPublicEvents($CalendarIds, $Start, $End, $Expand, $DefaultTimeZone);
        } else {
            \Aurora\System\Api::CheckAccess($UserId);
            $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
            $mResult = $this->getManager()->getEvents($sUserPublicId, $CalendarIds, $Start, $End);
        }

        $aResult = [];
        if (is_array($mResult)) {
            foreach ($mResult as $event) {
                if (TextUtils::isHtml($event['description'])) {
                    $event['description'] = TextUtils::clearHtml($event['description']);
                }

                if (TextUtils::isHtml($event['location'])) {
                    $event['location'] = TextUtils::clearHtml($event['location']);
                }
                $aResult[] = $event;
            }
        }

        return $aResult;
    }

    /**
     *
     * @param int $UserId
     * @param array $CalendarIds
     * @param int $Start
     * @param int $End
     * @param boolean $Expand
     * @return array|boolean
     */
    public function GetTasks($UserId, $CalendarIds, $Completed = true, $Search = '', $Start = null, $End = null, $Expand = true)
    {
        $mResult = [];
        if ($this->oModuleSettings->AllowTasks) {
            \Aurora\System\Api::CheckAccess($UserId);
            $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

            $mResult = $this->getManager()->getTasks($sUserPublicId, $CalendarIds, $Completed, $Search, $Start, $End, $Expand);
        }

        return $mResult;
    }

    private function _checkUserCalendar($sUserPublicId, $sCalendarId)
    {
        $oCalendar = $this->getManager()->getCalendar($sUserPublicId, $sCalendarId);
        if (!$oCalendar) {
            throw new Exceptions\Exception(Enums\ErrorCodes::CannotFindCalendar);
        } elseif ($oCalendar->Access === Enums\Permission::Read) {
            throw new Exceptions\Exception(Enums\ErrorCodes::NoWriteAccessForCalendar);
        }
    }

    /**
     *
     * @param int $UserId
     * @param string $newCalendarId
     * @param string $subject
     * @param string $description
     * @param string $location
     * @param int $startTS
     * @param int $endTS
     * @param boolean $allDay
     * @param string $alarms
     * @param string $attendees
     * @param string $rrule
     * @param int $selectStart
     * @param int $selectEnd
     * @return array|boolean
     */
    public function CreateEvent(
        $UserId,
        $newCalendarId,
        $subject,
        $description,
        $location,
        $startTS,
        $endTS,
        $allDay,
        $alarms,
        $attendees,
        $rrule,
        $selectStart,
        $selectEnd,
        $type = 'VEVENT',
        $status = false,
        $withDate = true,
        $owner = '',
        $isPrivate = false
    ) {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

        $this->_checkUserCalendar($sUserPublicId, $newCalendarId);

        $now = new \DateTime('now');
        $now->setTime(0, 0);
        if ($selectStart === null) {
            $selectStart = $now->getTimestamp() - 86400 * self::DEFAULT_PERIOD_IN_DAYS;
        }
        if ($selectEnd === null) {
            $selectEnd = $now->getTimestamp() + 86400 * self::DEFAULT_PERIOD_IN_DAYS;
        }

        $oEvent = new Classes\Event();
        $oEvent->IdCalendar = $newCalendarId;
        $oEvent->Name = $subject;
        $oEvent->Description = TextUtils::isHtml($description) ? TextUtils::clearHtml($description) : $description;
        $oEvent->Location = TextUtils::isHtml($location) ? TextUtils::clearHtml($location) : $location;
        $oEvent->IsPrivate = $isPrivate;
        if ($withDate) {
            $oEvent->Start = $startTS;
            $oEvent->End = $endTS;
            $oEvent->AllDay = $allDay;
            $oEvent->Alarms = @json_decode($alarms, true);
            $aRRule = !empty($rrule) ? @json_decode($rrule, true) : false;
            if ($aRRule) {
                $oUser = \Aurora\System\Api::getAuthenticatedUser();
                $oRRule = new Classes\RRule($oUser->DefaultTimeZone);
                $oRRule->Populate($aRRule);
                $oEvent->RRule = $oRRule;
            }
        }
        $oEvent->Attendees = [];
        $oEvent->Type = $type;
        $oEvent->Status = $status && $type === 'VTODO';
        if ($type === 'VTODO') {
            $attendees = json_encode([]);
        }
        $aArgs = [
            'attendees'		=> $attendees,
            'owner'		=> $owner,
            'UserPublicId'	=> $sUserPublicId
        ];
        $this->broadcastEvent(
            'UpdateEventAttendees',
            $aArgs,
            $oEvent
        );

        $mResult = $this->getManager()->createEvent($sUserPublicId, $oEvent);

        if ($mResult) {
            $aArgs = ['Event' => $oEvent];
            $this->broadcastEvent(
                'CreateEvent',
                $aArgs
            );
            $mResult = $this->getManager()->getExpandedEvent($sUserPublicId, $oEvent->IdCalendar, $mResult, $selectStart, $selectEnd);
        }

        return $mResult;
    }

    /**
     *
     * @param int $UserId
     * @param string $CalendarId
     * @param string $EventId
     * @param array $Data
     * @return mixed
     */
    public function CreateEventFromData($UserId, $CalendarId, $EventId, $Data)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

        $this->_checkUserCalendar($sUserPublicId, $CalendarId);

        return $this->getManager()->createEventFromRaw($sUserPublicId, $CalendarId, $EventId, $Data);
    }

    /**
     *
     * @param int $UserId
     * @param string $CalendarId
     * @param string $Subject
     * @return array|boolean
     */
    public function CreateTask($UserId, $CalendarId, $Subject)
    {
        $mResult = false;
        if ($this->oModuleSettings->AllowTasks) {
            \Aurora\System\Api::CheckAccess($UserId);
            $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

            $this->_checkUserCalendar($sUserPublicId, $CalendarId);

            $oEvent = new Classes\Event();
            $oEvent->IdCalendar = $CalendarId;
            $oEvent->Name = $Subject;
            $oEvent->Start = \time();
            $oEvent->End = \time();
            $oEvent->Type = 'VTODO';

            $mResult = $this->getManager()->createEvent($sUserPublicId, $oEvent);
        }

        return $mResult;
    }

    /**
     *
     * @param int $UserId
     * @param string $CalendarId
     * @param string $TaskId
     * @param string $Subject
     * @param string $Status
     * @param bool $WithDate
     * @return array|boolean
     */
    public function UpdateTask($UserId, $CalendarId, $TaskId, $Subject, $Status, $WithDate = false)
    {
        $bResult = false;
        if ($this->oModuleSettings->AllowTasks) {
            \Aurora\System\Api::CheckAccess($UserId);
            $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

            $this->_checkUserCalendar($sUserPublicId, $CalendarId);

            $oEvent = new Classes\Event();
            $oEvent->IdCalendar = $CalendarId;
            $oEvent->Id = $TaskId;
            $oEvent->Name = $Subject;
            $oEvent->Type = 'VTODO';
            $oEvent->Status = $Status ? 'COMPLETED' : '';

            if ($WithDate) {
                $aEvent = $this->GetBaseEvent($UserId, $CalendarId, $TaskId);
                if ($aEvent) {
                    $oEvent->Start = $aEvent['startTS'];
                    $oEvent->End = $aEvent['endTS'];
                }
            }

            if ($this->getManager()->updateEvent($sUserPublicId, $oEvent)) {
                return $this->GetBaseEvent($UserId, $CalendarId, $TaskId);
            }
        }

        return $bResult;
    }


    /**
     *
     * @param int $UserId
     * @param string $newCalendarId
     * @param string $calendarId
     * @param string $uid
     * @param string $subject
     * @param string $description
     * @param string $location
     * @param int $startTS
     * @param int $endTS
     * @param boolean $allDay
     * @param string $alarms
     * @param string $attendees
     * @param string $rrule
     * @param int $allEvents
     * @param string $recurrenceId
     * @param int $selectStart
     * @param int $selectEnd
     * @return array|boolean
     */
    public function UpdateEvent(
        $UserId,
        $newCalendarId,
        $calendarId,
        $uid,
        $subject,
        $description,
        $location,
        $startTS,
        $endTS,
        $allDay,
        $alarms,
        $attendees,
        $rrule,
        $allEvents,
        $recurrenceId,
        $selectStart,
        $selectEnd,
        $type = 'VEVENT',
        $status = false,
        $withDate = true,
        $isPrivate = false,
        $owner = ''
    ) {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        $mResult = false;

        $this->_checkUserCalendar($sUserPublicId, $calendarId);
        if ($calendarId !== $newCalendarId) {
            $this->_checkUserCalendar($sUserPublicId, $newCalendarId);
        }

        $now = new \DateTime('now');
        $now->setTime(0, 0);
        if ($selectStart === null) {
            $selectStart = $now->getTimestamp() - 86400 * self::DEFAULT_PERIOD_IN_DAYS;
        }
        if ($selectEnd === null) {
            $selectEnd = $now->getTimestamp() + 86400 * self::DEFAULT_PERIOD_IN_DAYS;
        }

        $oEvent = new Classes\Event();
        $oEvent->IdCalendar = $calendarId;
        $oEvent->Id = $uid;
        $oEvent->Name = $subject;
        $oEvent->Description = TextUtils::isHtml($description) ? TextUtils::clearHtml($description) : $description;
        $oEvent->Location = TextUtils::isHtml($location) ? TextUtils::clearHtml($location) : $location;
        $oEvent->IsPrivate = $isPrivate;
        if ($withDate) {
            $oEvent->Start = $startTS;
            $oEvent->End = $endTS;
            $oEvent->AllDay = $allDay;
            if (isset($alarms)) {
                $oEvent->Alarms = @json_decode($alarms, true);
            }
            if (isset($rrule)) {
                $aRRule = @json_decode($rrule, true);
            }
            if ($aRRule) {
                $oUser = \Aurora\System\Api::getAuthenticatedUser();
                $oRRule = new Classes\RRule($oUser->DefaultTimeZone);
                $oRRule->Populate($aRRule);
                $oEvent->RRule = $oRRule;
            }
        }
        $oEvent->Attendees = [];
        $oEvent->Type = $type;
        if ($type === 'VTODO') {
            $attendees = json_encode([]);
        }
        $aArgs = [
            'attendees'		=> $attendees,
            'owner'		=> $owner,
            'UserPublicId'	=> $sUserPublicId
        ];
        $this->broadcastEvent(
            'UpdateEventAttendees',
            $aArgs,
            $oEvent
        );
        if (!empty($status)) {
            $oEvent->Status = $status && $type === 'VTODO';
        }

        if ($allEvents === 1) {
            $mResult = $this->getManager()->updateExclusion($sUserPublicId, $oEvent, $recurrenceId);
        } else {
            $mResult = $this->getManager()->updateEvent($sUserPublicId, $oEvent);
            if ($mResult && $newCalendarId !== $oEvent->IdCalendar) {
                $mResult = $this->getManager()->moveEvent($sUserPublicId, $oEvent->IdCalendar, $newCalendarId, $oEvent->Id);
                $oEvent->IdCalendar = $newCalendarId;
            }
        }
        if ($mResult) {
            $aArgs = ['Event' => $oEvent];
            $this->broadcastEvent(
                'CreateEvent',
                $aArgs
            );
            $mResult = $this->getManager()->getExpandedEvent($sUserPublicId, $oEvent->IdCalendar, $oEvent->Id, $selectStart, $selectEnd);
        }

        return $mResult;
    }

    /**
     *
     * @param int $UserId
     * @param string $calendarId
     * @param string $uid
     * @param boolean $allEvents
     * @param string $recurrenceId
     * @return array|boolean
     */
    public function DeleteEvent($UserId, $calendarId, $uid, $allEvents, $recurrenceId)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

        $this->_checkUserCalendar($sUserPublicId, $calendarId);

        $mResult = false;
        if ($sUserPublicId) {
            if ($allEvents === 1) {
                $oEvent = new Classes\Event();
                $oEvent->IdCalendar = $calendarId;
                $oEvent->Id = $uid;
                $mResult = $this->getManager()->updateExclusion($sUserPublicId, $oEvent, $recurrenceId, true);
            } else {
                $mResult = $this->getManager()->deleteEvent($sUserPublicId, $calendarId, $uid);
            }
        }

        return $mResult;
    }

    /**
     *
     * @param int $UserId
     * @param string $CalendarId
     * @param string $File
     * @return array|boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function AddEventsFromFile($UserId, $CalendarId, $File)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);
        $mResult = false;

        $this->_checkUserCalendar($sUserPublicId, $CalendarId);

        if (empty($CalendarId) || empty($File)) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
        }

        $sData = $this->getFilecacheManager()->get($sUserPublicId, $File, '', self::GetName());
        if (!empty($sData)) {
            $mResult = $this->getManager()->createEventFromRaw($sUserPublicId, $CalendarId, null, $sData);
        }

        return $mResult;
    }

    public function EntryCalendarPub()
    {
        $sResult = '';

        $oApiIntegrator = \Aurora\System\Managers\Integrator::getInstance();

        if ($oApiIntegrator) {
            \Aurora\Modules\CoreWebclient\Module::Decorator()->SetHtmlOutputHeaders();

            if (!strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'firefox')) {
                @\header('Last-Modified: ' . \gmdate('D, d M Y H:i:s') . ' GMT');
            }

            $oSettings = &\Aurora\System\Api::GetSettings();
            if (($oSettings->CacheCtrl && isset($_COOKIE['aft-cache-ctrl']))) {
                @\setcookie(
                    'aft-cache-ctrl',
                    '',
                    \strtotime('-1 hour'),
                    \Aurora\System\Api::getCookiePath(),
                    null,
                    \Aurora\System\Api::getCookieSecure()
                );
                \MailSo\Base\Http::NewInstance()->StatusHeader(304);
                exit();
            }
            $oCoreClientModule = \Aurora\System\Api::GetModule('CoreWebclient');
            if ($oCoreClientModule instanceof \Aurora\System\Module\AbstractModule) {
                $sResult = file_get_contents($oCoreClientModule->GetPath() . '/templates/Index.html');
                if (is_string($sResult)) {
                    $sFrameOptions = $oSettings->XFrameOptions;
                    if (0 < \strlen($sFrameOptions)) {
                        @\header('X-Frame-Options: ' . $sFrameOptions);
                    }

                    $sAuthToken = isset($_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY]) ? $_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY] : '';
                    $sResult = strtr($sResult, array(
                        '{{AppVersion}}' => \Aurora\System\Application::GetVersion(),
                        '{{IntegratorDir}}' => $oApiIntegrator->isRtl() ? 'rtl' : 'ltr',
                        '{{IntegratorLinks}}' => $oApiIntegrator->buildHeadersLink(),
                        '{{IntegratorBody}}' => $oApiIntegrator->buildBody(
                            array(
                                'public_app' => true,
                                'modules_list' => $oApiIntegrator->GetModulesForEntry('CalendarWebclient')
                            )
                        )
                    ));
                }
            }
        }
        return $sResult;
    }

    /**
     *
     * @param int $UserId
     * @param string $Data
     * @param string $FromEmail
     * @return boolean
     */
    public function ProcessICS($UserId, $Data, $FromEmail)
    {
        \Aurora\System\Api::CheckAccess($UserId);

        return $this->getManager()->processICS($UserId, $Data, $FromEmail);
    }

    /**
     *
     * @param int $UserId
     * @param array $UploadData
     * @param string $CalendarID
     * @return array
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function UploadCalendar($UserId, $UploadData, $CalendarID)
    {
        \Aurora\System\Api::CheckAccess($UserId);
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($UserId);

        $sCalendarId = !empty($CalendarID) ? $CalendarID : '';

        $sError = '';
        $aResponse = array(
            'ImportedCount' => 0
        );

        if (is_array($UploadData)) {
            $bIsIcsExtension  = strtolower(pathinfo($UploadData['name'], PATHINFO_EXTENSION)) === 'ics';

            if ($bIsIcsExtension) {
                $sSavedName = 'import-post-' . md5($UploadData['name'] . $UploadData['tmp_name']);
                if ($this->getFilecacheManager()->moveUploadedFile($sUserPublicId, $sSavedName, $UploadData['tmp_name'], '', self::GetName())) {
                    $iImportedCount = $this->getManager()->importToCalendarFromIcs(
                        $sUserPublicId,
                        $sCalendarId,
                        $this->getFilecacheManager()->generateFullFilePath($sUserPublicId, $sSavedName, '', self::GetName())
                    );

                    if (false !== $iImportedCount && -1 !== $iImportedCount) {
                        $aResponse['ImportedCount'] = $iImportedCount;
                    } else {
                        $sError = 'unknown';
                    }

                    $this->getFilecacheManager()->clear($sUserPublicId, $sSavedName, '', self::GetName());
                } else {
                    $sError = 'unknown';
                }
            } else {
                throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::IncorrectFileExtension);
            }
        } else {
            $sError = 'unknown';
        }

        if (0 < strlen($sError)) {
            $aResponse['Error'] = $sError;
        }

        return $aResponse;
    }

    public function onGetBodyStructureParts($aParts, &$aResult)
    {
        foreach ($aParts as $oPart) {
            if ($oPart instanceof \MailSo\Imap\BodyStructure &&
                    $oPart->ContentType() === 'text/calendar') {
                $aResult[] = $oPart;
                break;
            }
        }
    }

    public function onExtendMessageData($aData, &$oMessage)
    {
        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        $sUserPublicId = \Aurora\System\Api::getUserPublicIdById($oUser->Id);
        $sFromEmail = '';
        $oFromCollection = $oMessage->getFrom();
        if ($oFromCollection && 0 < $oFromCollection->Count()) {
            $oFrom = &$oFromCollection->GetByIndex(0);
            if ($oFrom) {
                $sFromEmail = trim($oFrom->GetEmail());
            }
        }
        foreach ($aData as $aDataItem) {
            if ($aDataItem['Part'] instanceof \MailSo\Imap\BodyStructure && $aDataItem['Part']->ContentType() === 'text/calendar') {
                $sData = $aDataItem['Data'];
                if (!empty($sData)) {
                    try {
                        $mResult = $this->getManager()->processICS($sUserPublicId, $sData, $sFromEmail);
                    } catch (\Exception $oEx) {
                        $mResult = false;
                    }
                    if (is_array($mResult) && !empty($mResult['Action']) && !empty($mResult['Body'])) {
                        $sTemptFile = md5($sFromEmail . $sData) . '.ics';
                        if ($this->getFilecacheManager()->put($sUserPublicId, $sTemptFile, $sData, '', self::GetName())) {
                            $oIcs = Classes\Ics::createInstance();

                            $mResult['Description'] = !empty($mResult['Description']) ? $mResult['Description'] : '';
                            $mResult['Location'] = !empty($mResult['Location']) ? $mResult['Location'] : '';

                            $mResult['Description'] = TextUtils::isHtml($mResult['Description']) ? TextUtils::clearHtml($mResult['Description']) : $mResult['Description'];
                            $mResult['Location'] = TextUtils::isHtml($mResult['Location']) ? TextUtils::clearHtml($mResult['Location']) : $mResult['Location'];

                            $oIcs->Uid = $mResult['UID'];
                            $oIcs->Sequence = $mResult['Sequence'];
                            $oIcs->File = $sTemptFile;
                            $oIcs->Type = 'SAVE';
                            $oIcs->Attendee = null;
                            $oIcs->Location = $mResult['Location'];
                            $oIcs->Description = $mResult['Description'];
                            $oIcs->Summary = !empty($mResult['Summary']) ? $mResult['Summary'] : '';
                            $oIcs->When = !empty($mResult['When']) ? $mResult['When'] : '';
                            $oIcs->CalendarId = !empty($mResult['CalendarId']) ? $mResult['CalendarId'] : '';
                            $oIcs->AttendeeList = $mResult['AttendeeList'];
                            $oIcs->Organizer = $mResult['Organizer'];

                            $this->broadcastEvent(
                                'CreateIcs',
                                $mResult,
                                $oIcs
                            );

                            $oMessage->addExtend('ICAL', $oIcs);
                        } else {
                            \Aurora\System\Api::Log('Can\'t save temp file "' . $sTemptFile . '"', \Aurora\System\Enums\LogLevel::Error);
                        }
                    }
                }
            }
        }
    }

    public function onGetMobileSyncInfo($aArgs, &$mResult)
    {
        /** @var \Aurora\Modules\Dav\Module */
        $oDavModule = \Aurora\Modules\Dav\Module::Decorator();
        $iUserId = \Aurora\System\Api::getAuthenticatedUserId();
        $aCalendars = self::Decorator()->GetCalendars($iUserId);

        if (isset($aCalendars['Calendars']) && is_array($aCalendars['Calendars']) && 0 < count($aCalendars['Calendars'])) {
            foreach ($aCalendars['Calendars'] as $oCalendar) {
                if ($oCalendar instanceof Classes\Calendar) {
                    $mResult['Dav']['Calendars'][] = array(
                        'Name' => $oCalendar->DisplayName,
                        'Url' => rtrim($oDavModule->GetServerUrl() . $oCalendar->Url, "/") . "/"
                    );
                }
            }
        }
    }

    public function onBeforeDeleteUser($aArgs, &$mResult)
    {
        if (isset($aArgs['UserId'])) {
            $this->oUserForDelete = \Aurora\System\Api::getUserById($aArgs['UserId']);
        }
    }

    public function onAfterDeleteUser($aArgs, &$mResult)
    {
        $sUserPublicId = $this->oUserForDelete instanceof \Aurora\Modules\Core\Models\User ? $this->oUserForDelete->PublicId : null;
        if ($sUserPublicId) {
            $this->getManager()->deletePrincipalCalendars($sUserPublicId);
        }
    }
}
