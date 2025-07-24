<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\db\DonationQuery;
use craft\commerce\elements\db\PurchasableQuery;
use craft\commerce\elements\Donation;
use craft\commerce\Plugin;
use craft\db\Query;
use craftcommercetests\fixtures\StoreFixture;
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

    public $depends = [
        StoreFixture::class,
    ];

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
        // Make sure donation is installed
        if ((int)(new Query())->from(Table::DONATIONS)->count() === 0) {
            $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $donation = new Donation();
            $donation->siteId = $primarySite->id;
            $donation->sku = 'DONATION-CC5';
            $donation->availableForPurchase = false;
            $donation->taxCategoryId = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
            $donation->shippingCategoryId = Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($primaryStore->id)->id;
            Craft::$app->getElements()->saveElement($donation);
        }

        $query = Donation::find();

        self::assertTrue(method_exists($query, 'availableForPurchase'));
        $query->availableForPurchase($availableForPurchase);
        $query->status(null);
        $all = $query->all();

        // Donation on installation is not available for purchase
        self::assertCount($availableForPurchase ? 0 : 1, $all);

        if (isset($donation) || count($all)) {
            // Delete donation
            $donation ??= $all[0];
            Craft::$app->getElements()->deleteElement($donation, true);
        }
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
