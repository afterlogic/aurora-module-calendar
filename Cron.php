<?php
namespace Aurora\Modules\Calendar;

use Afterlogic\DAV\Backend;

require_once dirname(__file__)."/../../system/autoload.php";
\Aurora\System\Api::Init();

class Reminder
{
    private $oApiUsersManager;

    private $oApiCalendarManager;

    private $oApiMailManager;

    private $oApiAccountsManager;

    private $oCalendarModule;

    /**
     * @var array
     */
    private $aUsers;

    /**
     * @var array
     */
    private $aCalendars;

    /**
     * @var string
     */
    private $sCurRunFilePath;

    public function __construct()
    {
        $this->aUsers = array();
        $this->aCalendars = array();
        $this->sCurRunFilePath = \Aurora\System\Api::DataPath().'/reminder-run';

        $oMailModule =  \Aurora\System\Api::GetModule('Mail');
        $this->oCalendarModule = \Aurora\System\Api::GetModule('Calendar');

        $this->oApiUsersManager = \Aurora\System\Api::GetModule('Core')->getUsersManager();
        $this->oApiCalendarManager = $this->oCalendarModule->getManager();
        $this->oApiMailManager = $oMailModule->getMailManager();
        $this->oApiAccountsManager = $oMailModule->getAccountsManager();
    }

    public static function NewInstance()
    {
        return new self();
    }

    /**
     * @param string $sKey
     * @param Aurora\Modules\Core\Classes\User $oUser = null
     * @param array $aParams = null
     *
     * @return string
     */
    private function i18n($sKey, $oUser = null, $aParams = null, $iMinutes = null)
    {
        return $this->oCalendarModule->I18N($sKey, $aParams, $iMinutes, $oUser->UUID);
    }

    /**
     * @param string $sLogin
     *
     * @return CAccount
     */
    private function &getUser($sLogin)
    {
        $mResult = null;

        if (!isset($this->aUsers[$sLogin])) {
            $this->aUsers[$sLogin] = $this->oApiUsersManager->getUserByPublicId($sLogin);
        }

        $mResult =& $this->aUsers[$sLogin];

        if (is_array($this->aUsers[$sLogin]) && 30 < count($this->aUsers[$sLogin])) {
            $this->aUsers = array_slice($this->aUsers, -30);
        }

        return $mResult;
    }

    /**
     * @param Aurora\Modules\Core\Classes\User $oUser
     * @param string $sUri
     *
     * @return CalendarInfo|null
     */
    private function &getCalendar($oUser, $sUri)
    {
        $mResult = null;
        if ($this->oApiCalendarManager) {
            if (!isset($this->aCalendars[$sUri])) {
                $this->aCalendars[$sUri] = $this->oApiCalendarManager->getCalendar($oUser->PublicId, $sUri);
            }

            if (isset($this->aCalendars[$sUri])) {
                $mResult =& $this->aCalendars[$sUri];
            }
        }

        return $mResult;
    }

    /**
     * @param Aurora\Modules\Core\Classes\User $oUser
     * @param string $sCalendarUri
     *
     * @return CalendarInfo|null
     */
    private function getUserCalendarInstance($oUser, $sCalendarUri)
    {
        $mResult = null;
        $sMainCalendarId = Backend::CalDAV()->getCalendarIdByUri($sCalendarUri);
        if ($this->oApiCalendarManager) {
            $userCalendars = $this->oApiCalendarManager->getSharedCalendars($oUser->PublicId);
            
            foreach ($userCalendars as $oCalendar) {
                $sCalendarId = Backend::CalDAV()->getCalendarIdByUri($oCalendar->Id);
                if ($sCalendarId === $sMainCalendarId) {
                    $mResult = $oCalendar;
                    break;
                }
            }
        }

        return $mResult;
    }

    /**
     * @param Aurora\Modules\Core\Classes\User $oUser
     * @param string $sCalendarUri
     *
     * @return CalendarInfo|null
     */
    private function isCalendarMutedForUser($oUser, $sCalendarUri)
    {
        $bCaledarMuted = false;
        $aMutedCalendars = json_decode($oUser->{'Calendar::MutedCalendarIds'});
        if (is_array($aMutedCalendars)) {
            $oUserCalendarInstance = $this->getUserCalendarInstance($oUser, $sCalendarUri);
            $bCaledarMuted = in_array($oUserCalendarInstance->Id, $aMutedCalendars);
        }

        return $bCaledarMuted;
    }

