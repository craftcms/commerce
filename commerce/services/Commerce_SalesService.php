<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Sale service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_SalesService extends BaseApplicationComponent
{

	private $_allSales;

    private $_allActiveSales;

    /**
     * @param int $id
     *
     * @return Commerce_SaleModel|null
     */
    public function getSaleById($id)
    {
		foreach ($this->getAllSales() as $sale){
			if ($sale->id == $id){
				return $sale;
			}
		}

        return null;
    }

    /**
     * Getting all sales applicable for the current user and given product
     *
     * @param Commerce_ProductModel $product
     *
     * @return Commerce_SaleModel[]
     */
    public function getForProduct(Commerce_ProductModel $product)
    {
	    $matchedSales = [];
	    foreach($this->_getAllActiveSales() as $sale)
	    {
		    if ($this->matchProductAndSale($product, $sale))
		    {
			    $matchedSales[] = $sale;
		    }
	    }

	    return $matchedSales;
    }

    /**
     * @return Commerce_SaleModel[]
     */
    public function getAllSales()
    {
	    if (!isset($this->_allSales))
	    {
		    $sales = craft()->db->createCommand()
			    ->select('sales.id,
			    sales.name,
				sales.description,
				sales.dateFrom,
				sales.dateTo,
				sales.discountType,
				sales.discountAmount,
				sales.allGroups,
				sales.allProducts,
				sales.allProductTypes,
				sales.enabled,
				sp.productId,
				spt.productTypeId,
				sug.userGroupId')
			    ->from('commerce_sales sales')
			    ->leftJoin('commerce_sale_products sp','sp.saleId=sales.id')
			    ->leftJoin('commerce_sale_producttypes spt','spt.saleId=sales.id')
			    ->leftJoin('commerce_sale_usergroups sug','sug.saleId=sales.id')
		        ->queryAll();

			$allSalesById = [];
		    $products = [];
		    $productTypes = [];
		    $groups = [];
		    foreach ($sales as $sale)
		    {
			    $id = $sale['id'];
			    if(!isset($allSalesById[$id])){
				    $allSalesById[$id] = Commerce_SaleModel::populateModel($sale);
			    }

			    if($sale['productId'])
			    {
				    $products[$id][] = $sale['productId'];
				}

			    if($sale['productTypeId'])
			    {
				    $productTypes[$id][] = $sale['productTypeId'];
			    }

			    if($sale['userGroupId'])
			    {
				    $groups[$id][] = $sale['userGroupId'];
			    }
		    }

		    foreach($allSalesById as $id => $sale)
		    {
			    $sale->productIds= isset($product[$id]) ? $product[$id] : [];
			    $sale->productTypeIds = isset($productTypes[$id]) ? $productTypes[$id] : [];
			    $sale->groupIds = isset($groups[$id]) ? $groups[$id] : [];
		    }
			$this->_allSales = array_values($allSalesById);
	    }

	    return $this->_allSales;
    }

    /**
     * @param Commerce_VariantModel $variant
     *
     * @return Commerce_SaleModel[]
     */
    public function getSalesForVariant(Commerce_VariantModel $variant)
    {
	    $matchedSales = [];
	    foreach($this->_getAllActiveSales() as $sale)
	    {
		    if ($this->matchProductAndSale($variant->product, $sale))
		    {
			    $matchedSales[] = $sale;
		    }
	    }

	    return $matchedSales;
    }

    /**
     * @param Commerce_ProductModel $product
     * @param Commerce_SaleModel $sale
     *
     * @return bool
     */
    public function matchProductAndSale(Commerce_ProductModel $product, Commerce_SaleModel $sale)
    {
	    // Product ID match
        if (!$sale->allProducts && !in_array($product->id, $sale->getProductIds()))
        {
            return false;
        }

	    // Product Type match
        if (!$sale->allProductTypes && !in_array($product->typeId, $sale->getProductTypeIds()))
        {
            return false;
        }

	    // User Group match
        $userGroups = craft()->commerce_discounts->getCurrentUserGroupIds();
        if (!$sale->allGroups && !array_intersect($userGroups, $sale->getGroupIds()))
        {
            return false;
        }

        return true;
    }

    /**
     * @param Commerce_SaleModel $model
     * @param array $groups ids
     * @param array $productTypes ids
     * @param array $products ids
     *
     * @return bool
     * @throws \Exception
     */
    public function saveSale(
        Commerce_SaleModel $model,
        array $groups,
        array $productTypes,
        array $products
    )
    {
        if ($model->id) {
            $record = Commerce_SaleRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No sale exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_SaleRecord();
        }

        $fields = [
            'id',
            'name',
            'description',
            'dateFrom',
            'dateTo',
            'discountType',
            'discountAmount',
            'enabled'
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->allGroups = $model->allGroups = empty($groups);
        $record->allProductTypes = $model->allProductTypes = empty($productTypes);
        $record->allProducts = $model->allProducts = empty($products);

        $record->validate();
        $model->addErrors($record->getErrors());

        CommerceDbHelper::beginStackedTransaction();
        try {
            if (!$model->hasErrors()) {
                $record->save(false);
                $model->id = $record->id;

                Commerce_SaleUserGroupRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);
                Commerce_SaleProductRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);
                Commerce_SaleProductTypeRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);

                foreach ($groups as $groupId) {
                    $relation = new Commerce_SaleUserGroupRecord;
                    $relation->attributes = [
                        'userGroupId' => $groupId,
                        'saleId' => $model->id
                    ];
                    $relation->insert();
                }

                foreach ($productTypes as $productTypeId) {
                    $relation = new Commerce_SaleProductTypeRecord;
                    $relation->attributes = [
                        'productTypeId' => $productTypeId,
                        'saleId' => $model->id
                    ];
                    $relation->insert();
                }

                foreach ($products as $productId) {
                    $relation = new Commerce_SaleProductRecord;
                    $relation->attributes = [
                        'productId' => $productId,
                        'saleId' => $model->id
                    ];
                    $relation->insert();
                }

                CommerceDbHelper::commitStackedTransaction();

                return true;
            }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        CommerceDbHelper::rollbackStackedTransaction();

        return false;
    }

    /**
     * @param int $id
     */
    public function deleteSaleById($id)
    {
        Commerce_SaleRecord::model()->deleteByPk($id);
    }

    private function _getAllActiveSales()
    {
        if (!isset($this->_allActiveSales))
        {
	        $sales = $this->getAllSales();
	        $activeSales = [];
	        foreach ($sales as $sale){
		        if ($sale->enabled)
		        {
			        if ($sale->dateFrom == null || $sale->dateFrom <= DateTimeHelper::currentTimeForDb())
			        {
				        if ($sale->dateTo == null || $sale->dateTo >= DateTimeHelper::currentTimeForDb()) {
					        $activeSales[] = $sale;
				        }
			        }
		        }
	        }

            $this->_allActiveSales = $activeSales;
        }

        return $this->_allActiveSales;
    }
}
