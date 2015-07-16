<?php
namespace Craft;

require_once(__DIR__ . '/Market_BaseElementType.php');

class Market_OrderElementType extends Market_BaseElementType
{

	public function getName()
	{
		return Craft::t('Orders');
	}

	public function hasContent()
	{
		return true;
	}

	public function hasTitles()
	{
		return false;
	}

	public function hasStatuses()
	{
		return false;
	}

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
				'label'    => craft::t("All") . ' ' .$orderType->name,
				'criteria' => ['typeId' => $orderType->id]
			];

			$key = 'orderType:' . $orderType->id . ':completedAt:null';

			$sources[$key] = [
				'label'    => Craft::t('Incomplete'),
				'criteria' => ['typeId' => $orderType->id, 'completedAt' => ":empty:"]
			];

			foreach ($orderType->orderStatuses as $status){
				$key = 'orderType:' . $orderType->id . ':orderStatus:' . $status->id;
				$sources[$key] = [
					'label'    => ucwords($status->name),
					'criteria' => ['typeId' => $orderType->id, 'orderStatus' => $status->id]
				];
			}
		}

		return $sources;

	}

	public function defineTableAttributes($source = NULL)
	{
		return [
			'number'     => Craft::t('Number'),
			'orderStatus'=> Craft::t('Status'),
			'finalPrice' => Craft::t('Total Payable'),
			'completedAt'=> Craft::t('Completed')
		];
	}

	public function defineSearchableAttributes()
	{
		return ['number'];
	}

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
				return "";
			}

		}

		return parent::getTableAttributeHtml($element, $attribute);
	}

	public function defineSortableAttributes()
	{
		return [
			'number'     => Craft::t('Number'),
			'completedAt'     => Craft::t('Completed At'),
			'finalPrice' => Craft::t('Total Payable'),
			'orderStatusId' => Craft::t('Order Status'),
		];
	}


	public function defineCriteriaAttributes()
	{
		return [
			'typeId' => AttributeType::Mixed,
			'type'   => AttributeType::Mixed,
			'number' => AttributeType::Mixed,
			'completedAt'  => AttributeType::Mixed,
			'orderStatus'  => AttributeType::Mixed,
			'orderStatusId'  => AttributeType::Mixed,
			'customer'  => AttributeType::Mixed,
			'customerId'  => AttributeType::Mixed
		];
	}


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
				orders.typeId')
			->join('market_orders orders', 'orders.id = elements.id')
			->join('market_ordertypes ordertypes', 'ordertypes.id = orders.typeId');

		if ($criteria->type) {
			if ($criteria->type instanceof Market_OrderTypeModel) {
				$criteria->typeId = $criteria->type->id;
				$criteria->type   = NULL;
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
				$criteria->orderStatus   = NULL;
			}else{
				$query->andWhere(DbHelper::parseParam('orders.orderStatusId', $criteria->orderStatus, $query->params));
			}
		}

		if ($criteria->orderStatusId){
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
	}


	public function populateElementModel($row)
	{
		return Market_OrderModel::populateModel($row);
	}

}
