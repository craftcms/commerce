<?php
namespace Craft;

require_once(__DIR__ . '/Commerce_BaseElementType.php');

/**
 * Class Commerce_OrderElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementtypes
 * @since     1.0
 */
class Commerce_OrderElementType extends Commerce_BaseElementType
{

    /**
     * @return null|string
     */
    public function getName()
    {
        return Craft::t('Orders');
    }

    /**
     * @return bool
     */
    public function hasContent()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function hasTitles()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function hasStatuses()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isLocalized()
    {
        return false;
    }

    /**
     * @param null $source
     *
     * @return array
     */
    public function getAvailableActions($source = null)
    {
        $actions = [];

        if (craft()->userSession->checkPermission('commerce-manageOrders'))
        {
            $deleteAction = craft()->elements->getAction('Delete');
            $deleteAction->setParams([
                'confirmationMessage' => Craft::t('Are you sure you want to delete the selected orders?'),
                'successMessage' => Craft::t('Orders deleted.'),
            ]);
            $actions[] = $deleteAction;

            // Only allow mass updating order status when all selected are of the same status, and not carts.
            $isStatus = strpos($source, 'orderStatus:');
            if ($isStatus === 0) {
                $updateOrderStatusAction = craft()->elements->getAction('Commerce_UpdateOrderStatus');
                $actions[] = $updateOrderStatusAction;
            }
        }

        // Allow plugins to add additional actions
        $allPluginActions = craft()->plugins->call('commerce_addOrderActions', [$source], true);

        foreach ($allPluginActions as $pluginActions) {
            $actions = array_merge($actions, $pluginActions);
        }

        return $actions;
    }

    /**
     * @param null $context
     *
     * @return array
     */
    public function getSources($context = null)
    {
        $sources = [
            '*' => [
                'label' => Craft::t('All Orders'),
                'criteria' => ['completed' => true]
            ]
        ];

        $sources[] = ['heading' => Craft::t("Order Status")];

        foreach (craft()->commerce_orderStatuses->getAllOrderStatuses() as $orderStatus) {
            $key = 'orderStatus:' . $orderStatus->handle;
            $sources[$key] = [
                'status' => $orderStatus->color,
                'label' => $orderStatus->name,
                'criteria' => ['orderStatus' => $orderStatus]
            ];
        }


        $sources[] = ['heading' => Craft::t("Carts")];

        $edge = new DateTime();
        $interval = new DateInterval("PT1H");
        $interval->invert = 1;
        $edge->add($interval);

        $sources['carts:active'] = [
            'label' => Craft::t('Active Carts'),
            'criteria' => ['updatedAfter' => $edge, 'dateOrdered' => ":empty:"]
        ];

        $sources['carts:inactive'] = [
            'label' => Craft::t('Inactive Carts'),
            'criteria' => ['updatedBefore' => $edge, 'dateOrdered' => ":empty:"]
        ];

        // Allow plugins to modify the sources
        craft()->plugins->call('commerce_modifyOrderSources', [&$sources, $context]);

        return $sources;
    }

    /**
     * @return array
     */
    public function defineAvailableTableAttributes()
    {
        $attributes = [
            'number' => ['label' => Craft::t('Number')],
            'id' => ['label' => Craft::t('ID')],
            'orderStatus' => ['label' => Craft::t('Status')],
            'totalPrice' => ['label' => Craft::t('Total')],
            'totalPaid' => ['label' => Craft::t('Total Paid')],
            'dateOrdered' => ['label' => Craft::t('Date Ordered')],
            'datePaid' => ['label' => Craft::t('Date Paid')],
            'dateCreated' => ['label' => Craft::t('Date Created')],
            'dateUpdated' => ['label' => Craft::t('Date Updated')],
            'email' => ['label' => Craft::t('Email')],
        ];

        // Allow plugins to modify the attributes
        $pluginAttributes = craft()->plugins->call('commerce_defineAdditionalOrderTableAttributes', array(), true);

        foreach ($pluginAttributes as $thisPluginAttributes)
        {
            $attributes = array_merge($attributes, $thisPluginAttributes);
        }

        return $attributes;
    }

    /**
     * @param string|null $source
     *
     * @return array
     */
    public function getDefaultTableAttributes($source = null)
    {
        $attributes = ['number'];

        if (strncmp($source, 'carts:', 6) !== 0) {
            $attributes[] = 'orderStatus';
            $attributes[] = 'totalPrice';
            $attributes[] = 'dateOrdered';
            $attributes[] = 'totalPaid';
            $attributes[] = 'datePaid';
        } else {
            $attributes[] = 'dateUpdated';
            $attributes[] = 'totalPrice';
        }

        return $attributes;
    }

    /**
     * @return array
     */
    public function defineSearchableAttributes()
    {
        return ['number', 'email'];
    }

    /**
     * @param BaseElementModel $element
     * @param string $attribute
     *
     * @return mixed|string
     */
    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        // First give plugins a chance to set this
        $pluginAttributeHtml = craft()->plugins->callFirst('commerce_getOrderTableAttributeHtml', [$element, $attribute], true);

        if ($pluginAttributeHtml !== null) {
            return $pluginAttributeHtml;
        }

