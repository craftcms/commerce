<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\StoreTrait;
use craft\commerce\elements\Order;
use craft\commerce\records\Pdf as PdfRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use Dompdf\Adapter\CPDF;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * PDF model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 *
 * @property-read array $config
 */
class Pdf extends Model implements HasStoreInterface
{
    use StoreTrait;

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var string|null Subject
     */
    public ?string $description = null;

    /**
     * @var bool Is Enabled
     */
    public bool $enabled = true;

    /**
     * @var bool Is default PDF for order
     */
    public bool $isDefault = false;

    /**
     * @var string|null Template path
     */
    public ?string $templatePath = null;

    /**
     * @var string|null Filename format
     */
    public ?string $fileNameFormat = null;

    /**
     * @var int|null Sort order
     */
    public ?int $sortOrder = null;

    /**
     * @var string The orientation of the paper to use for generated order PDF files.
     *
     * Options are `'portrait'` and `'landscape'`.
     *
     * @since 5.0.0
     */
    public string $paperOrientation = PdfRecord::PAPER_ORIENTATION_PORTRAIT;

    /**
     * @var string The size of the paper to use for generated order PDFs.
     *
     * The full list of supported paper sizes can be found [in the dompdf library](https://github.com/dompdf/dompdf/blob/master/src/Adapter/CPDF.php#L45).
     *
     * @since 5.0.0
     */
    public string $paperSize = 'letter';

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @var string locale language
     */
    public string $language = PdfRecord::LOCALE_ORDER_LANGUAGE;


    /**
     * @return string
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/pdfs/' . $this->getStore()->handle . '/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name', 'handle', 'templatePath', 'language'], 'required'],
            [['handle'],
                UniqueValidator::class,
                'targetClass' => PdfRecord::class,
                'targetAttribute' => ['handle', 'storeId'],
                'message' => '{attribute} "{value}" has already been taken.',
            ],
            [['paperOrientation'], 'in', 'range' => [PdfRecord::PAPER_ORIENTATION_PORTRAIT, PdfRecord::PAPER_ORIENTATION_LANDSCAPE]],
            [['paperSize'], 'in', 'range' => array_keys(CPDF::$PAPER_SIZES)],
            [[
                'description',
                'enabled',
                'fileNameFormat',
                'handle',
                'id',
                'isDefault',
                'language',
                'name',
                'paperOrientation',
                'paperSize',
                'sortOrder',
                'storeId',
                'templatePath',
                'uid',
            ], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'config';

        return $fields;
    }

    /**
     * Determines the language this PDF
     *
     * @param Order|null $order
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
     * @since 3.2.0
     */
    public function getConfig(): array
    {
        return [
            'description' => $this->description,
            'enabled' => $this->enabled,
            'fileNameFormat' => $this->fileNameFormat,
            'handle' => $this->handle,
            'isDefault' => $this->isDefault,
            'language' => $this->language,
            'name' => $this->name,
            'paperOrientation' => $this->paperOrientation,
            'paperSize' => $this->paperSize,
            'sortOrder' => $this->sortOrder ?: 9999,
            'store' => $this->getStore()->uid,
            'templatePath' => $this->templatePath,
        ];
    }

    /**
     * @return string[]
     * @since 5.0.0
     */
    public static function getPaperOrientationOptions(): array
    {
        return [
            PdfRecord::PAPER_ORIENTATION_PORTRAIT => Craft::t('commerce', 'Portrait'),
            PdfRecord::PAPER_ORIENTATION_LANDSCAPE => Craft::t('commerce', 'Landscape'),
        ];
    }

    /**
     * @return array
     * @since 5.0.0
     */
    public static function getPaperSizeOptions(): array
    {
        return collect(CPDF::$PAPER_SIZES)->mapWithKeys(fn($value, $key) => [$key => $key])->all();
    }
}
