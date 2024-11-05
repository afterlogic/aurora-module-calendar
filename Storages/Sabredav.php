<?php
/**
 * This code is licensed under Afterlogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Storages;

use Afterlogic\DAV\CalDAV\Backend\PDO;
use Afterlogic\DAV\CalDAV\Shared\Calendar;
use Afterlogic\DAV\CalDAV\SharedWithAll\Calendar as SharedWithAllCalendar;
use Afterlogic\DAV\Server;
use Aurora\System\Api;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Sabre\CalDAV\SharedCalendar;
use Sabre\CalDAV\Subscriptions\Subscription;
use Sabre\DAV\Xml\Property\Href;
use Sabre\VObject\Component\VCalendar;

use function Sabre\Uri\split;

/**
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @internal
 */
class Sabredav extends \Aurora\System\Managers\AbstractStorage
{
    /**
     * @var array
     */
    public $Principal;

    /*
     * @var string
     */
    public $sUserPublicId;

    /*
     * @var array
     */
    protected $CalendarsCache;

    /*
     * @var array
     */
    protected $SharedCalendarsCache = [];

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

    /*
     * @var string
     */
    protected $UserPublicId;

    /**
     * @param \Aurora\System\Managers\AbstractManager $oManager
     */
    public function __construct(\Aurora\System\Managers\AbstractManager &$oManager)
    {
        parent::__construct($oManager);

        $this->UserPublicId = null;
        $this->TenantUser = null;
        $this->Principal = array();

        $this->CalendarsCache = array();
        $this->CalDAVCalendarsCache = array();
        $this->CalDAVCalendarObjectsCache = array();
    }

    /**
     * @param int $sUserPublicId
     *
     * @return bool
     */
    protected function _initialized($sUserPublicId)
    {
        return ($sUserPublicId !== null && $this->UserPublicId !== null);
    }

    /**
     * @param string $sUserPublicId
     */
    public function init($sUserPublicId)
    {
        //		if (!$this->_initialized($sUserPublicId))
        //		{
        $this->UserPublicId = $sUserPublicId;
        $this->Principal = $this->getPrincipalInfo($sUserPublicId);
        //		}
    }

    /**
     * @return \Afterlogic\DAV\CalDAV\Backend\PDO
     */
    public function getBackend()
    {
        return \Afterlogic\DAV\Backend::Caldav();
    }

