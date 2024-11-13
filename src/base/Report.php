<?php

namespace craft\commerce\base;

use craft\db\Query;

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
        return [];
    }

    public function getCpEditUrl(): ?string
    {
        return 'commerce/reports/' . $this->getHandle();
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
        // TODO: Implement getQuery() method.
    }
}
