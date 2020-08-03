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
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name','handle'], 'required'];
        $rules[] = [['templatePath'], 'required'];
        return $rules;
    }
}