    /**
     * @param string $sUserPublicId
     *
     * @return array
     */
    public function getPrincipalInfo($sUserPublicId)
    {
        $aPrincipal = array();

        $aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(
            \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sUserPublicId
        );
        if (isset($aPrincipalProperties['uri'])) {
            $aPrincipal['uri'] = $aPrincipalProperties['uri'];
            $aPrincipal['id'] = $aPrincipalProperties['id'];
        } else {
            $aPrincipal['uri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sUserPublicId;
            $aPrincipal['id'] = -1;
        }
        return $aPrincipal;
    }

    /**
     * @param string $sUserPublicId
     *
     * @return int
     */
    public function getCalendarAccess($sUserPublicId, $sCalendarId)
    {
        $iResult = \Aurora\Modules\Calendar\Enums\Permission::Read;
        $oCalendar = $this->getCalendar($sUserPublicId, $sCalendarId);
        if ($oCalendar) {
            $iResult = $oCalendar->Shared ? $oCalendar->Access : \Aurora\Modules\Calendar\Enums\Permission::Write;
        }
        return $iResult;
    }

    /**
     * @param string $sUserPublicId
     * @param string $uid
     *
     * @return string|false
     */

    public function findEventInCalendars($sUserPublicId, $uid)
    {
        $result = false;

        $this->init($sUserPublicId);
        $oCalendarHome = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
        $calendarPath = $oCalendarHome->getCalendarObjectByUID($uid);
        if (!empty($calendarPath)) {
            list($dirname, ) = split($calendarPath);
            $result = $dirname;
        }

        return $result;
    }

    /**
     * Returns a single calendar, by name
     *
     * @param string $sPath
     *
     * @return \Sabre\CalDAV\Calendar|bool
     */
    public function getCalDAVCalendar($sPath)
    {
        $oCalendar = false;
        if (!empty($sPath)) {
            list(, $sCalendarId) = \Sabre\Uri\split($sPath);
            if (count($this->CalDAVCalendarsCache) > 0 && isset($this->CalDAVCalendarsCache[$sCalendarId][$this->UserPublicId])) {
                $oCalendar = $this->CalDAVCalendarsCache[$sCalendarId][$this->UserPublicId];
            } else {
                // $oCalendar = \Afterlogic\DAV\Server::getNodeForPath('calendars/'.$sPath, $this->Principal['id']);
                // if ($oCalendar) {
                //     $this->CalDAVCalendarsCache[$sCalendarId][$this->UserPublicId] = $oCalendar;
                // }
                $oCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
                if ($oCalendars && $oCalendars->childExists($sCalendarId)) {
                    $oCalendar = $oCalendars->getChild($sCalendarId);
                    $this->CalDAVCalendarsCache[$sCalendarId][$this->UserPublicId] = $oCalendar;
                }
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
        if (!($oCalDAVCalendar instanceof \Sabre\CalDAV\Calendar || $oCalDAVCalendar instanceof \Sabre\CalDAV\Subscriptions\Subscription)) {
            return false;
        }

        /** @var \Aurora\Modules\Calendar\Module @oCalendarModule */
        $oCalendarModule = Api::GetModule('Calendar');

        if ($oCalDAVCalendar instanceof \Sabre\CalDAV\Subscriptions\Subscription && !$oCalendarModule->oModuleSettings->AllowSubscribedCalendars) {
            return false;
        }

        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        $oCalendar = new \Aurora\Modules\Calendar\Classes\Calendar($oCalDAVCalendar->getName());
        $oCalendar->Shares = [];
        $aTenantPrincipal = $this->getPrincipalInfo($this->getTenantUser());

        if ($oCalDAVCalendar instanceof \Sabre\CalDAV\SharedCalendar) {
            if ($oCalDAVCalendar->getShareAccess() !== \Sabre\DAV\Sharing\Plugin::ACCESS_NOTSHARED) {
                foreach ($oCalDAVCalendar->getInvites() as $oSharee) {
                    if ($oSharee->principal !== $aTenantPrincipal['uri']) {
                        if ($oSharee instanceof \Sabre\DAV\Xml\Element\Sharee) {
                            $shareEmail = $oSharee->href;
                            if (!strpos($shareEmail, ':')) {
                                $shareEmail = basename($shareEmail);
                            } else {
                                list($schema, $everythingElse) = explode(':', $shareEmail, 2);
                                $schema = strtolower($schema);
                                if ('mailto' === $schema) {
                                    $shareEmail = strtolower($everythingElse);
                                }
                            }

                            if ($oSharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER) {
                                $oCalendar->Owner = $shareEmail;
                            } elseif ($oSharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE || $oSharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_READ) {
                                $oCalendar->Shares[] = [
                                    'name' => $shareEmail,
                                    'email' => $shareEmail,
                                    'access' => $oSharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE ?
                                        \Aurora\Modules\Calendar\Enums\Permission::Write : \Aurora\Modules\Calendar\Enums\Permission::Read
                                ];
                            }
                        }
                    } else {
                        $oCalendar->SharedToAll = true;
                        $oCalendar->SharedToAllAccess = $oSharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE ?
                            \Aurora\Modules\Calendar\Enums\Permission::Write : \Aurora\Modules\Calendar\Enums\Permission::Read;
                    }
                }
                $oCalendar->Shared = $oCalDAVCalendar->getShareAccess() !== \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER;
                $oCalendar->Access = $oCalDAVCalendar->getShareAccess() === \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE ||
                        $oCalDAVCalendar->getShareAccess() === \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER ||
                            ($oUser && $oCalendar->Owner === $oUser->PublicId) ?
                        \Aurora\Modules\Calendar\Enums\Permission::Write : \Aurora\Modules\Calendar\Enums\Permission::Read;
            }
        }
        if ($oUser && !$oCalendar->Shared) {
            $oCalendar->Owner = $oUser->PublicId;
        }

        $aProps = $oCalDAVCalendar->getProperties([
            'id',
            '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}calendar-description',
            '{DAV:}displayname',
            '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag',
            '{http://apple.com/ns/ical/}calendar-color',
            '{http://apple.com/ns/ical/}calendar-order',
            '{http://sabredav.org/ns}owner-principal',
            '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}source'
        ]);
        if (isset($aProps['id']) && isset($aProps['id'][0])) {
            $oCalendar->IntId = $aProps['id'][0];
        }
        if (isset($aProps['{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}calendar-description'])) {
            $oCalendar->Description = $aProps['{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}calendar-description'];
        }
        if (isset($aProps['{DAV:}displayname'])) {
            $oCalendar->DisplayName = $aProps['{DAV:}displayname'];
        }
        if (isset($aProps['{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'])) {
            $oCalendar->CTag = $aProps['{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'];
        }
        if (isset($aProps['{http://apple.com/ns/ical/}calendar-color'])) {
            $oCalendar->Color = $aProps['{http://apple.com/ns/ical/}calendar-color'];
        }
        if (isset($aProps['{http://apple.com/ns/ical/}calendar-order'])) {
            $oCalendar->Order = $aProps['{http://apple.com/ns/ical/}calendar-order'];
        }
        if (isset($aProps['{http://sabredav.org/ns}owner-principal'])) {
            $oCalendar->Principals = [$aProps['{http://sabredav.org/ns}owner-principal']];
        } else {
            $oCalendar->Principals = [$oCalDAVCalendar->getOwner()];
        }

        $oCalendar->Url = 'calendars/' . $oCalDAVCalendar->getName();
        $oCalendar->RealUrl = 'calendars/' . $oCalDAVCalendar->getName();
        $oCalendar->SyncToken = $oCalDAVCalendar instanceof \Sabre\CalDAV\Calendar ? (string) $oCalDAVCalendar->getSyncToken() : '';

        $sPrincipalUri = '';
        $aProperties = $oCalDAVCalendar->getProperties(['principaluri']);
        if (isset($aProperties['principaluri'])) {
            $sPrincipalUri = $aProperties['principaluri'];
        }

        $oCalendar->PubHash = $this->getPublicCalendarHash($oCalendar->Id);
        $oCalendar->IsPublic = $this->getPublishStatus($oCalendar->Id);

        if ($oCalDAVCalendar instanceof \Sabre\CalDAV\Subscriptions\Subscription) {
            $oCalendar->Subscribed = true;
            if (isset($aProps['{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}source'])) {
                $oCalendar->Source = $aProps['{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}source']->getHref();
            }
        }
        if (!$oCalendar->Subscribed) {
            $oCalendar->IsDefault = (!$oCalendar->Shared && ($oCalDAVCalendar instanceof \Afterlogic\DAV\CalDAV\Calendar || $oCalDAVCalendar instanceof \Afterlogic\DAV\CalDAV\Shared\Calendar) && $oCalDAVCalendar->isDefault());
        }

        return $oCalendar;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     *
     * @return \Aurora\Modules\Calendar\Classes\Calendar|bool
     */
    public function getCalendar($sUserPublicId, $sCalendarId)
    {
        $this->init($sUserPublicId);

        $oCalDAVCalendar = null;
        $oCalendar = false;
        if (count($this->CalendarsCache) > 0 && isset($this->CalendarsCache[$this->UserPublicId][$sCalendarId])) {
            $oCalendar = $this->CalendarsCache[$this->UserPublicId][$sCalendarId];
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
     * @return string
     */
    public function getPublicAccount()
    {
        return $this->getPublicUser();
    }

    /**
     * @return array|null
     */
    public function getTenantUser($oUser = null)
    {
        if (!isset($this->TenantUser)) {
            $sPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;

            $oUser = $oUser ? $oUser : \Aurora\System\Api::getAuthenticatedUser();
            if ($oUser) {
                $sPrincipal = $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
            }

            $this->TenantUser = $sPrincipal;
        }
        return $this->TenantUser;
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

    public function getPublicCalendar($sCalendar)
    {
        $oCalendar = false;

        $oBackend = $this->getBackend();

        if ($oBackend instanceof PDO) {
            $aCalendar = $oBackend->getPublicCalendar($sCalendar);
            if ($aCalendar) {
                $oCalendar = new \Afterlogic\DAV\CalDAV\PublicCalendar($oBackend, $aCalendar);
            }
        }

        return $oCalendar;
    }

    /**
     * @param string $sUserPublicId
     *
     * @return array
     */
    public function getCalendars($sUserPublicId)
    {
        $aCalendars = array();
        if (!empty($sUserPublicId)) {
            $this->init($sUserPublicId);

            if (count($this->CalendarsCache) > 0 && isset($this->CalendarsCache[$sUserPublicId])) {
                $aCalendars = $this->CalendarsCache[$sUserPublicId];
            } else {
                $oCalendarHome = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

                foreach ($oCalendarHome->getChildren() as $oCalDAVCalendar) {
                    $oCalendar = $this->parseCalendar($oCalDAVCalendar);
                    if ($oCalendar && !($oCalendar->Shared || $oCalendar->SharedToAll)) {
                        $aCalendars[$oCalendar->Id] = $oCalendar;
                    }
                }
                $this->CalendarsCache[$sUserPublicId] = $aCalendars;
            }
        }
        return $aCalendars;
    }

    public function getSharedCalendars($sUserPublicId)
    {
        $aCalendars = array();
        if (!empty($sUserPublicId)) {
            $this->init($sUserPublicId);

            if (count($this->SharedCalendarsCache) > 0 && isset($this->SharedCalendarsCache[$sUserPublicId])) {
                $aCalendars = $this->SharedCalendarsCache[$sUserPublicId];
            } else {
                $oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

                foreach ($oUserCalendars->getChildren() as $oCalDAVCalendar) {
                    $oCalendar = $this->parseCalendar($oCalDAVCalendar);
                    if ($oCalendar && ($oCalendar->Shared || $oCalendar->SharedToAll)) {
                        $aCalendars[$oCalendar->Id] = $oCalendar;
                    }
                }

                $this->SharedCalendarsCache[$sUserPublicId] = $aCalendars;
            }
        }
        return $aCalendars;
    }

    /**
     * @param string $sUserPublicId
     *
     * @return array
     */
    public function GetCalendarNames($sUserPublicId)
    {
        $aCalendarNames = array();
        $aCalendars = $this->getCalendars($sUserPublicId);
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
     * @param string $sUserPublicId
     * @param string $sName
     * @param string $sDescription
     * @param int $iOrder
     * @param string $sColor
     *
     * @return string
     */
    public function createCalendar($sUserPublicId, $sName, $sDescription, $iOrder, $sColor, $sUUID = null)
    {
        $this->init($sUserPublicId);

        $oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

        if ($sUUID === null) {
            $sSystemName = \Sabre\DAV\UUIDUtil::getUUID();
        } else {
            $sSystemName = $sUUID;
        }

        $oUserCalendars->createExtendedCollection(
            $sSystemName,
            new \Sabre\DAV\MkCol(
                [
                    '{DAV:}collection',
                    '{urn:ietf:params:xml:ns:caldav}calendar'
                ],
                [
                    '{DAV:}displayname' => $sName,
                    '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => 1,
                    '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}calendar-description' => $sDescription,
                    '{http://apple.com/ns/ical/}calendar-color' => $sColor,
                    '{http://apple.com/ns/ical/}calendar-order' => $iOrder
                ]
            )
        );
        return $sSystemName;
    }

    public function createSubscribedCalendar($sUserPublicId, $sName, $sSource, $iOrder, $sColor, $sUUID = null)
    {
        $this->init($sUserPublicId);

        $oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);

        if ($sUUID === null) {
            $sSystemName = \Sabre\DAV\UUIDUtil::getUUID();
        } else {
            $sSystemName = $sUUID;
        }

        $oUserCalendars->createExtendedCollection(
            $sSystemName,
            new \Sabre\DAV\MkCol(
                [
                    '{DAV:}collection',
                    '{http://calendarserver.org/ns/}subscribed'
                ],
                [
                    '{DAV:}displayname' => $sName,
                    '{http://calendarserver.org/ns/}source' => new Href($sSource),
                    '{http://apple.com/ns/ical/}calendar-color' => $sColor,
                    '{http://apple.com/ns/ical/}calendar-order' => $iOrder
                ]
            )
        );
        return $sSystemName;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sName
     * @param string $sDescription
     * @param int $iOrder
     * @param string $sColor
     *
     * @return bool
     */
    public function updateCalendar($sUserPublicId, $sCalendarId, $sName, $sDescription, $iOrder, $sColor)
    {
        $this->init($sUserPublicId);

        $oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
        if ($oUserCalendars->childExists($sCalendarId)) {
            $oCalDAVCalendar = $oUserCalendars->getChild($sCalendarId);
            if ($oCalDAVCalendar) {
                Server::checkPrivileges('calendars/' . $oCalDAVCalendar->getName(), '{DAV:}write-properties');

                $aUpdateProperties = array();
                $bOnlyColor = ($sName === null && $sDescription === null && $iOrder === null);
                if ($bOnlyColor) {
                    $aUpdateProperties = array(
                        '{http://apple.com/ns/ical/}calendar-color' => $sColor
                    );
                } else {
                    $aUpdateProperties = array(
                        '{DAV:}displayname' => $sName,
                        '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}calendar-description' => $sDescription,
                        '{http://apple.com/ns/ical/}calendar-color' => $sColor,
                        '{http://apple.com/ns/ical/}calendar-order' => $iOrder
                    );
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
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sName
     * @param string $sSource
     * @param int $iOrder
     * @param string $sColor
     *
     * @return bool
     */
    public function updateSubscribedCalendar($sUserPublicId, $sCalendarId, $sName, $sSource, $iOrder, $sColor)
    {
        $this->init($sUserPublicId);

        $oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
        if ($oUserCalendars->childExists($sCalendarId)) {
            $oCalDAVCalendar = $oUserCalendars->getChild($sCalendarId);
            if ($oCalDAVCalendar) {
                $aUpdateProperties = array();
                $bOnlyColor = ($sName === null && $sSource === null && $iOrder === null);
                if ($bOnlyColor) {
                    $aUpdateProperties = array(
                        '{http://apple.com/ns/ical/}calendar-color' => $sColor
                    );
                } else {
                    $aUpdateProperties = array(
                        '{DAV:}displayname' => $sName,
                        '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}source' => new Href($sSource),
                        '{http://apple.com/ns/ical/}calendar-color' => $sColor,
                        '{http://apple.com/ns/ical/}calendar-order' => $iOrder
                    );
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
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sColor
     *
     * @return bool
     */
    public function updateCalendarColor($sUserPublicId, $sCalendarId, $sColor)
    {
        return $this->updateCalendar($sUserPublicId, $sCalendarId, null, null, null, $sColor);
    }

    public function deletePrincipalCalendars($sUserPublicId)
    {
        $oBackend = $this->getBackend();

        if ($oBackend instanceof PDO) {
            $oBackend->deletePrincipalCalendars('principals/' . $sUserPublicId);
            $oBackend->deleteSubscriptionsByPrincipal('principals/' . $sUserPublicId);
        }

        $this->deleteRemindersByUser($sUserPublicId);
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     *
     * @return bool
     */
    public function deleteCalendar($sUserPublicId, $sCalendarId)
    {
        $this->init($sUserPublicId);

        $oUserCalendars = new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
        if ($oUserCalendars->childExists($sCalendarId)) {
            $oCalDAVCalendar = $oUserCalendars->getChild($sCalendarId);
            if ($oCalDAVCalendar) {
                $oCalDAVCalendar->delete();

                $this->deleteReminderByCalendar($sCalendarId);
                unset($this->CalDAVCalendarsCache[$sCalendarId]);
                unset($this->CalDAVCalendarObjectsCache[$sCalendarId]);

                return true;
            }
        }
        return false;
    }

    /**
     * @param string $sUserPublicId
     */
    public function clearAllCalendars($sUserPublicId)
    {
        $this->init($sUserPublicId);

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

        return true;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     *
     * @return bool
     */
    public function unsubscribeCalendar($sUserPublicId, $sCalendarId)
    {
        $this->init($sUserPublicId);

        $oCalendar = $this->getCalendar($sUserPublicId, $sCalendarId);
        if ($oCalendar) {
            //            $this->getBackend()->updateShares($oCalendar->IntId, array(), array($sUserPublicId));
        }

        return true;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param array $aShares
     *
     * @return bool
     */
    public function updateCalendarShares($sUserPublicId, $sCalendarId, $aShares)
    {
        $this->init($sUserPublicId);

        $oDAVCalendar = $this->getCalDAVCalendar($sCalendarId);

        if (!$oDAVCalendar) {
            // SharedWithAll
            $this->Principal = $this->getPrincipalInfo($this->getTenantUser());
            $oDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        }

        if ($oDAVCalendar instanceof SharedCalendar) {
            $aServerShares = $oDAVCalendar->getInvites();
            $aShereEmails = array_map(
                function ($aItem) {
                    return $aItem['email'];
                },
                $aShares
            );
            foreach ($aServerShares as $oSharee) {
                $sShareEmail = basename($oSharee->principal);
                if (!in_array($sShareEmail, $aShereEmails)) {
                    $aShares[] = [
                        'email' => $sShareEmail,
                        'access' => \Aurora\Modules\Calendar\Enums\Permission::RemovePermission
                    ];
                }
            }

            $aShareObjects = [];
            $bSharedWithAll = false;
            foreach ($aShares as $aShare) {
                $oShareObject = new \Sabre\DAV\Xml\Element\Sharee();
                $oShareObject->href = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $aShare['email'];
                $oShareObject->principal = $oShareObject->href;

                switch ($aShare['access']) {
                    case \Aurora\Modules\Calendar\Enums\Permission::RemovePermission:
                        $oShareObject->access = \Sabre\DAV\Sharing\Plugin::ACCESS_NOACCESS;
                        break;
                    case \Aurora\Modules\Calendar\Enums\Permission::Write:
                        $oShareObject->access = \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE;
                        break;
                    default:
                        $oShareObject->access = \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
                }

                $aShareObjects[] = $oShareObject;

                $bSharedWithAll = !$bSharedWithAll && $aShare['email'] === $this->getTenantUser();
            }
            $oDAVCalendar->updateInvites($aShareObjects);
            return true;
        }

        return false;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sUserId
     * @param int $iPerms
     *
     * @return bool
     */
    public function updateCalendarShare($sUserPublicId, $sCalendarId, $sUserId, $iPerms = \Aurora\Modules\Calendar\Enums\Permission::RemovePermission)
    {
        $this->init($sUserPublicId);

        $oCalendar = $this->getCalendar($sUserPublicId, $sCalendarId);

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
                    } elseif ($iPerms === \Aurora\Modules\Calendar\Enums\Permission::Write) {
                        $aItem['readonly'] = false;
                    }
                    $add[] = $aItem;
                }

                //                $this->getBackend()->updateShares($oCalendar->IntId, $add, $remove);
            }
        }

        return true;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     *
     * @return bool
     */
    public function deleteCalendarShares($sUserPublicId, $sCalendarId)
    {
        $this->init($sUserPublicId);

        $oCalendar = $this->getCalendar($sUserPublicId, $sCalendarId);

        if ($oCalendar && count($oCalendar->Principals) > 0) {
            $this->updateCalendarShares($sUserPublicId, $sCalendarId, array());
        }

        return true;
    }

    /**
     * @param string $sCalendarId
     * @param bool $bIsPublic Default value is **false**.
     *
     * @return bool
     */
    public function publicCalendar($sCalendarId, $bIsPublic = false, $oUser = null)
    {
        return $this->getBackend()->setPublishStatus($sCalendarId, $bIsPublic, $oUser);
    }

    /**
     * @param string $sCalendarId
     *
     * @return bool
     */
    public function getPublishStatus($sCalendarId)
    {
        return $this->getBackend()->getPublishStatus($sCalendarId);
    }

    /**
     * @param string $sUserPublicId
     * @param \Aurora\Modules\Calendar\Classes\Calendar $oCalendar
     *
     * @return array
     */
    public function getCalendarUsers($sUserPublicId, $oCalendar)
    {
        $aResult = array();

        $this->init($sUserPublicId);

        if ($oCalendar != null) {
            $aShares = $this->getBackend()->getInvites($oCalendar->IntId);

            foreach ($aShares as $oShare) {
                $access = \Aurora\Modules\Calendar\Enums\Permission::Read;
                if ($oShare->access === \Sabre\DAV\Sharing\Plugin::ACCESS_READ) {
                    $access = \Aurora\Modules\Calendar\Enums\Permission::Read;
                } elseif ($oShare->access === \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE) {
                    $access = \Aurora\Modules\Calendar\Enums\Permission::Write;
                }
                $aResult[] = array(
                    'name' => basename($oShare->href),
                    'email' => basename($oShare->href),
                    'access' => $access
                );
            }
        }

        return $aResult;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     *
     * @return string|bool
     */
    public function exportCalendarToIcs($sUserPublicId, $sCalendarId)
    {
        $this->init($sUserPublicId);

        $mResult = false;
        $oCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalendar) {
            $aCollectedTimezones = array();

            $aTimezones = array();
            $aObjects = array();

            foreach ($oCalendar->getChildren() as $oChild) {
                $oNodeComp = \Sabre\VObject\Reader::read($oChild->get());
                foreach ($oNodeComp->children() as $oNodeChild) {
                    switch($oNodeChild->name) {
                        case 'VEVENT':
                        case 'VTODO':
                        case 'VJOURNAL':
                            $aObjects[] = $oNodeChild;
                            break;

                        case 'VTIMEZONE':
                            if (in_array((string)$oNodeChild->TZID, $aCollectedTimezones)) {
                                continue 2;
                            }

                            $aTimezones[] = $oNodeChild;
                            $aCollectedTimezones[] = (string)$oNodeChild->TZID;
                            break;
                    }
                }
            }

            $oVCal = new \Sabre\VObject\Component\VCalendar();
            foreach ($aTimezones as $oTimezone) {
                $oVCal->add($oTimezone);
            }
            foreach ($aObjects as $oObject) {
                $oVCal->add($oObject);
            }

            $mResult = $oVCal->serialize();
        }

        return $mResult;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sTempFileName
     *
     * @return mixed
     */
    public function importToCalendarFromIcs($sUserPublicId, $sCalendarId, $sTempFileName)
    {
        $this->init($sUserPublicId);

        $mResult = false;
        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar) {
            $oCalendar = $this->parseCalendar($oCalDAVCalendar);
            // You can either pass a readable stream, or a string.
            $h = fopen($sTempFileName, 'r');
            $splitter = new \Sabre\VObject\Splitter\ICalendar($h, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);

            $iCount = 0;
            while ($oVCalendar = $splitter->getNext()) {
                if ($oVCalendar instanceof VCalendar) {
                    $oVEvents = $oVCalendar->VEVENT;
                    if (!$oVEvents || 0 === count($oVEvents)) {
                        $oVEvents = $oVCalendar->VTODO;
                    }
                    if ($oVEvents && 0 < count($oVEvents) && isset($oVEvents[0])) {
                        $sUid = str_replace(array("/", "=", "+"), "", $oVEvents[0]->UID);

                        if (!$oCalDAVCalendar->childExists($sUid . '.ics')) {
                            $oVEvents[0]->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
                            Server::getInstance()->httpRequest->setUrl($oCalendar->Url . '/' . $sUid . '.ics');
                            try {
                                Server::getInstance()->createFile($oCalendar->Url . '/' . $sUid . '.ics', $oVCalendar->serialize());
                                $iCount++;
                            } catch (\Sabre\VObject\Recur\NoInstancesException $oEx) {
                                // If event is recurring, but it doesn't have a single
                                // instance. We are skipping this event
                                Api::Log($oEx->getMessage());
                            } catch (\Sabre\VObject\ParseException $oEx) {
                                // Skipping event with invalid data
                                Api::Log($oEx->getMessage());
                            }
                        }
                    }
                }
            }
            $mResult = $iCount;
        }

        return $mResult;
    }

    public function getCalendarHome()
    {
        return new \Afterlogic\DAV\CalDAV\CalendarHome($this->getBackend(), $this->Principal);
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
            if (count($this->CalDAVCalendarObjectsCache) > 0 &&
                isset($this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserPublicId])) {
                return $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserPublicId];
            } else {
                // $oChild = \Afterlogic\DAV\Server::getNodeForPath('calendars/'.$oCalDAVCalendar->getName() .'/'.$sEventFileName, $this->Principal['id']);
                // if ($oChild instanceof \Sabre\CalDAV\CalendarObject) {
                //     $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserPublicId] = $oChild;
                //     return $oChild;
                // }
                if ($oCalDAVCalendar->childExists($sEventFileName)) {
                    $oChild = $oCalDAVCalendar->getChild($sEventFileName);
                    if ($oChild instanceof \Sabre\CalDAV\CalendarObject) {
                        $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserPublicId] = $oChild;
                        return $oChild;
                    }
                } else {
                    $oCalendar = $this->parseCalendar($oCalDAVCalendar);
                    $curPrincipal = $this->Principal;
                    if ($this->Principal['id'] !== $oCalendar->Owner) {
                        $this->Principal = [
                            'uri' => 'principals/' . $oCalendar->Owner,
                            'id' => $oCalendar->Owner
                        ];
                    }
                    $oCalendarHome = $this->getCalendarHome();
                    $sChildUri = $oCalendarHome->getCalendarObjectByUID($sEventId);
                    $this->Principal = $curPrincipal;
                    if (!empty($sChildUri)) {
                        list(, $sEventFileName) = \Sabre\Uri\split($sChildUri);
                        if ($oCalDAVCalendar->childExists($sEventFileName)) {
                            $oChild = $oCalDAVCalendar->getChild($sEventFileName);
                            if ($oChild instanceof \Sabre\CalDAV\CalendarObject) {
                                $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sEventFileName][$this->UserPublicId] = $oChild;
                                return $oChild;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }


    /**
     * @param string $sUserPublicId
     * @param object $oCalendar
     * @param \Sabre\VObject\Component\VCalendar $oVCal
     * @param string $dStart
     * @param string $dEnd
     * @param bool $bExpand
     * @param string $sDefaultTimeZone
     *
     * @return array
     */
    public function getEventsFromVCalendar($sUserPublicId, $oCalendar, $oVCal, $dStart = null, $dEnd = null, $bExpand = true, $sDefaultTimeZone = null)
    {
        $bIsTodo = false;
        if (isset($oVCal->VTODO)) {
            if (isset($oVCal->VTODO->DUE)) {
                $oVCal->VTODO->DTEND = $oVCal->VTODO->DUE->getDateTime();
            }
            /** @var \Sabre\VObject\Component\VCalendar */
            $oVCal = \Sabre\VObject\Reader::read(
                str_replace('VTODO', 'VEVENT', $oVCal->serialize())
            );
            $bIsTodo = true;
        }

        if ($bExpand && $oVCal->VEVENT->RRULE) {
            $oExpandedVCal = null;

            foreach ($oVCal->VEVENT as $vEvent) {
                if (!isset($vEvent->DTSTART)) {
                    return []; // skip invalid repeat rrule instance without DTSTART
                }
            }

            if (isset($oVCal->VEVENT->DTSTART)) {
                try {
                    $oExpandedVCal = $oVCal->expand(
                        \Sabre\VObject\DateTimeParser::parse($dStart),
                        \Sabre\VObject\DateTimeParser::parse($dEnd)
                    );
                } catch (\Exception $oEx) {
                    return [];
                }
            } else {
                return [];
            }

            if ($bIsTodo && $oExpandedVCal) {
                $oVCal = \Sabre\VObject\Reader::read(
                    str_replace('VEVENT', 'VTODO', $oVCal->serialize())
                );

                $oExpandedVCal = \Sabre\VObject\Reader::read(
                    str_replace('VEVENT', 'VTODO', $oExpandedVCal->serialize())
                );
            }
        } else {
            if ($bIsTodo) {
                $oVCal = \Sabre\VObject\Reader::read(
                    str_replace('VEVENT', 'VTODO', $oVCal->serialize())
                );
            }
            $oExpandedVCal = clone $oVCal;
        }

        return \Aurora\Modules\Calendar\Classes\Parser::parseEvent($sUserPublicId, $oCalendar, $oExpandedVCal, $oVCal, $sDefaultTimeZone);
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventId
     * @param string $dStart
     * @param string $dEnd
     *
     * @return array
     */
    public function getExpandedEvent($sUserPublicId, $sCalendarId, $sEventId, $dStart, $dEnd)
    {
        $this->init($sUserPublicId);

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
                $mResult['Events'] = $this->getEventsFromVCalendar($sUserPublicId, $oCalendar, $oVCal, $dStart, $dEnd);
                $mResult['CTag'] = $oCalendar->CTag;
                $mResult['SyncToken'] = $oCalendar->SyncToken;
            }
        }

        return $mResult;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventId
     *
     * @return bool
     */
    public function eventExists($sUserPublicId, $sCalendarId, $sEventId)
    {
        $bResult = false;
        $this->init($sUserPublicId);

        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar && $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId) !== false) {
            $bResult = true;
        }

        return $bResult;
    }

    /**
     * @param int $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventId
     *
     * @return array|bool
     */
    public function getEvent($sUserPublicId, $sCalendarId, $sEventId)
    {
        $mResult = false;
        $this->init($sUserPublicId);

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

    public function getFilterForTasks($aSubFilter)
    {
        return [
            'name' => 'VCALENDAR',
            'comp-filters' => [
                [
                    'name' => 'VTODO',
                    'comp-filters' => [],
                    'prop-filters' => $aSubFilter,
                    'is-not-defined' => false,
                    'time-range' => null,
                ],
            ],
            'prop-filters' => [],
            'is-not-defined' => false,
            'time-range' => null,
        ];
    }

    public function getSerchFilterForTasks($sSearch)
    {
        return [
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

    public function getNotCompletedFilterForTasks()
    {
        return  [
            'name'           => 'STATUS',
            'is-not-defined' => false,
            'time-range'     => null,
            'param-filters' => [],
            'text-match'     => [
                'negate-condition' => true,
                'collation'        => 'i;ascii-casemap',
                'value'            => 'COMPLETED',
            ],
        ];
    }

    public function getTasksUrls($oCalendar, $bShowCompleted = true, $sSearch = '')
    {
        $aFilter = [];
        if (!$bShowCompleted) {
            $aFilter[] = $this->getNotCompletedFilterForTasks();
        }
        if (!empty($sSearch)) {
            $aFilter[] = $this->getSerchFilterForTasks($sSearch);
        }

        $aFilter = $this->getFilterForTasks($aFilter);
        $aResult = $oCalendar->calendarQuery($aFilter);

        if (!$bShowCompleted) {
            $aFilter = [[
                'name'           => 'STATUS',
                'is-not-defined' => true,
            ]];
            if (!empty($sSearch)) {
                $aFilter[] = $this->getSerchFilterForTasks($sSearch);
            }
            $aFilter = $this->getFilterForTasks($aFilter);

            $aResult = array_merge(
                $aResult,
                $oCalendar->calendarQuery($aFilter)
            );
        }

        return $aResult;
    }

    /**
     * @param object $oCalendar
     * @param object $dStart
     * @param object $dEnd
     *
     * @return array
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
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $dStart
     * @param string $dEnd
     * @param bool $bGetData
     *
     * @return array|bool
     */
    public function getEventsInfo($sUserPublicId, $sCalendarId, $dStart, $dEnd, $bGetData = false)
    {
        $aResult = array();
        $this->init($sUserPublicId);

        $oCalendar = $this->getCalDAVCalendar($sCalendarId);

        if ($oCalendar) {
            $aEventUrls = $this->getEventUrls($oCalendar, $dStart, $dEnd);
            $aTodoUrls = $this->getTasksUrls($oCalendar);
            $aUrls = array_merge($aEventUrls, $aTodoUrls);

            foreach ($aUrls as $sUrl) {
                if (isset($this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->UserPublicId])) {
                    $oEvent = $this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->UserPublicId];
                } else {
                    $oEvent = $oCalendar->getChild($sUrl);
                    $this->CalDAVCalendarObjectsCache[$oCalendar->getName()][$sUrl][$this->UserPublicId] = $oEvent;
                }

                $aEventInfo = array(
                    'Url' => $sUrl,
                    'ETag' => $oEvent->getETag(),
                    'LastModified' => $oEvent->getLastModified()
                );
                if ($bGetData) {
                    $aEventInfo['Data'] = $oEvent->get();
                }

                $aResult[$oCalendar->getName()][] = $aEventInfo;
            }
        }

        return $aResult;
    }

    public function getItemsByUrls($sUserPublicId, $oCalDAVCalendar, $aUrls, $dStart = null, $dEnd = null, $bExpand = false)
    {
        $mResult = array();
        $oCalendar = $this->parseCalendar($oCalDAVCalendar);

        $oCalendar->IsPublic = ($sUserPublicId === \Afterlogic\DAV\Constants::DAV_PUBLIC_PRINCIPAL);

        foreach ($aUrls as $sUrl) {
            $oCalDAVCalendarObject = null;
            if (isset($this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserPublicId])) {
                $oCalDAVCalendarObject = $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserPublicId];
            } else {
                if ($oCalDAVCalendar->childExists($sUrl)) {
                    $oCalDAVCalendarObject = $oCalDAVCalendar->getChild($sUrl);
                    $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$this->UserPublicId] = $oCalDAVCalendarObject;
                }
            }
            if ($oCalDAVCalendarObject) {
                $oVCal = \Sabre\VObject\Reader::read($oCalDAVCalendarObject->get());
                $aEvents = $this->getEventsFromVCalendar($sUserPublicId, $oCalendar, $oVCal, $dStart, $dEnd, $bExpand);
                foreach (array_keys($aEvents) as $key) {
                    $aEvents[$key]['lastModified'] = $oCalDAVCalendarObject->getLastModified();
                }
                $mResult = array_merge($mResult, $aEvents);
            }
        }

        return $mResult;
    }

    public function getVCalFromSubscription($oCalDAVCalendar)
    {
        $mResult = false;

        $aProps = $oCalDAVCalendar->getProperties([
            '{http://calendarserver.org/ns/}source'
        ]);
        if (isset($aProps['{http://calendarserver.org/ns/}source'])) {
            $client = new Client();
            try {
                $res = $client->get(
                    $aProps['{http://calendarserver.org/ns/}source']->getHref(),
                    [
                        'headers' => [
                            'Accept'     => '*/*',
                        ],
                        'http_errors' => false
                    ]
                );
                if ($res->getStatusCode() === 200) {
                    $data = (string) $res->getBody();
                    $mResult = \Sabre\VObject\Reader::read($data);
                }
            } catch (ConnectException $oEx) {
            }
        }

        return $mResult;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $dStart
     * @param string $dEnd
     * @param bool $bExpand
     *
     * @return array|bool
     */
    public function getEvents($sUserPublicId, $sCalendarId, $dStart, $dEnd, $bExpand = true)
    {
        $this->init($sUserPublicId);

        $mResult = false;
        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);

        if ($oCalDAVCalendar) {
            if ($oCalDAVCalendar instanceof Subscription) {
                $oVCal = $this->getVCalFromSubscription($oCalDAVCalendar);
                if ($oVCal) {
                    $oCalendar = $this->parseCalendar($oCalDAVCalendar);
                    $mResult = $this->getEventsFromVCalendar($sUserPublicId, $oCalendar, $oVCal, $dStart, $dEnd, $bExpand);
                    foreach (array_keys($mResult) as $key) {
                        $mResult[$key]['lastModified'] = $oCalDAVCalendar->getLastModified();
                    }
                }
            } else {
                $aUrls = $this->getEventUrls($oCalDAVCalendar, $dStart, $dEnd);
                $mResult = $this->getItemsByUrls($sUserPublicId, $oCalDAVCalendar, $aUrls, $dStart, $dEnd, $bExpand);
            }
        }

        return $mResult;
    }

    public function getPublicItemsByUrls($oCalDAVCalendar, $aUrls, $dStart = null, $dEnd = null, $bExpand = false, $sDefaultTimeZone = null)
    {
        $mResult = array();
        $oCalendar = $this->parseCalendar($oCalDAVCalendar);

        $oCalendar->IsPublic = true;

        foreach ($aUrls as $sUrl) {
            if (isset($this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$oCalendar->Owner])) {
                $oCalDAVCalendarObject = $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$oCalendar->Owner];
            } else {
                $oCalDAVCalendarObject = $oCalDAVCalendar->getChild($sUrl);
                $this->CalDAVCalendarObjectsCache[$oCalDAVCalendar->getName()][$sUrl][$oCalendar->Owner] = $oCalDAVCalendarObject;
            }
            $oVCal = \Sabre\VObject\Reader::read($oCalDAVCalendarObject->get());
            $iUserId = \Aurora\System\Api::getAuthenticatedUserId();
            $sUserPublicId = $iUserId ? \Aurora\System\Api::getUserPublicIdById($iUserId) : '';
            $aEvents = $this->getEventsFromVCalendar($sUserPublicId, $oCalendar, $oVCal, $dStart, $dEnd, $bExpand, $sDefaultTimeZone);
            foreach (array_keys($aEvents) as $key) {
                $aEvents[$key]['lastModified'] = $oCalDAVCalendarObject->getLastModified();
            }
            $mResult = array_merge($mResult, $aEvents);
        }

        return $mResult;
    }

    /**
     * @param string $sCalendarId
     * @param string $dStart
     * @param string $dEnd
     * @param bool $bExpand
     *
     * @return array|bool
     */
    public function getPublicEvents($sCalendarId, $dStart, $dEnd, $bExpand = true, $sDefaultTimeZone = null)
    {
        $mResult = false;
        $oCalDAVCalendar = $this->getPublicCalendar($sCalendarId);

        if ($oCalDAVCalendar) {
            $aUrls = $this->getEventUrls($oCalDAVCalendar, $dStart, $dEnd);
            $mResult = $this->getPublicItemsByUrls($oCalDAVCalendar, $aUrls, $dStart, $dEnd, $bExpand, $sDefaultTimeZone);
        }

        return $mResult;
    }


    public function getTasks($sUserPublicId, $sCalendarId, $bCompeted, $sSearch, $dStart = null, $dEnd = null, $bExpand = true)
    {
        $this->init($sUserPublicId);

        $mResult = false;
        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);

        if ($oCalDAVCalendar) {
            if ($oCalDAVCalendar instanceof Subscription) {
                $oVCal = $this->getVCalFromSubscription($oCalDAVCalendar);
                if ($oVCal) {
                    $oCalendar = $this->parseCalendar($oCalDAVCalendar);
                    $mResult = $this->getEventsFromVCalendar($sUserPublicId, $oCalendar, $oVCal, $dStart, $dEnd, $bExpand);
                    foreach ($mResult as $key => $value) {
                        $mResult[$key]['lastModified'] = $oCalDAVCalendar->getLastModified();
                        if ($value['type'] !== 'VTODO') {
                            unset($mResult[$key]);
                        }
                    }
                }
            } else {
                $aUrls = $this->getTasksUrls($oCalDAVCalendar, $bCompeted, $sSearch);
                $mResult = $this->getItemsByUrls($sUserPublicId, $oCalDAVCalendar, $aUrls, $dStart, $dEnd, $bExpand);
            }

            if (is_array($mResult) && !$bCompeted) {
                $mResult = array_filter($mResult, function ($task) {
                    return $task['status'] === false;
                });
            }
        }

        return $mResult;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventId
     * @param \Sabre\VObject\Component\VCalendar $oVCal
     *
     * @return string|null
     */
    public function createEvent($sUserPublicId, $sCalendarId, $sEventId, $oVCal)
    {
        $this->init($sUserPublicId);

        $sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar) {
            $oCalendar = $this->parseCalendar($oCalDAVCalendar);

            if (($oCalendar->Shared || $oCalendar->SharedToAll) && isset($oVCal->VEVENT) && isset($oVCal->VEVENT->CLASS) && (string) $oVCal->VEVENT->CLASS === 'PRIVATE') {
                return false;
            }

            if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
                $sData = $oVCal->serialize();
                Server::getInstance()->httpRequest->setUrl($oCalendar->Url . '/' . $sEventUrl);
                Server::getInstance()->createFile($oCalendar->Url . '/' . $sEventUrl, $sData);

                return $sEventId;
            }
        }

        return null;
    }


    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventId
     * @param string $sData
     *
     * @return bool
     */
    public function updateEventRaw($sUserPublicId, $sCalendarId, $sEventId, $sData)
    {
        $this->init($sUserPublicId);

        $sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar) {
            $oCalendar = $this->parseCalendar($oCalDAVCalendar);
            if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
                Server::getInstance()->setUser($sUserPublicId);
                Server::getInstance()->httpRequest->setUrl($oCalendar->Url . '/' . $sEventUrl);

                $oCalDAVCalendarObject = $this->getCalDAVCalendarObject($oCalDAVCalendar, $sEventId);
                if ($oCalDAVCalendarObject) {
                    $oChild = $oCalDAVCalendar->getChild($oCalDAVCalendarObject->getName());
                    if ($oChild) {
                        Server::getInstance()->updateFile($oCalendar->Url . '/' . $sEventUrl, $sData);
                        unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);
                        return true;
                    }
                } else {
                    Server::getInstance()->createFile($oCalendar->Url . '/' . $sEventUrl, $sData);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventId
     * @param \Sabre\VObject\Component\VCalendar $oVCal
     *
     * @return bool
     */
    public function updateEvent($sUserPublicId, $sCalendarId, $sEventId, $oVCal)
    {
        $this->init($sUserPublicId);

        $sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar) {
            $oCalendar = $this->parseCalendar($oCalDAVCalendar);
            if (($oCalendar->Shared || $oCalendar->SharedToAll) && isset($oVCal->VEVENT) && isset($oVCal->VEVENT->CLASS) && (string) $oVCal->VEVENT->CLASS === 'PRIVATE') {
                return false;
            }

            if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
                $sData = $oVCal->serialize();

                Server::getInstance()->setUser($sUserPublicId);
                Server::getInstance()->httpRequest->setUrl($oCalendar->Url . '/' . $sEventUrl);
                Server::getInstance()->updateFile($oCalendar->Url . '/' . $sEventUrl, $sData);

                unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sNewCalendarId
     * @param string $sEventId
     * @param string $sData
     *
     * @return bool
     */
    public function moveEvent($sUserPublicId, $sCalendarId, $sNewCalendarId, $sEventId, $sData)
    {
        $this->init($sUserPublicId);

        $sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar) {
            $oCalDAVCalendarNew = $this->getCalDAVCalendar($sNewCalendarId);
            if ($oCalDAVCalendarNew) {
                $oCalendar = $this->parseCalendar($oCalDAVCalendarNew);
                if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
                    Server::getInstance()->setUser($sUserPublicId);
                    Server::getInstance()->httpRequest->setUrl($oCalendar->Url . '/' . $sEventUrl);
                    Server::getInstance()->createFile($oCalendar->Url . '/' . $sEventUrl, $sData);

                    $oCalendarOld = $this->parseCalendar($oCalDAVCalendar);
                    if ($oCalendarOld->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
                        Server::getInstance()->tree->delete($oCalendarOld->Url . '/' . $sEventUrl);
                    }

                    // $this->deleteReminder($sEventId);
                    // $this->updateReminder($oCalendar, $sEventId, $sData);
                    unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventId
     *
     * @return bool
     */
    public function deleteEvent($sUserPublicId, $sCalendarId, $sEventId)
    {
        $this->init($sUserPublicId);

        $sEventUrl = (substr(strtolower($sEventId), -4) !== '.ics') ? $sEventId . '.ics' : $sEventId;

        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar) {
            $oCalendar = $this->parseCalendar($oCalDAVCalendar);
            if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
                Server::getInstance()->setUser($sUserPublicId);
                Server::getInstance()->httpRequest->setUrl($oCalendar->Url . '/' . $sEventUrl);
                Server::getInstance()->tree->delete($oCalendar->Url . '/' . $sEventUrl);

                $this->deleteReminder($sEventId);
                unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);

                return (string) (dirname($oCalendar->CTag) . "/" . ((int) basename($oCalendar->CTag) + 1));
            }
        }

        return false;
    }

    /**
     * @param string $sUserPublicId
     * @param string $sCalendarId
     * @param string $sEventUrl
     *
     * @return bool
     */
    public function deleteEventByUrl($sUserPublicId, $sCalendarId, $sEventUrl)
    {
        $this->init($sUserPublicId);

        $oCalDAVCalendar = $this->getCalDAVCalendar($sCalendarId);
        if ($oCalDAVCalendar) {
            $oCalendar = $this->parseCalendar($oCalDAVCalendar);
            if ($oCalendar->Access !== \Aurora\Modules\Calendar\Enums\Permission::Read) {
                // $oChild = $oCalDAVCalendar->getChild($sEventUrl);
                // $oChild->delete();

                Server::getInstance()->tree->delete($oCalendar->Url . '/' . $sEventUrl);

                unset($this->CalDAVCalendarObjectsCache[$sCalendarId][$sEventUrl]);

                return (string) (dirname($oCalendar->CTag) . "/" . ((int) basename($oCalendar->CTag) + 1));
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
        return \Afterlogic\DAV\Backend::Reminders()->addReminder($sEmail, $sCalendarUri, $sEventId, $time, $starttime);
    }

    public function updateReminder($oCalendar, $sEventId, $sData)
    {
        $sEmail = basename($oCalendar->Principals[0]);
        $sCalendarUri = $oCalendar->RealUrl;

        \Afterlogic\DAV\Backend::Reminders()->updateReminder(trim($sCalendarUri, '/') . '/' . $sEventId /*. '.ics'*/, $sData, $sEmail);
    }

    public function deleteReminder($sEventId)
    {
        return \Afterlogic\DAV\Backend::Reminders()->deleteReminder($sEventId);
    }

    public function deleteReminderByCalendar($sCalendarUri)
    {
        return \Afterlogic\DAV\Backend::Reminders()->deleteReminderByCalendar($sCalendarUri);
    }

    public function deleteRemindersByUser($sUser)
    {
        return \Afterlogic\DAV\Backend::Reminders()->deleteRemindersByUser($sUser);
    }

    public function deleteOutdatedReminders($time)
    {
        return \Afterlogic\DAV\Backend::Reminders()->deleteOutdatedReminders($time);
    }

    public function getChangesForCalendar($userPublicId, $calendarId, $syncToken, $limit = null)
    {
        $result = false;
        $calendar = $this->getCalendar($userPublicId, $calendarId);
        if ($calendar) {
            $calendarId = [$calendar->IntId, 0];
            $result = \Afterlogic\DAV\Backend::Caldav()->getChangesForCalendar($calendarId, $syncToken, 1, $limit);
        }

        return $result;
    }
}
