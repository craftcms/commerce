<?php

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\actions\UpdateOrderStatus;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;

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
                return $this->getOrderStatus()->getLabelHtml() ?? '<span class="status"></span>';
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

    // Protected Methods
    // =========================================================================

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
                'label' => Craft::t('commerce', 'All Orders'),
                'criteria' => ['isCompleted' => true],
                'defaultSort' => ['dateOrdered', 'desc'],
                'badgeCount' => $count
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Order Status')];

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
                    'handle' => $orderStatus->handle
                ]
            ];
        }

        $sources[] = ['heading' => Craft::t('commerce', 'Carts')];

        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();

        $updatedAfter = [];
        $updatedAfter[] = '>= ' . $edge;

        $criteriaActive = ['dateUpdated' => $updatedAfter, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:active',
            'label' => Craft::t('commerce', 'Active Carts'),
            'criteria' => $criteriaActive,
            'defaultSort' => ['commerce_orders.dateUpdated', 'asc'],
            'data' => [
                'handle' => 'cartsActive'
            ]
        ];
        $updatedBefore = [];
        $updatedBefore[] = '< ' . $edge;

        $criteriaInactive = ['dateUpdated' => $updatedBefore, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:inactive',
            'label' => Craft::t('commerce', 'Inactive Carts'),
            'criteria' => $criteriaInactive,
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsInactive'
            ]
        ];

        $criteriaAttemptedPayment = ['hasTransactions' => true, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:attempted-payment',
            'label' => Craft::t('commerce', 'Attempted Payments'),
            'criteria' => $criteriaAttemptedPayment,
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsAttemptedPayment'
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
            $deleteAction = $elementService->createAction(
                [
                    'type' => Delete::class,
                    'confirmationMessage' => Craft::t('commerce', 'Are you sure you want to delete the selected orders?'),
                    'successMessage' => Craft::t('commerce', 'Orders deleted.'),
                ]
            );
            $actions[] = $deleteAction;

            // Only allow mass updating order status when all selected are of the same status, and not carts.
            $isStatus = strpos($source, 'orderStatus:');

            if ($isStatus === 0) {
                $updateOrderStatusAction = $elementService->createAction([
                    'type' => UpdateOrderStatus::class
                ]);
                $actions[] = $updateOrderStatusAction;
            }

            // Restore
            $actions[] = Craft::$app->getElements()->createAction([
                'type' => Restore::class,
                'successMessage' => Craft::t('commerce', 'Orders restored.'),
                'partialSuccessMessage' => Craft::t('commerce', 'Some orders restored.'),
                'failMessage' => Craft::t('commerce', 'Orders not restored.'),
            ]);
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'order' => ['label' => Craft::t('commerce', 'Order')],
            'reference' => ['label' => Craft::t('commerce', 'Reference')],
            'shortNumber' => ['label' => Craft::t('commerce', 'Short Number')],
            'number' => ['label' => Craft::t('commerce', 'Number')],
            'id' => ['label' => Craft::t('commerce', 'ID')],
            'orderStatus' => ['label' => Craft::t('commerce', 'Status')],
            'total' => ['label' => Craft::t('commerce', 'Total')],
            'totalPrice' => ['label' => Craft::t('commerce', 'Total')],
            'totalPaid' => ['label' => Craft::t('commerce', 'Total Paid')],
            'totalDiscount' => ['label' => Craft::t('commerce', 'Total Discount')],
            'totalShippingCost' => ['label' => Craft::t('commerce', 'Total Shipping')],
            'totalTax' => ['label' => Craft::t('commerce', 'Total Tax')],
            'totalIncludedTax' => ['label' => Craft::t('commerce', 'Total Included Tax')],
            'dateOrdered' => ['label' => Craft::t('commerce', 'Date Ordered')],
            'datePaid' => ['label' => Craft::t('commerce', 'Date Paid')],
            'dateCreated' => ['label' => Craft::t('commerce', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('commerce', 'Date Updated')],
            'email' => ['label' => Craft::t('commerce', 'Email')],
            'shippingFullName' => ['label' => Craft::t('commerce', 'Shipping Full Name')],
            'shippingFirstName' => ['label' => Craft::t('commerce', 'Shipping First Name')],
            'shippingLastName' => ['label' => Craft::t('commerce', 'Shipping Last Name')],
            'billingFullName' => ['label' => Craft::t('commerce', 'Billing Full Name')],
            'billingFirstName' => ['label' => Craft::t('commerce', 'Billing First Name')],
            'billingLastName' => ['label' => Craft::t('commerce', 'Billing Last Name')],
            'shippingBusinessName' => ['label' => Craft::t('commerce', 'Shipping Business Name')],
            'billingBusinessName' => ['label' => Craft::t('commerce', 'Billing Business Name')],
            'shippingMethodName' => ['label' => Craft::t('commerce', 'Shipping Method')],
            'gatewayName' => ['label' => Craft::t('commerce', 'Gateway')],
            'paidStatus' => ['label' => Craft::t('commerce', 'Paid Status')]
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
            'number' => Craft::t('commerce', 'Number'),
            'reference' => Craft::t('commerce', 'Reference'),
            'id' => Craft::t('commerce', 'ID'),
            'orderStatusId' => Craft::t('commerce', 'Order Status'),
            'totalPrice' => Craft::t('commerce', 'Total Payable'),
            'totalPaid' => Craft::t('commerce', 'Total Paid'),
            [
                'label' => Craft::t('commerce', 'Shipping First Name'),
                'orderBy' => 'shipping_address.firstName',
                'attribute' => 'shippingFirstName',
            ],
            [
                'label' => Craft::t('commerce', 'Shipping Last Name'),
                'orderBy' => 'shipping_address.lastName',
                'attribute' => 'shippingLastName',
            ],
            [
                'label' => Craft::t('commerce', 'Shipping Full Name'),
                'orderBy' => 'shipping_address.fullName',
                'attribute' => 'shippingFullName',
            ],
            [
                'label' => Craft::t('commerce', 'Billing First Name'),
                'orderBy' => 'billing_address.firstName',
                'attribute' => 'billingFirstName',
            ],
            [
                'label' => Craft::t('commerce', 'Billing Last Name'),
                'orderBy' => 'billing_address.lastName',
                'attribute' => 'billingLastName',
            ],
            [
                'label' => Craft::t('commerce', 'Billing Full Name'),
                'orderBy' => 'billing_address.fullName',
                'attribute' => 'billingFullName',
            ],
            'dateOrdered' => Craft::t('commerce', 'Date Ordered'),
            [
                'label' => Craft::t('commerce', 'Date Updated'),
                'orderBy' => 'commerce_orders.dateUpdated',
                'attribute' => 'dateUpdated'
            ],
            'datePaid' => Craft::t('commerce', 'Date Paid')
        ];
    }
}
