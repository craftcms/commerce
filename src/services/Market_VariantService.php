<?php

namespace Craft;

class Market_VariantService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @return Market_VariantModel
	 */
	public function getById($id)
	{
		$variant = Market_VariantRecord::model()->with('product')->findById($id);

		return Market_VariantModel::populateModel($variant);
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
	 * @param int $id
	 */
	public function unsetOptionValues($id)
	{
		Market_VariantOptionValueRecord::model()->deleteAllByAttributes(['variantId' => $id]);
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
		$conditions = ['productId' => $id];
		if (!is_null($isMaster)) {
			$conditions['isMaster'] = $isMaster;
		}

		$variants = Market_VariantRecord::model()->with('product')->findAllByAttributes($conditions);

		return Market_VariantModel::populateModels($variants);
	}

	/**
	 * @param $variant
	 */
	public function disableVariant($variant)
	{
		$variant            = Market_ProductRecord::model()->findById($variant->id);
		$variant->deletedAt = DateTimeHelper::currentTimeForDb();
		$variant->saveAttributes(['deletedAt']);
	}

    /**
     * Apply sales, associated with the given product, to all given variants
     * @param Market_VariantModel[] $variants
     * @param Market_ProductModel   $product
     */
    public function applySales(array $variants, Market_ProductModel $product)
    {
        $sales  = craft()->market_sale->getForProduct($product);

        foreach ($sales as $sale) {
            foreach ($variants as $variant) {
                $variant->salePrice = $variant->price + $sale->calculateTakeoff($variant->price);
                if ($variant->salePrice < 0) {
                    $variant->salePrice = 0;
                }
            }
        }
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
		$record->minQty    = $model->minQty;

		if ($model->unlimitedStock) {
			$record->unlimitedStock = true;
			$record->stock          = 0;
		}

		if (!$model->unlimitedStock) {
			$record->stock          = $model->stock ? $model->stock : 0;
			$record->unlimitedStock = false;
		}

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors()) {
			$record->save(false);
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
				$optionValueIds = [$optionValueIds];
			}

			$values = [];
			foreach ($optionValueIds as $optionValueId) {
				$values[] = [$optionValueId, $variantId];
			}

			craft()->db->createCommand()->insertAll('market_variant_optionvalues', ['optionValueId', 'variantId'], $values);
		}

		return true;
	}
}