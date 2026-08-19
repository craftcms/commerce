<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Http\Controllers\Elements\ElementIndex\ElementIndexController;
use CraftCms\Cms\Http\Controllers\Elements\SearchController;
use CraftCms\Cms\Support\Query;
use CraftCms\Commerce\Catalog\Elements\Product;
use DateTime;

class ProductQuery
{
    public static function statusCondition(string $status, string $tablePrefix = ''): array|false
    {
        $now = new DateTime();
        $now->setTime((int)$now->format('H'), (int)$now->format('i'), 59);
        $currentTimeDb = Query::prepareDateForDb($now);

        return match ($status) {
            Product::STATUS_LIVE => [
                'and',
                [
                    $tablePrefix . 'elements.enabled' => true,
                    $tablePrefix . 'elements_sites.enabled' => true,
                ],
                ['<=', 'commerce_products.postDate', $currentTimeDb],
                [
                    'or',
                    ['commerce_products.expiryDate' => null],
                    ['>', 'commerce_products.expiryDate', $currentTimeDb],
                ],
            ],
            Product::STATUS_PENDING => [
                'and',
                [
                    $tablePrefix . 'elements.enabled' => true,
                    $tablePrefix . 'elements_sites.enabled' => true,
                ],
                ['>', 'commerce_products.postDate', $currentTimeDb],
            ],
            Product::STATUS_EXPIRED => [
                'and',
                [
                    $tablePrefix . 'elements.enabled' => true,
                    $tablePrefix . 'elements_sites.enabled' => true,
                ],
                ['not', ['commerce_products.expiryDate' => null]],
                ['<=', 'commerce_products.expiryDate', $currentTimeDb],
            ],
            Element::STATUS_ENABLED => [
                $tablePrefix . 'elements.enabled' => true,
                $tablePrefix . 'elements_sites.enabled' => true,
            ],
            Element::STATUS_DISABLED => [
                'or',
                [$tablePrefix . 'elements.enabled' => false],
                [$tablePrefix . 'elements_sites.enabled' => false],
            ],
            Element::STATUS_ARCHIVED => [$tablePrefix . 'elements.archived' => true],
            default => false,
        };
    }

    public static function cleanseQueryCriteria(array $criteria): array
    {
        $controllerClass = request()->route()?->getControllerClass();
        if ($controllerClass === ElementIndexController::class || $controllerClass === SearchController::class) {
            $criteria = ElementHelper::cleanseQueryCriteria($criteria);
        }

        return $criteria;
    }
}
