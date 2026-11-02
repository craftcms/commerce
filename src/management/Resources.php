<?php

declare(strict_types=1);

namespace craft\commerce\management;

use Craft;
use craft\base\conditions\ConditionInterface;
use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\models\ShippingRule;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\models\FieldLayout;
use DateTimeInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Money\Currency;
use RuntimeException;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\web\ForbiddenHttpException;

/** User-authorized CRUD over Commerce's configuration and store-management services. */
readonly class Resources
{
    public function __construct(private Access $access)
    {
    }

    /** @return array<string, mixed> */
    public function schema(User $actor, bool $configuration, ?ResourceType $type = null): array
    {
        $this->access->permission($actor);
        if ($configuration) {
            $this->access->configuration($actor);
        }

        if ($type === null) {
            $types = [];
            foreach (ResourceType::cases() as $candidate) {
                if ($candidate->configuration() !== $configuration) {
                    continue;
                }
                try {
                    $this->authorize($actor, $candidate, 'list');
                    $types[] = ['type' => $candidate->value, 'operations' => $this->operations($actor, $candidate)];
                } catch (ForbiddenHttpException) {
                    continue;
                }
            }

            return ['types' => $types];
        }

        $this->category($type, $configuration);
        $this->authorize($actor, $type, 'list');

        return [
            'type' => $type->value,
            'operations' => $this->operations($actor, $type),
            'storeIdRequired' => $type->storeScoped(),
            'identifier' => $this->identifierSchema($type),
            'createAttributes' => $this->attributesSchema($type, true),
            'updateAttributes' => $this->attributesSchema($type, false),
            'deletionEffects' => $type->deletionEffects(),
            'notes' => $type === ResourceType::Gateways ? ['Gateway settings and credentials are never returned. Configure gateways in the control panel.'] : [],
        ];
    }

    /** @return array<string, mixed> */
    public function list(User $actor, ResourceType $type, bool $configuration, ?int $storeId = null, int $limit = 100, int $offset = 0): array
    {
        $this->category($type, $configuration);
        $this->authorize($actor, $type, 'list');
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Use limit 1-100 and a nonnegative offset.');
        }
        $items = $this->records($actor, $type, $storeId)->sortBy('id')->values();
        $page = $items->slice($offset, $limit);

        return [
            'type' => $type->value,
            'count' => $page->count(),
            'items' => $page->map(fn(Component $item) => $this->serialize($type, $item))->values()->all(),
            'limit' => $limit,
            'offset' => $offset,
            'nextOffset' => $offset + $limit < $items->count() ? $offset + $limit : null,
        ];
    }

    /** @return array<string, mixed> */
    public function get(User $actor, ResourceType $type, bool $configuration, array $identifier, ?int $storeId = null): array
    {
        $this->category($type, $configuration);
        $this->authorize($actor, $type, 'get');

        return ['type' => $type->value, 'item' => $this->serialize($type, $this->find($actor, $type, $identifier, $storeId))];
    }

    /** @return array<string, mixed> */
    public function save(User $actor, ResourceType $type, bool $configuration, array $attributes, ?array $identifier = null, ?int $storeId = null): array
    {
        $this->category($type, $configuration);
        $operation = $identifier === null ? 'create' : 'update';
        $this->authorize($actor, $type, $operation);
        [$class, $service, , $save] = $type->binding();
        if ($save === null) {
            throw new InvalidArgumentException('This resource does not support writes.');
        }
        Input::validate($attributes, $this->attributesSchema($type, $identifier === null));

        $item = $identifier === null ? new $class() : clone $this->find($actor, $type, $identifier, $storeId);
        $emailIds = $attributes['emailIds'] ?? ($item instanceof OrderStatus ? $item->getEmailIds() : []);
        unset($attributes['emailIds']);
        if ($item instanceof Discount && isset($attributes['coupons'])) {
            $existing = collect($item->getCoupons())->keyBy('id');
            $codes = [];
            $ids = [];
            $coupons = [];
            foreach ($attributes['coupons'] as $values) {
                $id = $values['id'] ?? null;
                $code = mb_strtolower($values['code']);
                if (isset($codes[$code]) || ($id !== null && (isset($ids[$id]) || !$existing->has($id)))) {
                    throw new InvalidArgumentException('Coupons must have distinct codes and existing IDs must belong to this discount.');
                }
                $coupon = $id === null ? new Coupon() : clone $existing->get($id);
                Craft::configure($coupon, $values);
                $coupons[] = $coupon;
                $codes[$code] = true;
                $ids[$id ?? 0] = true;
            }
            $attributes['coupons'] = $coupons;
        }
        if ($item instanceof ProductType) {
            if (isset($attributes['siteSettings'])) {
                $sites = [];
                foreach ($attributes['siteSettings'] as $settings) {
                    if (isset($sites[$settings['siteId']]) || !Craft::$app->getSites()->getSiteById($settings['siteId'])) {
                        throw new InvalidArgumentException('Product type sites must exist and cannot be repeated.');
                    }
                    $sites[$settings['siteId']] = new ProductTypeSite($settings);
                }
                $attributes['siteSettings'] = $sites;
            }
            foreach (['productFieldLayout', 'variantFieldLayout'] as $name) {
                if (isset($attributes[$name])) {
                    $layout = FieldLayout::createFromConfig($attributes[$name]);
                    $existing = $name === 'productFieldLayout' ? $item->getProductFieldLayout() : $item->getVariantFieldLayout();
                    $layout->id = $existing->id;
                    $layout->uid = $existing->uid;
                    $layout->type = $existing->type;
                    $attributes[$name] = $layout;
                }
            }
        }
        Craft::configure($item, $attributes);

        if ($type->storeScoped()) {
            $this->access->store($actor, $item->storeId);
            if ($storeId !== null && $item->storeId !== $storeId) {
                throw new InvalidArgumentException('storeId must match the resource store.');
            }
        }
        if ($item instanceof ShippingRule) {
            $this->authorizeShippingRule($actor, $item);
            if (isset($attributes['shippingRuleCategories'])) {
                $categories = [];
                foreach ($attributes['shippingRuleCategories'] as $values) {
                    $id = $values['shippingCategoryId'];
                    if (isset($categories[$id]) || !Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id, $item->storeId)) {
                        throw new InvalidArgumentException('Shipping categories must be distinct and belong to this store.');
                    }
                    $categories[$id] = new ShippingRuleCategory($values);
                }
                $item->setShippingRuleCategories($categories);
            }
        }
        if ($item instanceof OrderStatus) {
            foreach ($emailIds as $emailId) {
                if (!Plugin::getInstance()->getEmails()->getEmailById($emailId, $item->storeId)) {
                    throw new InvalidArgumentException('Email not found in this store.');
                }
            }
        }
        if ($type === ResourceType::Emails && $item->pdfId && !Plugin::getInstance()->getPdfs()->getPdfById($item->pdfId, $item->storeId)) {
            throw new InvalidArgumentException('PDF not found in this store.');
        }

        $saved = $item instanceof OrderStatus ? Plugin::getInstance()->get($service)->$save($item, $emailIds) : Plugin::getInstance()->get($service)->$save($item);
        if (!$saved) {
            throw new InvalidArgumentException(implode("\n", $item->getErrorSummary(true)) ?: 'The resource could not be saved.');
        }

        return ['type' => $type->value, 'item' => $this->serialize($type, $item)];
    }

    /** @return array{type: string, deleted: bool} */
    public function delete(User $actor, ResourceType $type, bool $configuration, array $identifier, ?int $storeId = null): array
    {
        $this->category($type, $configuration);
        $this->authorize($actor, $type, 'delete');
        [, $service, , , $delete] = $type->binding();
        if ($delete === null) {
            throw new InvalidArgumentException($type->deletionEffects());
        }
        $item = $this->find($actor, $type, $identifier, $storeId);
        if ($item instanceof OrderStatus && !$item->canDelete()) {
            throw new InvalidArgumentException('An order status in use or marked as default cannot be deleted.');
        }
        $deleted = in_array($type, [ResourceType::OrderStatuses, ResourceType::LineItemStatuses], true)
            ? Plugin::getInstance()->get($service)->$delete(ArrayHelper::getValue($item, 'id'), ArrayHelper::getValue($item, 'storeId'))
            : Plugin::getInstance()->get($service)->$delete(ArrayHelper::getValue($item, 'id'));
        if (!$deleted) {
            throw new RuntimeException('The resource could not be deleted.');
        }

        return ['type' => $type->value, 'deleted' => true];
    }

    private function category(ResourceType $type, bool $configuration): void
    {
        if ($type->configuration() !== $configuration) {
            throw new InvalidArgumentException('Use commerce.' . ($type->configuration() ? 'configuration' : 'resources') . ' tools for this type.');
        }
    }

    private function authorize(User $actor, ResourceType $type, string $operation): void
    {
        if ($type->configuration()) {
            $this->access->configuration($actor, in_array($operation, ['create', 'update', 'delete'], true));
        } else {
            $this->access->permission($actor, ...$type->permissions($operation));
        }
        if (($type === ResourceType::Sales && !Plugin::getInstance()->getSales()->canUseSales()) || ($type === ResourceType::CatalogPricingRules && !Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules())) {
            throw new ForbiddenHttpException('This pricing feature is not available.');
        }
    }

    /** @return list<string> */
    private function operations(User $actor, ResourceType $type): array
    {
        [, , , $save, $delete] = $type->binding();
        $operations = ['list', 'get'];
        foreach ([...($save ? ['create', 'update'] : []), ...($delete ? ['delete'] : [])] as $operation) {
            try {
                $this->authorize($actor, $type, $operation);
                $operations[] = $operation;
            } catch (ForbiddenHttpException) {
                continue;
            }
        }

        return $operations;
    }

    /** @return Collection<int, Component> */
    private function records(User $actor, ResourceType $type, ?int $storeId): Collection
    {
        [, $service, $list] = $type->binding();
        if ($type->storeScoped()) {
            if ($storeId === null) {
                throw new InvalidArgumentException('Provide storeId for this resource type.');
            }
            $this->access->store($actor, $storeId);
        }
        $records = collect($type->storeScoped() ? Plugin::getInstance()->get($service)->$list($storeId) : Plugin::getInstance()->get($service)->$list());
        if ($type === ResourceType::ShippingRules) {
            $records = $records->filter(function(ShippingRule $rule) use ($actor): bool {
                try {
                    $this->authorizeShippingRule($actor, $rule);
                    return true;
                } catch (ForbiddenHttpException) {
                    return false;
                }
            });
        }

        return $records;
    }

    private function find(User $actor, ResourceType $type, array $identifier, ?int $storeId): Component
    {
        Input::validate($identifier, $this->identifierSchema($type));
        $key = array_key_first($identifier);
        $item = $this->records($actor, $type, $storeId)->first(fn(Component $item) => $item->$key === $identifier[$key]);
        if (!$item) {
            throw new InvalidArgumentException('Resource not found.');
        }

        return $item;
    }

    private function authorizeShippingRule(User $actor, ShippingRule $rule): void
    {
        foreach (Plugin::getInstance()->getStores()->getStoresByUserId($actor->id) as $store) {
            if (Plugin::getInstance()->getShippingMethods()->getShippingMethodById($rule->methodId, $store->id)) {
                if ($rule->storeId !== null && $rule->storeId !== $store->id) {
                    throw new ForbiddenHttpException('Shipping rules cannot move between stores.');
                }
                $rule->storeId = $store->id;
                return;
            }
        }
        throw new ForbiddenHttpException('Resource not found.');
    }

    /** @return array<string, mixed> */
    private function identifierSchema(ResourceType $type): array
    {
        $properties = ['id' => ['type' => 'integer', 'minimum' => 1]];
        if (isset($type->attributes()['handle'])) {
            $properties['handle'] = ['type' => 'string', 'minLength' => 1];
        }
        if ($type->configuration()) {
            $properties['uid'] = ['type' => 'string', 'minLength' => 1];
        }

        return ['type' => 'object', 'properties' => $properties, 'minProperties' => 1, 'maxProperties' => 1, 'additionalProperties' => false];
    }

    /** @return array<string, mixed> */
    private function attributesSchema(ResourceType $type, bool $create): array
    {
        $properties = $type->attributes();
        if (!$create) {
            unset($properties['storeId']);
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => $create ? $type->required() : [], 'additionalProperties' => false];
    }

    /** @return array<string, mixed> */
    private function serialize(ResourceType $type, Component $item): array
    {
        $data = ['id' => ArrayHelper::getValue($item, 'id')];
        if ($type->configuration()) {
            $data['uid'] = ArrayHelper::getValue($item, 'uid');
        }
        foreach (array_keys($type->attributes()) as $name) {
            $value = $item->$name;
            if ($name === 'coupons' && $item instanceof Discount) {
                $value = array_map(static fn(Coupon $coupon) => $coupon->toArray(['id', 'code', 'maxUses', 'uses']), $value);
            } elseif ($name === 'shippingRuleCategories' && $item instanceof ShippingRule) {
                $value = array_values(array_map(static fn(ShippingRuleCategory $category) => $category->toArray(['shippingCategoryId', 'condition', 'perItemRate', 'weightRate', 'percentageRate']), $value));
            }
            if ($item instanceof ProductType) {
                if ($name === 'siteSettings') {
                    $value = array_values(array_map(static fn(ProductTypeSite $site) => $site->toArray(['siteId', 'hasUrls', 'uriFormat', 'template', 'enabledByDefault']), $value));
                } elseif (in_array($name, ['shippingCategories', 'taxCategories'], true)) {
                    $value = array_values(array_map(static fn($category) => $category->id, $value));
                }
            }
            $data[$name] = match (true) {
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                $value instanceof ConditionInterface => $value->getConfig(),
                $value instanceof FieldLayout => $value->getConfig(),
                $value instanceof Currency => $value->getCode(),
                default => $value,
            };
        }

        return $data;
    }
}
