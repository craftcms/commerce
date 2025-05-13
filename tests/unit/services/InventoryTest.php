<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Variant;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\Plugin;
use craft\errors\DeprecationException;
use craftcommercetests\fixtures\ProductFixture;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * InventoryTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class InventoryTest extends Unit
{
    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    /**
     * @param array $updateConfigs
     * @param int $expected
     * @return void
     * @throws DeprecationException
     * @throws InvalidConfigException
     * @throws Exception
     * @dataProvider setStockLevelDataProvider
     */
    public function testUpdatePurchasableInventoryLevel(array $updateConfigs, int $expected): void
    {
        $variant = Variant::find()->sku('rad-hood')->one();
        $originalStock = $variant->getStock();

        foreach ($updateConfigs as $updateConfig) {
            $qty = $updateConfig['quantity'];
            unset($updateConfig['quantity']);

            Plugin::getInstance()->getInventory()->updatePurchasableInventoryLevel($variant, $qty, $updateConfig);
        }

        self::assertEquals($expected, $variant->getStock());

        Plugin::getInstance()->getInventory()->updatePurchasableInventoryLevel($variant, $originalStock);
    }

    /**
     * @return array[]
     */
    public function setStockLevelDataProvider(): array
    {
        return [
            'simple-single-arg' => [
                [
                    ['quantity' => 10],
                ],
                'expected' => 10,
            ],
            'set-and-adjust' => [
                [
                    ['quantity' => 10],
                    ['quantity' => 2, 'updateAction' => InventoryUpdateQuantityType::ADJUST],
                ],
                'expected' => 12,
            ],
            'just-adjust' => [
                [
                    ['quantity' => 2, 'updateAction' => InventoryUpdateQuantityType::ADJUST],
                ],
                'expected' => 2,
            ],
            'set-and-adjust-negative' => [
                [
                    ['quantity' => 10],
                    ['quantity' => -2, 'updateAction' => InventoryUpdateQuantityType::ADJUST],
                ],
                'expected' => 8,
            ],
        ];
    }
}
