<?php

namespace Adeelq\CustomerStatistics\Controller\Adminhtml\Statistics;

use Adeelq\CustomerStatistics\Controller\Adminhtml\Statistics;
use Adeelq\CoreModule\Controller\Adminhtml\AbstractIndex;

class Index extends AbstractIndex
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = Statistics::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Customer Statistics';
    }
}
