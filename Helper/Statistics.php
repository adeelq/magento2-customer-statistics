<?php

namespace Adeelq\CustomerStatistics\Helper;

use Adeelq\CoreModule\Helper\Base;
use Adeelq\CoreModule\Logger\Logger;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Throwable;

class Statistics extends Base
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $customerCollectionFactory;

    /**
     * @var ExpressionFactory
     */
    private ExpressionFactory $expressionFactory;

    /**
     * @param Logger $logger
     * @param CollectionFactory $customerCollectionFactory
     * @param ExpressionFactory $expressionFactory
     */
    public function __construct(
        Logger $logger,
        CollectionFactory $customerCollectionFactory,
        ExpressionFactory $expressionFactory
    ) {
        parent::__construct($logger);
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->expressionFactory = $expressionFactory;
    }

    /**
     * @param int $customerId
     * @param array $storeIds
     *
     * @return array
     */
    public function getCustomerStats(int $customerId, array $storeIds): array
    {
        try {
            $collection = $this->customerCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', $customerId)
                ->addAttributeToFilter('store_id', ['in' => $storeIds]);
            $this->addDynamicAttributes($collection);
            return $collection->getSize() ? $collection->getFirstItem()->getData() : [];
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
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
        try {
            $collection->getSelect()
                ->columns($this->buildColumns($collection));
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Collection $collection
     *
     * @return array
     */
    private function buildColumns(Collection $collection): array
    {
        try {
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
                            LIMIT 1
                        )',
                        $collection->getTable('sales_order')
                    )
                ),
                'total_orders_amount' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT SUM(grand_total)
                            FROM %s
                            WHERE customer_id = e.entity_id
                        )',
                        $collection->getTable('sales_order')
                    )
                ),
                'average_orders_amount' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT AVG(grand_total)
                            FROM %s
                            WHERE customer_id = e.entity_id
                        )',
                        $collection->getTable('sales_order')
                    )
                ),
                'most_used_payment_method' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT op.additional_information
                            FROM %s so
                            LEFT JOIN %s op ON op.parent_id = so.entity_id
                            WHERE so.customer_id = e.entity_id
                            GROUP BY op.method
                            ORDER BY COUNT(op.additional_information) DESC
                            LIMIT 1
                        )',
                        $collection->getTable('sales_order'),
                        $collection->getTable('sales_order_payment')
                    )
                ),
                'most_used_shipping_method' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT shipping_description
                            FROM %s
                            WHERE customer_id = e.entity_id
                            GROUP BY shipping_method
                            ORDER BY COUNT(shipping_description) DESC
                            LIMIT 1
                        )',
                        $collection->getTable('sales_order')
                    )
                ),
                'active_shopping_cart?' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT is_active
                            FROM %s
                            WHERE customer_id = e.entity_id AND is_active = 1
                            LIMIT 1
                        )',
                        $collection->getTable('quote')
                    )
                ),
                'shopping_cart_created_at' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT created_at
                            FROM %s
                            WHERE customer_id = e.entity_id AND is_active = 1
                            LIMIT 1
                        )',
                        $collection->getTable('quote')
                    )
                ),
                'shopping_cart_modified_at' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT updated_at
                            FROM %s
                            WHERE customer_id = e.entity_id AND is_active = 1
                            LIMIT 1
                        )',
                        $collection->getTable('quote')
                    )
                ),
                'products_in_shopping_cart' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT items_count
                            FROM %s
                            WHERE customer_id = e.entity_id AND is_active = 1
                            LIMIT 1
                        )',
                        $collection->getTable('quote')
                    )
                ),
                'last_product_wished_at' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT wi.added_at
                            FROM %s w
                            LEFT JOIN %s wi ON wi.wishlist_id = w.wishlist_id
                            WHERE w.customer_id = e.entity_id
                            ORDER BY wi.added_at DESC
                            LIMIT 1
                        )',
                        $collection->getTable('wishlist'),
                        $collection->getTable('wishlist_item')
                    )
                ),
                'total_products_in_wishlist' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT COUNT(*)
                            FROM %s w
                            LEFT JOIN %s wi ON wi.wishlist_id = w.wishlist_id
                            WHERE w.customer_id = e.entity_id
                            LIMIT 1
                        )',
                        $collection->getTable('wishlist'),
                        $collection->getTable('wishlist_item')
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
                        $collection->getTable('review'),
                        $collection->getTable('review_detail')
                    )
                ),
                'total_products_reviewed' => $this->getExpressionModel(
                    sprintf(
                        '(
                            SELECT COUNT(*)
                            FROM %s
                            WHERE customer_id = e.entity_id
                            LIMIT 1
                        )',
                        $collection->getTable('review_detail')
                    )
                )
            ];
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return [];
        }
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
}
