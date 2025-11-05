<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\base\Chippable;
use craft\base\Iconic;
use craft\commerce\base\Zone;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingZone;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;

/**
 * Shipping zone model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $cpEditUrl
 */
class ShippingAddressZone extends Zone implements Chippable, Iconic
{
    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name'], UniqueValidator::class, 'targetClass' => ShippingZone::class, 'targetAttribute' => ['name', 'storeId']];

        return $rules;
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/store-management/' . $this->getStore()->handle . '/shippingzones/' . $this->id);
    }

    /**
     * @param int|string $id
     * @return static|null
     */
    public static function get(int|string $id): ?static
    {
        return Plugin::getInstance()->getShippingZones()->getShippingZoneById($id);
    }

    /**
     * @return string
     */
    public function getUiLabel(): string
    {
        return Craft::t('site', $this->name);
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return 'map-location-dot';
    }
}
