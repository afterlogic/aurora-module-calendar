<?php
/**
 * This code is licensed under Afterlogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

/**
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Calendar
 * @subpackage Classes
 */
class Calendar
{
    public $Id;
    public $IntId;
    public $Url;
    public $IsDefault;
    public $DisplayName;
    public $CTag;
    public $ETag;
    public $Description;
    public $Color;
    public $Order;
    public $Shared;
    public $SharedToAll;
    public $SharedToAllAccess;
    public $Owner;
    public $Principals;
    public $Access;
    public $Shares;
    public $IsPublic;
    public $PubHash;
    public $RealUrl;
    public $SyncToken;
    public $Subscribed;
    public $Source;

    /**
     * @param string $sId
     * @param string $sDisplayName Default value is **null**.
     * @param string $sCTag Default value is **null**.
     * @param string $sETag Default value is **null**.
     * @param string $sDescription Default value is **null**.
     */
    public function __construct(
        $sId,
        $sDisplayName = null,
        $sCTag = null,
        $sETag = null,
        $sDescription = null,
        $sColor = null,
        $sOrder = null
    ) {
        $this->Id = rtrim(urldecode($sId), '/');
        $this->IntId = 0;
        $this->IsDefault = (\substr($this->Id, 0, \strlen(\Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID)) === \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID);
        $this->DisplayName = $sDisplayName;
        $this->CTag = $sCTag;
        $this->ETag = $sETag;
        $this->Description = $sDescription;
        $this->Color = $sColor;
        $this->Order = $sOrder;
        $this->Shared = false;
        $this->SharedToAll = false;
        $this->SharedToAllAccess = \Aurora\Modules\Calendar\Enums\Permission::Read;
        $this->Owner = '';
        $this->Principals = array();
        $this->Access = \Aurora\Modules\Calendar\Enums\Permission::Write;
        $this->Shares = array();
        $this->IsPublic = false;
        $this->PubHash = null;
        $this->SyncToken = null;
        $this->Subscribed = false;
        $this->Source = '';
    }

    /**
     * @return string
     */
    public function GetMainPrincipalUrl()
    {
        $sResult = '';
        if (is_array($this->Principals) && count($this->Principals) > 0) {
            $sResult = str_replace('/calendar-proxy-read', '', rtrim($this->Principals[0], '/'));
            $sResult = str_replace('/calendar-proxy-write', '', $sResult);
        }
        return $sResult;
    }

    /**
     * @deprecated since version 9.7.6
     * @param mixed $oAccount
     * @return bool
     */
    public function IsCalendarOwner($oAccount)
    {
        return ($oAccount === $this->Owner);
    }

    public function toResponseArray($aParameters = array())
    {
        return array(
            'Id' => $this->Id,
            'Url' => $this->Url,
            'ExportHash' => \Aurora\System\Api::EncodeKeyValues(array('CalendarId' => $this->Id)),
            'Color' => $this->Color,
            'Description' => $this->Description,
            'Name' => $this->DisplayName,
            'Owner' => $this->Owner,
            'IsDefault' => $this->IsDefault,
            'PrincipalId' => $this->GetMainPrincipalUrl(),
            'Shared' => $this->Shared,
            'SharedToAll' => $this->SharedToAll,
            'SharedToAllAccess' => $this->SharedToAllAccess,
            'Access' => $this->Access,
            'IsPublic' => $this->IsPublic,
            'PubHash' => $this->PubHash,
            'Shares' => $this->Shares,
            'CTag' => $this->CTag,
            'Etag' => $this->ETag,
            'SyncToken' => $this->SyncToken,
            'Subscribed' => $this->Subscribed,
            'Source' => $this->Source
        );
    }
}
