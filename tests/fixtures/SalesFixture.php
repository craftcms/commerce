<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use yii\base\InvalidArgumentException;

/**
 * Sales Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class SalesFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/sales.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Sale::class;

    /**
     * @var string[]
     */
    public $depends = [ProductFixture::class, CategoriesFixture::class, UserGroupsFixture::class];

    /**
     * @inheritDoc
     */
    public $saveMethod = 'saveSale';

    /**
     * @inheritDoc
     */
    public $deleteMethod = 'deleteSaleById';

    /**
     * @inheritDoc
     */
    public $service = 'sales';

    /**
     * @var array|null
     */
    private $_purchasableIds;

    /**
     * @var array|null
     */
    private $_categoryIds;

    /**
     * @var array|null
     */
    private $_userGroupIds;

    public function init()
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    protected function prepData($data)
    {
        $this->_purchasableIds = $data['_purchasableIds'] ?? null;
        if ($this->_purchasableIds !== null) {
            unset($data['_purchasableIds']);
        }

        $this->_categoryIds = $data['_categoryIds'] ?? null;
        if ($this->_categoryIds !== null) {
            unset($data['_categoryIds']);
        }

        $this->_userGroupIds = $data['_userGroupIds'] ?? null;
        if ($this->_userGroupIds !== null) {
            unset($data['_userGroupIds']);
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    protected function prepModel($model, $data)
    {
        if ($this->_purchasableIds !== null) {
            $model->setPurchasableIds($this->_purchasableIds);
            $this->_purchasableIds = null;
        }

        if ($this->_categoryIds !== null) {
            $model->setCategoryIds($this->_categoryIds);
            $this->_categoryIds = null;
        }

        if ($this->_userGroupIds !== null) {
            $model->setUserGroupIds($this->_userGroupIds);
            $this->_userGroupIds = null;
        }

        return $model;
    }
}