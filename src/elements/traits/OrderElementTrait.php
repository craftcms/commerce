<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\elements\actions\CopyLoadCartUrl;
use craft\commerce\elements\actions\DownloadOrderPdfAction;
use craft\commerce\elements\actions\UpdateOrderStatus;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\conditions\orders\OrderStatusConditionRule;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\exports\Expanded;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\exporters\Expanded as CraftExpanded;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\models\FieldLayout;
use craft\models\Site;
use Exception;
use yii\base\InvalidConfigException;

trait OrderElementTrait
{
    /**
     * @inheritdoc
     * @return OrderQuery The newly created [[OrderQuery]] instance.
     */
    public static function find(): OrderQuery
    {
        return new OrderQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): FieldLayout
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    /**
     * @inheritdoc
     */
    protected function htmlAttributes(string $context): array
    {
        $attributes = parent::htmlAttributes($context);
        $attributes['data'] = ['number' => $this->number];
        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'orderStatus':
            {
                return $this->getOrderStatus() ? $this->getOrderStatus()->getLabelHtml() : '';
            }
            case 'customer':
            {
                return $this->getCustomerLinkHtml();
            }
            case 'shippingFullName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->fullName ?? '') : '';
            }
            case 'shippingFirstName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->firstName ?? '') : '';
            }
            case 'shippingLastName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->lastName ?? '') : '';
            }
            case 'billingFullName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->fullName ?? '') : '';
            }
            case 'billingFirstName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->firstName ?? '') : '';
            }
            case 'billingLastName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->lastName ?? '') : '';
            }
            case 'shippingOrganizationName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->organization ?? '') : '';
            }
            case 'billingOrganizationName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->organization ?? '') : '';
            }
            case 'shippingMethodName':
            {
                return $this->shippingMethodName ?? '';
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
                return $this->storedTotalPaidAsCurrency;
            }
            case 'itemTotal':
            {
                return $this->storedItemTotalAsCurrency;
            }
            case 'itemSubtotal':
            {
                return $this->storedItemSubtotalAsCurrency;
            }
            case 'totalQty':
            {
                return (string)$this->storedTotalQty;
            }
            case 'total':
            {
                return $this->totalAsCurrency;
            }
            case 'totalPrice':
            {
                return $this->storedTotalPriceAsCurrency;
            }
            case 'totalShippingCost':
            {
                return $this->storedTotalShippingCostAsCurrency;
            }
            case 'totalDiscount':
            {
                return $this->storedTotalDiscountAsCurrency;
            }
            case 'totalTax':
            {
                return $this->storedTotalTaxAsCurrency;
            }
            case 'totalIncludedTax':
            {
                return $this->storedTotalTaxIncludedAsCurrency;
            }
            case 'totals':
            {
                $miniTable = [];

                $miniTable[] = [
                    'label' => Craft::t('commerce', 'Qty'),
                    'value' => $this->storedTotalQty,
                ];

                if ($this->itemSubtotal > 0) {
                    $miniTable[] = [
                        'label' => Craft::t('commerce', 'Items'),
                        'value' => $this->itemSubtotalAsCurrency,
                    ];
                }

                if ($this->storedTotalDiscount < 0) {
                    $miniTable[] = [
                        'label' => Craft::t('commerce', 'Discounts'),
                        'value' => $this->storedTotalDiscountAsCurrency,
                    ];
                }

                if ($this->storedTotalShippingCost > 0) {
                    $miniTable[] = [
                        'label' => Craft::t('commerce', 'Shipping'),
                        'value' => $this->storedTotalShippingCostAsCurrency,
                    ];
                }

                if ($this->storedTotalTaxIncluded > 0) {
                    $miniTable[] = [
                        'label' => Craft::t('commerce', 'Tax (inc)'),
                        'value' => $this->storedTotalTaxIncludedAsCurrency,
                    ];
                }

                if ($this->storedTotalTax > 0) {
                    $miniTable[] = [
                        'label' => Craft::t('commerce', 'Tax'),
                        'value' => $this->storedTotalTaxAsCurrency,
                    ];
                }

                if ($this->storedTotalPrice > 0) {
                    $miniTable[] = [
                        'label' => Craft::t('commerce', 'Price'),
                        'value' => $this->storedTotalPriceAsCurrency,
                    ];
                }

                return $this->_miniTable($miniTable);
            }
            case 'orderSite':
            {
                $site = Craft::$app->getSites()->getSiteById($this->orderSiteId);
                return $site->name ?? '';
            }
            default:
            {
                return parent::attributeHtml($attribute);
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
            'billingAddress',
            'email',
            'number',
            'shippingFirstName',
            'shippingLastName',
            'shippingFullName',
            'shippingAddress',
            'shortNumber',
            'transactionReference',
            'username',
            'reference',
            'skus',
            'lineItemDescriptions',
            'customerName',
        ];
    }

    /**
     * @inheritdoc
     * @noinspection PhpUnused
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
            case 'billingAddress':
                $address = $this->getBillingAddress();
                return $address ? Craft::$app->getAddresses()->formatAddress($address) : '';
            case 'shippingFirstName':
                return $this->shippingAddress->firstName ?? '';
            case 'shippingLastName':
                return $this->shippingAddress->lastName ?? '';
            case 'shippingFullName':
                return $this->shippingAddress->fullName ?? '';
            case 'shippingAddress':
                $address = $this->getShippingAddress();
                return $address ? Craft::$app->getAddresses()->formatAddress($address) : '';
            case 'transactionReference':
                return implode(' ', ArrayHelper::getColumn($this->getTransactions(), 'reference'));
            case 'username':
                return $this->getCustomer()->username ?? '';
            case 'skus':
                return implode(' ', ArrayHelper::getColumn($this->getLineItems(), 'sku'));
            case 'lineItemDescriptions':
                return implode(' ', ArrayHelper::getColumn($this->getLineItems(), 'description'));
            case 'customerName':
                return $this->getCustomer()->fullName ?? '';
            default:
                return parent::getSearchKeywords($attribute);
        }
    }


    /**
     * @inheritdoc
     * @throws Exception
     */
    protected static function defineSources(?string $context = null): array
    {
        $siteHandle = Craft::$app->getRequest()->getParam('site');
        $site = $siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : Craft::$app->getSites()->getCurrentSite();
        /** @var StoreBehavior $site */
        $store = $site->getStore();
        $orderCriteria = ['isCompleted' => true, 'storeId' => $store->id];

        $sources = [
            '*' => [
                'key' => '*',
                'label' => Craft::t('commerce', 'All Orders'),
                'criteria' => $orderCriteria,
                'defaultSort' => ['dateOrdered', 'desc'],
                'data' => [
                    'date-attr' => 'dateOrdered',
                ],
            ],
        ];

        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();

        $criteriaActive = ['dateUpdated' => ['>= ' . $edge], 'isCompleted' => false];
        $criteriaInactive = ['dateUpdated' => ['< ' . $edge], 'isCompleted' => false];
        $criteriaAttemptedPayment = ['hasTransactions' => true, 'isCompleted' => false];

        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($store->id)->all();

        $sources[] = ['heading' => $store->getName()];

        foreach ($orderStatuses as $orderStatus) {
            $key = 'orderStatus:' . $orderStatus->handle;

            $sources[$key] = [
                'key' => $key,
                'status' => $orderStatus->color,
                'label' => Craft::t('site', $orderStatus->name),
                'badgeCount' => 0,
                'criteria' => ArrayHelper::merge($orderCriteria, ['orderStatusId' => $orderStatus->id]),
                'defaultSort' => ['dateOrdered', 'desc'],
                'data' => [
                    'handle' => $orderStatus->handle,
                    'date-attr' => 'dateOrdered',
                ],
            ];
        }

        $sources[] = [
            'key' => 'carts:active:' . $store->handle,
            'label' => Craft::t('commerce', 'Active Carts'),
            'criteria' => ArrayHelper::merge($criteriaActive, ['storeId' => $store->id]),
            'defaultSort' => ['commerce_orders.dateUpdated', 'asc'],
            'data' => [
                'handle' => 'cartsActive',
                'date-attr' => 'dateUpdated',
            ],
        ];

        $sources[] = [
            'key' => 'carts:inactive:' . $store->handle,
            'label' => Craft::t('commerce', 'Inactive Carts'),
            'criteria' => ArrayHelper::merge($criteriaInactive, ['storeId' => $store->id]),
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsInactive',
                'date-attr' => 'dateUpdated',
            ],
        ];

        $sources[] = [
            'key' => 'carts:attempted-payment:' . $store->handle,
            'label' => Craft::t('commerce', 'Attempted Payments'),
            'criteria' => ArrayHelper::merge($criteriaAttemptedPayment, ['storeId' => $store->id]),
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsAttemptedPayment',
                'date-attr' => 'dateUpdated',
            ],
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source): array
    {
        $actions = parent::defineActions($source);

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            /** @var StoreBehavior|Site $site */
            $site = Cp::requestedSite();
            $store = $site->getStore();
            // Remove nested "all" prefix if it exists at the start of the string
            $source = str_starts_with($source, '*/') ? substr($source, 2) : $source;


            $elementService = Craft::$app->getElements();

            if ($store && Plugin::getInstance()->getPdfs()->getHasEnabledPdf($store->id)) {
                $actions[] = $elementService->createAction([
                    'type' => DownloadOrderPdfAction::class,
                    'storeId' => $store->id,
                ]);
            }

            if (Craft::$app->getUser()->checkPermission('commerce-deleteOrders')) {
                $deleteAction = $elementService->createAction(
                    [
                        'type' => Delete::class,
                        'confirmationMessage' => Craft::t('commerce', 'Are you sure you want to delete the selected orders?'),
                        'successMessage' => Craft::t('commerce', 'Orders deleted.'),
                    ]
                );
                $actions[] = $deleteAction;
            }

            if (Craft::$app->getUser()->checkPermission('commerce-editOrders')) {
                // Only allow mass updating order status when all selected are of the same status, and not carts.
                $isStatus = strpos($source, 'orderStatus:');
                if ($isStatus === 0) {
                    $updateOrderStatusAction = $elementService->createAction([
                        'type' => UpdateOrderStatus::class,
                    ]);
                    $actions[] = $updateOrderStatusAction;
                }

                $isStatus = strpos($source, 'carts:');
                if ($isStatus === 0) {
                    $updateOrderStatusAction = $elementService->createAction([
                        'type' => CopyLoadCartUrl::class,
                    ]);
                    $actions[] = $updateOrderStatusAction;
                }
            }

            if (Craft::$app->getUser()->checkPermission('commerce-deleteOrders')) {
                // Restore
                $actions[] = Craft::$app->getElements()->createAction([
                    'type' => Restore::class,
                    'successMessage' => Craft::t('commerce', 'Orders restored.'),
                    'partialSuccessMessage' => Craft::t('commerce', 'Some orders restored.'),
                    'failMessage' => Craft::t('commerce', 'Orders not restored.'),
                ]);
            }
        }

        return $actions;
    }

    /**
     * @inheritDoc
     */
    protected static function defineExporters(string $source): array
    {
        $default = parent::defineExporters($source);
        // Remove the standard expanded exporter and use our own
        ArrayHelper::removeValue($default, CraftExpanded::class);
        $default[] = Expanded::class;

        return $default;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'reference' => ['label' => Craft::t('commerce', 'Reference')],
            'shortNumber' => ['label' => Craft::t('commerce', 'Short Number')],
            'number' => ['label' => Craft::t('commerce', 'Number')],
            'id' => ['label' => Craft::t('commerce', 'ID')],
            'orderStatus' => ['label' => Craft::t('commerce', 'Status')],
            'totals' => ['label' => Craft::t('commerce', 'All Totals')],
            'totalQty' => ['label' => Craft::t('commerce', 'Total Qty')],
            'total' => ['label' => Craft::t('commerce', 'Total')],
            'totalPrice' => ['label' => Craft::t('commerce', 'Total Price')],
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
            'customer' => ['label' => Craft::t('commerce', 'Customer')],
            'shippingFullName' => ['label' => Craft::t('commerce', 'Shipping Full Name')],
            'shippingFirstName' => ['label' => Craft::t('commerce', 'Shipping First Name')],
            'shippingLastName' => ['label' => Craft::t('commerce', 'Shipping Last Name')],
            'billingFullName' => ['label' => Craft::t('commerce', 'Billing Full Name')],
            'billingFirstName' => ['label' => Craft::t('commerce', 'Billing First Name')],
            'billingLastName' => ['label' => Craft::t('commerce', 'Billing Last Name')],
            'shippingOrganizationName' => ['label' => Craft::t('commerce', 'Shipping Business Name')],
            'billingOrganizationName' => ['label' => Craft::t('commerce', 'Billing Business Name')],
            'shippingMethodName' => ['label' => Craft::t('commerce', 'Shipping Method')],
            'gatewayName' => ['label' => Craft::t('commerce', 'Gateway')],
            'paidStatus' => ['label' => Craft::t('commerce', 'Paid Status')],
            'couponCode' => ['label' => Craft::t('commerce', 'Coupon Code')],
            'itemTotal' => ['label' => Craft::t('commerce', 'Item Total')],
            'itemSubtotal' => ['label' => Craft::t('commerce', 'Item Subtotal')],
            'orderSite' => ['label' => Craft::t('commerce', 'Order Site')],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(?string $source = null): array
    {
        $attributes = [];
        $attributes[] = 'order';

        if (!str_starts_with($source, 'carts:')) {
            // For orders (including order status sources)
            $attributes[] = 'reference';
            if (!str_starts_with($source, 'orderStatus:')) {
                // Only show status column when not filtered by status
                $attributes[] = 'orderStatus';
            }
            $attributes[] = 'customer';
            $attributes[] = 'dateOrdered';
            $attributes[] = 'datePaid';
            $attributes[] = 'totalPaid';
            $attributes[] = 'paidStatus';
            $attributes[] = 'totals';
        } else {
            // For carts
            $attributes[] = 'shortNumber';
            $attributes[] = 'dateUpdated';
            $attributes[] = 'totalPrice';
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        /** @var OrderQuery $elementQuery */

        match ($attribute) {
            'totals', 'total', 'totalPrice', 'totalDiscount', 'totalShippingCost', 'totalTax', 'totalIncludedTax' => $elementQuery->withAdjustments(),
            'totalPaid', 'paidStatus' => $elementQuery->withTransactions(),
            'shippingFullName', 'shippingFirstName', 'shippingLastName', 'billingFullName', 'billingFirstName', 'billingLastName', 'shippingOrganizationName', 'billingOrganizationName', 'shippingMethodName' => $elementQuery->withAddresses(),
            'email', 'customer' => $elementQuery->withCustomer(),
            'itemTotal', 'itemSubtotal' => $elementQuery->withLineItems(),
            default => parent::prepElementQueryForTableAttribute($elementQuery, $attribute),
        };
    }

    /**
     * @inheritdoc
     * @return OrderCondition
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(OrderCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'number' => Craft::t('commerce', 'Number'),
            'reference' => Craft::t('commerce', 'Reference'),
            'orderStatusId' => Craft::t('commerce', 'Order Status'),
            'totalPrice' => Craft::t('commerce', 'Total Price'),
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
            [
                'label' => Craft::t('commerce', 'Date Ordered'),
                'orderBy' => 'dateOrdered',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('commerce', 'Date Updated'),
                'orderBy' => 'commerce_orders.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('commerce', 'Date Paid'),
                'orderBy' => 'datePaid',
                'defaultDir' => 'desc',
            ],
            'couponCode' => Craft::t('commerce', 'Coupon Code'),
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }

    /**
     * @param array $miniTable Expects an array with rows of 'label', 'value' keys values.
     */
    private function _miniTable(array $miniTable): string
    {
        $output = '<table style="padding: 0; width: 100%">';
        foreach ($miniTable as $row) {
            $output .= '<tr style="padding: 0">';
            $output .= '<td style="text-align: left; padding: 0px">' . $row['label'] . '</td>';
            $output .= '<td style="text-align: right; padding: 0px">' . $row['value'] . '</td>';
            $output .= '</tr>';
        }
        $output .= '</table>';

        return $output;
    }

    /**
     * @inheritdoc
     */
    public static function modifyCustomSource(array $config): array
    {
        try {
            /** @var OrderCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($config['condition']);
        } catch (InvalidConfigException) {
            return $config;
        }

        $rules = $condition->getConditionRules();

        // see if it's limited to one product type
        /** @var OrderStatusConditionRule|null $orderStatusConditionRule */
        $orderStatusConditionRule = ArrayHelper::firstWhere($rules, fn($rule) => $rule instanceof OrderStatusConditionRule);
        $orderStatusOptions = $orderStatusConditionRule?->getValues();

        /** @var StoreBehavior $currentSite */
        $currentSite = Cp::requestedSite();
        $store = $currentSite->getStore();


        if ($orderStatusOptions && count($orderStatusOptions) === 1) {
            $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusByUid(reset($orderStatusOptions));

            if ($store->id != $orderStatus->storeId) {
                $config['disabled'] = true;
            }

            if ($orderStatus) {
                $config['status'] = $orderStatus->color;
            }
        }

        return $config;
    }

    /**
     * @inheritdoc
     */
    protected static function defineCardAttributes(): array
    {
        /** @var OrderStatus $status */
        $status = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses()->first();
        $site = Craft::$app->getSites()->getCurrentSite();
        $number = Plugin::getInstance()->getCarts()->generateCartNumber();

        return array_merge(parent::defineCardAttributes(), [
            'shortNumber' => [
                'label' => Craft::t('commerce', 'Short Number'),
                'placeholder' => substr($number, 0, 7),
            ],
            'number' => [
                'label' => Craft::t('commerce', 'Number'),
                'placeholder' => $number,
            ],
            'id' => [
                'label' => Craft::t('commerce', 'ID'),
                'placeholder' => '12345',
            ],
            'orderStatus' => [
                'label' => Craft::t('commerce', 'Status'),
                'placeholder' => $status->getLabelHtml(),
            ],
            'totalQty' => [
                'label' => Craft::t('commerce', 'Total Qty'),
                'placeholder' => '10',
            ],
            'total' => [
                'label' => Craft::t('commerce', 'Total'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(123.99),
            ],
            'totalPrice' => [
                'label' => Craft::t('commerce', 'Total Price'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(123.99),
            ],
            'totalPaid' => [
                'label' => Craft::t('commerce', 'Total Paid'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(123.99),
            ],
            'totalDiscount' => [
                'label' => Craft::t('commerce', 'Total Discount'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(12.99),
            ],
            'totalShippingCost' => [
                'label' => Craft::t('commerce', 'Total Shipping'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(9.99),
            ],
            'totalTax' => [
                'label' => Craft::t('commerce', 'Total Tax'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(19.99),
            ],
            'totalIncludedTax' => [
                'label' => Craft::t('commerce', 'Total Included Tax'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(19.99),
            ],
            'dateOrdered' => [
                'label' => Craft::t('commerce', 'Date Ordered'),
                'placeholder' => Craft::$app->getFormattingLocale()->getFormatter()->asDate(time(), 'short'),
            ],
            'datePaid' => [
                'label' => Craft::t('commerce', 'Date Paid'),
                'placeholder' => Craft::$app->getFormattingLocale()->getFormatter()->asDate(time(), 'short'),
            ],
            'dateUpdated' => [
                'label' => Craft::t('commerce', 'Date Updated'),
                'placeholder' => Craft::$app->getFormattingLocale()->getFormatter()->asDate(time(), 'short'),
            ],
            'email' => [
                'label' => Craft::t('commerce', 'Email'),
                'placeholder' => 'user@example.com',
            ],
            'customer' => [
                'label' => Craft::t('commerce', 'Customer'),
                'placeholder' => Craft::t('commerce', 'Customer'),
            ],
            'shippingFullName' => [
                'label' => Craft::t('commerce', 'Shipping Full Name'),
                'placeholder' => Craft::t('commerce', 'Shipping Full Name'),
            ],
            'shippingFirstName' => [
                'label' => Craft::t('commerce', 'Shipping First Name'),
                'placeholder' => Craft::t('commerce', 'Shipping First Name'),
            ],
            'shippingLastName' => [
                'label' => Craft::t('commerce', 'Shipping Last Name'),
                'placeholder' => Craft::t('commerce', 'Shipping Last Name'),
            ],
            'billingFullName' => [
                'label' => Craft::t('commerce', 'Billing Full Name'),
                'placeholder' => Craft::t('commerce', 'Billing Full Name'),
            ],
            'billingFirstName' => [
                'label' => Craft::t('commerce', 'Billing First Name'),
                'placeholder' => Craft::t('commerce', 'Billing First Name'),
            ],
            'billingLastName' => [
                'label' => Craft::t('commerce', 'Billing Last Name'),
                'placeholder' => Craft::t('commerce', 'Billing Last Name'),
            ],
            'shippingOrganizationName' => [
                'label' => Craft::t('commerce', 'Shipping Business Name'),
                'placeholder' => Craft::t('commerce', 'Shipping Business Name'),
            ],
            'billingOrganizationName' => [
                'label' => Craft::t('commerce', 'Billing Business Name'),
                'placeholder' => Craft::t('commerce', 'Billing Business Name'),
            ],
            'shippingMethodName' => [
                'label' => Craft::t('commerce', 'Shipping Method'),
                'placeholder' => Craft::t('commerce', 'Shipping Method'),
            ],
            'gatewayName' => [
                'label' => Craft::t('commerce', 'Gateway'),
                'placeholder' => Craft::t('commerce', 'Gateway'),
            ],
            'paidStatus' => [
                'label' => Craft::t('commerce', 'Paid Status'),
                'placeholder' => Cp::statusLabelHtml(['color' => 'green', 'label' => Craft::t('commerce', 'Paid')]),
            ],
            'couponCode' => [
                'label' => Craft::t('commerce', 'Coupon Code'),
                'placeholder' => 'SAVE10',
            ],
            'itemTotal' => [
                'label' => Craft::t('commerce', 'Item Total'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(99.99),
            ],
            'itemSubtotal' => [
                'label' => Craft::t('commerce', 'Item Subtotal'),
                'placeholder' => '¤' . Craft::$app->getFormattingLocale()->getFormatter()->asDecimal(89.99),
            ],
            'orderSite' => [
                'label' => Craft::t('commerce', 'Order Site'),
                'placeholder' => $site->name,
            ],
            'reference' => [
                'label' => Craft::t('commerce', 'Reference'),
                'placeholder' => 'ORD-XXXXX',
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultCardAttributes(): array
    {
        return array_merge(parent::defineDefaultCardAttributes(), [
            'reference',
            'orderStatus',
            'totalPrice',
        ]);
    }
}
