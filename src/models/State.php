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
use yii\base\InvalidConfigException;

/**
 * State model.
 *
 * @property Country $country
 * @property string $cpEditUrl
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class State extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Abbreviation
     */
    public $abbreviation;

    /**
     * @var int Country ID
     */
    public $countryId;

    /**
     * @var bool Is Enabled
     */
    public $enabled;

    /**
     * @var int Ordering
     */
    public $sortOrder;


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
        return (string)$this->name;
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
     */
    public function getLabel(): string
    {
        return $this->name . ' (' . $this->getCountry()->name . ')';
    }
}
