<?php

namespace craft\commerce\base;

use craft\db\Query;
use craft\helpers\UrlHelper;

abstract class Report implements ReportInterface
{
    /**
     * @return string
     */
    abstract public function getTitle(): string;


    /**
     * @inheritDoc
     */
    public function getData(): mixed
    {
        return $this->getQuery()->all();
    }

    public function getCpEditUrl(): ?string
    {
        return $this->getHandle() ? UrlHelper::cpUrl('commerce/reporting/' . $this->getHandle()) : null;
    }

    public function getHandle(): ?string
    {
        return null;
    }

    public function getIcon(): ?string
    {
        return null;
    }

    public function getQuery(): Query
    {
        new Query();
    }
}
