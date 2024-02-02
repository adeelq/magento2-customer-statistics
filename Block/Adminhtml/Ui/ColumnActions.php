<?php

namespace Adeelq\CustomerStatistics\Block\Adminhtml\Ui;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ColumnActions extends Column
{
    /**
     * @var UrlInterface
     */
    protected UrlInterface $backendUrl;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $backendUrl
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $backendUrl,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->backendUrl = $backendUrl;
    }

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items']) && is_array($dataSource['data']['items'])) {
            $store = $this->context->getFilterParam('store_id');
            foreach ($dataSource['data']['items'] as &$item) {
                $link = $this->backendUrl->getUrl(
                    'adeelq_statistics/statistics/customer',
                    ['store' => $store, 'id' => $item['entity_id']]
                );
                $item[$this->getData('name')]['view'] = ['label' => __('Stats'), 'href' => $link];
            }
        }

        return $dataSource;
    }
}
