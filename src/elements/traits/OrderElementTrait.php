<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\elements\actions\CopyLoadCartUrl;
use craft\commerce\elements\actions\DownloadOrderPdfAction;
use craft\commerce\elements\actions\UpdateOrderStatus;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\exports\Expanded;
use craft\commerce\Plugin;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\elements\exporters\Expanded as CraftExpanded;
use craft\helpers\ArrayHelper;
use craft\models\FieldLayout;
use Exception;

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
        $attributes['data-number'] = $this->number;
        return $attributes;
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
                return $this->getShippingAddress() ? $this->getShippingAddress()->givenName ?? '' : '';
            }
            case 'shippingLastName':
            {
                return $this->getShippingAddress() ? $this->getShippingAddress()->familyName ?? '' : '';
            }
            case 'billingFullName':
            {
                return $this->getBillingAddress() ? $this->getBillingAddress()->fullName ?? '' : '';
            }
            case 'billingFirstName':
            {
                return $this->getBillingAddress() ? $this->getBillingAddress()->givenName ?? '' : '';
            }
            case 'billingLastName':
            {
                return $this->getBillingAddress() ? $this->getBillingAddress()->familyName ?? '' : '';
            }
            case 'shippingBusinessName':
            {
                return $this->getShippingAddress()->organization ?? '';
            }
            case 'billingBusinessName':
            {
                return $this->getBillingAddress()->organization ?? '';
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

                if ($this->itemSubtotal > 0) {
                    $miniTable[] = [
                        'label' => Craft::t('commerce', 'Items'),
                        'value' => $this->itemSubtotalAsCurrency,
                    ];
                }

                if ($this->storedTotalDiscount > 0) {
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
            'billingPhone',
            'billingAddressLines',
            'email',
            'number',
            'shippingFirstName',
            'shippingLastName',
            'shippingFullName',
            'shippingPhone',
            'shippingAddressLines',
            'shortNumber',
            'transactionReference',
            'username',
            'reference',
            'skus',
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
                return $this->billingAddress->givenName ?? '';
            case 'billingLastName':
                return $this->billingAddress->familyName ?? '';
            case 'billingFullName':
                return $this->billingAddress->fullName ?? '';
            case 'billingPhone':
                return $this->billingAddress->phone ?? '';
            case 'billingAddressLines':
                $address = $this->getBillingAddress();
                $addressLines = $address ? $address->getAddressLines(true) : [];
                return implode(' ', $addressLines);
            case 'shippingFirstName':
                return $this->shippingAddress->givenName ?? '';
            case 'shippingLastName':
                return $this->shippingAddress->familyName ?? '';
            case 'shippingFullName':
                return $this->shippingAddress->fullName ?? '';
            case 'shippingPhone':
                return $this->shippingAddress->phone ?? '';
            case 'shippingAddressLines':
                $address = $this->getShippingAddress();
                $addressLines = $address ? $address->getAddressLines(true) : [];
                return implode(' ', $addressLines);
            case 'transactionReference':
                return implode(' ', ArrayHelper::getColumn($this->getTransactions(), 'reference'));
            case 'username':
                return $this->getUser()->username ?? '';
            case 'skus':
                return implode(' ', ArrayHelper::getColumn($this->getLineItems(), 'sku'));
            default:
                return parent::getSearchKeywords($attribute);
        }
    }


    /**
     * @inheritdoc
     * @throws Exception
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            '*' => [
                'key' => '*',
                'label' => Craft::t('commerce', 'All Orders'),
                'criteria' => ['isCompleted' => true],
                'defaultSort' => ['dateOrdered', 'desc'],
                'badgeCount' => 0,
                'data' => [
                    'date-attr' => 'dateOrdered',
                ],
            ],
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Order Status')];

        foreach (Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $orderStatus) {
            $key = 'orderStatus:' . $orderStatus->handle;
            $criteriaStatus = ['orderStatusId' => $orderStatus->id];

            $sources[] = [
                'key' => $key,
                'status' => $orderStatus->color,
                'label' => Craft::t('site', $orderStatus->name),
                'criteria' => $criteriaStatus,
                'defaultSort' => ['dateOrdered', 'desc'],
                'badgeCount' => 0,
                'data' => [
                    'handle' => $orderStatus->handle,
                    'date-attr' => 'dateOrdered',
                ],
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
                'handle' => 'cartsActive',
                'date-attr' => 'dateUpdated',
            ],
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
                'handle' => 'cartsInactive',
                'date-attr' => 'dateUpdated',
            ],
        ];

        $criteriaAttemptedPayment = ['hasTransactions' => true, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:attempted-payment',
            'label' => Craft::t('commerce', 'Attempted Payments'),
            'criteria' => $criteriaAttemptedPayment,
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
    protected static function defineActions(string $source = null): array
    {
        $actions = parent::defineActions($source);

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $elementService = Craft::$app->getElements();

            if (Plugin::getInstance()->getPdfs()->getHasEnabledPdf()) {
                $actions[] = DownloadOrderPdfAction::class;
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
        ArrayHelper::append($default, Expanded::class);

        return $default;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'reference' => ['label' => Craft::t('commerce', 'Reference')],
            'shortNumber' => ['label' => Craft::t('commerce', 'Short Number')],
            'number' => ['label' => Craft::t('commerce', 'Number')],
            'id' => ['label' => Craft::t('commerce', 'ID')],
            'orderStatus' => ['label' => Craft::t('commerce', 'Status')],
            'totals' => ['label' => Craft::t('commerce', 'All Totals')],
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
            'shippingBusinessName' => ['label' => Craft::t('commerce', 'Shipping Business Name')],
            'billingBusinessName' => ['label' => Craft::t('commerce', 'Billing Business Name')],
            'shippingMethodName' => ['label' => Craft::t('commerce', 'Shipping Method')],
            'gatewayName' => ['label' => Craft::t('commerce', 'Gateway')],
            'paidStatus' => ['label' => Craft::t('commerce', 'Paid Status')],
            'couponCode' => ['label' => Craft::t('commerce', 'Coupon Code')],
            'itemTotal' => ['label' => Craft::t('commerce', 'Item Total')],
            'itemSubtotal' => ['label' => Craft::t('commerce', 'Item Subtotal')],
            'orderSite' => ['label' => Craft::t('commerce', 'Order Site')],
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
            $attributes[] = 'dateOrdered';
            $attributes[] = 'datePaid';
            $attributes[] = 'totalPaid';
            $attributes[] = 'paidStatus';
            $attributes[] = 'totals';
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
    public static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        /** @var OrderQuery $elementQuery */

        switch ($attribute) {
            case 'totals':
            case 'total':
            case 'totalPrice':
            case 'totalDiscount':
            case 'totalShippingCost':
            case 'totalTax':
            case 'totalIncludedTax':
                $elementQuery->withAdjustments();
                break;
            case 'totalPaid':
            case 'paidStatus':
                $elementQuery->withTransactions();
                break;
            case 'shippingFullName':
            case 'shippingFirstName':
            case 'shippingLastName':
            case 'billingFullName':
            case 'billingFirstName':
            case 'billingLastName':
            case 'shippingBusinessName':
            case 'billingBusinessName':
            case 'shippingMethodName':
                $elementQuery->withAddresses();
                break;
            case 'email':
            case 'customer':
                $elementQuery->withCustomer();
                break;
            case 'itemTotal':
            case 'itemSubtotal':
                $elementQuery->withLineItems();
                break;
            default:
                parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
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
                'orderBy' => 'shipping_address.givenName',
                'attribute' => 'shippingFirstName',
            ],
            [
                'label' => Craft::t('commerce', 'Shipping Last Name'),
                'orderBy' => 'shipping_address.familyName',
                'attribute' => 'shippingLastName',
            ],
            [
                'label' => Craft::t('commerce', 'Shipping Full Name'),
                'orderBy' => 'shipping_address.fullName',
                'attribute' => 'shippingFullName',
            ],
            [
                'label' => Craft::t('commerce', 'Billing First Name'),
                'orderBy' => 'billing_address.givenName',
                'attribute' => 'billingFirstName',
            ],
            [
                'label' => Craft::t('commerce', 'Billing Last Name'),
                'orderBy' => 'billing_address.familyName',
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
     *
     * @return string
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
}
