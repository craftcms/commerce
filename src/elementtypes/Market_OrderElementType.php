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
                'label' => Craft::t('All Orders'),
                'criteria' => ['completed' => true]
            ]
        ];

        $sources[] = ['heading' => Craft::t("Order Status")];

        foreach (craft()->market_orderStatus->getAll() as $orderStatus) {
            $key = 'orderStatus:' . $orderStatus->handle;
            $sources[$key] = [
                'statusColor' => $orderStatus->color,
                'label' => $orderStatus->name,
                'criteria' => ['orderStatus' => $orderStatus]
            ];
        }


        $sources[] = ['heading' => Craft::t("Carts")];

        $edge             = new DateTime();
        $interval         = new DateInterval("PT1H");
        $interval->invert = 1;
        $edge->add($interval);

        $sources['carts:active'] = [
            'label' => Craft::t('Active Carts'),
            'criteria' => ['updatedAfter'=>$edge,'completedAt' => ":empty:"]
        ];

        $sources['carts:inactive'] = [
            'label' => Craft::t('Inactive Carts'),
            'criteria' => ['updatedBefore'=>$edge,'completedAt' => ":empty:"]
        ];

        return $sources;

    }

    /**
     * @param null $source
     * @return array
     */
	public function defineTableAttributes($source = NULL)
	{
        if (explode(':',$source)[0] == 'carts'){
            return [
                'number'     => Craft::t('Number'),
                'dateUpdated'=> Craft::t('Last Updated'),
                'finalPrice' => Craft::t('Total')
            ];
        }
		return [
			'number'     => Craft::t('Number'),
			'orderStatus'=> Craft::t('Status'),
			'finalPrice' => Craft::t('Total Payable'),
			'completedAt'=> Craft::t('Completed'),
			'paidAt' => Craft::t('Paid')
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
			if ($element->orderStatus){
				return $element->orderStatus->printName();
			}else{
				return sprintf('<span class="market status %s"></span> %s','', '');
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
            'number' => AttributeType::Mixed,
            'completedAt' => AttributeType::Mixed,
            'updatedOn' => AttributeType::Mixed,
            'updatedAfter' => AttributeType::Mixed,
            'updatedBefore' => AttributeType::Mixed,
            'orderStatus' => AttributeType::Mixed,
            'orderStatusId' => AttributeType::Mixed,
            'completed' => AttributeType::Bool,
            'customer'  => AttributeType::Mixed,
            'customerId'  => AttributeType::Mixed
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
        orders.number,
        orders.couponCode,
        orders.itemTotal,
        orders.baseDiscount,
        orders.baseShippingRate,
        orders.finalPrice,
        orders.paidTotal,
        orders.orderStatusId,
        orders.completedAt,
        orders.email,
        orders.completedAt,
        orders.paidAt,
        orders.currency,
        orders.lastIp,
        orders.message,
        orders.returnUrl,
        orders.cancelUrl,
        orders.orderStatusId,
        orders.billingAddressId,
        orders.billingAddressData,
        orders.shippingAddressId,
        orders.shippingAddressData,
        orders.shippingMethodId,
        orders.paymentMethodId,
        orders.customerId,
        orders.dateUpdated')
            ->join('market_orders orders', 'orders.id = elements.id');

        if ($criteria->completed) {
            if ($criteria->completed == true) {
                $query->andWhere('orders.completedAt is not null');
                $criteria->completed = null;
            }
        }

        if ($criteria->completedAt) {
            $query->andWhere(DbHelper::parseParam('orders.completedAt', $criteria->completedAt, $query->params));
        }

        if ($criteria->number) {
            $query->andWhere(DbHelper::parseParam('orders.number', $criteria->number, $query->params));
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

        if($criteria->customer) {
          if ($criteria->customer instanceof Market_CustomerModel) {
            $criteria->customerId = $criteria->customer->id;
            $criteria->customer = null;
          }
        }

        if($criteria->customerId){
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
