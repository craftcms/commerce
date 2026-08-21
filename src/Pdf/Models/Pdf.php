<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Records\Pdf as PdfRecord;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use Dompdf\Adapter\CPDF;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class Pdf extends Component implements HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $handle = null;

    public ?string $description = null;

    public bool $enabled = true;

    public bool $isDefault = false;

    public string $templatePath = '';

    public ?string $fileNameFormat = null;

    public ?int $sortOrder = null;

    public string $paperOrientation = PdfRecord::PAPER_ORIENTATION_PORTRAIT;

    public string $paperSize = 'letter';

    public ?string $uid = null;

    public string $language = PdfRecord::LOCALE_ORDER_LANGUAGE;

    public int $linkExpiry = 86400;

    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/settings/pdfs/' . $this->getStore()->handle . '/' . $this->id);
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'handle' => ['required', 'string', Rule::unique(Table::PDFS, 'handle')->where('storeId', $this->storeId)],
            'templatePath' => ['required', 'string'],
            'language' => ['required', 'string'],
            'paperOrientation' => ['required', Rule::in([PdfRecord::PAPER_ORIENTATION_PORTRAIT, PdfRecord::PAPER_ORIENTATION_LANDSCAPE])],
            'paperSize' => ['required', Rule::in(array_keys(CPDF::$PAPER_SIZES))],
        ];
    }

    #[\Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), ['config']);
    }

    public function getRenderLanguage(?Order $order = null): string
    {
        $language = $this->language;

        if ($order === null && $language === PdfRecord::LOCALE_ORDER_LANGUAGE) {
            throw new \InvalidArgumentException('Can not get language for this PDF without providing an order');
        }

        if ($order && $language === PdfRecord::LOCALE_ORDER_LANGUAGE) {
            $language = $order->orderLanguage;
        }

        return $language;
    }

    public function getConfig(): array
    {
        return [
            'description' => $this->description,
            'enabled' => $this->enabled,
            'fileNameFormat' => $this->fileNameFormat ?? '',
            'handle' => $this->handle,
            'isDefault' => $this->isDefault,
            'language' => $this->language,
            'name' => $this->name,
            'paperOrientation' => $this->paperOrientation,
            'paperSize' => $this->paperSize,
            'sortOrder' => $this->sortOrder ?: 9999,
            'store' => $this->getStore()->uid,
            'templatePath' => $this->templatePath,
            'linkExpiry' => $this->linkExpiry,
        ];
    }

    public static function getPaperOrientationOptions(): array
    {
        return [
            PdfRecord::PAPER_ORIENTATION_PORTRAIT => t('Portrait', category: 'commerce'),
            PdfRecord::PAPER_ORIENTATION_LANDSCAPE => t('Landscape', category: 'commerce'),
        ];
    }

    public static function getPaperSizeOptions(): array
    {
        return collect(CPDF::$PAPER_SIZES)->mapWithKeys(fn($value, $key) => [$key => $key])->all();
    }
}
