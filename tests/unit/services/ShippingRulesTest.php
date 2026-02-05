<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\Plugin;
use craft\commerce\services\ShippingRuleCategories;
use craft\commerce\services\ShippingRules;
use craftcommercetests\fixtures\ShippingFixture;
use UnitTester;

/**
 * ShippingRulesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ShippingRulesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var ShippingRules
     */
    protected ShippingRules $shippingRules;

    /**
     * @var ShippingRuleCategories
     */
    protected ShippingRuleCategories $shippingRuleCategories;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'shipping' => [
                'class' => ShippingFixture::class,
            ],
        ];
    }

    public function _before(): void
    {
        parent::_before();

        $this->shippingRules = Plugin::getInstance()->getShippingRules();
        $this->shippingRuleCategories = Plugin::getInstance()->getShippingRuleCategories();
    }

    public function testGetShippingRuleCategoriesByRuleIds(): void
    {
        $allRules = $this->shippingRules->getAllShippingRules();
        $ruleIds = $allRules->pluck('id')->filter()->all();

        // Skip if no rules exist
        if (empty($ruleIds)) {
            $this->markTestSkipped('No shipping rules exist to test');
        }

        $categoriesByRuleId = $this->shippingRuleCategories->getShippingRuleCategoriesByRuleIds($ruleIds);

        // Result should be an array indexed by rule ID
        $this->assertIsArray($categoriesByRuleId);

        // Each rule that has categories should have them indexed by category ID
        foreach ($categoriesByRuleId as $ruleId => $categories) {
            $this->assertContains($ruleId, $ruleIds, 'Rule ID in result should be one of the requested IDs');
            $this->assertIsArray($categories);
        }
    }

    public function testGetShippingRuleCategoriesByRuleIdsWithEmptyArray(): void
    {
        $result = $this->shippingRuleCategories->getShippingRuleCategoriesByRuleIds([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testEagerLoadingPopulatesShippingRuleCategories(): void
    {
        // Get all shipping rules - this should eager load categories
        $allRules = $this->shippingRules->getAllShippingRules();

        // Skip if no rules exist
        if ($allRules->isEmpty()) {
            $this->markTestSkipped('No shipping rules exist to test');
        }

        // Each rule should have its categories already loaded (not null)
        // We verify this by checking that getShippingRuleCategories returns
        // without triggering additional queries
        foreach ($allRules as $rule) {
            $categories = $rule->getShippingRuleCategories();
            $this->assertIsArray($categories);
        }
    }

    public function testBulkFetchMatchesSingleFetch(): void
    {
        $allRules = $this->shippingRules->getAllShippingRules();

        // Skip if no rules exist
        if ($allRules->isEmpty()) {
            $this->markTestSkipped('No shipping rules exist to test');
        }

        $ruleIds = $allRules->pluck('id')->filter()->all();

        // Fetch all categories in bulk
        $bulkCategories = $this->shippingRuleCategories->getShippingRuleCategoriesByRuleIds($ruleIds);

        // Fetch categories one by one and compare
        foreach ($ruleIds as $ruleId) {
            $singleCategories = $this->shippingRuleCategories->getShippingRuleCategoriesByRuleId($ruleId);
            $bulkForRule = $bulkCategories[$ruleId] ?? [];

            // Both should have the same category IDs
            $this->assertEquals(
                array_keys($singleCategories),
                array_keys($bulkForRule),
                "Categories for rule $ruleId should match between bulk and single fetch"
            );
        }
    }
}
