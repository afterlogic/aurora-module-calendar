<?php
namespace Aurora\Modules\Calendar;

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

		$this->oApiUsersManager = \Aurora\System\Api::GetModule('Core')->oApiUsersManager ;
		$this->oApiCalendarManager = $this->oCalendarModule->oApiCalendarManager;
		$this->oApiMailManager = $oMailModule->oApiMailManager;
		$this->oApiAccountsManager = $oMailModule->oApiAccountsManager;
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

		if (!isset($this->aUsers[$sLogin]))
		{
			$this->aUsers[$sLogin] = $this->oApiUsersManager->getUserByPublicId($sLogin);
		}

		$mResult =& $this->aUsers[$sLogin];

		if (30 < count($this->aUsers[$sLogin]))
		{
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
		if ($this->oApiCalendarManager)
		{
			if (!isset($this->aCalendars[$sUri]))
			{
				$this->aCalendars[$sUri] = $this->oApiCalendarManager->getCalendar($oUser->EntityId, $sUri);
			}

			if (isset($this->aCalendars[$sUri]))
			{
				$mResult =& $this->aCalendars[$sUri];
			}
		}

		return $mResult;
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

		return sprintf('
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
		return sprintf("%s\r\n\r\n%s%s\r\n\r\n%s: %s %s\r\n\r\n%s",
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
		if ($oToEmails && $oToEmails->Count())
		{
			$oMessage->SetTo($oToEmails);
		}

		if ($mHtml !== null)
		{
			$oMessage->AddText($mHtml, true);
		}

		if ($mText !== null)
		{
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
		$oMessage = $this->createMessage($oUser, $sSubject,
			$this->createBodyHtml($oUser, $sEventName, $sDate, $sCalendarName, $sEventText, $sCalendarColor),
			$this->createBodyText($oUser, $sEventName, $sDate, $sCalendarName, $sEventText));

		try
		{
			$oAccount = $this->oApiAccountsManager->getAccountByEmail($oUser->PublicId);
			if (!$oAccount instanceof \Aurora\Modules\Mail\Classes\Account)
			{
				return false;
			}
			return $this->oApiMailManager->sendMessage($oAccount, $oMessage);
		}
		catch (Exception $oException)
		{
			\Aurora\System\Api::Log('MessageSend Exception', \Aurora\System\Enums\LogLevel::Error, 'cron-');
			\Aurora\System\Api::LogException($oException, \Aurora\System\Enums\LogLevel::Error, 'cron-');
		}

		return false;
	}
	
	private function getSubject($oUser, $sEventStart, $iEventStartTS, $sEventName, $sDate, $iNowTS, $bAllDay = false)
	{
		$sSubject = '';
		
		if ($bAllDay)
		{
			$oEventStart = new \DateTime("@$iEventStartTS", new \DateTimeZone('UTC'));
			$oEventStart->setTimezone(new \DateTimeZone($oUser->DefaultTimeZone ? : 'UTC'));
			$iEventStartTS = $oEventStart->getTimestamp() - $oEventStart->getOffset();
		}
		
		$iMinutes = round(($iEventStartTS - $iNowTS) / 60);
		
		if ($iMinutes > 0 && $iMinutes < 60)
		{
			$sSubject = $this->i18n('SUBJECT_MINUTES_PLURAL', $oUser, array(
				'EVENT_NAME' => $sEventName,
				'DATE' => date('G:i', strtotime($sEventStart)),
				'COUNT' => $iMinutes
			), $iMinutes);
		}
		else if ($iMinutes >= 60 && $iMinutes < 1440)
		{
			$sSubject = $this->i18n('SUBJECT_HOURS_PLURAL', $oUser, array(
				'EVENT_NAME' => $sEventName,
				'DATE' => date('G:i', strtotime($sEventStart)),
				'COUNT' => round($iMinutes / 60)
			), round($iMinutes / 60));
		}
		else if ($iMinutes >= 1440 && $iMinutes < 10080)
		{
			$sSubject = $this->i18n('SUBJECT_DAYS_PLURAL', $oUser, array(
				'EVENT_NAME' => $sEventName,
				'DATE' => $sDate,
				'COUNT' => round($iMinutes / 1440)
			), round($iMinutes / 1440));
		}
		else if ($iMinutes >= 10080)
		{
			$sSubject = $this->i18n('SUBJECT_WEEKS_PLURAL', $oUser, array(
				'EVENT_NAME' => $sEventName,
				'DATE' => $sDate,
				'COUNT' => round($iMinutes / 10080)
			), round($iMinutes / 10080));
		}
		else
		{
			$sSubject = $this->i18n('SUBJECT', $oUser, array(
				'EVENT_NAME' => $sEventName,
				'DATE' => $sDate
			));
		}		
		
		return $sSubject;
	}
	
	private function getDateTimeFormat($oUser)
	{
		$sDateFormat = 'm/d/Y';
		$sTimeFormat = 'h:i A';

		if ($oUser->DateFormat === \Aurora\System\Enums\DateFormat::DDMMYYYY)
		{
			$sDateFormat = 'd/m/Y';
		}
		else if ($oUser->DateFormat === \Aurora\System\Enums\DateFormat::DD_MONTH_YYYY)
		{
			$sDateFormat = 'd m Y';
		}

		if ($oUser->DateFormat == \Aurora\System\Enums\TimeFormat::F24)
		{
			$sTimeFormat = 'H:i';
		}
		
		return $sDateFormat.' '.$sTimeFormat;
	}
	
	public function GetReminders($iStart, $iEnd)
	{
		$aReminders = $this->oApiCalendarManager->getReminders($iStart, $iEnd);
		$aEvents = array();

		if ($aReminders && is_array($aReminders) && count($aReminders) > 0)
		{
			$aCacheEvents = array();
			foreach($aReminders as $aReminder)
			{
				$oUser = $this->getUser($aReminder['user']);

				$sCalendarUri = $aReminder['calendaruri'];
				$sEventId = $aReminder['eventid'];
				$iStartTime = $aReminder['starttime'];

				if (!isset($aCacheEvents[$sEventId]) && isset($oUser))
				{
					$aCacheEvents[$sEventId]['data'] = $this->oApiCalendarManager->getEvent($oUser->UUID, $sCalendarUri, $sEventId);

					$dt = new \DateTime();
					$dt->setTimestamp($iStartTime);
					$sDefaultTimeZone = new \DateTimeZone($oUser->DefaultTimeZone ? : 'UTC');
					$dt->setTimezone($sDefaultTimeZone);

					$aEventClear = Array();
					if (is_array($aCacheEvents[$sEventId]['data']))
					{
						foreach ($aCacheEvents[$sEventId]['data'] as $key =>$val)
						{
							if (is_int($key))
							{
								$aEventClear[$key] = $val['id'];

								if (is_array($val['alarms']))
								{
									$oNowDT = new \DateTime('now', $sDefaultTimeZone);

									$oStarReminderDT = new \DateTime();
									$oStarReminderDT->setTimestamp($val['startTS'] - max($val['alarms']) * 60);
									$oStarReminderDT->setTimezone($sDefaultTimeZone);

									$oStarEventDT = new \DateTime();
									$oStarEventDT->setTimestamp($val['startTS']);
									$oStarEventDT->setTimezone($sDefaultTimeZone);

									if ($oNowDT->getTimestamp() < $oStarReminderDT->getTimestamp() || !($oNowDT->format('H:i:s') >= $oStarReminderDT->format('H:i:s') && $oNowDT->format('H:i:s') <= $oStarEventDT->format('H:i:s')))
									{ //start - ( max(alarms) * 60 ) > now OR StarReminderTime >= now >= StarEventTime
										unset($aCacheEvents[$sEventId]['data'][$key]);
									}
								}
							}
						}
						//clearing of the doubles of excluded event 
						$aCountEventClear = array_count_values($aEventClear);
						foreach ($aCountEventClear as $key => $value)
						{
							if ($value > 1)
							{
								$aDoubles = array_keys($aEventClear, $key);
								foreach ($aDoubles as $val)
								{
									if (isset($aCacheEvents[$sEventId]['data'][$val]) && !isset($aCacheEvents[$sEventId]['data'][$val]['excluded']))
									{
										unset($aCacheEvents[$sEventId]['data'][$val]);
									}
								}
							}
						}
					}
					$aCacheEvents[$sEventId]['time'] = $dt->format($this->getDateTimeFormat($oUser));
				}

				if (isset($aCacheEvents[$sEventId]))
				{
					$aEvents[$aReminder['user']][$sCalendarUri][$sEventId] = $aCacheEvents[$sEventId];
				}
			}
		}
		return $aEvents;
	}

	public function Execute()
	{
		\Aurora\System\Api::Log('---------- Start cron script', \Aurora\System\Enums\LogLevel::Full, 'cron-');

		$oTimeZoneUTC = new \DateTimeZone('UTC');
		$oNowDT_UTC = new \DateTime('now', $oTimeZoneUTC);
		$iNowTS_UTC = $oNowDT_UTC->getTimestamp();

		$oStartDT_UTC = clone $oNowDT_UTC;
		$oStartDT_UTC->sub(new \DateInterval('PT30M'));

		if (file_exists($this->sCurRunFilePath))
		{
			$handle = fopen($this->sCurRunFilePath, 'r');
			$sCurRunFileTS = fread($handle, 10);
			if (!empty($sCurRunFileTS) && is_numeric($sCurRunFileTS))
			{
				$oStartDT_UTC = new \DateTime("@$sCurRunFileTS");
			}
		}

		$iStartTS_UTC = $oStartDT_UTC->getTimestamp();

		if ($iNowTS_UTC >= $iStartTS_UTC)
		{
			\Aurora\System\Api::Log('Start time: '.$oStartDT_UTC->format('r'), \Aurora\System\Enums\LogLevel::Full, 'cron-');
			\Aurora\System\Api::Log('End time: '.$oNowDT_UTC->format('r'), \Aurora\System\Enums\LogLevel::Full, 'cron-');
			
			$aEvents = $this->GetReminders($iStartTS_UTC, $iNowTS_UTC);
			
			foreach ($aEvents as $sEmail => $aUserCalendars)
			{
				foreach ($aUserCalendars as $sCalendarUri => $aUserEvents)
				{
					foreach ($aUserEvents as $aUserEvent)
					{
						$aSubEvents = $aUserEvent['data'];

						if (isset($aSubEvents, $aSubEvents['vcal']))
						{
							$vCal = $aSubEvents['vcal'];
							foreach ($aSubEvents as $mKey => $aEvent)
							{
								if ($mKey !== 'vcal')
								{
									$oUser = $this->getUser($sEmail);
									$oCalendar = $this->getCalendar($oUser, $sCalendarUri);

									if ($oCalendar)
									{
										$sEventId = $aEvent['uid'];
										$sEventStart = $aEvent['start'];
										$iEventStartTS = $aEvent['startTS'];
										$sEventName = $aEvent['subject'];
										$sEventText = $aEvent['description'];
										$bAllDay = $aEvent['allDay'];

										if (isset($vCal->getBaseComponent('VEVENT')->RRULE) && $iEventStartTS < $iNowTS_UTC)
										{ // the correct date for repeatable events
											$oEventStartDT = new \DateTime();
											$oEventStartDT->setTimestamp($iEventStartTS);
											$oEventStartDT->setTimezone($oTimeZoneUTC);
											$oEventStartDT = new \DateTime($oNowDT_UTC->format('Y-m-d') . ' ' . $oEventStartDT->format('H:i:s'), $oTimeZoneUTC);
											$iEventStartTS = $oEventStartDT->getTimestamp();
											$oEventStartDT->setTimezone(new \DateTimeZone($oUser->DefaultTimeZone ? : 'UTC'));
											$sEventStart = $oEventStartDT->format('Y-m-d H:i:s');
										}

										$sDate = $aUserEvent['time'];
										
										$sSubject = $this->getSubject($oUser, $sEventStart, $iEventStartTS, $sEventName, $sDate, $iNowTS_UTC, $bAllDay);
										
										$aUsers = array(
											$oUser->IdUser => $oUser
										);
										
										$aCalendarUsers = $this->oApiCalendarManager->getCalendarUsers($oUser, $oCalendar);
										if (0 < count($aCalendarUsers))
										{
											foreach ($aCalendarUsers as $aCalendarUser)
											{
												$oCalendarUser = $this->getUser($aCalendarUser['email']);
												if ($oCalendarUser)
												{
													$aUsers[$oCalendarUser->IdUser] = $oCalendarUser;
												}
											}
										}
										
										foreach ($aUsers as $oUsertem)
										{
											$bIsMessageSent = $this->sendMessage($oUsertem, $sSubject, $sEventName, $sDate, $oCalendar->DisplayName, $sEventText, $oCalendar->Color);
											if ($bIsMessageSent)
											{
												$this->oApiCalendarManager->updateReminder($oUsertem->PublicId, $sCalendarUri, $sEventId, $vCal->serialize());
												\Aurora\System\Api::Log('Send reminder for event: \''.$sEventName.'\' started on \''.$sDate.'\' to \''.$oUsertem->PublicId.'\'', \Aurora\System\Enums\LogLevel::Full, 'cron-');
											}
											else
											{
												\Aurora\System\Api::Log('Send reminder for event: FAILED!', \Aurora\System\Enums\LogLevel::Full, 'cron-');
											}
										}
									}
									else
									{
										\Aurora\System\Api::Log('Calendar '.$sCalendarUri.' not found!', \Aurora\System\Enums\LogLevel::Full, 'cron-');
									}
								}
							}
						}
					}
				}
			}

			file_put_contents($this->sCurRunFilePath, $iNowTS_UTC);
		}

		\Aurora\System\Api::Log('---------- End cron script', \Aurora\System\Enums\LogLevel::Full, 'cron-');
	}
}

$iTimer = microtime(true);

Reminder::NewInstance()->Execute();

\Aurora\System\Api::Log('Cron execution time: '.(microtime(true) - $iTimer).' sec.', \Aurora\System\Enums\LogLevel::Full, 'cron-');
