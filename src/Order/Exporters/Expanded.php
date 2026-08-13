<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Exporters;

use CraftCms\Cms\Component\ComponentHelper;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Exporters\Expanded as CraftExpanded;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Field\Contracts\EagerLoadingFieldInterface;
use CraftCms\Cms\Field\Fields;
use CraftCms\Cms\Support\DateTimeHelper;

/**
 * Expanded represents an "Expanded" order exporter.
 */
class Expanded extends CraftExpanded
{
    /** @param ElementQueryInterface&ElementQuery<ElementInterface> $query */
    #[\Override]
    public function export(ElementQueryInterface $query): mixed
    {
        // This export should be identical to the parent, except for the additional extra fields
        $extraAttributes = ['adjustments', 'billingAddress', 'shippingAddress', 'transactions'];

        // Eager-load as much as we can
        $eagerLoadableFields = [];
        foreach (app(Fields::class)->getAllFields() as $field) {
            if ($field instanceof EagerLoadingFieldInterface) {
                $eagerLoadableFields[] = [
                    'path' => $field->handle,
                    'criteria' => [
                        'status' => null,
                    ],
                ];
            }
        }

        $data = [];

        $query->with($eagerLoadableFields);

        $query->each(function(ElementInterface $element) use (&$data, $extraAttributes) {
            // Get the basic array representation excluding custom fields
            $attributes = array_flip($element->attributes());
            if (($fieldLayout = $element->getFieldLayout()) !== null) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    unset($attributes[$field->handle]);
                }
            }

            $datetimeAttributes = ComponentHelper::datetimeAttributes($element);
            $otherAttributes = array_diff(array_keys($attributes), $datetimeAttributes);
            $elementArr = $element->toArray($otherAttributes, $extraAttributes);

            foreach ($datetimeAttributes as $attribute) {
                $date = $element->$attribute;
                $elementArr[$attribute] = $date ? DateTimeHelper::toIso8601($date) : $element->$attribute;
            }

            if ($fieldLayout !== null) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    $value = $element->getFieldValue($field->handle);
                    $elementArr[$field->handle] = $field->serializeValue($value, $element);
                }
            }

            $data[] = $elementArr;
        }, 100);

        return $data;
    }
}
