<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AfterLogic Software License
 *
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Storages;

class Sabredav extends Storage
{
	/**
	 * @var array
	 */
	public $Principal;

	/*
	 * @var string
	 */
	public $UserUUID;

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
	 * @param \Aurora\System\Managers\AbstractManager $oManager
	 */
	public function __construct(\Aurora\System\Managers\AbstractManager &$oManager)
	{
		parent::__construct($oManager);

		$this->UserUUID = null;
		$this->TenantUser = null;
		$this->Principal = array();

		$this->CalendarsCache = array();
		$this->CalDAVCalendarsCache = array();
		$this->CalDAVCalendarObjectsCache = array();
	}
	
    /**
	 * @param int $sUserUUID
	 *
     * @return bool
     */		
	protected function _initialized($sUserUUID)
	{
		return ($sUserUUID !== null && $this->UserUUID !== null);
	}

	/**
	 * @param string $sUserUUID
	 */
	public function init($sUserUUID)
	{
		if (!$this->_initialized($sUserUUID)) {
			$this->UserUUID = $sUserUUID;
			$this->Principal = $this->getPrincipalInfo($sUserUUID);
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
	 * @param string $sUserUUID
	 *
	 * @return array
	 */
	public function getPrincipalInfo($sUserUUID)
	{
		$aPrincipal = array();

		$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserUUID);
		if (isset($aPrincipalProperties['uri'])) {
			$aPrincipal['uri'] = $aPrincipalProperties['uri'];
			$aPrincipal['id'] = $aPrincipalProperties['id'];
		} else {
			$aPrincipal['uri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserUUID;
			$aPrincipal['id'] = -1;
		}
		return $aPrincipal;
	}

	/**
	 * @param string $sUserUUID
	 *
	 * @return int
	 */
	public function getCalendarAccess($sUserUUID, $sCalendarId)
	{
		$iResult = \Aurora\Modules\Calendar\Enums\Permission::Read;
		$oCalendar = $this->getCalendar($sUserUUID, $sCalendarId);
		if ($oCalendar) {
			$iResult = $oCalendar->Shared ? $oCalendar->Access : \Aurora\Modules\Calendar\Enums\Permission::Write;
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
		if (count($this->CalDAVCalendarsCache) > 0 && isset($this->CalDAVCalendarsCache[$sCalendarId][$this->UserUUID])) {
			$oCalendar = $this->CalDAVCalendarsCache[$sCalendarId][$this->UserUUID];
		} else {
			$oCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
			if (isset($oCalendars) && $oCalendars->childExists($sCalendarId)) {
				$oCalendar = $oCalendars->getChild($sCalendarId);
				$this->CalDAVCalendarsCache[$sCalendarId][$this->UserUUID] = $oCalendar;
			}
		}
	
		return $oCalendar;
	}

    /**
     * @param \Sabre\CalDAV\Calendar $oCalDAVCalendar
	 * 
     * @return \Aurora\Modules\Calendar\Classes\Calendar
     */	
	public function parseCalendar($oCalDAVCalendar)
	{
		if (!($oCalDAVCalendar instanceof \Sabre\CalDAV\Calendar)) {
			return false;
		}
		$aProps = $oCalDAVCalendar->getProperties(array());
		
		$oCalendar = new \Aurora\Modules\Calendar\Classes\Calendar($oCalDAVCalendar->getName());
		$oCalendar->IntId = $oCalDAVCalendar->getId();

		if ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar) {
			$oCalendar->Shared = true;
			if (isset($aProps['{http://sabredav.org/ns}read-only'])) {
				$oCalendar->Access = $aProps['{http://sabredav.org/ns}read-only'] ? \Aurora\Modules\Calendar\Enums\Permission::Read : \Aurora\Modules\Calendar\Enums\Permission::Write;
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
		$sUserUUID = basename(urldecode($sPrincipal));
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$oCalendar->Owner = isset($oUser->PublicId) ? $oUser->PublicId : '';
		$oCalendar->Url = '/calendars/'.$this->UserUUID.'/'.$oCalDAVCalendar->getName();
		$oCalendar->RealUrl = 'calendars/'.$oCalendar->Owner.'/'.$oCalDAVCalendar->getName();
		$oCalendar->SyncToken = $oCalDAVCalendar->getSyncToken();

//		$aTenantPrincipal = $this->getPrincipalInfo($this->getTenantUser($this->Account));
//		if($aTenantPrincipal && $aTenantPrincipal['uri'] === $oCalDAVCalendar->getOwner()) {
//			$oCalendar->SharedToAll = true;
//		}
		
		return $oCalendar;
	}
	
	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * 
     * @return \Aurora\Modules\Calendar\Classes\Calendar|bool
	 */
	public function getCalendar($sUserUUID, $sCalendarId)
	{
		$this->init($sUserUUID);

		$oCalDAVCalendar = null;
		$oCalendar = false;
		if (count($this->CalendarsCache) > 0 && isset($this->CalendarsCache[$this->UserUUID][$sCalendarId])) {
			$oCalendar = $this->CalendarsCache[$this->UserUUID][$sCalendarId];
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
     * @return \Aurora\Modules\Core\Classes\User
	 */
	public function getPublicAccount()
	{
		return $this->getPublicUser();
	}

	/**
	 * @param string $sUserUUID
	 * 
	 * @return array|null
	 */
	public function getTenantUser($sUserUUID)
	{
		
		// TODO:
/*		if (!isset($this->TenantUser)) {
			$sPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
			if ($iUserId->IdTenant > 0) {
				$oApiTenantsMan =\Aurora\System\Api::GetCoreManager('tenants');
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
	 * @param string $sUserUUID
	 * 
     * @return string
	 */
	public function getTenantAccount($sUserUUID)
	{
		$oTenantAccount = new \Aurora\Modules\StandardAuth\Classes\Account(new \CDomain());
		$oTenantAccount->Email = $this->getTenantUser($sUserUUID);
		$oTenantAccount->FriendlyName = \Aurora\System\Api::ClientI18N('CONTACTS/SHARED_TO_ALL', $oAccount);
		
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
	 * @param string $sUserUUID
	 * 
     * @return array
	 */
	public function getCalendars($sUserUUID)
	{
		$aCalendars = array();
		if (!empty($sUserUUID))
		{
			$this->init($sUserUUID);

			if (count($this->CalendarsCache) > 0 && isset($this->CalendarsCache[$sUserUUID])) {
				$aCalendars = $this->CalendarsCache[$sUserUUID];
			} else {
				$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

				foreach ($oUserCalendars->getChildren() as $oCalDAVCalendar) {

					$oCalendar = $this->parseCalendar($oCalDAVCalendar);
					if ($oCalendar) {
						$aCalendars[$oCalendar->Id] = $oCalendar;
					}
				}

				$this->CalendarsCache[$sUserUUID] = $aCalendars;
			}
		}
 		return $aCalendars;
	}
	
	/**
	 * @param string $sUserUUID
	 * 
     * @return array
	 */
	public function GetCalendarNames($sUserUUID)
	{
		$aCalendarNames = array();
		$aCalendars = $this->getCalendars($sUserUUID);
		if (is_array($aCalendars)) {
			/* @var $oCalendar \Aurora\Modules\Calendar\Classes\Calendar */
			foreach ($aCalendars as $oCalendar) {
				if ($oCalendar instanceof \Aurora\Modules\Calendar\Classes\Calendar) {
					$aCalendarNames[$oCalendar->Id] = $oCalendar->DisplayName;
				}
			}
		}
		return $aCalendarNames;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 * 
	 * @return string
	 */
	public function createCalendar($sUserUUID, $sName, $sDescription, $iOrder, $sColor)
	{
		$this->init($sUserUUID);

		$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

		$sSystemName = \Sabre\DAV\UUIDUtil::getUUID();
		$oUserCalendars->createExtendedCollection($sSystemName, 
			new \Sabre\DAV\MkCol(
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sName
	 * @param string $sDescription
	 * @param int $iOrder
	 * @param string $sColor
	 * 
	 * @return bool
	 */
	public function updateCalendar($sUserUUID, $sCalendarId, $sName, $sDescription, $iOrder, $sColor)
	{
		$this->init($sUserUUID);
		
		$bOnlyColor = ($sName === null && $sDescription === null && $iOrder === null);

		$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
		if ($oUserCalendars->childExists($sCalendarId)) {
			$oCalDAVCalendar = $oUserCalendars->getChild($sCalendarId);
			if ($oCalDAVCalendar) {
				$aCalendarProperties = $oCalDAVCalendar->getProperties([]);
				$sPrincipal = $oCalDAVCalendar->getOwner(); 

				$sOwnerPrincipal = isset($aCalendarProperties['{http://sabredav.org/ns}owner-principal']) ? 
						$aCalendarProperties['{http://sabredav.org/ns}owner-principal'] : $sPrincipal; 
				$bIsOwner = (isset($sOwnerPrincipal) && basename($sOwnerPrincipal) === $sUserUUID);

				$bShared = ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar);
				$bSharedToAll = (isset($sPrincipal) && basename($sPrincipal) === $this->getTenantUser($sUserUUID));
				$bSharedToMe = ($bShared && !$bSharedToAll && !$bIsOwner);
				
				$aUpdateProperties = array();
				if ($bSharedToMe) {
					$aUpdateProperties = array(
						'href' => $sUserUUID,
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sColor
	 * 
	 * @return bool
	 */
	public function updateCalendarColor($sUserUUID, $sCalendarId, $sColor)
	{
		return $this->updateCalendar($sUserUUID, $sCalendarId, null, null, null, $sColor);
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * 
	 * @return bool
	 */
	public function deleteCalendar($sUserUUID, $sCalendarId)
	{
		$this->init($sUserUUID);

		$oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
		if ($oUserCalendars->childExists($sCalendarId)) {
			$oCalDAVCalendar = $oUserCalendars->getChild($sCalendarId);
			if ($oCalDAVCalendar) {
				if ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar) {
					$this->unsubscribeCalendar($sUserUUID, $sCalendarId);
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
	 * @param string $sUserUUID
	 */
	public function clearAllCalendars($sUserUUID)
	{
		$this->init($sUserUUID);

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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 *
	 * @return bool
	 */
	public function unsubscribeCalendar($sUserUUID, $sCalendarId)
	{
		$this->init($sUserUUID);

		$oCalendar = $this->getCalendar($sUserUUID, $sCalendarId);
		if ($oCalendar) {
			$this->getBackend()->updateShares($oCalendar->IntId, array(), array($sUserUUID->Email));
		}

		return true;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param array $aShares
	 *
	 * @return bool
	 */
	public function updateCalendarShares($sUserUUID, $sCalendarId, $aShares)
	{
		$this->init($sUserUUID);

		$oCalendar = $this->getCalendar($sUserUUID, $sCalendarId);

		if ($oCalendar) {
			$aCalendarUsers = $this->getCalendarUsers($sUserUUID, $oCalendar);
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
					if ($aShare['access'] === \Aurora\Modules\Calendar\Enums\Permission::RemovePermission) {
						$remove[] = $aShare['email'];
					} else {
						$add[] = array(
							'href' => $aShare['email'],
							'readonly' => ($aShare['access'] === \Aurora\Modules\Calendar\Enums\Permission::Read) ? 1 : 0,
						);
					}
				}
				
				$this->getBackend()->updateShares($oCalendar->IntId, $add, $remove);
			}
		}

		return true;
	}
	
	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sUserId
	 * @param int $iPerms
	 *
	 * @return bool
	 */
	public function updateCalendarShare($sUserUUID, $sCalendarId, $sUserId, $iPerms = \Aurora\Modules\Calendar\Enums\Permission::RemovePermission)
	{
		$this->init($sUserUUID);

		$oCalendar = $this->getCalendar($sUserUUID, $sCalendarId);

		if ($oCalendar) {
			if (count($oCalendar->Principals) > 0) {
				$add = array();
				$remove = array();
				if ($iPerms === \Aurora\Modules\Calendar\Enums\Permission::RemovePermission) {
					$remove[] = $sUserId;
				} else {
					$aItem['href'] = $sUserId;
					if ($iPerms === \Aurora\Modules\Calendar\Enums\Permission::Read) {
						$aItem['readonly'] = true;
					}
					elseif ($iPerms === \Aurora\Modules\Calendar\Enums\Permission::Write) {
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 *
	 * @return bool
	 */
	public function deleteCalendarShares($sUserUUID, $sCalendarId)
	{
		$this->init($sUserUUID);

		$oCalendar = $this->getCalendar($sUserUUID, $sCalendarId);

		if ($oCalendar) {
			if (count($oCalendar->Principals) > 0) {
				$this->updateCalendarShares($sUserUUID, $sCalendarId, array());
			}
		}

		return true;
	}
	
	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param bool $bIsPublic Default value is **false**.
	 * 
	 * @return bool
	 */
	public function publicCalendar($sUserUUID, $sCalendarId, $bIsPublic = false)
	{
		$iPermission = $bIsPublic ? \Aurora\Modules\Calendar\Enums\Permission::Read : \Aurora\Modules\Calendar\Enums\Permission::RemovePermission;
		
		return $this->updateCalendarShare($sUserUUID, $sCalendarId, $this->getPublicUser(), $iPermission);
	}

	/**
	 * @param stirng $sUserUUID
	 * @param \Aurora\Modules\Calendar\Classes\Calendar $oCalendar
	 * 
	 * @return array
	 */
	public function getCalendarUsers($sUserUUID, $oCalendar)
	{
		$aResult = array();
		$this->init($sUserUUID);

		if ($oCalendar != null) {
			$aShares = $this->getBackend()->getShares($oCalendar->IntId);

			foreach($aShares as $aShare) {
				$aResult[] = array(
					'name' => basename($aShare['href']),
					'email' => basename($aShare['href']),
					'access' => $aShare['readOnly'] ? \Aurora\Modules\Calendar\Enums\Permission::Read : \Aurora\Modules\Calendar\Enums\Permission::Write
				);
			}
		}
		
		return $aResult;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * 
	 * @return string|bool
	 */
	public function exportCalendarToIcs($sUserUUID, $sCalendarId)
	{
		$this->init($sUserUUID);

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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sTempFileName
	 * 
	 * @return mixed
	 */
	public function importToCalendarFromIcs($sUserUUID, $sCalendarId, $sTempFileName)
	{
		$this->init($sUserUUID);

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
		if ($oCalDAVCalendar) 
		{
			$sEventFileName = $sEventId . '.ics';
			if (count($this->CalDAVCalendarObjectsCache) > 0 && 
				isset($this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserUUID])) 
			{
				return $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserUUID];
			} 
			else 
			{
				if ($oCalDAVCalendar->childExists($sEventFileName)) 
				{
					$oChild = $oCalDAVCalendar->getChild($sEventFileName);
					if ($oChild instanceof \Sabre\CalDAV\CalendarObject) 
					{
						$this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserUUID] = $oChild;
						return $oChild;
					}
				} 
				else 
				{
					foreach ($oCalDAVCalendar->getChildren() as $oChild) 
					{
						if ($oChild instanceof \Sabre\CalDAV\CalendarObject) 
						{
							$oVCal = \Sabre\VObject\Reader::read($oChild->get());
							if ($oVCal && $oVCal->VEVENT) 
							{
								foreach ($oVCal->VEVENT as $oVEvent) 
								{
									foreach($oVEvent->select('UID') as $oUid) 
									{
										if ((string)$oUid === $sEventId) 
										{
											$this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserUUID] = $oChild;
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
	 * @param string $sUserUUID
	 * @param object $oCalendar
	 * @param string $dStart
	 * @param string $dEnd
	 * 
	 * @return array
	 */
	public function getEventsFromVCalendar($sUserUUID, $oCalendar, $oVCal, $dStart = null, $dEnd = null, $bExpand = true)
	{
		$oVCalOriginal = clone $oVCal;

		if ($bExpand && $dStart !== null && $dEnd !== null)
		{
			$oVCal = $oVCalOriginal->expand(
				\Sabre\VObject\DateTimeParser::parse($dStart), 
				\Sabre\VObject\DateTimeParser::parse($dEnd)
			);
		}
		
		$aEvents = \Aurora\Modules\Calendar\Classes\Parser::parseEvent($sUserUUID, $oCalendar, $oVCal, $oVCalOriginal);
		
		return $aEvents;
	}
	
	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $dStart
	 * @param string $dEnd
	 * 
	 * @return array
	 */
	public function getExpandedEvent($sUserUUID, $sCalendarId, $sEventId, $dStart, $dEnd)
	{
		$this->init($sUserUUID);

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
				$mResult['Events'] = $this->getEventsFromVCalendar($sUserUUID, $oCalendar, $oVCal, $dStart, $dEnd);
				$mResult['CTag'] = $oCalendar->CTag;
				$mResult['SyncToken'] = $oCalendar->SyncToken;
			}
		}
		
		return $mResult;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sEventId
	 * @param array $aCalendars
	 * 
	 * @return array
	 */
	public function findEventInCalendars($sUserUUID,  $sEventId, $aCalendars)
	{
		$aEventCalendarIds = array();
		foreach (array_keys($aCalendars) as $sKey) {
			if ($this->eventExists($sUserUUID, $sKey, $sEventId)) {
				$aEventCalendarIds[] = $sKey;
			}
		}
		
		return $aEventCalendarIds;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * 
	 * @return bool
	 */
	public function eventExists($sUserUUID, $sCalendarId, $sEventId)
	{
		$bResult = false;
		$this->init($sUserUUID);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar && $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId) !== false) {
			$bResult = true;
		}
		
		return $bResult;
	}
	
	/**
	 * @param int $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * 
	 * @return array|bool
	 */
	public function getEvent($sUserUUID, $sCalendarId, $sEventId)
	{
		$mResult = false;
		$this->init($sUserUUID);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) 
		{		
			$oCalendarObject = $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId);
			if ($oCalendarObject) 
			{
				$mResult = array(
					'url'  => $oCalendarObject->getName(),
					'vcal' => \Sabre\VObject\Reader::read($oCalendarObject->get())
				);
			}
		}
		
		return $mResult;
	}

	public function getTasksUrls($oCalendar, $bShowCompleted = true, $sSearch = '')
	{
		$aFilter = [];
		if (!$bShowCompleted)
		{
			$aFilter[] = [
				'name'           => 'STATUS',
				'is-not-defined' => false,
				'time-range'     => false,
				'param-filters' => [],
				'text-match'     => [
					'negate-condition' => true,
					'collation'        => 'i;ascii-casemap',
					'value'            => 'COMPLETED',
				],
			];
		}
		if (!empty($sSearch))
		{
			$aFilter[] = [
				'name' => 'SUMMARY',
				'is-not-defined' => false,
				'param-filters' => array(),
				'time-range' => null,			
				'text-match' => array(
					'negate-condition' => false,
					'collation' => 'i;ascii-casemap',
					'value' => $sSearch,
				),
			];
		}
		return $oCalendar->calendarQuery(array(
			'name' => 'VCALENDAR',
			'comp-filters' => [
				[
					'name' => 'VTODO',
					'comp-filters' => [],
					'prop-filters' => $aFilter,
					'is-not-defined' => false,
					'time-range' => null,
				],
			],
			'prop-filters' => [],
			'is-not-defined' => false,
			'time-range' => null,
		));
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
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dEnd
	 * @param bool $bGetData
	 * 
	 * @return array|bool
	 */
	public function getEventsInfo($sUserUUID, $sCalendarId, $dStart, $dEnd, $bGetData = false)
	{
		$aResult = array();
		$this->init($sUserUUID);

		$oCalendar = $this->getCalDAVCalendar($sCalendarId);
		
		if ($oCalendar)
		{
			$aEventUrls = $this->getEventUrls($oCalendar, $dStart, $dEnd);
			$aTodoUrls = $this->getTodoUrls($oCalendar);
			$aUrls = array_merge($aEventUrls, $aTodoUrls);
			
			foreach ($aUrls as $sUrl)
			{
				if (isset($this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->UserUUID]))
				{
					$oEvent = $this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->UserUUID];
				}
				else
				{
					$oEvent = $oCalendar->getChild($sUrl);
					$this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->UserUUID] = $oEvent;
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

	public function getItemsByUrls($sUserUUID, $oCalDAVCalendar, $aUrls, $dStart = null, $dEnd = null, $bExpand = false)
	{
		$mResult = array();
		$oCalendar = $this->parseCalendar($oCalDAVCalendar);
		
		$oCalendar->IsPublic = ($sUserUUID === \Afterlogic\DAV\Constants::DAV_PUBLIC_PRINCIPAL);
		
		foreach ($aUrls as $sUrl) 
		{
			if (isset($this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserUUID])) {
				$oCalDAVCalendarObject = $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserUUID];
			} else {
				$oCalDAVCalendarObject = $oCalDAVCalendar->getChild($sUrl);
				$this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserUUID] = $oCalDAVCalendarObject;		
			}
			$oVCal = \Sabre\VObject\Reader::read($oCalDAVCalendarObject->get());
			$aEvents = $this->getEventsFromVCalendar($sUserUUID, $oCalendar, $oVCal, $dStart, $dEnd, $bExpand);
			foreach (array_keys($aEvents) as $key) {
				$aEvents[$key]['lastModified'] = $oCalDAVCalendarObject->getLastModified();
			}
			$mResult = array_merge($mResult, $aEvents);
		}
		
		return $mResult;
	}
	
	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $dStart
	 * @param string $dEnd
	 * @param bool $bExpand
	 * 
	 * @return array|bool
	 */
	public function getEvents($sUserUUID, $sCalendarId, $dStart, $dEnd, $bExpand = true)
	{
		$this->init($sUserUUID);

		$mResult = false;
		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);

		if ($oCalDAVCalendar) {

			$aUrls = $this->getEventUrls($oCalDAVCalendar, $dStart, $dEnd);
			$mResult = $this->getItemsByUrls($sUserUUID, $oCalDAVCalendar, $aUrls, $dStart, $dEnd, $bExpand);
		}

		return $mResult;
	}
	
	
	public function getTasks($sUserUUID, $sCalendarId, $bCompeted, $sSearch)
	{
		$this->init($sUserUUID);

		$mResult = false;
		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);

		if ($oCalDAVCalendar) {

			$aUrls = $this->getTasksUrls($oCalDAVCalendar, $bCompeted, $sSearch);
			$mResult = $this->getItemsByUrls($sUserUUID, $oCalDAVCalendar, $aUrls);
		}		

		return $mResult;
	}
	
	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 * 
	 * @return string|null
	 */
	public function createEvent($sUserUUID, $sCalendarId, $sEventId, $oVCal)
	{
		$this->init($sUserUUID);

		$sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
				$sData = $oVCal->serialize();
				$oCalDAVCalendar->createFile($sEventUrl, $sData);

				$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);

				return $sEventId;
			}
		}

		return null;
	}


	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 * 
	 * @return bool
	 */
	public function updateEventRaw($sUserUUID, $sCalendarId, $sEventId, $sData)
	{
		$this->init($sUserUUID);

		$sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
				$oCalDAVCalendarObject = $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId);
				if ($oCalDAVCalendarObject) {
					$oChild = $oCalDAVCalendar->getChild($oCalDAVCalendarObject->getName());
					if ($oChild) {
						$oChild->put($sData);
						$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
						unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);
						return true;
					}
				} else {
					$oCalDAVCalendar->createFile($sEventUrl, $sData);
					$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * @param array $oVCal
	 * 
	 * @return bool
	 */
	public function updateEvent($sUserUUID, $sCalendarId, $sEventId, $oVCal)
	{
 		$this->init($sUserUUID);
		
		$sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) 
		{
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) 
			{
				$oChild = $oCalDAVCalendar->getChild($sEventUrl);
				$sData = $oVCal->serialize();
				
				$oChild->put($sData);
				
				$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
				unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);
				return true;
			}
		}
		
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sNewCalendarId
	 * @param string $sEventId
	 * @param string $sData
	 *
	 * @return bool
	 */
	public function moveEvent($sUserUUID, $sCalendarId, $sNewCalendarId, $sEventId, $sData)
	{
		$this->init($sUserUUID);

		$sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalDAVCalendarNew = $this->getCalDAVCalendar($sNewCalendarId);
			if ($oCalDAVCalendarNew) {
				$oCalendar = $this->parseCalendar($oCalDAVCalendarNew);
				if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
					$oCalDAVCalendarNew->createFile($sEventUrl, $sData);
	
					$oChild = $oCalDAVCalendar->getChild($sEventUrl);
					$oChild->delete();

					$this->deleteReminder($sEventId);
					$this->updateReminder($oCalendar->Owner, $oCalendar->RealUrl, $sEventId, $sData);
					unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventId
	 * 
	 * @return bool
	 */
	public function deleteEvent($sUserUUID, $sCalendarId, $sEventId)
	{
		$this->init($sUserUUID);

		$sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar) {
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
				$oChild = $oCalDAVCalendar->getChild($sEventUrl);
				$oChild->delete();

				$this->deleteReminder($sEventId);
				unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);

				return (string) ($oCalendar->CTag + 1);
			}
		}
		
		return false;
	}
	
	/**
	 * @param string $sUserUUID
	 * @param string $sCalendarId
	 * @param string $sEventUrl
	 * 
	 * @return bool
	 */
	public function deleteEventByUrl($sUserUUID, $sCalendarId, $sEventUrl)
	{
		$this->init($sUserUUID);

		$oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
		if ($oCalDAVCalendar)
		{
			$oCalendar = $this->parseCalendar($oCalDAVCalendar);
			if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read)
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
