<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @internal
 * 
 * @package Calendar
 * @subpackage Storages
 */
class CApiCalendarSabredavStorage extends CApiCalendarStorage
{
	/**
	 * @var array
	 */
	public $Principal;

	/*
	 * @var int
	 */
	public $UserId;

	/*
	 * @var array
	 */
	protected $CalendarsCache;

	/*
	 * @var array
	 */
	protected $CalDAVCalendarsCache;

	/*
	 * @var array
	 */
	protected $CalDAVCalendarObjectsCache;
	
	/*
	 * @var string
	 */
	protected $TenantUser;

	/**
	 * @param CApiGlobalManager $oManager
	 */
	public function __construct(AApiManager &$oManager)
	{
		parent::__construct('sabredav', $oManager);

		$this->UserId = null;
		$this->TenantUser = null;
		$this->Principal = array();

		$this->CalendarsCache = array();
		$this->CalDAVCalendarsCache = array();
		$this->CalDAVCalendarObjectsCache = array();
	}
	
    /**
	 * @param int $iUserId
	 *
     * @return bool
     */		
	protected function _initialized($iUserId)
	{
		return ($iUserId !== null && $this->UserId !== null);
	}

	/**
	 * @param int $iUserId
	 */
	public function init($iUserId)
	{
		if (!$this->_initialized($iUserId)) {
			$this->UserId = $iUserId;
//			\Afterlogic\DAV\Server::getInstance()->setAccount($iUserId);

			$this->Principal = $this->getPrincipalInfo($iUserId);
		}
	}

	/**
	 * @return \Afterlogic\DAV\
	 */
	public function getBackend()
	{
		return \Afterlogic\DAV\Backend::Caldav();
	}

	/**
	 * @param string $iUserId
	 *
	 * @return array
	 */
	public function getPrincipalInfo($iUserId)
	{
		$aPrincipal = array();

		$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $iUserId);
		if (isset($aPrincipalProperties['uri'])) {
			$aPrincipal['uri'] = $aPrincipalProperties['uri'];
			$aPrincipal['id'] = $aPrincipalProperties['id'];
		} else {
			$aPrincipal['uri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $iUserId;
			$aPrincipal['id'] = -1;
		}
		return $aPrincipal;
	}

	/**
	 * @param int $iUserId
	 *
	 * @return int
	 */
	public function getCalendarAccess($iUserId, $sCalendarId)
	{
		$iResult = ECalendarPermission::Read;
		$oCalendar = $this->getCalendar($iUserId, $sCalendarId);
		if ($oCalendar) {
			$iResult = $oCalendar->Shared ? $oCalendar->Access : ECalendarPermission::Write;
		}
		return $iResult;
	}

    /**
     * Returns a single calendar, by name
     *
     * @param string $sPath
	 *
     * @return \Sabre\CalDAV\Calendar|bool
     */	
	protected function getCalDAVCalendar($sPath)
	{
		$oCalendar = false;
		list(, $sCalendarId) = \Sabre\HTTP\URLUtil::splitPath($sPath);
		if (count($this->CalDAVCalendarsCache) > 0 && isset($this->CalDAVCalendarsCache[$sCalendarId][$this->UserId])) {
			$oCalendar = $this->CalDAVCalendarsCache[$sCalendarId][$this->UserId];
		} else {
			$oCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
			if (isset($oCalendars) && $oCalendars->childExists($sCalendarId)) {
				$oCalendar = $oCalendars->getChild($sCalendarId);
				$this->CalDAVCalendarsCache[$sCalendarId][$this->UserId] = $oCalendar;
			}
		}
	
		return $oCalendar;
	}

