<?php

namespace Craft;

class Market_VariantService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_VariantModel
	 */
	public function getById($id)
	{
		$variant = Market_VariantRecord::model()->with('product')->findById($id);
		$variantModel = Market_VariantModel::populateModel($variant);
		$variantModel->product = Market_ProductModel::populateModel($variant->product);
		return $variantModel;
	}

	/**
	 * @param int $id
	 */
	public function deleteById($id)
	{
		$this->unsetOptionValues($id);
		Market_VariantRecord::model()->deleteByPk($id);
	}

	/**
	 * Delete all variant-optionValue relations by variant id
	 *
	 * @param int $variantId
	 */
	public function unsetOptionValues($variantId)
	{
		Market_VariantOptionValueRecord::model()->deleteAllByAttributes(array('variantId' => $variantId));
	}

	/**
	 * @param int $productId
	 */
	public function disableAllByProductId($productId)
	{
		$variants = $this->getAllByProductId($productId);
		foreach ($variants as $variant) {
			$this->disableVariant($variant);
		}
	}

	/**
	 * @param int  $id
	 * @param bool $isMaster null / true / false. All by default
	 *
	 * @return Market_VariantModel[]
	 */
	public function getAllByProductId($id, $isMaster = NULL)
	{
		$conditions = array('productId' => $id);
		if (!is_null($isMaster)) {
			$conditions['isMaster'] = $isMaster;
		}

		$variants = Market_VariantRecord::model()->findAllByAttributes($conditions);

		return Market_VariantModel::populateModels($variants);
	}

	/**
	 * @param $variant
	 */
	public function disableVariant($variant)
	{
		$variant            = Market_ProductRecord::model()->findById($variant->id);
		$variant->deletedAt = DateTimeHelper::currentTimeForDb();
		$variant->saveAttributes(array('deletedAt'));
	}

	/**
	 * Save a model into DB
	 *
	 * @param Market_VariantModel $model
	 *
	 * @return bool
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_VariantModel $model)
	{
		if ($model->id) {
			$record = Market_VariantRecord::model()->findById($model->id);

			if (!$record) {
				throw new HttpException(404);
			}
		} else {
			$record = new Market_VariantRecord();
		}

		$record->isMaster  = $model->isMaster;
		$record->productId = $model->productId;
		$record->sku       = $model->sku;
		$record->price     = $model->price;
		$record->width     = $model->width;
		$record->height    = $model->height;
		$record->length    = $model->length;
		$record->weight    = $model->weight;

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors()) {
			// Save it!
			$record->save(false);

			// Now that we have a ID, save it on the model
			$model->id = $record->id;

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set option values to a variant
	 *
	 * @param int   $variantId
	 * @param int[] $optionValueIds
	 *
	 * @return bool
	 */
	public function setOptionValues($variantId, $optionValueIds)
	{
		$this->unsetOptionValues($variantId);

		if ($optionValueIds) {
			if (!is_array($optionValueIds)) {
				$optionValueIds = array($optionValueIds);
			}

			$values = array();
			foreach ($optionValueIds as $optionValueId) {
				$values[] = array($optionValueId, $variantId);
			}

			craft()->db->createCommand()->insertAll('market_variant_optionvalues', array('optionValueId', 'variantId'), $values);
		}

		return true;
	}
}