    /**
     * @param Aurora\Modules\Core\Classes\User $oUser
     * @param string $sEventName
     * @param string $sDateStr
     * @param string $sCalendarName
     * @param string $sEventText
     * @param string $sCalendarColor
     *
     * @return string
     */
    private function createBodyHtml($oUser, $sEventName, $sDateStr, $sCalendarName, $sEventText, $sCalendarColor)
    {
        $sEventText = nl2br($sEventText);

        return sprintf(
            '
			<div style="padding: 10px; font-size: 12px; text-align: center; word-wrap: break-word;">
				<div style="border: 4px solid %s; padding: 15px; width: 370px;">
					<h2 style="margin: 5px; font-size: 18px; line-height: 1.4;">%s</h2>
					<span>%s%s</span><br/>
					<span>%s: %s</span><br/><br/>
					<span>%s</span><br/>
				</div>
				<p style="color:#667766; width: 400px; font-size: 10px;">%s</p>
			</div>',
            $sCalendarColor,
            $sEventName,
            ucfirst($this->i18n('EVENT_BEGIN', $oUser)),
            $sDateStr,
            $this->i18n('CALENDAR', $oUser),
            $sCalendarName,
            $sEventText,
            $this->i18n('EMAIL_EXPLANATION', $oUser, array(
                'EMAIL' => '<a href="mailto:'.$oUser->PublicId.'">'.$oUser->PublicId.'</a>',
                'CALENDAR_NAME' => $sCalendarName
            ))
        );
    }

    /**
     * @param Aurora\Modules\Core\Classes\User $oUser
     * @param string $sEventName
     * @param string $sDateStr
     * @param string $sCalendarName
     * @param string $sEventText
     *
     * @return string
     */
    private function createBodyText($oUser, $sEventName, $sDateStr, $sCalendarName, $sEventText)
    {
        return sprintf(
            "%s\r\n\r\n%s%s\r\n\r\n%s: %s %s\r\n\r\n%s",
            $sEventName,
            ucfirst($this->i18n('EVENT_BEGIN', $oUser)),
            $sDateStr,
            $this->i18n('CALENDAR', $oUser),
            $sCalendarName,
            $sEventText,
            $this->i18n('EMAIL_EXPLANATION', $oUser, array(
                'EMAIL' => '<a href="mailto:'.$oUser->PublicId.'">'.$oUser->PublicId.'</a>',
                'CALENDAR_NAME' => $sCalendarName
            ))
        );
    }

    /**
     * @param Aurora\Modules\Core\Classes\User $oUser
     * @param string $sSubject
     * @param string $mHtml = null
     * @param string $mText = null
     *
     * @return \MailSo\Mime\Message
     */
    private function createMessage($oUser, $sSubject, $mHtml = null, $mText = null)
    {
        $oMessage = \MailSo\Mime\Message::NewInstance();
        $oMessage->RegenerateMessageId();

        //		$sXMailer = \Aurora\System\Api::GetConf('webmail.xmailer-value', '');
        //		if (0 < strlen($sXMailer))
        //		{
        //			$oMessage->SetXMailer($sXMailer);
        //		}

        $oMessage
            ->SetFrom(\MailSo\Mime\Email::NewInstance($oUser->PublicId))
            ->SetSubject($sSubject)
        ;

        $oToEmails = \MailSo\Mime\EmailCollection::NewInstance($oUser->PublicId);
        if ($oToEmails && $oToEmails->Count()) {
            $oMessage->SetTo($oToEmails);
        }

        if ($mHtml !== null) {
            $oMessage->AddText($mHtml, true);
        }

        if ($mText !== null) {
            $oMessage->AddText($mText, false);
        }

        return $oMessage;
    }

