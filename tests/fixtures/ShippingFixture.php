<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;

/**
 * Shipping Rules Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.4
 */
class ShippingFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/shipping-rules.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ShippingRule::class;

    /**
     * @var string[]
     */
    public $depends = [
        ShippingZonesFixture::class,
        ShippingMethodsFixture::class,
    ];

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'saveShippingRule';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteShippingRuleById';

    /**
     * @inheritDoc
     */
    public $service = 'shippingRules';

    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function prepData($data)
    {
        if (isset($data['methodId']) && is_string($data['methodId'])) {
            $method = Plugin::getInstance()->getShippingMethods()->getShippingMethodByHandle($data['methodId']);
            if ($method) {
                $data['methodId'] = $method->id ?? null;
            } else {
                unset($data['methodId']);
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function load(): void
    {
        $originalEdition = Plugin::getInstance()->edition;

        parent::load();

        Plugin::getInstance()->edition = $originalEdition;
    }
}