    /**
     * @param \Sabre\CalDAV\Calendar $oCalDAVCalendar
	 * 
     * @return \CCalendar
     */	
	public function parseCalendar($oCalDAVCalendar)
	{
		if (!($oCalDAVCalendar instanceof \Sabre\CalDAV\Calendar)) {
			return false;
		}
		$aProps = $oCalDAVCalendar->getProperties(array());
		
		$oCalendar = new \CCalendar($oCalDAVCalendar->getName());
		$oCalendar->IntId = $oCalDAVCalendar->getId();

		if ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar) {
			$oCalendar->Shared = true;
			if (isset($aProps['{http://sabredav.org/ns}read-only'])) {
				$oCalendar->Access = $aProps['{http://sabredav.org/ns}read-only'] ? ECalendarPermission::Read : ECalendarPermission::Write;
			}
			if (isset($aProps['{http://calendarserver.org/ns/}summary'])) {
				$oCalendar->Description = $aProps['{http://calendarserver.org/ns/}summary'];
			}
		} else {
			if (isset($aProps['{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description'])) {
				$oCalendar->Description = $aProps['{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description'];
			}
		}

		if (isset($aProps['{DAV:}displayname'])) {
			$oCalendar->DisplayName = $aProps['{DAV:}displayname'];
		}
		if (isset($aProps['{'.\Sabre\CalDAV\Plugin::NS_CALENDARSERVER.'}getctag'])) {
			$oCalendar->CTag = $aProps['{'.\Sabre\CalDAV\Plugin::NS_CALENDARSERVER.'}getctag'];
		}
		if (isset($aProps['{http://apple.com/ns/ical/}calendar-color'])) {
			$oCalendar->Color = $aProps['{http://apple.com/ns/ical/}calendar-color'];
		}
		if (isset($aProps['{http://apple.com/ns/ical/}calendar-order'])) {
			$oCalendar->Order = $aProps['{http://apple.com/ns/ical/}calendar-order'];
		}
		if (isset($aProps['{http://sabredav.org/ns}owner-principal'])) {
			$oCalendar->Principals = array($aProps['{http://sabredav.org/ns}owner-principal']);
		} else {
			$oCalendar->Principals = array($oCalDAVCalendar->getOwner());
		}

		$sPrincipal = $oCalendar->GetMainPrincipalUrl();
		$sUserId = basename(urldecode($sPrincipal));

		$oCalendar->Owner = (!empty($sUserId)) ? $sUserId : $this->UserId;
		$oCalendar->Url = '/calendars/'.$this->UserId.'/'.$oCalDAVCalendar->getName();
		$oCalendar->RealUrl = 'calendars/'.$oCalendar->Owner.'/'.$oCalDAVCalendar->getName();
		$oCalendar->SyncToken = $oCalDAVCalendar->getSyncToken();

//		$aTenantPrincipal = $this->getPrincipalInfo($this->getTenantUser($this->Account));
//		if($aTenantPrincipal && $aTenantPrincipal['uri'] === $oCalDAVCalendar->getOwner()) {
//			$oCalendar->SharedToAll = true;
//		}
		
		return $oCalendar;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * 
     * @return \CCalendar|bool
	 */
	public function getCalendar($iUserId, $sCalendarId)
	{
		$this->init($iUserId);

		$oCalDAVCalendar = null;
		$oCalendar = false;
		if (count($this->CalendarsCache) > 0 && isset($this->CalendarsCache[$this->UserId][$sCalendarId])) {
			$oCalendar = $this->CalendarsCache[$this->UserId][$sCalendarId];
		} else {
			$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
			if ($oCalDAVCalendar) {
				$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			}
		}
		return $oCalendar;
	}

	/**
     * @return string
	 */
	public function getPublicUser()
	{
		return \Afterlogic\DAV\Constants::DAV_PUBLIC_PRINCIPAL;
	}

	/**
     * @return \CAccount
	 */
	public function getPublicAccount()
	{
		$oAccount = new CAccount(new CDomain());
		$oAccount->Email = $this->getPublicUser();
		return $oAccount;
	}

	/**
	 * @param int $iUserId
	 * 
	 * @return array|null
	 */
	public function getTenantUser($iUserId)
	{
		
		// TODO:
/*		if (!isset($this->TenantUser)) {
			$sPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
			if ($iUserId->IdTenant > 0) {
				$oApiTenantsMan = CApi::GetCoreManager('tenants');
				$oTenant = $oApiTenantsMan ? $oApiTenantsMan->getTenantById($iUserId->IdTenant) : null;
				if ($oTenant) {
					$sPrincipal = $oTenant->Login . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
				}
			}

			$this->TenantUser = $sPrincipal;
		}
 * 
 */
		return $this->TenantUser;
	}
	
