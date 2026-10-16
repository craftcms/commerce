<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\product;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\actions\SetDefaultVariant;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\Db;
use craftcommercetests\fixtures\ProductFixture;
use DateTime;

/**
 * ProductDefaultVariantTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ProductDefaultVariantTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

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

    public function testDefaultVariantSetOnProvisionalDraftIsApplied(): void
    {
        [$product, $variantA, $variantB] = $this->_createProduct('draft-default');

        try {
            $draft = Craft::$app->getDrafts()->createDraft($product, provisional: true);

            foreach ([$variantB->id => 1, $variantA->id => 2] as $variantId => $sortOrder) {
                Db::update(CraftTable::ELEMENTS_OWNERS, ['sortOrder' => $sortOrder], [
                    'ownerId' => $draft->id,
                    'elementId' => $variantId,
                ]);
            }

            $action = new SetDefaultVariant();
            self::assertTrue($action->performAction(
                Variant::find()->ownerId($draft->id)->id($variantB->id)->status(null)
            ));

            self::assertSame($variantA->id, $this->_defaultVariantId($product->id));
            self::assertSame([$variantA->id], $this->_flaggedVariantIds($product->id));

            $draft = Product::find()->id($draft->id)->drafts()->provisionalDrafts()->status(null)->one();
            self::assertNotNull($draft);
            Craft::$app->getDrafts()->applyDraft($draft);

            self::assertSame($variantB->id, $this->_defaultVariantId($product->id));
            self::assertSame([$variantB->id], $this->_flaggedVariantIds($product->id));
            self::assertSame(
                [$variantB->id, $variantA->id],
                Variant::find()->productId($product->id)->status(null)->orderBy(['sortOrder' => SORT_ASC])->ids()
            );
            self::assertSame($variantB->id, Product::find()->id($product->id)->status(null)->one()?->getDefaultVariant()?->id);
        } finally {
            Craft::$app->getElements()->deleteElementById($product->id, Product::class, null, true);
        }
    }

    public function testDefaultVariantSetToDerivativeVariantIsApplied(): void
    {
        [$product, $variantA, $variantB] = $this->_createProduct('draft-derivative');

        try {
            $draft = Craft::$app->getDrafts()->createDraft($product, provisional: true);

            $draftVariantB = Variant::find()->ownerId($draft->id)->id($variantB->id)->status(null)->one();
            self::assertNotNull($draftVariantB);
            $derivativeVariantB = Craft::$app->getElements()->duplicateElement($draftVariantB, [
                'canonicalId' => $variantB->id,
                'primaryOwner' => $draft,
                'owner' => $draft,
                'sortOrder' => $draftVariantB->getSortOrder(),
            ]);
            Db::delete(CraftTable::ELEMENTS_OWNERS, ['elementId' => $variantB->id, 'ownerId' => $draft->id]);
            self::assertNotSame($variantB->id, $derivativeVariantB->id);

            $action = new SetDefaultVariant();
            self::assertTrue($action->performAction(
                Variant::find()->ownerId($draft->id)->id($derivativeVariantB->id)->status(null)
            ));
            self::assertSame($derivativeVariantB->id, $this->_defaultVariantId($draft->id));

            $draft = Product::find()->id($draft->id)->drafts()->provisionalDrafts()->status(null)->one();
            self::assertNotNull($draft);
            Craft::$app->getDrafts()->applyDraft($draft);

            self::assertSame($variantB->id, $this->_defaultVariantId($product->id));
            self::assertSame([$variantB->id], $this->_flaggedVariantIds($product->id));
            self::assertSame($variantB->id, Product::find()->id($product->id)->status(null)->one()?->getDefaultVariant()?->id);
        } finally {
            Craft::$app->getElements()->deleteElementById($product->id, Product::class, null, true);
        }
    }

    public function testLegacyIsDefaultFlagsAreClearedWhenThereIsNoDefaultVariant(): void
    {
        [$product, $variantA, $variantB] = $this->_createProduct('no-default');

        try {
            foreach ([$variantA, $variantB] as $variant) {
                $variant->enabled = false;
                Craft::$app->getElements()->saveElement($variant, false);
            }

            $product = Product::find()->id($product->id)->status(null)->one();
            Craft::$app->getElements()->saveElement($product, false);

            self::assertNull($this->_defaultVariantId($product->id));
            self::assertSame([], $this->_flaggedVariantIds($product->id));
        } finally {
            Craft::$app->getElements()->deleteElementById($product->id, Product::class, null, true);
        }
    }

    /**
     * @param string $handle
     * @return array{0: Product, 1: Variant, 2: Variant}
     */
    private function _createProduct(string $handle): array
    {
        $product = new Product();
        $product->title = "Default Variant Test Product $handle";
        $product->typeId = 2001;
        $product->slug = "default-variant-test-product-$handle";
        $product->enabled = true;
        $product->enabledForSite = true;
        $product->postDate = new DateTime('now');

        $variantA = new Variant();
        $variantA->title = "Default Variant Test A $handle";
        $variantA->sku = "default-variant-test-a-$handle";
        $variantA->basePrice = 10;
        $variantA->sortOrder = 1;
        $variantA->isDefault = true;

        $variantB = new Variant();
        $variantB->title = "Default Variant Test B $handle";
        $variantB->sku = "default-variant-test-b-$handle";
        $variantB->basePrice = 20;
        $variantB->sortOrder = 2;
        $variantB->isDefault = false;

        $product->setVariants([$variantA, $variantB]);
        self::assertTrue(Craft::$app->getElements()->saveElement($product, false));

        $date = Db::prepareDateForDb(new DateTime('-1 hour'));
        Db::update(CraftTable::ELEMENTS, ['dateCreated' => $date, 'dateUpdated' => $date], [
            'id' => [$product->id, $variantA->id, $variantB->id],
        ], updateTimestamp: false);

        $product = Product::find()->id($product->id)->status(null)->one();
        self::assertNotNull($product);

        return [$product, $variantA, $variantB];
    }

    private function _defaultVariantId(int $productId): ?int
    {
        $defaultVariantId = (new Query())
            ->select(['defaultVariantId'])
            ->from([Table::PRODUCTS])
            ->where(['id' => $productId])
            ->scalar();

        return $defaultVariantId ? (int)$defaultVariantId : null;
    }

    /**
     * @return int[]
     */
    private function _flaggedVariantIds(int $productId): array
    {
        return array_map('intval', (new Query())
            ->select(['id'])
            ->from([Table::VARIANTS])
            ->where(['primaryOwnerId' => $productId, 'isDefault' => true])
            ->orderBy(['id' => SORT_ASC])
            ->column());
    }
}
