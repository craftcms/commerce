<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp\Elements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Mcp\Elements\BaseElementAdapter;
use CraftCms\Commerce\Mcp\ElementData;

abstract class CommerceElementAdapter extends BaseElementAdapter
{
    protected function findQuery(): ElementQueryInterface
    {
        return static::elementType()::find()->status(null);
    }

    public function serialize(ElementInterface $element, ?array $fields = null, bool $summary = false): array
    {
        return $this->elementSerializer->serialize($element, ElementData::attributes($element), fields: $fields);
    }
}
