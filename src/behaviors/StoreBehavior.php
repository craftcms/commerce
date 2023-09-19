<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\behaviors;

use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\models\Site;
use Exception;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

/**
 * Store Behavior
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class StoreBehavior extends Behavior
{
    /** @var Site */
    public $owner;

    /**
     * @throws Exception
     */
    public function attach($owner)
    {
        if (!$owner instanceof Site) {
            throw new Exception('StoreBehavior can only be attached to a Site model');
        }

        parent::attach($owner);
    }

    /**
     * @return Store|null
     * @throws InvalidConfigException
     */
    public function getStore(): ?Store
    {
        return Plugin::getInstance()->getStores()->getStoreBySiteId($this->owner->id);
    }
}
