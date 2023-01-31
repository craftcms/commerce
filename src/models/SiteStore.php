<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\Db;
use craft\models\Site;

/**
 * Store model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class SiteStore extends Model
{
    /**
     * @var int Site ID
     */
    public int $siteId;

    /**
     * @var ?int Store ID
     */
    public ?int $storeId = null;

    /**
     * @var string|null Store UID
     */
    public ?string $uid = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['storeId', 'siteId'], 'required'];
        $rules[] = [['storeId', 'siteId'], 'safe'];

        return $rules;
    }

    /**
     * @return Store
     */
    public function getStore(): Store
    {
        return Plugin::getInstance()->getStores()->getStoreById($this->storeId);
    }

    /**
     * @return Site|null
     */
    public function getSite()
    {
        return Craft::$app->getSites()->getSiteById($this->siteId);
    }

    /**
     * @return string|null
     */
    public function getStoreUid()
    {
        if ($this->storeId && $uid = Db::uidById('{{%commerce_stores}}', $this->storeId)) {
            return $uid;
        }

        return null;
    }

    /**
     * Returns the project config data for this store.
     */
    public function getConfig(): array
    {
        return [
            'store' => $this->getStoreUid(),
        ];
    }
}
