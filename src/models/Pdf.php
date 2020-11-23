<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * PDF model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 */
class Pdf extends Model
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
     * @var string Handle
     */
    public $handle;

    /**
     * @var string Subject
     */
    public $description;

    /**
     * @var bool Is Enabled
     */
    public $enabled = true;

    /**
     * @var bool Is default PDF for order
     */
    public $isDefault;

    /**
     * @var string Template path
     */
    public $templatePath;

    /**
     * @var string Filename format
     */
    public $fileNameFormat;

    /**
     * @var int Sort order
     */
    public $sortOrder;

    /**
     * @var string UID
     */
    public $uid;

    /**
     * @var string locale language
     */
    public $locale;

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['templatePath'], 'required'];
        return $rules;
    }

    /**
     * Returns the field layout config for this email.
     *
     * @return array
     * @since 3.2.0
     */
    public function getConfig(): array
    {
        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'description' => $this->description,
            'templatePath' => $this->templatePath,
            'fileNameFormat' => $this->fileNameFormat,
            'enabled' => (bool)$this->enabled,
            'sortOrder' => (int)$this->sortOrder ?: 9999,
            'isDefault' => (bool)$this->isDefault,
            'locale' => $this->locale
        ];
    }
}
