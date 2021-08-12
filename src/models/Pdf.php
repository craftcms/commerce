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
 *
 * @property-read array $config
 */
class Pdf extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Handle
     */
    public string $handle;

    /**
     * @var string Subject
     */
    public string $description;

    /**
     * @var bool Is Enabled
     */
    public bool $enabled = true;

    /**
     * @var bool Is default PDF for order
     */
    public bool $isDefault;

    /**
     * @var string Template path
     */
    public string $templatePath;

    /**
     * @var string Filename format
     */
    public string $fileNameFormat;

    /**
     * @var int Sort order
     */
    public int $sortOrder;

    /**
     * @var string UID
     */
    public string $uid;

    /**
     * @var string locale language
     */
    public string $language;

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle', 'templatePath', 'language'], 'required'];
        return $rules;
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
            'enabled' => $this->enabled,
            'sortOrder' => $this->sortOrder ?: 9999,
            'isDefault' => $this->isDefault,
            'language' => $this->language
        ];
    }
}
