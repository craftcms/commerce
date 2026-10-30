<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerCondition;
use CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerConditionRule;

/**
 * CatalogPricingRuleCustomerConditionRule::modifyQuery() called $elementQuery->id() from inside
 * modifyQuery(), which was silently non-functional for the same reason as SkuConditionRule (see
 * tests/Feature/Purchasable/Conditions/SkuConditionRuleTest.php) — now fixed by calling
 * CraftCms\Cms\Element\Queries\ElementQuery::applyId() directly.
 */
function createTestUser(string $username): User
{
    $user = new User();
    $user->username = $username;
    $user->email = "$username@crafttest.com";
    $user->active = true;
    if (!Elements::saveElement($user)) {
        throw new RuntimeException('Could not save user: ' . json_encode($user->errors()->all()));
    }

    return $user;
}

test('modifyQuery filters users by the selected customer', function() {
    $userA = createTestUser('catalogpricing-customer-a');
    $userB = createTestUser('catalogpricing-customer-b');

    $rule = new CatalogPricingRuleCustomerConditionRule();
    $rule->setElementIds([$userA->id]);

    $condition = new CatalogPricingRuleCustomerCondition(User::class);
    $condition->addConditionRule($rule);

    $query = User::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($userA->id);
    expect($ids)->not->toContain($userB->id);
});
