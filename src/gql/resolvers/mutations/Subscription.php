<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\mutations;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Subscription as SubscriptionElement;
use craft\gql\base\ElementMutationResolver;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Throwable;

/**
 * Class Subscription
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Subscription extends ElementMutationResolver
{
    /**
     * Saves an existing subscription using the passed arguments.
     *
     * @param mixed $source
     * @param array $arguments
     * @param mixed $context
     * @param ResolveInfo $resolveInfo
     * @return SubscriptionElement
     * @throws Throwable
     */
    public function saveSubscription(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): SubscriptionElement
    {
        $this->requireSchemaAction('subscriptions.all', 'save');

        $query = SubscriptionElement::find()->status(null);
        if (!empty($arguments['uid'])) {
            $query->uid($arguments['uid']);
        } elseif (!empty($arguments['id'])) {
            $query->id($arguments['id']);
        } else {
            throw new Error('A subscription ID or UID is required.');
        }

        $subscription = $query->one();

        if (!$subscription) {
            throw new Error('No such subscription exists');
        }

        $subscription = $this->populateElementWithData($subscription, $arguments, $resolveInfo);
        $subscription->setScenario(Element::SCENARIO_LIVE);
        $subscription = $this->saveElement($subscription);

        return SubscriptionElement::find()->id($subscription->id)->status(null)->one();
    }

    /**
     * Deletes a subscription identified by the passed arguments.
     *
     * @param mixed $source
     * @param array $arguments
     * @param mixed $context
     * @param ResolveInfo $resolveInfo
     * @return bool
     * @throws Throwable
     */
    public function deleteSubscription(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): bool
    {
        $this->requireSchemaAction('subscriptions.all', 'delete');

        $subscription = SubscriptionElement::find()->id($arguments['id'])->status(null)->one();

        if (!$subscription) {
            return false;
        }

        $this->requireUserAuthorization($subscription, 'delete');

        return Craft::$app->getElements()->deleteElement($subscription, $arguments['hardDelete'] ?? false);
    }
}
