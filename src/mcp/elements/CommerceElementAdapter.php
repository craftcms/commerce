<?php

declare(strict_types=1);

namespace craft\commerce\mcp\elements;

use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Transfer;
use craft\commerce\management\Access;
use craft\commerce\management\Mutation;
use craft\commerce\mcp\ElementData;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\mcp\elements\BaseElementAdapter;
use Mcp\Exception\ToolCallException;

abstract class CommerceElementAdapter extends BaseElementAdapter
{
    protected function findQuery(): ElementQueryInterface
    {
        return static::elementType()::find()->drafts(null)->provisionalDrafts(null)->status(null);
    }

    public function serialize(ElementInterface $element, ?array $fields = null, bool $summary = false): array
    {
        return $this->elementSerializer->serialize($element, ElementData::attributes($element), fields: $fields);
    }

    public function canView(User $actor, ElementInterface $element): bool
    {
        return Access::allows($actor, 'view', $element);
    }

    public function canDelete(User $actor, ElementInterface $element): bool
    {
        return Access::allows($actor, 'delete', $element);
    }

    protected function authorizeSave(User $actor, ElementInterface $element): void
    {
        if (!Access::allows($actor, 'save', $element)) {
            throw new ToolCallException('Resource not found.');
        }
    }

    public function update(ElementInterface $element, array $attributes, array $fields, User $actor): array
    {
        $key = match (true) {
            $element instanceof Order => 'commerce:order-management:',
            $element instanceof Transfer => 'commerce:transfer-management:',
            default => 'commerce:element-management:',
        };
        return Mutation::run($key . $element->id, function() use ($element, $attributes, $fields, $actor): array {
            $fresh = $this->find($element->id, null, $element->siteId);
            if (!$fresh) {
                throw new ToolCallException('Resource not found.');
            }
            return parent::update($fresh, $attributes, $fields, $actor);
        });
    }
}
