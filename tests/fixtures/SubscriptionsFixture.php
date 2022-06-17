<?php
/**
* @link      https://craftcms.com/
* @copyright Copyright (c) Pixel & Tonic, Inc.
* @license   https://craftcms.github.io/license/
*/

namespace craftcommercetests\fixtures;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin;
use craft\test\fixtures\elements\BaseElementFixture;

/**
* Class SubscriptionsFixture.
*
* @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
* @since 4.0.4
*/
class SubscriptionsFixture extends BaseElementFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/subscriptions.php';

    public $depends = [SubscriptionPlansFixture::class];

    /**
     * @inheritdoc
     */
    protected function createElement(): ElementInterface
    {
        return new Subscription();
    }

    /**
     * @inheritdoc
     */
    protected function populateElement(ElementInterface $element, array $attributes): void
    {
        /** @var Subscription $element */
        foreach ($attributes as $name => $val) {
            if ($name === '_plan') {
                if ($plan = Plugin::getInstance()->getPlans()->getPlanByHandle($val)) {
                    $element->planId = $plan->id;
                }

                unset($attributes['_plan']);
            }
        }

        parent::populateElement($element, $attributes);
    }
}
