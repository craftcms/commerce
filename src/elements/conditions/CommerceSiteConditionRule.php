<?php

namespace craft\commerce\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\SiteConditionRule;

/**
 * Site condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class CommerceSiteConditionRule extends SiteConditionRule
{
    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue(Craft::$app->getSites()->getCurrentSite()->uid);
    }
}