        switch ($attribute) {
            case 'orderStatus': {
                if ($element->orderStatus) {
                    return $element->orderStatus->htmlLabel();
                } else {
                    return '<span class="status"></span>';
                }
            }
            case 'totalPaid':
            case 'totalPrice': {
                $currency = craft()->commerce_settings->getOption('defaultCurrency');

                return craft()->numberFormatter->formatCurrency($element->$attribute, strtoupper($currency));
            }
            default: {
                return parent::getTableAttributeHtml($element, $attribute);
            }
        }
    }

    /**
     * @return array
     */
    public function defineSortableAttributes()
    {
        $attributes = [
            'number' => Craft::t('Number'),
            'id' => Craft::t('ID'),
            'orderStatusId' => Craft::t('Order Status'),
            'dateOrdered' => Craft::t('Date Ordered'),
            'totalPrice' => Craft::t('Total Payable'),
            'totalPaid' => Craft::t('Total Paid'),
            'datePaid' => Craft::t('Date Paid')
        ];

        // Allow plugins to modify the attributes
        craft()->plugins->call('commerce_modifyOrderSortableAttributes', [&$attributes]);

        return $attributes;
    }


    /**
     * @return array
     */
    public function defineCriteriaAttributes()
    {
        return [
            'number' => AttributeType::Mixed,
            'email' => AttributeType::Mixed,
            'dateOrdered' => AttributeType::Mixed,
            'updatedOn' => AttributeType::Mixed,
            'updatedAfter' => AttributeType::Mixed,
            'updatedBefore' => AttributeType::Mixed,
            'orderStatus' => AttributeType::Mixed,
            'orderStatusId' => AttributeType::Mixed,
            'completed' => AttributeType::Bool,
            'customer' => AttributeType::Mixed,
            'customerId' => AttributeType::Mixed,
            'user' => AttributeType::Mixed,
            'isPaid' => AttributeType::Bool,
            'isUnpaid' => AttributeType::Bool
        ];
    }

    /**
     * @param DbCommand $query
     * @param ElementCriteriaModel $criteria
     *
     * @return bool|false|null|void
     */
    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {
        $query
            ->addSelect('orders.id,
        orders.number,
        orders.couponCode,
        orders.itemTotal,
        orders.baseDiscount,
        orders.baseShippingCost,
        orders.totalPrice,
        orders.totalPaid,
        orders.orderStatusId,
        orders.dateOrdered,
        orders.email,
        orders.dateOrdered,
        orders.datePaid,
        orders.currency,
        orders.lastIp,
        orders.message,
        orders.returnUrl,
        orders.cancelUrl,
        orders.orderStatusId,
        orders.billingAddressId,
        orders.shippingAddressId,
        orders.shippingMethod,
        orders.paymentMethodId,
        orders.customerId,
        orders.dateUpdated')
            ->join('commerce_orders orders', 'orders.id = elements.id');

        if ($criteria->completed) {
            if ($criteria->completed == true) {
                $query->andWhere('orders.dateOrdered is not null');
                $criteria->completed = null;
            }
        }

        if ($criteria->dateOrdered) {
            $query->andWhere(DbHelper::parseParam('orders.dateOrdered', $criteria->dateOrdered, $query->params));
        }

        if ($criteria->number) {
            $query->andWhere(DbHelper::parseParam('orders.number', $criteria->number, $query->params));
        }

        if ($criteria->email) {
            $query->andWhere(DbHelper::parseParam('orders.email', $criteria->email, $query->params));
        }

        if ($criteria->orderStatus) {
            if ($criteria->orderStatus instanceof Commerce_OrderStatusModel) {
                $criteria->orderStatusId = $criteria->orderStatus->id;
                $criteria->orderStatus = null;
            } else {
                $query->andWhere(DbHelper::parseParam('orders.orderStatusId', $criteria->orderStatus, $query->params));
            }
        }

        if ($criteria->orderStatusId) {
            $query->andWhere(DbHelper::parseParam('orders.orderStatusId', $criteria->orderStatusId, $query->params));
        }

        if ($criteria->user) {
            if ($criteria->user instanceof UserModel) {
                $customer = craft()->commerce_customers->getCustomerByUserId($criteria->user->id);
                if($customer){
                    $criteria->customerId = $customer->id;
                    $criteria->user = null;
                } else {
                    return false;
                }
            }
        }

        if ($criteria->customer) {
            if ($criteria->customer instanceof Commerce_CustomerModel) {
                if ($criteria->customer->id) {
                    $criteria->customerId = $criteria->customer->id;
                    $criteria->customer = null;
                } else {
                    return false;
                }
            }
        }

        if ($criteria->customerId) {
            $query->andWhere(DbHelper::parseParam('orders.customerId', $criteria->customerId, $query->params));
        }

        if ($criteria->updatedOn) {
            $query->andWhere(DbHelper::parseDateParam('orders.dateUpdated', $criteria->updatedOn, $query->params));
        } else {
            if ($criteria->updatedAfter) {
                $query->andWhere(DbHelper::parseDateParam('orders.dateUpdated', '>=' . $criteria->updatedAfter, $query->params));
            }

            if ($criteria->updatedBefore) {
                $query->andWhere(DbHelper::parseDateParam('orders.dateUpdated', '<' . $criteria->updatedBefore, $query->params));
            }
        }

        if ($criteria->isPaid == true) {
            $query->andWhere(DbHelper::parseParam('orders.totalPaid', '>= orders.totalPrice', $query->params));
        }

        if ($criteria->isUnpaid == true) {
            $query->andWhere(DbHelper::parseParam('orders.totalPaid', '< orders.totalPrice', $query->params));
        }
    }


    /**
     * Populate the Order.
     *
     * @param array $row
     *
     * @return BaseModel
     */
    public function populateElementModel($row)
    {
        return Commerce_OrderModel::populateModel($row);
    }

}
