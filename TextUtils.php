<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Calendar;

class TextUtils
{
    public static function clearHtml($sText)
    {
        $oDom = \MailSo\Base\HtmlUtils::GetDomFromText($sText);
        $aNodes = $oDom->getElementsByTagName('*');

        $bHasExternals = false;
        $aFoundCIDs = [];
        $aContentLocationUrls = [];
        $aFoundedContentLocationUrls = [];

        foreach ($aNodes as /* @var $oElement \DOMElement */ $oElement) {
            $sTagNameLower = \strtolower($oElement->tagName);
            if ('img' === $sTagNameLower) {
                $oElement->parentNode->removeChild($oElement);
            }

            $sBackground = $oElement->hasAttribute('background') ? \trim($oElement->getAttribute('background')) : '';
            $sBackgroundColor = $oElement->hasAttribute('bgcolor') ? \trim($oElement->getAttribute('bgcolor')) : '';

            if (!empty($sBackground) || !empty($sBackgroundColor)) {
                if (!empty($sBackground)) {
                    $oElement->removeAttribute('background');
                }

                if (!empty($sBackgroundColor)) {
                    $oElement->removeAttribute('bgcolor');
                }
            }

            if ($oElement->hasAttribute('style')) {
                $oElement->setAttribute(
                    'style',
                    \MailSo\Base\HtmlUtils::ClearStyle(
                        $oElement->getAttribute('style'),
                        $oElement,
                        $bHasExternals,
                        $aFoundCIDs,
                        $aContentLocationUrls,
                        $aFoundedContentLocationUrls
                    )
                );
            }
        }
        $sText = $oDom->saveHTML();
        unset($oDom);

        $sText = \MailSo\Base\HtmlUtils::ClearTags($sText);
        $sText = \MailSo\Base\HtmlUtils::ClearBodyAndHtmlTag($sText);
        $sText = \MailSo\Base\HtmlUtils::ClearOn($sText);

        return $sText;
    }

    /**
     * Checks if provided string is a HTML with tags
     */
    public static function isHtml($sText)
    {
        preg_match_all('/<\/?[a-zA-Z-]+(?:\s|\s[^>]+|\S)?>/', $sText, $matches, PREG_SET_ORDER);

        return count($matches) > 0;
    }
}
