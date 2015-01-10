<?php
namespace Craft;

class Stripey_ProductElementType extends BaseElementType
{

    public function getName()
    {
        return Craft::t('Products');
    }

    public function hasContent()
    {
        return true;
    }

    public function hasTitles()
    {
        return true;
    }

    public function hasStatuses()
    {
        return true;
    }

    public function getSources($context = null)
    {

        $sources = array(
            '*' => array(
                'label' => Craft::t('All products'),
            )
        );

        foreach (craft()->stripey_productType->getAllProductTypes() as $productType) {
            $key = 'productType:' . $productType->id;

            $sources[$key] = array(
                'label'    => $productType->name,
                'criteria' => array('typeId' => $productType->id)
            );
        }

        return $sources;

    }


    public function defineTableAttributes($source = null)
    {
        return array(
            'title'       => Craft::t('Name'),
            'availableOn' => Craft::t('Available On')
        );
    }

    public function defineSearchableAttributes()
    {
        return array('title');
    }


    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        return parent::getTableAttributeHtml($element, $attribute);
    }

    /**
     * Sortable by
     *
     * @return array
     */
    public function defineSortableAttributes()
    {
        return array(
            'availableOn' => Craft::t('Available On'),
            'expiresOn'   => Craft::t('Expires On'),
            'title'       => Craft::t('Name')
        );
    }


    /**
     * @inheritDoc IElementType::getStatuses()
     *
     * @return array|null
     */
    public function getStatuses()
    {
        return array(
            Stripey_ProductModel::LIVE    => Craft::t('Live'),
            Stripey_ProductModel::PENDING => Craft::t('Pending'),
            Stripey_ProductModel::EXPIRED => Craft::t('Expired'),
            BaseElementModel::DISABLED    => Craft::t('Disabled')
        );
    }


    public function defineCriteriaAttributes()
    {
        return array(
            'typeId'      => AttributeType::Mixed,
            'type'        => AttributeType::Mixed,
            'availableOn' => AttributeType::Mixed,
            'expiresOn'   => AttributeType::Mixed,
            'after'       => AttributeType::Mixed,
            'before'      => AttributeType::Mixed,
            'status'      => array(AttributeType::String, 'default' => Stripey_ProductModel::LIVE),
        );
    }

    /**
     * @inheritDoc IElementType::getElementQueryStatusCondition()
     *
     * @param DbCommand $query
     * @param string    $status
     *
     * @return array|false|string|void
     */
    public function getElementQueryStatusCondition(DbCommand $query, $status)
    {
        $currentTimeDb = DateTimeHelper::currentTimeForDb();

        switch ($status) {
            case Stripey_ProductModel::LIVE: {
                return array('and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    "products.availableOn <= '{$currentTimeDb}'",
                    array('or', 'products.expiresOn is null', "products.expiresOn > '{$currentTimeDb}'")
                );
            }

            case Stripey_ProductModel::PENDING: {
                return array('and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    "products.availableOn > '{$currentTimeDb}'"
                );
            }

            case Stripey_ProductModel::EXPIRED: {
                return array('and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    'products.expiresOn is not null',
                    "products.expiresOn <= '{$currentTimeDb}'"
                );
            }
        }
    }


    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {

        $query
            ->addSelect("products.id, products.typeId, products.availableOn, products.expiresOn")
            ->join('stripey_products products', 'products.id = elements.id')
            ->join('stripey_producttypes producttypes', 'producttypes.id = products.typeId');

        if ($criteria->availableOn) {
            $query->andWhere(DbHelper::parseDateParam('products.availableOn', $criteria->postDate, $query->params));
        } else {
            if ($criteria->after) {
                $query->andWhere(DbHelper::parseDateParam('products.availableOn', '>=' . $criteria->after, $query->params));
            }

            if ($criteria->before) {
                $query->andWhere(DbHelper::parseDateParam('products.availableOn', '<' . $criteria->before, $query->params));
            }
        }

        if ($criteria->expiresOn) {
            $query->andWhere(DbHelper::parseDateParam('products.expiresOn', $criteria->expiryDate, $query->params));
        }


        if ($criteria->type) {
            if ($criteria->type instanceof Stripey_ProductTypeModel) {
                $criteria->typeId = $criteria->type->id;
                $criteria->type   = null;
            } else {
                $query->andWhere(DbHelper::parseParam('producttypes.handle', $criteria->type, $query->params));
            }
        }

        if ($criteria->typeId) {
            $query->andWhere(DbHelper::parseParam('products.typeId', $criteria->typeId, $query->params));
        }
    }


    public function populateElementModel($row)
    {
        return Stripey_ProductModel::populateModel($row);
    }

} 