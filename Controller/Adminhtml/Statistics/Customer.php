<?php

namespace Adeelq\CustomerStatistics\Controller\Adminhtml\Statistics;

class Customer extends Index
{
    /**
     * @inheritDoc
     */
    protected function getLabelTitle(): string
    {
        return 'Lifetime Customer Statistics';
    }
}
