<?php

namespace Adeelq\CustomerStatistics\Helper;

use Magento\Backend\Model\UrlInterface;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Throwable;

class Statistics
{
    /**
     * @var Timezone
     */
    private Timezone $timezoneConverter;

    /**
     * @var DateTimeFactory
     */
    private DateTimeFactory $dateTimeFactory;

    /**
     * @var UrlInterface
     */
    private UrlInterface $backendUrl;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $customerCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ExpressionFactory
     */
    private ExpressionFactory $expressionFactory;

    /**
     * @param UrlInterface $backendUrl
     * @param Timezone $timezoneConverter
     * @param DateTimeFactory $dateTimeFactory
     * @param CollectionFactory $customerCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ExpressionFactory $expressionFactory
     */
    public function __construct(
        UrlInterface $backendUrl,
        Timezone $timezoneConverter,
        DateTimeFactory $dateTimeFactory,
        CollectionFactory $customerCollectionFactory,
        StoreManagerInterface $storeManager,
        ExpressionFactory $expressionFactory
    ) {
        $this->backendUrl = $backendUrl;
        $this->timezoneConverter = $timezoneConverter;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->storeManager = $storeManager;
        $this->expressionFactory = $expressionFactory;
    }

    /**
     * @param int $customerId
     *
     * @return array
     */
    public function getCustomerStats(int $customerId): array
    {
        try {
            $collection = $this->customerCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', $customerId)
                ->addAttributeToFilter('store_id', ['in' => array_keys($this->storeManager->getStores())]);
            $this->addDynamicAttributes($collection);
            $collection->getSelect()->group(['e.entity_id']);
            return $collection->exportToArray();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param Collection $collection
     *
     * @return void
     */
    private function addDynamicAttributes(Collection $collection): void
    {
        $collection->getSelect()
            ->columns($this->buildColumns($collection))
            ->joinLeft(
                ['sales' => $collection->getTable('sales_order')],
                'e.entity_id = sales.customer_id',
                [
                    'total_orders_amount' => 'SUM(grand_total)',
                    'average_orders_amount' => 'AVG(grand_total)'
                ]
            );
    }

    /**
     * @param Collection $collection
     *
     * @return array
     */
    private function buildColumns(Collection $collection): array
    {
        $salesOrderGrid = $collection->getTable('sales_order_grid');
        return [
            'last_login_at' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT last_login_at
                        FROM %s
                        WHERE customer_id = e.entity_id
                        ORDER BY log_id DESC
                        LIMIT 1
                    )',
                    $collection->getTable('customer_log')
                )
            ),
            'last_logout_at' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT last_logout_at
                        FROM %s
                        WHERE customer_id = e.entity_id
                        ORDER BY log_id DESC
                        LIMIT 1
                    )',
                    $collection->getTable('customer_log')
                )
            ),
            'last_visit_at' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT last_visit_at
                        FROM %s
                        WHERE customer_id = e.entity_id
                        ORDER BY visitor_id DESC
                        LIMIT 1
                    )',
                    $collection->getTable('customer_visitor')
                )
            ),
            'last_ordered_at' => $this->getExpressionModel(
                "(
                        SELECT created_at
                        FROM $salesOrderGrid
                        WHERE customer_id = e.entity_id
                        ORDER BY created_at DESC
                        LIMIT 1
                    )"
            ),
            'last_order_amount' => $this->getExpressionModel(
                "(
                        SELECT grand_total
                        FROM $salesOrderGrid
                        WHERE customer_id = e.entity_id
                        ORDER BY created_at DESC
                        LIMIT 1
                    )"
            ),
            'total_orders' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT COUNT(*)
                        FROM %s
                        WHERE customer_id = e.entity_id
                        GROUP BY customer_id
                        LIMIT 1
                    )',
                    $collection->getTable('sales_order')
                )
            ),
            'most_used_payment_method' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT op.method
                        FROM %s so
                        LEFT JOIN %s op ON op.parent_id = so.entity_id
                        WHERE so.customer_id = e.entity_id
                        GROUP BY op.method
                        LIMIT 1
                    )',
                    $collection->getTable('sales_order'),
                    $collection->getTable('sales_order_payment')
                )
            ),
            'most_used_shipping_method' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT e.shipping_description
                        FROM %s
                        WHERE customer_id = e.entity_id
                        GROUP BY shipping_method
                        LIMIT 1
                    )',
                    $collection->getTable('sales_order')
                )
            ),
            'last_product_reviewed_at' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT r.created_at
                        FROM %s r
                        LEFT JOIN %s rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        ORDER BY r.created_at DESC
                        LIMIT 1
                    )',
                    $collection->getTable('review'), $collection->getTable('review_detail')
                )
            ),
            'total_products_reviewed' => $this->getExpressionModel(
                sprintf(
                    '(
                        SELECT COUNT(*)
                        FROM %s r
                        LEFT JOIN %s rd ON rd.review_id = r.review_id
                        WHERE rd.customer_id = e.entity_id
                        GROUP BY customer_id
                        LIMIT 1
                    )',
                    $collection->getTable('review'),
                    $collection->getTable('review_detail')
                )
            )
        ];
    }

    /**
     * @param string $expressionString
     *
     * @return Expression
     */
    private function getExpressionModel(string $expressionString): Expression
    {
        return $this->expressionFactory->create(['expression' => $expressionString]);
    }

    /**
     * @param array $timestamps
     *
     * @return array
     */
    private function convertTimestampsToDatetime(array $timestamps): array
    {
        try {
            $configTimeZone = $this->timezoneConverter->getConfigTimezone();
            $storeDateTime = $this->dateTimeFactory->create('now', new \DateTimeZone($configTimeZone));
            foreach ($timestamps as &$timestamp) {
                $timestamp = $storeDateTime->setTimestamp($timestamp)->format('F d Y H:i:s');
            }
            return $timestamps;
        } catch (Throwable) {
            return $timestamps;
        }
    }
}
