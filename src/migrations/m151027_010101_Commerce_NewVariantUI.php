<?php
namespace Craft;

class m151027_010101_Commerce_NewVariantUI extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnBefore('commerce_variants','sortOrder',ColumnType::Int,'width');

        // delete those variants that are implicit but not the default variant of product types with single variants.
        $deletableVariants = craft()->db->createCommand()->setText("
select v.id as variantId, v.isImplicit as isImplicit, p.id as productId, p.typeId as ProductTypeId, pt.hasVariants as hasVariants from {{commerce_variants}} v
left join {{commerce_products}} p
ON v.productId = p.id
left join {{commerce_producttypes}} pt
ON p.typeId = pt.id
where (hasVariants = 1 and isImplicit = 1)
")->queryAll();

        foreach($deletableVariants as $dv){
            craft()->elements->deleteElementById($dv['variantId']);
            craft()->db->createCommand()->delete('commerce_variants', "id = :xid", array(":xid"=>$dv['variantId']));
        }

        $this->dropColumn('commerce_variants','isImplicit');
    }
}