    /**
     *
     * @param Aurora\Modules\Core\Classes\User $oUser
     * @param string $sSubject
     * @param string $sEventName
     * @param string $sDate
     * @param string $sCalendarName
     * @param string $sEventText
     * @param string $sCalendarColor
     *
     * @return bool
     */
    private function sendMessage($oUser, $sSubject, $sEventName, $sDate, $sCalendarName, $sEventText, $sCalendarColor)
    {
        $oMessage = $this->createMessage(
            $oUser,
            $sSubject,
            $this->createBodyHtml($oUser, $sEventName, $sDate, $sCalendarName, $sEventText, $sCalendarColor),
            $this->createBodyText($oUser, $sEventName, $sDate, $sCalendarName, $sEventText)
        );

        try {
            $oAccount = $this->oApiAccountsManager->getAccountUsedToAuthorize($oUser->PublicId);
            if (!$oAccount instanceof \Aurora\Modules\Mail\Classes\Account) {
                return false;
            }
            return $this->oApiMailManager->sendMessage($oAccount, $oMessage);
        } catch (\Exception $oException) {
            \Aurora\System\Api::Log('MessageSend Exception', \Aurora\System\Enums\LogLevel::Error, 'cron-');
            \Aurora\System\Api::LogException($oException, \Aurora\System\Enums\LogLevel::Error, 'cron-');
        }

        return false;
    }

    /**
     * @param \Aurora\Modules\Core\Classes\User $oUser
     * @param string $sEventStart
     * @param int $iEventStartTS
     * @param string $sEventName
     * @param string $sDate
     * @param int $iNowTS
     * @param bool $bAllDay = false
     *
     * @return string
     */
    private function getSubject($oUser, $sEventStart, $iEventStartTS, $sEventName, $sDate, $iNowTS, $bAllDay = false)
    {
        $sSubject = '';

        if ($bAllDay) {
            $oEventStart = new \DateTime("@$iEventStartTS", new \DateTimeZone('UTC'));
            $oEventStart->setTimezone(new \DateTimeZone($oUser->DefaultTimeZone ?: 'UTC'));
            $iEventStartTS = $oEventStart->getTimestamp() - $oEventStart->getOffset();
        }

        $iMinutes = round(($iEventStartTS - $iNowTS) / 60);

        if ($iMinutes > 0 && $iMinutes < 60) {
            $sSubject = $this->i18n('SUBJECT_MINUTES_PLURAL', $oUser, array(
                'EVENT_NAME' => $sEventName,
                'DATE' => date('G:i', strtotime($sEventStart)),
                'COUNT' => $iMinutes
            ), $iMinutes);
        } elseif ($iMinutes >= 60 && $iMinutes < 1440) {
            $sSubject = $this->i18n('SUBJECT_HOURS_PLURAL', $oUser, array(
                'EVENT_NAME' => $sEventName,
                'DATE' => date('G:i', strtotime($sEventStart)),
                'COUNT' => round($iMinutes / 60)
            ), round($iMinutes / 60));
        } elseif ($iMinutes >= 1440 && $iMinutes < 10080) {
            $sSubject = $this->i18n('SUBJECT_DAYS_PLURAL', $oUser, array(
                'EVENT_NAME' => $sEventName,
                'DATE' => $sDate,
                'COUNT' => round($iMinutes / 1440)
            ), round($iMinutes / 1440));
        } elseif ($iMinutes >= 10080) {
            $sSubject = $this->i18n('SUBJECT_WEEKS_PLURAL', $oUser, array(
                'EVENT_NAME' => $sEventName,
                'DATE' => $sDate,
                'COUNT' => round($iMinutes / 10080)
            ), round($iMinutes / 10080));
        } else {
            $sSubject = $this->i18n('SUBJECT', $oUser, array(
                'EVENT_NAME' => $sEventName,
                'DATE' => $sDate
            ));
        }

        return $sSubject;
    }

