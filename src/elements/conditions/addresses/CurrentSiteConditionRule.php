<?php

namespace craft\commerce\elements\conditions\addresses;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\SiteConditionRule;

/**
 * Current Site condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class CurrentSiteConditionRule extends SiteConditionRule
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('app', 'Current Site');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        return $this->matchValue($currentSite->uid);
    }
}
