<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Models;

use craft\commerce\elements\Order;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Email\Records\Email as EmailRecord;
use CraftCms\Commerce\Pdf\Models\Pdf as PdfModel;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;

class Email extends Component implements HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $subject = null;

    public string $recipientType = EmailRecord::TYPE_CUSTOMER;

    public ?string $replyTo = null;

    public bool $enabled = true;

    public ?string $templatePath = null;

    public ?string $plainTextTemplatePath = null;

    public ?int $pdfId = null;

    public string $language = EmailRecord::LOCALE_ORDER_LANGUAGE;

    public ?int $renderSiteId = null;

    public ?string $uid = null;

    private ?string $_senderAddress = null;

    private ?string $_senderName = null;

    private ?string $_bcc = null;

    private ?string $_cc = null;

    private ?string $_to = null;

    #[\Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), ['pdf', 'config']);
    }

    public function getRenderLanguage(?Order $order = null): string
    {
        $language = $this->language;

        if ($order === null && $language === EmailRecord::LOCALE_ORDER_LANGUAGE) {
            throw new \InvalidArgumentException('Can not get language for this email without providing an order');
        }

        if ($order && $language === EmailRecord::LOCALE_ORDER_LANGUAGE) {
            $language = $order->orderLanguage;
        }

        return $language;
    }

    public function getRenderSite(?Order $order = null): Site
    {
        $renderSiteId = $this->renderSiteId ?? $order?->orderSiteId;

        if ($renderSiteId !== null) {
            return Sites::getSiteById($renderSiteId);
        }

        return Sites::getPrimarySite();
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'subject' => ['required', 'string'],
            'name' => ['required', 'string'],
            'templatePath' => ['required', 'string'],
            'language' => ['required', 'string'],
            'recipientType' => ['required', 'in:' . EmailRecord::TYPE_CUSTOMER . ',' . EmailRecord::TYPE_CUSTOM],
            'to' => ['required_if:recipientType,' . EmailRecord::TYPE_CUSTOM],
        ];
    }

    public function getPdf(): ?PdfModel
    {
        if (!$this->pdfId) {
            return null;
        }

        return app(Pdfs::class)->getPdfById($this->pdfId, $this->storeId);
    }

    public function setSenderAddress(?string $senderAddress): void
    {
        $this->_senderAddress = $senderAddress;
    }

    public function getSenderAddress(bool $parse = true): ?string
    {
        if (!$parse) {
            return $this->_senderAddress;
        }

        if (!$senderAddress = Env::parse($this->_senderAddress)) {
            $senderAddress = Env::parse(\CraftCms\Cms\Email\Data\EmailSettings::fromProjectConfig()->fromEmail);
        }

        return $senderAddress;
    }

    public function setBcc(?string $bcc): void
    {
        $this->_bcc = $bcc;
    }

    public function getBcc(bool $parse = true): ?string
    {
        if (!$parse) {
            return $this->_bcc;
        }

        return Env::parse($this->_bcc);
    }

    public function setCc(?string $cc): void
    {
        $this->_cc = $cc;
    }

    public function getCc(bool $parse = true): ?string
    {
        if (!$parse) {
            return $this->_cc;
        }

        return Env::parse($this->_cc);
    }

    public function setTo(?string $to): void
    {
        $this->_to = $to;
    }

    public function getTo(bool $parse = true): ?string
    {
        if (!$parse) {
            return $this->_to;
        }

        return Env::parse($this->_to);
    }

    public function setSenderName(?string $senderName): void
    {
        $this->_senderName = $senderName;
    }

    public function getSenderName(bool $parse = true): ?string
    {
        if (!$parse) {
            return $this->_senderName;
        }

        if (!$senderName = Env::parse($this->_senderName)) {
            $senderName = Env::parse(\CraftCms\Cms\Email\Data\EmailSettings::fromProjectConfig()->fromName);
        }

        return $senderName;
    }

    public function getConfig(): array
    {
        return [
            'bcc' => $this->getBcc(false) ?: null,
            'cc' => $this->getCc(false) ?: null,
            'senderAddress' => $this->getSenderAddress(false) ?: null,
            'senderName' => $this->getSenderName(false) ?: null,
            'enabled' => $this->enabled,
            'language' => $this->language,
            'name' => $this->name,
            'pdf' => $this->getPdf()?->uid,
            'plainTextTemplatePath' => $this->plainTextTemplatePath ?? null,
            'recipientType' => $this->recipientType,
            'renderSite' => $this->renderSiteId ? Sites::getSiteById($this->renderSiteId)?->uid : null,
            'replyTo' => $this->replyTo ?: null,
            'store' => $this->getStore()->uid,
            'subject' => $this->subject,
            'templatePath' => $this->templatePath ?: null,
            'to' => $this->getTo(false) ?: null,
        ];
    }

    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/settings/emails/' . $this->getStore()->handle . '/' . $this->id);
    }
}
