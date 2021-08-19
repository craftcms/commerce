<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * State model.
 *
 * @property Country $country
 * @property-read string $label
 * @property string $cpEditUrl
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class State extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Abbreviation
     */
    public ?string $abbreviation = null;

    /**
     * @var int|null Country ID
     */
    public ?int $countryId = null;

    /**
     * @var bool Is Enabled
     */
    public bool $enabled = true;

    /**
     * @var int|null Ordering
     */
    public ?int $sortOrder = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['countryId', 'name', 'abbreviation'], 'required'];

        return $rules;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/store-settings/states/' . $this->id);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return Country
     * @throws InvalidConfigException if [[countryId]] is missing or invalid
     */
    public function getCountry(): Country
    {
        if ($this->countryId === null) {
            throw new InvalidConfigException('State is missing its country ID');
        }

        return Plugin::getInstance()->getCountries()->getCountryById($this->countryId);
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getLabel(): string
    {
        return $this->name . ' (' . $this->getCountry()->name . ')';
    }
}
