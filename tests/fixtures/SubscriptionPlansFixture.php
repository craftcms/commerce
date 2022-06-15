<?php
/**
* @link      https://craftcms.com/
* @copyright Copyright (c) Pixel & Tonic, Inc.
* @license   https://craftcms.github.io/license/
*/

namespace craftcommercetests\fixtures;

use Craft;
use craft\commerce\records\Plan;
use craft\test\ActiveFixture;

/**
* Class SubscriptionPlansFixture.
*
* @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
* @since 4.0.5
*/
class SubscriptionPlansFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/subscription-plans.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Plan::class;
}