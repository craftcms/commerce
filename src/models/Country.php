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
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null ISO code
     */
    public ?string $iso = null;

    /**
     * @var bool State Required
     */
    public bool $isStateRequired = false;

    /**
     * @var bool Is Enabled
     */
    public bool $enabled = true;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTIme $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTIme $dateUpdated = null;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'states';

        return $fields;
    }

    /**
     * @return array
     * @throws InvalidConfigException
     * @since 3.1
     */
    public function getStates(): array
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
