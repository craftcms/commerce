<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\mutations;

use Craft;
use craft\commerce\elements\Order as OrderElement;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\gql\base\ElementMutationResolver;
use craft\helpers\Json;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Throwable;

/**
 * Class Order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Order extends ElementMutationResolver
{
    /** @inheritdoc */
    protected array $immutableAttributes = [
        'id',
        'uid',
        'storeId',
        'customerId',
        'email',
        'recalculationMode',
        'isCompleted',
        'lineItems',
        'billingAddress',
        'shippingAddress',
    ];

    /**
     * Saves an order using the passed arguments.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return OrderElement
     * @throws Throwable
     */
    public function saveOrder(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): OrderElement
    {
        $commerce = Plugin::getInstance();

        if (!empty($arguments['id']) || !empty($arguments['uid'])) {
            $query = OrderElement::find();
            if (!empty($arguments['uid'])) {
                $query->uid($arguments['uid']);
            } else {
                $query->id($arguments['id']);
            }

            $order = $query->one();

            if (!$order) {
                throw new Error('No such order exists');
            }

            $this->requireSchemaAction('orders.' . $order->getStore()->uid, 'save');
        } else {
            $store = isset($arguments['storeId'])
                ? $commerce->getStores()->getStoreById($arguments['storeId'])
                : $commerce->getStores()->getPrimaryStore();

            if (!$store) {
                throw new Error('No such store exists');
            }

            $this->requireSchemaAction("orders.$store->uid", 'create');

            $order = new OrderElement([
                'number' => $commerce->getCarts()->generateCartNumber(),
                'origin' => OrderElement::ORIGIN_REMOTE,
                'storeId' => $store->id,
            ]);
        }

        // Check this before anything is changed, as setting the customer by email can create a user
        $this->requireUserAuthorization($order, 'save');

        // Normalize the address data along with the order’s, but set it on the order separately
        $addresses = $this->recursivelyNormalizeArgumentValues($resolveInfo, array_intersect_key($arguments, [
            'billingAddress' => true,
            'shippingAddress' => true,
        ]));

        if (!empty($arguments['recalculationMode'])) {
            $order->setRecalculationMode($arguments['recalculationMode']);
        }

        if (isset($arguments['customerId'])) {
            $customer = Craft::$app->getUsers()->getUserById($arguments['customerId']);
            if (!$customer) {
                throw new Error('No such user exists');
            }
            $order->setCustomer($customer);
        } elseif (!empty($arguments['email'])) {
            $order->setCustomer(Craft::$app->getUsers()->ensureUserByEmail($arguments['email']));
        }

        /** @var OrderElement $order */
        $order = $this->populateElementWithData($order, $arguments, $resolveInfo);

        if (array_key_exists('billingAddress', $addresses)) {
            $order->setBillingAddress($addresses['billingAddress']);
        }

        if (array_key_exists('shippingAddress', $addresses)) {
            $order->setShippingAddress($addresses['shippingAddress']);
        }

        if (isset($arguments['lineItems'])) {
            $order->setLineItems($this->lineItems($order, $arguments['lineItems']));
        }

        if (isset($arguments['shippingMethodHandle'])) {
            $order->shippingMethodName = $commerce->getShippingMethods()->getShippingMethodByHandle($arguments['shippingMethodHandle'], $order->storeId)?->name;
        }

        $markAsComplete = !empty($arguments['isCompleted']) && !$order->isCompleted;

        /** @var OrderElement $order */
        $order = $this->saveElement($order);

        if ($markAsComplete && !$order->markAsComplete()) {
            throw new Error('Couldn’t mark the order as complete.');
        }

        return OrderElement::find()->id($order->id)->one();
    }

    /**
     * Deletes an order identified by the passed arguments.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return bool
     * @throws Throwable
     */
    public function deleteOrder(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): bool
    {
        $order = OrderElement::find()->id($arguments['id'])->one();

        if (!$order) {
            return false;
        }

        $this->requireSchemaAction('orders.' . $order->getStore()->uid, 'delete');
        $this->requireUserAuthorization($order, 'delete');

        return Craft::$app->getElements()->deleteElement($order, $arguments['hardDelete'] ?? false);
    }

    /**
     * Returns the order’s line items, populated with the submitted line item data.
     *
     * @param OrderElement $order
     * @param array $lineItemsData
     * @return LineItem[]
     * @throws Error
     */
    private function lineItems(OrderElement $order, array $lineItemsData): array
    {
        $existingLineItems = [];
        foreach ($order->getLineItems() as $lineItem) {
            $existingLineItems[$lineItem->id] = $lineItem;
        }

        $lineItems = [];

        foreach ($lineItemsData as $data) {
            $options = isset($data['options']) ? Json::decode($data['options']) : null;

            if (!empty($data['id'])) {
                $lineItem = $existingLineItems[$data['id']] ?? throw new Error('No such line item exists on this order');
                if (isset($options)) {
                    $lineItem->setOptions($options);
                }
            } else {
                if (empty($data['purchasableId'])) {
                    throw new Error('A purchasable ID is required to create a line item.');
                }

                try {
                    $lineItem = Plugin::getInstance()->getLineItems()->create($order, [
                        'purchasableId' => $data['purchasableId'],
                        'options' => $options ?? [],
                    ]);
                } catch (Throwable $e) {
                    throw new Error($e->getMessage());
                }
            }

            foreach (['qty', 'note', 'privateNote'] as $attribute) {
                if (isset($data[$attribute])) {
                    $lineItem->$attribute = $data[$attribute];
                }
            }

            $lineItems[] = $lineItem;
        }

        return $lineItems;
    }
}