    /**
     * @param \Aurora\Modules\Core\Classes\User $oUser
     *
     * @return string
     */
    private function getDateTimeFormat($oUser)
    {
        $sDateFormat = 'm/d/Y';
        $sTimeFormat = 'h:i A';

        if ($oUser->DateFormat === \Aurora\System\Enums\DateFormat::DDMMYYYY) {
            $sDateFormat = 'd/m/Y';
        } elseif ($oUser->DateFormat === \Aurora\System\Enums\DateFormat::MMDDYYYY) {
            $sDateFormat = 'm/d/Y';
        } elseif ($oUser->DateFormat === \Aurora\System\Enums\DateFormat::DD_MONTH_YYYY) {
            $sDateFormat = 'd m Y';
        } elseif ($oUser->DateFormat === \Aurora\System\Enums\DateFormat::MMDDYY) {
            $sDateFormat = 'm/d/y';
        } elseif ($oUser->DateFormat === \Aurora\System\Enums\DateFormat::DDMMYY) {
            $sDateFormat = 'd/m/Y';
        }

        if ($oUser->TimeFormat == \Aurora\System\Enums\TimeFormat::F24) {
            $sTimeFormat = 'H:i';
        }

        return $sDateFormat.' '.$sTimeFormat;
    }

    /**
     * Get reminders for events that are starting in the next 30 minutes.
     *
     * @param int $iStart
     * @param int $iEnd
     *
     * @return array
     */
    public function GetReminders($iStart, $iEnd)
    {
        $aReminders = $this->oApiCalendarManager->getReminders($iStart, $iEnd);
        $aEvents = array();

        \Aurora\System\Api::Log('Reminder entries found: ' . count($aReminders), \Aurora\System\Enums\LogLevel::Full, 'cron-');
        
        if ($aReminders && is_array($aReminders) && count($aReminders) > 0) {
            $aCacheEvents = array();
            foreach ($aReminders as $aReminder) {
                \Aurora\System\Api::Log('Reminder details: '. $aReminder['time'] . '|' . $aReminder['user'] . '|' . $aReminder['calendaruri'] . '|' . $aReminder['eventid'], \Aurora\System\Enums\LogLevel::Full, 'cron-');
                $oUser = $this->getUser($aReminder['user']);

                $sCalendarUri = $aReminder['calendaruri'];
                $sEventId = $aReminder['eventid'];
                $pathInfo = pathinfo($sEventId);
                if (isset($pathInfo['extension']) && strtolower($pathInfo['extension']) === 'ics') {
                    $sEventId = $pathInfo['filename'];
                }
                $iStartTime = $aReminder['starttime'];
                $iReminderTime = $aReminder['time'];

                if (!isset($aCacheEvents[$sEventId]) && isset($oUser)) {
                    $aCacheEvents[$sEventId]['data'] = $this->oApiCalendarManager->getEvent($oUser->PublicId, $sCalendarUri, $sEventId);

                    $dt = new \DateTime();
                    $dt->setTimestamp($iStartTime);
                    $oDefaultTimeZone = new \DateTimeZone($oUser->DefaultTimeZone ?: 'UTC');
                    $dt->setTimezone($oDefaultTimeZone);

                    $aEventClear = [];
                    if (is_array($aCacheEvents[$sEventId]['data'])) {
                        $CurrentEvent = null;
                        foreach ($aCacheEvents[$sEventId]['data'] as $key =>$aEvent) {
                            if (is_int($key)) {
                                if (empty($CurrentEvent)) {
                                    $CurrentEvent = $aEvent;
                                } elseif (isset($aEvent['excluded']) && $this->EventHasReminder($aEvent, $iReminderTime)) {
                                    $CurrentEvent = $aEvent;
                                }
                                unset($aCacheEvents[$sEventId]['data'][$key]);
                            }
                        }
                        if (!empty($CurrentEvent)) {
                            $aCacheEvents[$sEventId]['data'][0] = $CurrentEvent;
                        }
                    }
                    $aCacheEvents[$sEventId]['time'] = $dt->format($this->getDateTimeFormat($oUser));
                }

                if (isset($aCacheEvents[$sEventId])) {
                    $aEvents[$aReminder['user']][$sCalendarUri][$sEventId] = $aCacheEvents[$sEventId];
                }
            }
        }
        return $aEvents;
    }

