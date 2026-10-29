<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\elements;

use craft\commerce\elements\Subscription as SubscriptionElement;
use craft\elements\db\ElementQuery;
use craft\elements\ElementCollection;
use craft\gql\base\ElementResolver;
use craft\helpers\Gql as GqlHelper;
use yii\base\UnknownMethodException;

/**
 * Class Subscription
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Subscription extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = SubscriptionElement::find();
        // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (!$query instanceof ElementQuery) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            try {
                $query->$key($value);
            } catch (UnknownMethodException $e) {
                if ($value !== null) {
                    throw $e;
                }
            }
        }

        if (!GqlHelper::canSchema('subscriptions.all')) {
            return ElementCollection::empty();
        }

        // Limit authenticated queries to the user’s own subscriptions, unless they can manage subscriptions
        $user = GqlHelper::getSchemaUser();
        if ($user && !$user->can('commerce-manageSubscriptions')) {
            $query->andWhere(['commerce_subscriptions.userId' => $user->id]);
        }

        return $query;
    }
}
