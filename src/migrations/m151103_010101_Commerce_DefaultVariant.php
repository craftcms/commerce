<?php
namespace Craft;

class m151103_010101_Commerce_DefaultVariant extends BaseMigration
{
    public function safeUp()
    {
        // add default dimensions to products
        $this->addColumnAfter('commerce_products', 'defaultHeight', 'decimal(14,4) DEFAULT NULL', 'expiryDate');
        $this->addColumnAfter('commerce_products', 'defaultWidth', 'decimal(14,4) DEFAULT NULL', 'expiryDate');
        $this->addColumnAfter('commerce_products', 'defaultLength', 'decimal(14,4) DEFAULT NULL', 'expiryDate');
        $this->addColumnAfter('commerce_products', 'defaultWeight', 'decimal(14,4) DEFAULT NULL', 'expiryDate');

        // add default price and variantId to products
        $this->addColumnAfter('commerce_products', 'defaultVariantId', 'int(11) DEFAULT NULL', 'expiryDate');
        $this->addColumnAfter('commerce_products', 'defaultSku', AttributeType::String, 'expiryDate');
        $this->addColumnAfter('commerce_products', 'defaultPrice', 'decimal(14,4) DEFAULT NULL', 'expiryDate');

        // add isDefault to variant
        $this->addColumnAfter('commerce_variants', 'isDefault', ColumnType::Bool, 'productId');

        $firstVariants = craft()->db->createCommand()
            ->select('*')
            ->from('commerce_variants')
            ->group('productId')
            ->queryAll();

        // save default variants, set defaults on product
        foreach($firstVariants as $variant){
            craft()->db->createCommand()->update('commerce_variants', ['isDefault' => 1], 'id = :idx', [':idx' => $variant['id']]);

            $data = [
                'defaultPrice' => $variant['price'],
                'defaultVariantId' => $variant['id'],
                'defaultSku' => $variant['sku'],
                'defaultWeight' => $variant['weight'],
                'defaultLength' => $variant['length'],
                'defaultHeight' => $variant['height'],
                'defaultWidth' => $variant['width'],
            ];
            craft()->db->createCommand()->update('commerce_products', $data, 'id = :idx', [':idx' => $variant['productId']]);
        }


    }
}