    /**
     * Check if a reminder exists for a specific user and event.
     *
     * @param array $aReminders
     * @param string $sUserPublicId
     * @param string $sEventId
     *
     * @return bool
     */
    public function hasReminderForUser($aReminders, $sUserPublicId, $sEventId)
    {
        foreach ($aReminders as $sEmail => $aUserCalendars) {
            foreach ($aUserCalendars as $sCalendarUri => $aUserEvents) {
                foreach ($aUserEvents as $sUserEventId => $aUserEvent) {
                    if ($sEmail === $sUserPublicId && $sUserEventId === $sEventId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if the event has a reminder set for the specified time.
     *
     * @param array $aEvent
     * @param int $iReminderTime
     *
     * @return bool
     */
    public function EventHasReminder($aEvent, $iReminderTime)
    {
        foreach ($aEvent['alarms'] as $iAlarm) {
            if ($aEvent['startTS'] - $iAlarm * 60 === (int) $iReminderTime) {
                return true;
            }
        }
        return false;
    }

    public function Execute()
    {
        \Aurora\System\Api::Log('---------- Start cron script', \Aurora\System\Enums\LogLevel::Full, 'cron-');

        $oTimeZoneUTC = new \DateTimeZone('UTC');
        $oNowDT_UTC = new \DateTimeImmutable('19.08.2025 13:10:00', $oTimeZoneUTC);

        $iNowTS = $oNowDT_UTC->getTimestamp();

        $oStartDT_UTC = $oNowDT_UTC->sub(new \DateInterval('PT30M'));

        // Check if the current run file exists and read the timestamp from it
        if (file_exists($this->sCurRunFilePath)) {
            $handle = fopen($this->sCurRunFilePath, 'r');
            $sCurRunFileTS = fread($handle, 10);
            if (!empty($sCurRunFileTS) && is_numeric($sCurRunFileTS)) {
                $oStartDT_UTC = new \DateTimeImmutable("@$sCurRunFileTS");
            }
        }

        $iStartTS = $oStartDT_UTC->getTimestamp();

        // If the current time is greater than or equal to the start time, proceed with reminders
        if ($iNowTS >= $iStartTS) {
            \Aurora\System\Api::Log('Start time: ' . $iStartTS . ' ' . $oStartDT_UTC->format('r'), \Aurora\System\Enums\LogLevel::Full, 'cron-');
            \Aurora\System\Api::Log('End time:   ' . $iNowTS . ' ' . $oNowDT_UTC->format('r'), \Aurora\System\Enums\LogLevel::Full, 'cron-');

            // Get reminders for events that are starting in the next 30 minutes
            $aEvents = $this->GetReminders($iStartTS, $iNowTS);
            
            \Aurora\System\Api::Log('Events found: ' . count($aEvents), \Aurora\System\Enums\LogLevel::Full, 'cron-');
            \Aurora\System\Api::Log('Recipients: ' . implode(',', array_keys($aEvents)), \Aurora\System\Enums\LogLevel::Full, 'cron-');

            foreach ($aEvents as $sEmail => $aUserCalendars) {
                foreach ($aUserCalendars as $sCalendarUri => $aUserEvents) {
                    foreach ($aUserEvents as $aUserEvent) {
                        $aSubEvents = $aUserEvent['data'];

                        if (isset($aSubEvents, $aSubEvents['vcal'])) {
                            $vCal = $aSubEvents['vcal'];
                            foreach ($aSubEvents as $mKey => $aEvent) {
                                if ($mKey !== 'vcal') {
                                    $oUser = $this->getUser($sEmail);
                                    $oCalendar = $this->getCalendar($oUser, $sCalendarUri);

                                    if ($oCalendar) {
                                        $sEventId = $aEvent['uid'];
                                        $sEventStart = $aEvent['start'];
                                        $iEventStartTS = $aEvent['startTS'];
                                        $sEventName = $aEvent['subject'];
                                        $sEventText = $aEvent['description'];
                                        $bAllDay = $aEvent['allDay'];
                                        $sDate = $aUserEvent['time'];

                                        // attendees list is needed later to check if particular user is an attendee
                                        $aAttendees = [];

                                        // getting attendees from the event
                                        $aBaseEvents = $vCal->getBaseComponents('VEVENT');
                                        if (isset($aBaseEvents[0]) && isset($aBaseEvents[0]->ATTENDEE)) {
                                            foreach ($aBaseEvents[0]->ATTENDEE as $attendee) {
                                                $aAttendees[] = str_replace('mailto:', '', strtolower((string)$attendee));
                                            }
                                        }

                                        if (isset($vCal->getBaseComponent('VEVENT')->RRULE) && $iEventStartTS < $iNowTS) { // the correct date for repeatable events
                                            if (isset($aBaseEvents[0])) {
                                                $oEventStartDT = \Aurora\Modules\Calendar\Classes\Helper::getNextRepeat($oNowDT_UTC, $aBaseEvents[0]);
                                                $sEventStart = $oEventStartDT->format('Y-m-d H:i:s');
                                                if ($bAllDay) {
                                                    $sDate = $oEventStartDT->format('d m Y');
                                                } else {
                                                    $sDate = $oEventStartDT->format('d m Y H:i');
                                                }
                                                $iEventStartTS = $oEventStartDT->getTimestamp();
                                            }
                                        }

                                        $sSubject = $this->getSubject($oUser, $sEventStart, $iEventStartTS, $sEventName, $sDate, $iNowTS, $bAllDay);

                                        $aUsers = array();

                                        // getting users for sending message to

                                        // getting the list of users whom this calendar is shared with
                                        $aCalendarUsers = $this->oApiCalendarManager->getCalendarUsers($oUser->PublicId, $oCalendar);

                                        // filtering out user who muted 
                                        if (0 < count($aCalendarUsers)) {
                                            foreach ($aCalendarUsers as $aCalendarUser) {
                                                $oCalendarUser = $this->getUser($aCalendarUser['email']);
                                                if ($oCalendarUser) {
                                                    // check if calendar is muted for the user
                                                    $bCaledarMuted = $this->isCalendarMutedForUser($oCalendarUser, $oCalendar->Id);

                                                    // check if user is an attendee of the event
                                                    $bUserIsAttendee = $oUser->PublicId !== $oCalendarUser->PublicId && in_array(strtolower($oCalendarUser->PublicId), $aAttendees);

                                                    // check if user already has the event with reminder in own calendar
                                                    $bHasReminderForUser = $this->hasReminderForUser($aEvents, $oCalendarUser->PublicId, $sEventId);

                                                    // add user to the list if calendar isn't muted or user is on the list of attendees
                                                    if (!$bCaledarMuted && (!$bUserIsAttendee || ($bUserIsAttendee && !$bHasReminderForUser))) {
                                                        $aUsers[$oCalendarUser->EntityId] = $oCalendarUser;
                                                    }
                                                }
                                            }
                                        }

                                        foreach ($aUsers as $oUserItem) {
                                            $bIsMessageSent = $this->sendMessage($oUserItem, $sSubject, $sEventName, $sDate, $oCalendar->DisplayName, $sEventText, $oCalendar->Color);
                                            if ($bIsMessageSent) {
                                                $this->oApiCalendarManager->updateReminder($oUserItem->PublicId, $sCalendarUri, $sEventId . '.ics', $vCal->serialize());
                                                \Aurora\System\Api::Log('Send reminder for event: \''.$sEventName.'\' started on \''.$sDate.'\' to \''.$oUserItem->PublicId.'\'', \Aurora\System\Enums\LogLevel::Full, 'cron-');
                                            } else {
                                                \Aurora\System\Api::Log('Send reminder for event: FAILED!', \Aurora\System\Enums\LogLevel::Full, 'cron-');
                                            }
                                        }
                                    } else {
                                        \Aurora\System\Api::Log('Calendar '.$sCalendarUri.' not found!', \Aurora\System\Enums\LogLevel::Full, 'cron-');
                                    }
                                }
                            }
                        }
                    }
                }
            }

            file_put_contents($this->sCurRunFilePath, $iNowTS);
        }

        \Aurora\System\Api::Log('---------- End cron script', \Aurora\System\Enums\LogLevel::Full, 'cron-');
    }
}

$iTimer = microtime(true);

Reminder::NewInstance()->Execute();

\Aurora\System\Api::Log('Cron execution time: '.(microtime(true) - $iTimer).' sec.', \Aurora\System\Enums\LogLevel::Full, 'cron-');
