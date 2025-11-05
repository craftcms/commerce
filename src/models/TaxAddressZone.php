<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\base\Chippable;
use craft\base\Iconic;
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
class TaxAddressZone extends Zone implements Chippable, Iconic
{
    /**
     * @var bool Default
     */
    public bool $default = false;

    /**
     * @param int|string $id
     * @return static|null
     */
    public static function get(int|string $id): ?static
    {
        return Plugin::getInstance()->getTaxZones()->getTaxZoneById($id);
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
     * @return string
     */
    public function getUiLabel(): string
    {
        return \Craft::t('site', $this->name);
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
