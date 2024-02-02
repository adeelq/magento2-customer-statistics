<?php

namespace Adeelq\CustomerStatistics\Controller\Adminhtml;

use Magento\Backend\App\Action;

abstract class Statistics extends Action
{
    /**
     * @inheritDoc
     */
    const ADMIN_RESOURCE = 'Adeelq_CustomerStatistics::CustomerStatistics';
}