	/**
	 * @param int $iUserId
	 * 
     * @return string
	 */
	public function getTenantAccount($iUserId)
	{
		$oTenantAccount = new CAccount(new CDomain());
		$oTenantAccount->Email = $this->getTenantUser($iUserId);
		$oTenantAccount->FriendlyName = \CApi::ClientI18N('CONTACTS/SHARED_TO_ALL', $oAccount);
		
		return $oTenantAccount;
	}	

	/*
	 * @param string $sCalendarId
	 * 
     * @return string
	 */
	public function getPublicCalendarHash($sCalendarId)
	{
		return $sCalendarId;
	}

	/**
	 * @param int $iUserId
	 * 
     * @return array
	 */
	public function getCalendars($iUserId)
	{
		$this->init($iUserId);

		$aCalendars = array();
		if (count($this->CalendarsCache) > 0 && isset($this->CalendarsCache[$iUserId])) {
			$aCalendars = $this->CalendarsCache[$iUserId];
		} else {
			$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

			foreach ($oUserCalendars->getChildren() as $oCalDAVCalendar) {
				
				$oCalendar = $this->parseCalendar($oCalDAVCalendar);
				if ($oCalendar) {
					$aCalendars[$oCalendar->Id] = $oCalendar;
				}
			}

			$this->CalendarsCache[$iUserId] = $aCalendars;
		}
 		return $aCalendars;
	}
	
	/**
	 * @param int $iUserId
	 * 
     * @return array
	 */
	public function GetCalendarNames($iUserId)
	{
		$aCalendarNames = array();
		$aCalendars = $this->getCalendars($iUserId);
		if (is_array($aCalendars)) {
			/* @var $oCalendar \CCalendar */
			foreach ($aCalendars as $oCalendar) {
				if ($oCalendar instanceof \CCalendar) {
					$aCalendarNames[$oCalendar->Id] = $oCalendar->DisplayName;
				}
			}
		}
		return $aCalendarNames;
	}

