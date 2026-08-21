<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Conditions;

use CraftCms\Cms\Condition\BaseLightswitchConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\User\Elements\User;
use RuntimeException;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

class SignedInConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Signed In', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new RuntimeException('Signed in condition rule does not support element queries.');
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var User $element */
        $currentUser = currentUserElement();
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
