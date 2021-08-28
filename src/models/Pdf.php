<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\records\Pdf as PdfRecord;
use yii\base\InvalidArgumentException;

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
    public $language;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name', 'handle', 'templatePath', 'language'], 'required'],
        ];
    }

    /**
     * Determines the language this PDF
     *
     * @param Order|null $order
     * @return string
     */
    public function getRenderLanguage(Order $order = null): string
    {
        $language = $this->language;

        if ($order == null && $language == PdfRecord::LOCALE_ORDER_LANGUAGE) {
            throw new InvalidArgumentException('Can not get language for this PDF without providing an order');
        }

        if ($order && $language == PdfRecord::LOCALE_ORDER_LANGUAGE) {
            $language = $order->orderLanguage;
        }

        return $language;
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
            'language' => $this->language
        ];
    }
}
