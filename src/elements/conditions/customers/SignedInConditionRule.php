<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\customers;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use yii\base\NotSupportedException;

/**
 * Signed In Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.6
 *
 * @property null|array|OrderCondition $orderCondition
 */
class SignedInConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('app', 'Signed In');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Signed in condition rule does not support element queries.');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var User $element */
        $currentUser = Craft::$app->getUser()->getIdentity();
        $isStoreAdministrator = $currentUser && $currentUser->can('accessCp') && $currentUser->can('commerce-editOrders');

        // If the current user is a store admin, and they are editing an order
        if ($isStoreAdministrator) {
            if ($this->value && $element->getIsCredentialed()) {
                return true;
            }

            if (!$this->value && !$element->getIsCredentialed()) {
                return true;
            }

            return false;
        }

        if (!$this->value && !$currentUser) {
            return true;
        }

        if ($this->value && $currentUser && $currentUser->id === $element->id) {
            return true;
        }

        return false;
    }
}
