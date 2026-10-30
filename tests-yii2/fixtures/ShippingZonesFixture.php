<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin;

/**
 * Shipping Zones Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.4
 */
class ShippingZonesFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/shipping-zones.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ShippingAddressZone::class;

    /**
     * @var string[]
     */
    public $depends = [];

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'saveShippingZone';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteShippingZoneById';

    /**
     * @inheritDoc
     */
    public $service = 'shippingZones';

    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }
}
