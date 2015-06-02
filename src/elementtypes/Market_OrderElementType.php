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
			'finalPrice' => Craft::t('Total Payable'),
		];
	}


	public function defineCriteriaAttributes()
	{
		return [
			'typeId' => AttributeType::Mixed,
			'type'   => AttributeType::Mixed,
			'number' => AttributeType::Mixed,
			'orderStatus'  => AttributeType::Mixed,
			'orderStatusId'  => AttributeType::Mixed,
		];
	}


	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('orders.id, orders.typeId, orders.number, orders.finalPrice, orders.orderStatusId, orders.completedAt')
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
	}


	public function populateElementModel($row)
	{
		return Market_OrderModel::populateModel($row);
	}

} 