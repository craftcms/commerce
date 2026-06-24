<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\base\Chippable;
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
class ShippingAddressZone extends Zone implements Chippable
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
     * @inheritdoc
     */
    public static function get(int|string $id): ?static
    {
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $zone = Plugin::getInstance()->getShippingZones()->getShippingZoneById((int)$id, $store->id);
            if ($zone !== null) {
                /** @phpstan-ignore-next-line */
                return $zone;
            }
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getUiLabel(): string
    {
        return Craft::t('site', $this->name);
    }

    /**
     * @inheritdoc
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}
