<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\exports;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\elements\exporters\Expanded as CraftExpanded;
use craft\elements\db\ElementQueryInterface;

/**
 * Expanded represents an "Expanded" order exporter.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.7
 */
class Expanded extends CraftExpanded
{
    /**
     * @inheritdoc
     */
    public function export(ElementQueryInterface $query): array
    {
        // This export should be identical to the parent, except for the additional extra fields
        $extraAttributes = ['adjustments', 'billingAddress', 'shippingAddress', 'transactions'];

        // Eager-load as much as we can
        $eagerLoadableFields = [];
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if ($field instanceof EagerLoadingFieldInterface) {
                $eagerLoadableFields[] = $field->handle;
            }
        }

        $data = [];

        /** @var OrderQuery $query */
        $query->with($eagerLoadableFields);
        $query->withAll();

        foreach ($query->each() as $element) {
            // Get the basic array representation excluding custom fields
            $attributes = array_flip($element->attributes());
            if (($fieldLayout = $element->getFieldLayout()) !== null) {
                foreach ($fieldLayout->getFields() as $field) {
                    unset($attributes[$field->handle]);
                }
            }
            $elementArr = $element->toArray(array_keys($attributes), $extraAttributes);
            if ($fieldLayout !== null) {
                foreach ($fieldLayout->getFields() as $field) {
                    $value = $element->getFieldValue($field->handle);
                    $elementArr[$field->handle] = $field->serializeValue($value, $element);
                }
            }
            $data[] = $elementArr;
        }

        return $data;
    }
}
