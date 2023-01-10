<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\Db;

/**
 * Store model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class SiteSettings extends Model
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
     * @return string|null
     */
    public function getStoreUid()
    {
        if($this->storeId && $uid = Db::uidById('{{%commerce_stores}}', $this->storeId)) {
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
