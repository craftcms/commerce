<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\elements\db\DonationQuery;
use craft\commerce\elements\db\PurchasableQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Donation;
use craft\commerce\elements\Variant;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;

/**
 * DonationQueryTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class DonationQueryTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @return void
     */
    public function testQuery(): void
    {
        self::assertInstanceOf(DonationQuery::class, Donation::find());
        self::assertInstanceOf(PurchasableQuery::class, Donation::find());
    }

    /**
     * @param bool $availableForPurchase
     * @return void
     * @dataProvider availableForPurchaseDataProvider
     */
    public function testAvailableForPurchase(bool $availableForPurchase): void
    {
        $query = Donation::find();

        self::assertTrue(method_exists($query, 'availableForPurchase'));
        $query->availableForPurchase($availableForPurchase);
        $query->status(null);

        // Donation on installation is not available for purchase
        self::assertCount($availableForPurchase ? 0 : 1, $query->all());
    }

    /**
     * @return array
     */
    public function availableForPurchaseDataProvider(): array
    {
        return [
            'available' => [true],
            'not-available' => [false],
        ];
    }
}
