<?php

namespace Adeelq\CustomerStatistics\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Adeelq\CustomerStatistics\Helper\Statistics as StatisticsHelper;

class Statistics extends Container
{
    const COLUMNS = [
        'last_login_at',
        'last_logout_at',
        'last_visit_at',

        'last_ordered_at',
        'last_order_amount',
        'total_orders',
        'total_orders_amount',
        'average_orders_amount',

        'most_used_payment_method',
        'most_used_shipping_method',

        'active_shopping_cart',
        'total_products_in_shopping_cart',

        'last_product_reviewed_at',
        'total_products_reviewed',

        'last_product_wished_at',
        'total_wishlist_products'
    ];

    /**
     * @var Escaper
     */
    public Escaper $escaper;

    /**
     * @var StatisticsHelper
     */
    private StatisticsHelper $statisticsHelper;

    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param StatisticsHelper $statisticsHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        StatisticsHelper $statisticsHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->escaper = $escaper;
        $this->statisticsHelper = $statisticsHelper;
    }

    /**
     * @return StatisticsHelper
     */
    public function getStatisticsHelper(): StatisticsHelper
    {
        return $this->statisticsHelper;
    }

    /**
     * @return array
     */
    public function getTableHeaders(): array
    {
        $customerId = $this->getRequest()->getParam('id');
        echo "<pre>";
        var_dump($this->statisticsHelper->getCustomerStats($customerId)); exit;
        return ['Some', 'Fake', 'Data'];
    }

    /**
     * @return array
     */
    public function getTableRows(): array
    {
        return [
            ['Fake', 'Fake', 'Fake']
        ];
    }
}