	/**
	 * @param int $iUserId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 * 
	 * @return string
	 */
	public function createCalendar($iUserId, $sName, $sDescription, $iOrder, $sColor)
	{
		$this->init($iUserId);

		$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

		$sSystemName = \Sabre\DAV\UUIDUtil::getUUID();
		$oUserCalendars->createExtendedCollection($sSystemName, 
			new Sabre\DAV\MkCol(
				[
					'{DAV:}collection',
					'{urn:ietf:params:xml:ns:caldav}calendar'
				],
				[
					'{DAV:}displayname' => $sName,
					'{'.\Sabre\CalDAV\Plugin::NS_CALENDARSERVER.'}getctag' => 1,
					'{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description' => $sDescription,
					'{http://apple.com/ns/ical/}calendar-color' => $sColor,
					'{http://apple.com/ns/ical/}calendar-order' => $iOrder
				]
			)
		);
		return $sSystemName;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 * 
	 * @return bool
	 */
	public function updateCalendar($iUserId, $sCalendarId, $sName, $sDescription, $iOrder, $sColor)
	{
		$this->init($iUserId);
		
		$bOnlyColor = ($sName === null && $sDescription === null && $iOrder === null);

		$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
		if ($oUserCalendars->childExists($sCalendarId)) {
			$oCalDAVCalendar = $oUserCalendars->getChild($sCalendarId);
			if ($oCalDAVCalendar) {
				$aCalendarProperties = $oCalDAVCalendar->getProperties([]);
				$sPrincipal = $oCalDAVCalendar->getOwner(); 

				$sOwnerPrincipal = isset($aCalendarProperties['{http://sabredav.org/ns}owner-principal']) ? 
						$aCalendarProperties['{http://sabredav.org/ns}owner-principal'] : $sPrincipal; 
				$bIsOwner = (isset($sOwnerPrincipal) && basename($sOwnerPrincipal) === $iUserId);

				$bShared = ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar);
				$bSharedToAll = (isset($sPrincipal) && basename($sPrincipal) === $this->getTenantUser($iUserId));
				$bSharedToMe = ($bShared && !$bSharedToAll && !$bIsOwner);
				
				$aUpdateProperties = array();
				if ($bSharedToMe) {
					$aUpdateProperties = array(
						'href' => $iUserId,
						'color' => $sColor,
					);
					if (!$bOnlyColor) {
						$aUpdateProperties['displayname'] = $sName;
						$aUpdateProperties['summary'] = $sDescription;
						$aUpdateProperties['color'] = $sColor;
					}
				} else {
					if ($bOnlyColor) {
						$aUpdateProperties = array(
							'{http://apple.com/ns/ical/}calendar-color' => $sColor
						);
					} else {
						$aUpdateProperties = array(
							'{DAV:}displayname' => $sName,
							'{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description' => $sDescription,
							'{http://apple.com/ns/ical/}calendar-color' => $sColor,
							'{http://apple.com/ns/ical/}calendar-order' => $iOrder
						);
					}
				}
				unset($this->CalDAVCalendarsCache[$sCalendarId]);
				unset($this->CalDAVCalendarObjectsCache[$sCalendarId]);
				$oPropPatch = new \Sabre\DAV\PropPatch($aUpdateProperties);
				$oCalDAVCalendar->propPatch($oPropPatch);
				return $oPropPatch->commit();
			}
		}
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sColor
	 * 
	 * @return bool
	 */
	public function updateCalendarColor($iUserId, $sCalendarId, $sColor)
	{
		return $this->updateCalendar($iUserId, $sCalendarId, null, null, null, $sColor);
	}

	/**
	 * @param string $sCalendarId
	 * @param int $iVisible
	 */
	public function updateCalendarVisible($sCalendarId, $iVisible)
	{
		@setcookie($sCalendarId, $iVisible, time() + 86400);
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * 
	 * @return bool
	 */
	public function deleteCalendar($iUserId, $sCalendarId)
	{
		$this->init($iUserId);

		$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
		if ($oUserCalendars->childExists($sCalendarId)) {
			$oCalDAVCalendar = $oUserCalendars->getChild($sCalendarId);
			if ($oCalDAVCalendar) {
				if ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar) {
					$this->unsubscribeCalendar($iUserId, $sCalendarId);
				} else {
					$oCalDAVCalendar->delete();
				}

				$this->deleteReminderByCalendar($sCalendarId);
				unset($this->CalDAVCalendarsCache[$sCalendarId]);
				unset($this->CalDAVCalendarObjectsCache[$sCalendarId]);

				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $iUserId
	 */
	public function clearAllCalendars($iUserId)
	{
		$this->init($iUserId);

		if (is_array($this->Principal) && count($this->Principal)) {
			$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
			foreach ($oUserCalendars->getChildren() as $oCalDAVCalendar) {
				if ($oCalDAVCalendar instanceof \Sabre\CalDAV\Calendar) {
					if ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar) {
						//$this->unsubscribeCalendar($iUserId, $sCalendarId);
					} else {
						$oCalDAVCalendar->delete();
					}
				}
			}
		}
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 *
	 * @return bool
	 */
	public function unsubscribeCalendar($iUserId, $sCalendarId)
	{
		$this->init($iUserId);

		$oCalendar = $this->getCalendar($iUserId, $sCalendarId);
		if ($oCalendar) {
			$this->getBackend()->updateShares($oCalendar->IntId, array(), array($iUserId->Email));
		}

		return true;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param array $aShares
	 *
	 * @return bool
	 */
	public function updateCalendarShares($iUserId, $sCalendarId, $aShares)
	{
		$this->init($iUserId);

		$oCalendar = $this->getCalendar($iUserId, $sCalendarId);

		if ($oCalendar) {
			$aCalendarUsers = $this->getCalendarUsers($iUserId, $oCalendar);
			$aSharesEmails = array_map(function ($aItem) {
				return $aItem['email'];
			}, $aShares);
			
			$add = array();
			$remove = array();
			
			// add to delete list
			foreach($aCalendarUsers as $aCalendarUser) {
				if (!in_array($aCalendarUser['email'], $aSharesEmails)) {
					$remove[] = $aCalendarUser['email'];
				}
			}
			
			if (count($oCalendar->Principals) > 0) {
				foreach ($aShares as $aShare) {
					if ($aShare['access'] === \ECalendarPermission::RemovePermission) {
						$remove[] = $aShare['email'];
					} else {
						$add[] = array(
							'href' => $aShare['email'],
							'readonly' => ($aShare['access'] === \ECalendarPermission::Read) ? 1 : 0,
						);
					}
				}
				
				$this->getBackend()->updateShares($oCalendar->IntId, $add, $remove);
			}
		}

		return true;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sUserId
	 * @param int $iPerms
	 *
	 * @return bool
	 */
	public function updateCalendarShare($iUserId, $sCalendarId, $sUserId, $iPerms = ECalendarPermission::RemovePermission)
	{
		$this->init($iUserId);

		$oCalendar = $this->getCalendar($iUserId, $sCalendarId);

		if ($oCalendar) {
			if (count($oCalendar->Principals) > 0) {
				$add = array();
				$remove = array();
				if ($iPerms === ECalendarPermission::RemovePermission) {
					$remove[] = $sUserId;
				} else {
					$aItem['href'] = $sUserId;
					if ($iPerms === \ECalendarPermission::Read) {
						$aItem['readonly'] = true;
					}
					elseif ($iPerms === \ECalendarPermission::Write) {
						$aItem['readonly'] = false;
					}
					$add[] = $aItem;
				}
				
				$this->getBackend()->updateShares($oCalendar->IntId, $add, $remove);
			}
		}

		return true;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 *
	 * @return bool
	 */
	public function deleteCalendarShares($iUserId, $sCalendarId)
	{
		$this->init($iUserId);

		$oCalendar = $this->getCalendar($iUserId, $sCalendarId);

		if ($oCalendar) {
			if (count($oCalendar->Principals) > 0) {
				$this->updateCalendarShares($iUserId, $sCalendarId, array());
			}
		}

		return true;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param bool $bIsPublic Default value is **false**.
	 * 
	 * @return bool
	 */
	public function publicCalendar($iUserId, $sCalendarId, $bIsPublic = false)
	{
		$iPermission = $bIsPublic ? \ECalendarPermission::Read : \ECalendarPermission::RemovePermission;
		
		return $this->updateCalendarShare($iUserId, $sCalendarId, $this->getPublicUser(), $iPermission);
	}

	/**
	 * @param int $iUserId
	 * @param CCalendar $oCalendar
	 * 
	 * @return array
	 */
	public function getCalendarUsers($iUserId, $oCalendar)
	{
		$aResult = array();
		$this->init($iUserId);

		if ($oCalendar != null) {
			$aShares = $this->getBackend()->getShares($oCalendar->IntId);

			foreach($aShares as $aShare) {
				$aResult[] = array(
					'name' => basename($aShare['href']),
					'email' => basename($aShare['href']),
					'access' => $aShare['readOnly'] ? ECalendarPermission::Read : ECalendarPermission::Write
				);
			}
		}
		
		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * 
	 * @return string|bool
	 */
	public function exportCalendarToIcs($iUserId, $sCalendarId)
	{
		$this->init($iUserId);

		$mResult = false;
		$oCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalendar) {
			$aCollectedTimezones = array();

			$aTimezones = array();
			$aObjects = array();

			foreach ($oCalendar->getChildren() as $oChild) {
				$oNodeComp = \Sabre\VObject\Reader::read($oChild->get());
				foreach($oNodeComp->children() as $oNodeChild) {
					switch($oNodeChild->name) 
					{
						case 'VEVENT' :
						case 'VTODO' :
						case 'VJOURNAL' :
							$aObjects[] = $oNodeChild;
							break;

						case 'VTIMEZONE' :
							if (in_array((string)$oNodeChild->TZID, $aCollectedTimezones))
							{
								continue;
							}

							$aTimezones[] = $oNodeChild;
							$aCollectedTimezones[] = (string)$oNodeChild->TZID;
							break;

					}
				}
			}

			$oVCal = new \Sabre\VObject\Component\VCalendar();
			foreach($aTimezones as $oTimezone) {
				$oVCal->add($oTimezone);
			}
			foreach($aObjects as $oObject) {
				$oVCal->add($oObject);
			}

			$mResult = $oVCal->serialize();
		}

		return $mResult;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sTempFileName
	 * 
	 * @return mixed
	 */
	public function importToCalendarFromIcs($iUserId, $sCalendarId, $sTempFileName)
	{
		$this->init($iUserId);

		$mResult = false;
		$oCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalendar) {
			// You can either pass a readable stream, or a string.
			$h = fopen($sTempFileName, 'r');
			$splitter = new \Sabre\VObject\Splitter\ICalendar($h);

			$iCount = 0;
			while($oVCalendar = $splitter->getNext()) {
				$oVEvents = $oVCalendar->getBaseComponents('VEVENT');
				if (isset($oVEvents) && 0 < count($oVEvents)) {
					$sUid = str_replace(array("/", "=", "+"), "", $oVEvents[0]->UID);
					
					if (!$oCalendar->childExists($sUid . '.ics')) {
						$oVEvents[0]->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
						$oCalendar->createFile($sUid . '.ics', $oVCalendar->serialize());
						$iCount++;
					}
				}
			}
			$mResult = $iCount;
		}
		
		return $mResult;
	}
	

	/**
	 * @param \Sabre\CalDAV\Calendar $oCalDAVCalendar
	 * @param string $sEventId
	 * 
	 * @return \Sabre\CalDAV\CalendarObject
	 */
	public function getCalDAVCalendarObject($oCalDAVCalendar, $sEventId)
	{
		if ($oCalDAVCalendar) {
			$sEventFileName = $sEventId . '.ics';
			if (count($this->CalDAVCalendarObjectsCache) > 0 && isset($this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->Account->Email])) {
				return $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->Account->Email];
			} else {
				if ($oCalDAVCalendar->childExists($sEventFileName)) {
					$oChild = $oCalDAVCalendar->getChild($sEventFileName);
					if ($oChild instanceof \Sabre\CalDAV\CalendarObject) {
						$this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->Account->Email] = $oChild;
						return $oChild;
					}
				} else {
					foreach ($oCalDAVCalendar->getChildren() as $oChild) {
						if ($oChild instanceof \Sabre\CalDAV\CalendarObject) {
							$oVCal = \Sabre\VObject\Reader::read($oChild->get());
							if ($oVCal && $oVCal->VEVENT) {
								foreach ($oVCal->VEVENT as $oVEvent) {
									foreach($oVEvent->select('UID') as $oUid) {
										if ((string)$oUid === $sEventId) {
											$this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->Account->Email] = $oChild;
											return $oChild;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		
		return false;
	}
	

	/**
	 * @param int $iUserId
	 * @param object $oCalendar
	 * @param string $dStart
	 * @param string $dEnd
	 * 
	 * @return array
	 */
	public function getEventsFromVCalendar($iUserId, $oCalendar, $oVCal, $dStart, $dEnd, $bExpand = true)
	{
		$oVCalOriginal = clone $oVCal;

		if ($bExpand && $dStart !== null && $dEnd !== null)
		{
			$oVCal->expand(
				\Sabre\VObject\DateTimeParser::parse($dStart), 
				\Sabre\VObject\DateTimeParser::parse($dEnd)
			);
		}
		
		$aEvents = CalendarParser::parseEvent($iUserId, $oCalendar, $oVCal, $oVCalOriginal);
		
		return $aEvents;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $dStart
	 * @param string $dEnd
	 * 
	 * @return array
	 */
	public function getExpandedEvent($iUserId, $sCalendarId, $sEventId, $dStart, $dEnd)
	{
		$this->init($iUserId);

		$mResult = array(
			'Events' => array(),
			'CTag' => 1
		);
		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalDAVCalendarObject = $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId);
			if ($oCalDAVCalendarObject) {
				$oVCal = \Sabre\VObject\Reader::read($oCalDAVCalendarObject->get());

				$oCalendar = $this->parseCalendar($oCalDAVCalendar);
				$mResult['Events'] = $this->getEventsFromVCalendar($iUserId, $oCalendar, $oVCal, $dStart, $dEnd);
				$mResult['CTag'] = $oCalendar->CTag;
				$mResult['SyncToken'] = $oCalendar->SyncToken;
			}
		}
		
		return $mResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sEventId
	 * @param array $aCalendars
	 * 
	 * @return array
	 */
	public function findEventInCalendars($iUserId,  $sEventId, $aCalendars)
	{
		$aEventCalendarIds = array();
		foreach (array_keys($aCalendars) as $sKey) {
			if ($this->eventExists($iUserId, $sKey, $sEventId)) {
				$aEventCalendarIds[] = $sKey;
			}
		}
		
		return $aEventCalendarIds;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * 
	 * @return bool
	 */
	public function eventExists($iUserId, $sCalendarId, $sEventId)
	{
		$bResult = false;
		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar && $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId) !== false) {
			$bResult = true;
		}
		
		return $bResult;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * 
	 * @return array|bool
	 */
	public function getEvent($iUserId, $sCalendarId, $sEventId)
	{
		$mResult = false;
		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {		
			$oCalendarObject = $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId);
			if ($oCalendarObject) {
				$mResult = array(
					'url'  => $oCalendarObject->getName(),
					'vcal' => \Sabre\VObject\Reader::read($oCalendarObject->get())
				);
			}
		}
		
		return $mResult;
	}

	/**
	 * @param object $oCalendar
	 * @param object $dStart
	 * @param object $dEnd
	 *
	 * @return string
	 */
	public function getEventUrls($oCalendar, $dStart, $dEnd)
	{
		$aTimeRange = ($dStart !== null && $dEnd !== null) ? 
				array(
					'start' => \Sabre\VObject\DateTimeParser::parse($dStart),
					'end' => \Sabre\VObject\DateTimeParser::parse($dEnd)
				) : null;		
		
		return $oCalendar->calendarQuery(array(
			'name' => 'VCALENDAR',
			'comp-filters' => array(
				array(
					'name' => 'VEVENT',
					'comp-filters' => array(),
					'prop-filters' => array(),
					'is-not-defined' => false,
					'time-range' => $aTimeRange,
				),
			),
			'prop-filters' => array(),
			'is-not-defined' => false,
			'time-range' => null,
		));
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dEnd
	 * @param bool $bGetData
	 * 
	 * @return array|bool
	 */
	public function getEventsInfo($iUserId, $sCalendarId, $dStart, $dEnd, $bGetData = false)
	{
		$aResult = array();
		$this->init($iUserId);

		$oCalendar = $this->getCalDAVCalendar($sCalendarId);
		
		if ($oCalendar)
		{
			$aUrls = $this->getEventUrls($oCalendar, $dStart, $dEnd);
			
			foreach ($aUrls as $sUrl)
			{
				if (isset($this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->Account->Email]))
				{
					$oEvent = $this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->Account->Email];
				}
				else
				{
					$oEvent = $oCalendar->getChild($sUrl);
					$this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->Account->Email] = $oEvent;		
				}

				$aEventInfo = array(
					'Url' => $sUrl,
					'ETag' => $oEvent->getETag(),
					'LastModified' => $oEvent->getLastModified()
				);
				if ($bGetData)
				{
					$aEventInfo['Data'] = $oEvent->get();
				}
				
				$aResult[$oCalendar->getName()][] = $aEventInfo;
			}
		}
		
		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dEnd
	 * @param bool $bExpand
	 * 
	 * @return array|bool
	 */
	public function getEvents($iUserId, $sCalendarId, $dStart, $dEnd, $bExpand = true)
	{
		$this->init($iUserId);

		$mResult = false;
		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);

		if ($oCalDAVCalendar) {
			$aUrls = $this->getEventUrls($oCalDAVCalendar, $dStart, $dEnd);
			
 			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			$mResult = array();
			foreach ($aUrls as $sUrl) {
				if (isset($this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserId])) {
					$oCalDAVCalendarObject = $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserId];
				} else {
					$oCalDAVCalendarObject = $oCalDAVCalendar->getChild($sUrl);
					$this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserId] = $oCalDAVCalendarObject;		
				}
				$oVCal = \Sabre\VObject\Reader::read($oCalDAVCalendarObject->get());
				$aEvents = $this->getEventsFromVCalendar($iUserId, $oCalendar, $oVCal, $dStart, $dEnd, $bExpand);
				foreach (array_keys($aEvents) as $key) {
					$aEvents[$key]['lastModified'] = $oCalDAVCalendarObject->getLastModified();
				}
				$mResult = array_merge($mResult, $aEvents);
			}
		}

		return $mResult;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 * 
	 * @return string|null
	 */
	public function createEvent($iUserId, $sCalendarId, $sEventId, $oVCal)
	{
		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \ECalendarPermission::Read) {
				$sData = $oVCal->serialize();
				$oCalDAVCalendar->createFile($sEventId.'.ics', $sData);

				$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);

				return $sEventId;
			}
		}

		return null;
	}


	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 * 
	 * @return bool
	 */
	public function updateEventRaw($iUserId, $sCalendarId, $sEventId, $sData)
	{
		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \ECalendarPermission::Read) {
				$oCalDAVCalendarObject = $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId);
				if ($oCalDAVCalendarObject) {
					$oChild = $oCalDAVCalendar->getChild($oCalDAVCalendarObject->getName());
					if ($oChild) {
						$oChild->put($sData);
						$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
						unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventId.'.ics']);
						return true;
					}
				} else {
					$oCalDAVCalendar->createFile($sEventId.'.ics', $sData);
					$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $oVCal
	 * 
	 * @return bool
	 */
	public function updateEvent($iUserId, $sCalendarId, $sEventId, $oVCal)
	{
 		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \ECalendarPermission::Read) {
				$oChild = $oCalDAVCalendar->getChild($sEventId . '.ics');
				$sData = $oVCal->serialize();
				$oChild->put($sData);
				
				$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
				unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventId.'.ics']);
				return true;
			}
		}
		
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sNewCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return bool
	 */
	public function moveEvent($iUserId, $sCalendarId, $sNewCalendarId, $sEventId, $sData)
	{
		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalDAVCalendarNew = $this->getCalDAVCalendar($sNewCalendarId);
			if ($oCalDAVCalendarNew) {
				$oCalendar = $this->parseCalendar($oCalDAVCalendarNew);
				if ($oCalendar->Access !== \ECalendarPermission::Read) {
					$oCalDAVCalendarNew->createFile($sEventId . '.ics', $sData);
	
					$oChild = $oCalDAVCalendar->getChild($sEventId . '.ics');
					$oChild->delete();

					$this->deleteReminder($sEventId);
					$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
					unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventId.'.ics']);
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * 
	 * @return bool
	 */
	public function deleteEvent($iUserId, $sCalendarId, $sEventId)
	{
		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \ECalendarPermission::Read) {
				$oChild = $oCalDAVCalendar->getChild($sEventId.'.ics');
				$oChild->delete();

				$this->deleteReminder($sEventId);
				unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventId.'.ics']);

				return (string) ($oCalendar->CTag + 1);
			}
		}
		
		return false;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sCalendarId
	 * @param string $sEventUrl
	 * 
	 * @return bool
	 */
	public function deleteEventByUrl($iUserId, $sCalendarId, $sEventUrl)
	{
		$this->init($iUserId);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar)
		{
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \ECalendarPermission::Read)
			{
				$oChild = $oCalDAVCalendar->getChild($sEventUrl);
				$oChild->delete();

				unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);

				return (string) ($oCalendar->CTag + 1);
			}
		}
		
		return false;
	}

	public function getReminders($start, $end)
	{
		return \Afterlogic\DAV\Backend::Reminders()->getReminders($start, $end);
	}

	public function AddReminder($sEmail, $sCalendarUri, $sEventId, $time = null, $starttime = null)
	{
		return \Afterlogic\DAV\Backend::Reminders()->addReminders($sEmail, $sCalendarUri, $sEventId, $time, $starttime);
	}
	
	public function updateReminder($sEmail, $sCalendarUri, $sEventId, $sData)
	{
		\Afterlogic\DAV\Backend::Reminders()->updateReminder(trim($sCalendarUri, '/') . '/' . $sEventId . '.ics', $sData, $sEmail);
	}

	public function deleteReminder($sEventId)
	{
		return \Afterlogic\DAV\Backend::Reminders()->deleteReminder($sEventId);
	}

	public function deleteReminderByCalendar($sCalendarUri)
	{
		return \Afterlogic\DAV\Backend::Reminders()->deleteReminderByCalendar($sCalendarUri);
	}
	
}
