<?php
namespace Craft;

require_once(__DIR__ . '/Market_BaseElementType.php');

class Market_OrderElementType extends Market_BaseElementType
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
     * @param null $context
     * @return array
     */
    public function getSources($context = NULL)
    {
        $sources = [
            '*' => [
                'label' => Craft::t('All orders'),
            ]
        ];

        foreach (craft()->market_orderType->getAll() as $orderType) {

            $sources[] = ['heading' => $orderType->name];

            $key = 'orderType:' . $orderType->id;
            $sources[$key] = [
                'label' => craft::t("All") . ' \'' . $orderType->name .'\'',
                'criteria' => ['typeId' => $orderType->id]
            ];

            $key = 'orderType:' . $orderType->id . ':completedAt:null';

            $sources[$key] = [
                'label' => Craft::t('Incomplete Carts'),
                'criteria' => ['typeId' => $orderType->id, 'completedAt' => ":empty:"]
            ];

            foreach ($orderType->orderStatuses as $status) {
                $key = 'orderType:' . $orderType->id . ':orderStatus:' . $status->id;
                $sources[$key] = [
                    'label' => ucwords($status->name),
                    'criteria' => ['typeId' => $orderType->id, 'orderStatus' => $status->id]
                ];
            }
        }

        return $sources;

    }

    /**
     * @param null $source
     * @return array
     */
    public function defineTableAttributes($source = NULL)
    {
        return [
            'number' => Craft::t('Number'),
            'orderStatus' => Craft::t('Status'),
            'finalPrice' => Craft::t('Total Payable'),
            'completedAt' => Craft::t('Completed')
        ];
    }

    public function defineSearchableAttributes()
    {
        return ['number'];
    }

    /**
     * @param BaseElementModel $element
     * @param string $attribute
     * @return mixed|string
     */
    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {

        if ($attribute == 'finalPrice') {
            $currency = craft()->market_settings->getOption('defaultCurrency');
            return craft()->numberFormatter->formatCurrency($element->finalPrice, strtoupper($currency));
        }

        if ($attribute == 'orderStatus') {
            if ($element->orderStatus) {
                return $element->orderStatus->printName();
            } else {
                return "";
            }

        }

        return parent::getTableAttributeHtml($element, $attribute);
    }

    /**
     * @return array
     */
    public function defineSortableAttributes()
    {
        return [
            'number' => Craft::t('Number'),
            'completedAt' => Craft::t('Completed At'),
            'finalPrice' => Craft::t('Total Payable'),
            'orderStatusId' => Craft::t('Order Status'),
        ];
    }


    /**
     * @return array
     */
    public function defineCriteriaAttributes()
    {
        return [
            'typeId' => AttributeType::Mixed,
            'type' => AttributeType::Mixed,
            'number' => AttributeType::Mixed,
            'completedAt' => AttributeType::Mixed,
            'orderStatus' => AttributeType::Mixed,
            'orderStatusId' => AttributeType::Mixed,
            'completed' => AttributeType::Bool,
        ];
    }

    /**
     * @param DbCommand $query
     * @param ElementCriteriaModel $criteria
     *
     * @return void
     */
    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {
        $query
            ->addSelect('orders.id,
                        orders.typeId,
                        orders.number,
                        orders.couponCode,
                        orders.itemTotal,
                        orders.finalPrice,
                        orders.baseDiscount,
                        orders.baseShippingRate,
                        orders.email,
                        orders.completedAt,
                        orders.lastIp,
                        orders.message,
                        orders.returnUrl,
                        orders.cancelUrl,
                        orders.billingAddressId,
                        orders.shippingAddressId,
                        orders.shippingMethodId,
                        orders.paymentMethodId,
                        orders.customerId,
                        orders.orderStatusId')
            ->join('market_orders orders', 'orders.id = elements.id')
            ->join('market_ordertypes ordertypes', 'ordertypes.id = orders.typeId');

        if ($criteria->completed) {
            if ($criteria->completed == true) {
                $query->andWhere('orders.completedAt is not null');
                $criteria->completed = null;
            }
        }

        if ($criteria->type) {
            if ($criteria->type instanceof Market_OrderTypeModel) {
                $criteria->typeId = $criteria->type->id;
                $criteria->type = NULL;
            } else {
                $query->andWhere(DbHelper::parseParam('ordertypes.handle', $criteria->type, $query->params));
            }
        }

        if ($criteria->typeId) {
            $query->andWhere(DbHelper::parseParam('orders.typeId', $criteria->typeId, $query->params));
        }

        if ($criteria->number) {
            $query->andWhere(DbHelper::parseParam('orders.number', $criteria->number, $query->params));
        }

        if ($criteria->completedAt) {
            $query->andWhere(DbHelper::parseParam('orders.completedAt', $criteria->completedAt, $query->params));
        }

        if ($criteria->orderStatus) {
            if ($criteria->orderStatus instanceof Market_OrderStatusModel) {
                $criteria->orderStatusId = $criteria->orderStatus->id;
                $criteria->orderStatus = NULL;
            } else {
                $query->andWhere(DbHelper::parseParam('orders.orderStatusId', $criteria->orderStatus, $query->params));
            }
        }

        if ($criteria->orderStatusId) {
            $query->andWhere(DbHelper::parseParam('orders.orderStatusId', $criteria->orderStatusId, $query->params));
        }
    }


    /**
     * Populate the Order.
     *
     * @param array $row
     * @return BaseModel
     */
    public function populateElementModel($row)
    {
        return Market_OrderModel::populateModel($row);
    }

} 