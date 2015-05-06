<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_DiscountService
 *
 * @package Craft
 */
class Market_DiscountService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_DiscountModel
	 */
	public function getById($id)
	{
		$record = Market_DiscountRecord::model()->findById($id);

		return Market_DiscountModel::populateModel($record);
	}

	/**
	 * Getting all discounts applicable for the current user and given items
	 * list
	 *
	 * @param Market_LineItemModel[] $lineItems
	 *
	 * @return Market_DiscountModel[]
	 */
	public function getForItems(array $lineItems)
	{
		//getting ids lists
		$productIds     = [];
		$productTypeIds = [];
		foreach ($lineItems as $item) {
			$productIds[]     = $item->variant->productId;
			$productTypeIds[] = $item->variant->product->typeId;
		}
		$productTypeIds = array_unique($productTypeIds);

		$groupIds = $this->getCurrentUserGroups();

		//building criteria
		$criteria        = new \CDbCriteria();
		$criteria->group = 't.id';
		$criteria->addCondition('t.enabled = 1');
		$criteria->addCondition('t.dateFrom IS NULL OR t.dateFrom <= NOW()');
		$criteria->addCondition('t.dateTo IS NULL OR t.dateTo >= NOW()');

		$criteria->join = 'LEFT JOIN {{' . Market_DiscountProductRecord::model()->getTableName() . '}} dp ON dp.discountId = t.id ';
		$criteria->join .= 'LEFT JOIN {{' . Market_DiscountProductTypeRecord::model()->getTableName() . '}} dpt ON dpt.discountId = t.id ';
		$criteria->join .= 'LEFT JOIN {{' . Market_DiscountUserGroupRecord::model()->getTableName() . '}} dug ON dug.discountId = t.id ';

		if ($productIds) {
			$list = implode(',', $productIds);
			$criteria->addCondition("dp.productId IN ($list) OR t.allProducts = 1");
		} else {
			$criteria->addCondition("t.allProducts = 1");
		}

		if ($productTypeIds) {
			$list = implode(',', $productTypeIds);
			$criteria->addCondition("dpt.productTypeId IN ($list) OR t.allProductTypes = 1");
		} else {
			$criteria->addCondition("t.allProductTypes = 1");
		}

		if ($groupIds) {
			$list = implode(',', $groupIds);
			$criteria->addCondition("dug.userGroupId IN ($list) OR t.allGroups = 1");
		} else {
			$criteria->addCondition("t.allGroups = 1");
		}

		//searching
		return $this->getAll($criteria);
	}

	/**
	 * @return array
	 */
	public function getCurrentUserGroups()
	{
		$groupIds = [];
		$user     = craft()->userSession->getUser();
		if ($user) {
			foreach ($user->getGroups() as $group) {
				$groupIds[] = $group->id;
			}

			return $groupIds;
		}

		return $groupIds;
	}

	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Market_DiscountModel[]
	 */
	public function getAll($criteria = [])
	{
		$records = Market_DiscountRecord::model()->findAll($criteria);

		return Market_DiscountModel::populateModels($records);
	}

	/**
	 * Get discount by code and check it's active and applies to the current user
	 *
	 * @param int    $code
	 * @param string $error
	 *
	 * @return true
	 */
	public function checkCode($code, $customerId, &$error = '')
	{
		$model = $this->getByCode($code);
		if (!$model->id) {
			$error = 'Given coupon code not found';

			return false;
		}

		if (!$model->enabled) {
			$error = 'Discount is not active';

			return false;
		}

		if($model->totalUseLimit > 0 && $model->totalUses >= $model->totalUseLimit) {
			$error = 'Discount is out of limit';

			return false;
		}

		$now = new DateTime();
		if ($model->dateFrom && $model > $now || $model->dateTo && $model->dateTo < $now) {
			$error = 'Discount is out of date';

			return false;
		}

		$groupIds = $this->getCurrentUserGroups();
		if (!$model->allGroups && !array_intersect($groupIds, $model->getGroupsIds())) {
			$error = 'Discount is not allowed for the current user';

			return false;
		}

		if($customerId) {
			$uses = Market_CustomerDiscountUseRecord::model()->findByAttributes(['customerId' => $customerId, 'discountId' => $model->id]);
			if($uses && $uses->uses >= $model->perUserLimit) {
				$error = 'You can not use this discount anymore';

				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $code
	 *
	 * @return Market_DiscountModel
	 */
	public function getByCode($code)
	{
		$record = Market_DiscountRecord::model()->findByAttributes(['code' => $code]);

		return Market_DiscountModel::populateModel($record);
	}

	/**
	 * @param Market_LineItemModel $lineItem
	 * @param Market_DiscountModel $discount
	 *
	 * @return bool
	 */
	public function matchLineItem(Market_LineItemModel $lineItem, Market_DiscountModel $discount)
	{
		if ($lineItem->underSale && $discount->excludeOnSale) {
			return false;
		}

		$productId = $lineItem->variant->productId;
		if (!$discount->allProducts && !in_array($productId, $discount->getProductsIds())) {
			return false;
		}

		$productTypeId = $lineItem->variant->product->typeId;
		if (!$discount->allProductTypes && !in_array($productTypeId, $discount->getProductTypesIds())) {
			return false;
		}

		$userGroups = $this->getCurrentUserGroups();
		if (!$discount->allGroups && !array_intersect($userGroups, $discount->getGroupsIds())) {
			return false;
		}

		return true;
	}

	/**
	 * @param Market_DiscountModel $model
	 * @param array                $groups       ids
	 * @param array                $productTypes ids
	 * @param array                $products     ids
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save(Market_DiscountModel $model, array $groups, array $productTypes, array $products)
	{
		if ($model->id) {
			$record = Market_DiscountRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No discount exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_DiscountRecord();
		}

		$fields = ['id', 'name', 'description', 'dateFrom', 'dateTo', 'enabled', 'purchaseTotal', 'purchaseQty', 'baseDiscount', 'perItemDiscount',
			'percentDiscount', 'freeShipping', 'excludeOnSale', 'code', 'perUserLimit', 'totalUseLimit'];
		foreach ($fields as $field) {
			$record->$field = $model->$field;
		}

		$record->allGroups       = $model->allGroups = empty($groups);
		$record->allProductTypes = $model->allProductTypes = empty($productTypes);
		$record->allProducts     = $model->allProducts = empty($products);

		$record->validate();
		$model->addErrors($record->getErrors());

		MarketDbHelper::beginStackedTransaction();
		try {
			if (!$model->hasErrors()) {
				$record->save(false);
				$model->id = $record->id;

				Market_DiscountUserGroupRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);
				Market_DiscountProductRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);
				Market_DiscountProductTypeRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);

				foreach ($groups as $groupId) {
					$relation             = new Market_DiscountUserGroupRecord;
					$relation->attributes = ['userGroupId' => $groupId, 'discountId' => $model->id];
					$relation->insert();
				}

				foreach ($productTypes as $productTypeId) {
					$relation             = new Market_DiscountProductTypeRecord;
					$relation->attributes = ['productTypeId' => $productTypeId, 'discountId' => $model->id];
					$relation->insert();
				}

				foreach ($products as $productId) {
					$relation             = new Market_DiscountProductRecord;
					$relation->attributes = ['productId' => $productId, 'discountId' => $model->id];
					$relation->insert();
				}

				MarketDbHelper::commitStackedTransaction();

				return true;
			}
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::rollbackStackedTransaction();

		return false;
	}

	/**
	 * @param int $id
	 */
	public function deleteById($id)
	{
		Market_DiscountRecord::model()->deleteByPk($id);
	}

	/**
	 * Update discount uses counters
	 * @param Event $event
	 */
	public function orderCompleteHandler(Event $event) {
		/** @var Market_OrderModel $order */
		$order = $event->params['order'];

		if(!$order->couponCode) {
			return;
		}

		/** @var Market_DiscountRecord $record */
		$record = Market_DiscountRecord::model()->findByAttributes(['code' => $order->couponCode]);
		if (!$record || !$record->id) {
			return;
		}

		if($record->totalUseLimit) {
			$record->saveCounters(['totalUses' => 1]);
		}

		if($record->perUserLimit && $order->customerId) {
			$table = Market_CustomerDiscountUseRecord::model()->getTableName();
			craft()->db->createCommand("
                INSERT INTO {{" . $table . "}} (customerId, discountId, uses)
                VALUES (:cid, :did, 1)
                ON DUPLICATE KEY UPDATE uses = uses + 1
            ")->execute(['cid' => $order->customerId, 'did' => $record->id]);
		}
	}

}