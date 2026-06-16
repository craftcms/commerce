<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\base\Chippable;
use craft\commerce\base\Zone;
use craft\commerce\Plugin;
use craft\commerce\records\TaxZone;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;

/**
 * Tax zone model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $cpEditUrl
 */
class TaxAddressZone extends Zone implements Chippable
{
    /**
     * @var bool Default
     */
    public bool $default = false;

    /**
     * @inheritdoc
     */
    public static function get(int|string $id): ?static
    {
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $zone = Plugin::getInstance()->getTaxZones()->getTaxZoneById((int)$id, $store->id);
            if ($zone !== null) {
                /** @phpstan-ignore-next-line */
                return $zone;
            }
        }
        return null;
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('taxzones/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function getUiLabel(): string
    {
        return \Craft::t('site', $this->name);
    }

    /**
     * @inheritdoc
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name'], UniqueValidator::class, 'targetClass' => TaxZone::class, 'targetAttribute' => ['name', 'storeId']];
        $rules[] = [['default'], 'safe'];

        return $rules;
    }
}
