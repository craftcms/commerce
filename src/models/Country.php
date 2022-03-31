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

/**
 * Country Model
 *
 * @property string $cpEditUrl
 * @property-read array $states
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Country extends Model
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
     * @var string ISO code
     */
    public $iso;

    /**
     * @var bool State Required
     */
    public $isStateRequired;

    /**
     * @var bool Is Enabled
     */
    public $enabled = true;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public $dateUpdated;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->name;
    }

    /**
     * @return array
     * @since 3.1
     */
    public function getStates()
    {
        return Plugin::getInstance()->getStates()->getStatesByCountryId($this->id);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['iso', 'name'], 'required'],
            [['iso'], 'string', 'length' => [2]],
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/store-settings/countries/' . $this->id);
    }
}
