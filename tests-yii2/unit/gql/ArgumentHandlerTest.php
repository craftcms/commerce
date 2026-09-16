<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\gql;

use Codeception\Test\Unit;
use craft\commerce\gql\handlers\HasProduct;
use craft\commerce\gql\handlers\HasVariant;
use craft\gql\ArgumentManager;
use craft\gql\handlers\RelatedEntries;
use UnitTester;

/**
 * ArgumentHandlerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6.5
 */
class ArgumentHandlerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * Tests that HasProduct processes nested GQL relation arguments (e.g. relatedToEntries)
     * by delegating to ArgumentManager, converting them to proper relatedTo criteria.
     */
    public function testHasProductProcessesNestedRelationArgs(): void
    {
        $relatedEntryIds = [42, 99];

        $relatedEntriesHandler = $this->make(RelatedEntries::class, [
            'getIds' => fn() => [$relatedEntryIds],
        ]);

        $argumentManager = new ArgumentManager();
        $relatedEntriesHandler->setArgumentManager($argumentManager);
        $argumentManager->setHandler('relatedToEntries', $relatedEntriesHandler);

        $hasProductHandler = new HasProduct();
        $hasProductHandler->setArgumentManager($argumentManager);
        $argumentManager->setHandler('hasProduct', $hasProductHandler);

        $result = $argumentManager->prepareArguments([
            'hasProduct' => [
                'relatedToEntries' => [['section' => 'news']],
            ],
        ]);

        self::assertIsArray($result['hasProduct']);
        self::assertArrayNotHasKey('relatedToEntries', $result['hasProduct']);
        self::assertArrayHasKey('relatedTo', $result['hasProduct']);
        self::assertSame(['and', ['element' => $relatedEntryIds]], $result['hasProduct']['relatedTo']);
    }

    /**
     * Tests that HasProduct passes standard (non-GQL) criteria through unchanged.
     */
    public function testHasProductPassesThroughStandardArgs(): void
    {
        $argumentManager = new ArgumentManager();
        $hasProductHandler = new HasProduct();
        $hasProductHandler->setArgumentManager($argumentManager);
        $argumentManager->setHandler('hasProduct', $hasProductHandler);

        $result = $argumentManager->prepareArguments([
            'hasProduct' => [
                'slug' => 'rad-hoodie',
                'type' => 'hoodies',
            ],
        ]);

        self::assertSame(['slug' => 'rad-hoodie', 'type' => 'hoodies'], $result['hasProduct']);
    }

    /**
     * Tests that HasVariant processes nested GQL relation arguments (e.g. relatedToEntries)
     * by delegating to ArgumentManager, converting them to proper relatedTo criteria.
     */
    public function testHasVariantProcessesNestedRelationArgs(): void
    {
        $relatedEntryIds = [7, 13];

        $relatedEntriesHandler = $this->make(RelatedEntries::class, [
            'getIds' => fn() => [$relatedEntryIds],
        ]);

        $argumentManager = new ArgumentManager();
        $relatedEntriesHandler->setArgumentManager($argumentManager);
        $argumentManager->setHandler('relatedToEntries', $relatedEntriesHandler);

        $hasVariantHandler = new HasVariant();
        $hasVariantHandler->setArgumentManager($argumentManager);
        $argumentManager->setHandler('hasVariant', $hasVariantHandler);

        $result = $argumentManager->prepareArguments([
            'hasVariant' => [
                'relatedToEntries' => [['section' => 'news']],
            ],
        ]);

        self::assertIsArray($result['hasVariant']);
        self::assertArrayNotHasKey('relatedToEntries', $result['hasVariant']);
        self::assertArrayHasKey('relatedTo', $result['hasVariant']);
        self::assertSame(['and', ['element' => $relatedEntryIds]], $result['hasVariant']['relatedTo']);
    }

    /**
     * Tests that HasVariant passes standard (non-GQL) criteria through unchanged.
     */
    public function testHasVariantPassesThroughStandardArgs(): void
    {
        $argumentManager = new ArgumentManager();
        $hasVariantHandler = new HasVariant();
        $hasVariantHandler->setArgumentManager($argumentManager);
        $argumentManager->setHandler('hasVariant', $hasVariantHandler);

        $result = $argumentManager->prepareArguments([
            'hasVariant' => [
                'sku' => 'hct-blue',
            ],
        ]);

        self::assertSame(['sku' => 'hct-blue'], $result['hasVariant']);
    }
}
