<?php
namespace Craft;

class m160917_010103_Commerce_DescriptionFormat extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_producttypes','descriptionFormat',AttributeType::String,'skuFormat');

        $data = [];
        $data['descriptionFormat'] = "{% if object.product.type.hasVariants %}{{ object.product.title }} - {{ object.title}}{% else %}{{ object.title}}{% endif %}";
        craft()->db->createCommand()->update('commerce_producttypes', $data);
    }
}
