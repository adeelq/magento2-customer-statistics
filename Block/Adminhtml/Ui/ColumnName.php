<?php

namespace Adeelq\CustomerStatistics\Block\Adminhtml\Ui;

use Magento\Ui\Component\Listing\Columns\Column;

class ColumnName extends Column
{
    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items']) && is_array($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = sprintf('%s %s', $item['firstname'], $item['lastname']);
            }
        }

        return $dataSource;
    }
}
