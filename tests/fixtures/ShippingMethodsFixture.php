<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\Sale;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;

/**
 * Shipping Methods Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.4
 */
class ShippingMethodsFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/shipping-methods.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ShippingMethod::class;

    /**
     * @var string[]
     */
    public $depends = [];

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'saveShippingMethod';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteShippingMethodById';

    /**
     * @inheritDoc
     */
    public $service = 'shippingMethods';

    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    protected function prepData($data)
    {
        return $data;
    }

    /**
     * @inheritDoc
     */
    protected function prepModel($model, $data)
    {
        return $model;
    }
}
