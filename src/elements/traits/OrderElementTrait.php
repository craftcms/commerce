<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\actions\DeleteOrder;
use craft\commerce\elements\actions\UpdateOrderStatus;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use DateTime;

trait OrderElementTrait
{
    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @return OrderQuery The newly created [[OrderQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new OrderQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'orderStatus':
            {
                return $this->getOrderStatus() ? $this->getOrderStatus()->getLabelHtml() ?? '<span class="status"></span>' : '';
            }
            case 'customer':
            {
                return $this->getCustomerLinkHtml();
            }
            case 'shippingFullName':
            {
                return $this->getShippingAddress() ? $this->getShippingAddress()->fullName ?? '' : '';
            }
            case 'shippingFirstName':
            {
                return $this->getShippingAddress() ? $this->getShippingAddress()->firstName ?? '' : '';
            }
            case 'shippingLastName':
            {
                return $this->getShippingAddress() ? $this->getShippingAddress()->lastName ?? '' : '';
            }
            case 'billingFullName':
            {
                return $this->getBillingAddress() ? $this->getBillingAddress()->fullName ?? '' : '';
            }
            case 'billingFirstName':
            {
                return $this->getBillingAddress() ? $this->getBillingAddress()->firstName ?? '' : '';
            }
            case 'billingLastName':
            {
                return $this->getBillingAddress() ? $this->getBillingAddress()->lastName ?? '' : '';
            }
            case 'shippingBusinessName':
            {
                return $this->getShippingAddress()->businessName ?? '';
            }
            case 'billingBusinessName':
            {
                return $this->getBillingAddress()->businessName ?? '';
            }
            case 'shippingMethodName':
            {
                return $this->getShippingMethod()->name ?? '';
            }
            case 'gatewayName':
            {
                return $this->getGateway()->name ?? '';
            }
            case 'paidStatus':
            {
                return $this->getPaidStatusHtml();
            }
            case 'totalPaid':
            {
                return Craft::$app->getFormatter()->asCurrency($this->getTotalPaid(), $this->currency);
            }
            case 'total':
            {
                return Craft::$app->getFormatter()->asCurrency($this->getTotal(), $this->currency);
            }
            case 'totalPrice':
            {
                return Craft::$app->getFormatter()->asCurrency($this->getTotalPrice(), $this->currency);
            }
            case 'totalShippingCost':
            {
                $amount = $this->getTotalShippingCost();
                return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
            }
            case 'totalDiscount':
            {
                $amount = $this->getTotalDiscount();
                if ($this->$attribute >= 0) {
                    return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
                }

                return Craft::$app->getFormatter()->asCurrency($amount * -1, $this->currency);
            }
            case 'totalTax':
            {
                $amount = $this->getTotalTax();
                return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
            }
            case 'totalIncludedTax':
            {
                $amount = $this->getTotalTaxIncluded();
                return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
            }
            default:
            {
                return parent::tableAttributeHtml($attribute);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'billingFirstName',
            'billingLastName',
            'billingFullName',
            'email',
            'number',
            'shippingFirstName',
            'shippingLastName',
            'shippingFullName',
            'shortNumber',
            'transactionReference',
            'username',
            'reference'
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords(string $attribute): string
    {
        switch ($attribute) {
            case 'billingFirstName':
                return $this->billingAddress->firstName ?? '';
            case 'billingLastName':
                return $this->billingAddress->lastName ?? '';
            case 'billingFullName':
                return $this->billingAddress->fullName ?? '';
            case 'shippingFirstName':
                return $this->shippingAddress->firstName ?? '';
            case 'shippingLastName':
                return $this->shippingAddress->lastName ?? '';
            case 'shippingFullName':
                return $this->shippingAddress->fullName ?? '';
            case 'transactionReference':
                return implode(' ', ArrayHelper::getColumn($this->getTransactions(), 'reference'));
            case 'username':
                return $this->getUser()->username ?? '';
            default:
                return parent::getSearchKeywords($attribute);
        }
    }


    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $allCriteria = ['isCompleted' => true];
        $count = Craft::configure(self::find(), $allCriteria)->count();

        $sources = [
            '*' => [
                'key' => '*',
                'label' => Plugin::t('All Orders'),
                'criteria' => ['isCompleted' => true],
                'defaultSort' => ['dateOrdered', 'desc'],
                'badgeCount' => $count,
                'data' => [
                    'date-attr' => 'dateOrdered',
                ],
            ]
        ];

        $sources[] = ['heading' => Plugin::t('Order Status')];

        foreach (Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $orderStatus) {
            $key = 'orderStatus:' . $orderStatus->handle;
            $criteriaStatus = ['orderStatusId' => $orderStatus->id];

            $count = (new Query())
                ->where(['o.orderStatusId' => $orderStatus->id, 'e.dateDeleted' => null])
                ->from([Table::ORDERS . ' o'])
                ->leftJoin(['{{%elements}} e'], '[[o.id]] = [[e.id]]')
                ->count();

            $sources[] = [
                'key' => $key,
                'status' => $orderStatus->color,
                'label' => $orderStatus->name,
                'criteria' => $criteriaStatus,
                'defaultSort' => ['dateOrdered', 'desc'],
                'badgeCount' => $count,
                'data' => [
                    'handle' => $orderStatus->handle,
                    'date-attr' => 'dateOrdered',
                ]
            ];
        }

        $sources[] = ['heading' => Plugin::t('Carts')];

        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();

        $updatedAfter = [];
        $updatedAfter[] = '>= ' . $edge;

        $criteriaActive = ['dateUpdated' => $updatedAfter, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:active',
            'label' => Plugin::t('Active Carts'),
            'criteria' => $criteriaActive,
            'defaultSort' => ['commerce_orders.dateUpdated', 'asc'],
            'data' => [
                'handle' => 'cartsActive',
                'date-attr' => 'dateUpdated',
            ]
        ];
        $updatedBefore = [];
        $updatedBefore[] = '< ' . $edge;

        $criteriaInactive = ['dateUpdated' => $updatedBefore, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:inactive',
            'label' => Plugin::t('Inactive Carts'),
            'criteria' => $criteriaInactive,
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsInactive',
                'date-attr' => 'dateUpdated',
            ]
        ];

        $criteriaAttemptedPayment = ['hasTransactions' => true, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:attempted-payment',
            'label' => Plugin::t('Attempted Payments'),
            'criteria' => $criteriaAttemptedPayment,
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsAttemptedPayment',
                'date-attr' => 'dateUpdated',
            ]
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = parent::defineActions($source);

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $elementService = Craft::$app->getElements();
            if (Craft::$app->getUser()->checkPermission('commerce-deleteOrders')) {
                $deleteAction = $elementService->createAction(
                    [
                        'type' => DeleteOrder::class,
                        'confirmationMessage' => Plugin::t('Are you sure you want to delete the selected orders?'),
                        'successMessage' => Plugin::t('Orders deleted.'),
                    ]
                );
                $actions[] = $deleteAction;
            }

            if (Craft::$app->getUser()->checkPermission('commerce-editOrders')) {
                // Only allow mass updating order status when all selected are of the same status, and not carts.
                $isStatus = strpos($source, 'orderStatus:');


                if ($isStatus === 0) {
                    $updateOrderStatusAction = $elementService->createAction([
                        'type' => UpdateOrderStatus::class
                    ]);
                    $actions[] = $updateOrderStatusAction;
                }
            }

            if (Craft::$app->getUser()->checkPermission('commerce-deleteOrders')) {
                // Restore
                $actions[] = Craft::$app->getElements()->createAction([
                    'type' => Restore::class,
                    'successMessage' => Plugin::t('Orders restored.'),
                    'partialSuccessMessage' => Plugin::t('Some orders restored.'),
                    'failMessage' => Plugin::t('Orders not restored.'),
                ]);
            }
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'order' => ['label' => Plugin::t('Order')],
            'reference' => ['label' => Plugin::t('Reference')],
            'shortNumber' => ['label' => Plugin::t('Short Number')],
            'number' => ['label' => Plugin::t('Number')],
            'id' => ['label' => Plugin::t('ID')],
            'orderStatus' => ['label' => Plugin::t('Status')],
            'total' => ['label' => Plugin::t('Total')],
            'totalPrice' => ['label' => Plugin::t('Total')],
            'totalPaid' => ['label' => Plugin::t('Total Paid')],
            'totalDiscount' => ['label' => Plugin::t('Total Discount')],
            'totalShippingCost' => ['label' => Plugin::t('Total Shipping')],
            'totalTax' => ['label' => Plugin::t('Total Tax')],
            'totalIncludedTax' => ['label' => Plugin::t('Total Included Tax')],
            'dateOrdered' => ['label' => Plugin::t('Date Ordered')],
            'datePaid' => ['label' => Plugin::t('Date Paid')],
            'dateCreated' => ['label' => Plugin::t('Date Created')],
            'dateUpdated' => ['label' => Plugin::t('Date Updated')],
            'email' => ['label' => Plugin::t('Email')],
            'customer' => ['label' => Plugin::t('Customer')],
            'shippingFullName' => ['label' => Plugin::t('Shipping Full Name')],
            'shippingFirstName' => ['label' => Plugin::t('Shipping First Name')],
            'shippingLastName' => ['label' => Plugin::t('Shipping Last Name')],
            'billingFullName' => ['label' => Plugin::t('Billing Full Name')],
            'billingFirstName' => ['label' => Plugin::t('Billing First Name')],
            'billingLastName' => ['label' => Plugin::t('Billing Last Name')],
            'shippingBusinessName' => ['label' => Plugin::t('Shipping Business Name')],
            'billingBusinessName' => ['label' => Plugin::t('Billing Business Name')],
            'shippingMethodName' => ['label' => Plugin::t('Shipping Method')],
            'gatewayName' => ['label' => Plugin::t('Gateway')],
            'paidStatus' => ['label' => Craft::t('commerce', 'Paid Status')],
            'couponCode' => ['label' => Craft::t('commerce', 'Coupon Code')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source = null): array
    {
        $attributes = [];
        $attributes[] = 'order';

        if (0 !== strpos($source, 'carts:')) {
            $attributes[] = 'reference';
            $attributes[] = 'orderStatus';
            $attributes[] = 'totalPrice';
            $attributes[] = 'dateOrdered';
            $attributes[] = 'totalPaid';
            $attributes[] = 'datePaid';
            $attributes[] = 'paidStatus';
        } else {
            $attributes[] = 'shortNumber';
            $attributes[] = 'dateUpdated';
            $attributes[] = 'totalPrice';
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'number' => Plugin::t('Number'),
            'reference' => Plugin::t('Reference'),
            'id' => Plugin::t('ID'),
            'orderStatusId' => Plugin::t('Order Status'),
            'totalPrice' => Plugin::t('Total Payable'),
            'totalPaid' => Plugin::t('Total Paid'),
            [
                'label' => Plugin::t('Shipping First Name'),
                'orderBy' => 'shipping_address.firstName',
                'attribute' => 'shippingFirstName',
            ],
            [
                'label' => Plugin::t('Shipping Last Name'),
                'orderBy' => 'shipping_address.lastName',
                'attribute' => 'shippingLastName',
            ],
            [
                'label' => Plugin::t('Shipping Full Name'),
                'orderBy' => 'shipping_address.fullName',
                'attribute' => 'shippingFullName',
            ],
            [
                'label' => Plugin::t('Billing First Name'),
                'orderBy' => 'billing_address.firstName',
                'attribute' => 'billingFirstName',
            ],
            [
                'label' => Plugin::t('Billing Last Name'),
                'orderBy' => 'billing_address.lastName',
                'attribute' => 'billingLastName',
            ],
            [
                'label' => Plugin::t('Billing Full Name'),
                'orderBy' => 'billing_address.fullName',
                'attribute' => 'billingFullName',
            ],
            'dateOrdered' => Plugin::t('Date Ordered'),
            [
                'label' => Plugin::t('Date Updated'),
                'orderBy' => 'commerce_orders.dateUpdated',
                'attribute' => 'dateUpdated'
            ],
            'datePaid' => Craft::t('commerce', 'Date Paid'),
            'couponCode' => Craft::t('commerce', 'Coupon Code'),
        ];
    }
}
