<?php

namespace Adeelq\CustomerStatistics\Block\Adminhtml;

use IntlDateFormatter;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Escaper;
use Adeelq\CustomerStatistics\Helper\Statistics as StatisticsHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

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
        'active_shopping_cart?',
        'shopping_cart_created_at',
        'shopping_cart_modified_at',
        'products_in_shopping_cart',
        'last_product_wished_at',
        'total_products_in_wishlist',
        'last_product_reviewed_at',
        'total_products_reviewed',
    ];

    const DATE_TIME_COLUMNS = [
        'last_login_at',
        'last_logout_at',
        'last_visit_at',
        'last_ordered_at',
        'last_product_wished_at',
        'last_product_reviewed_at',
        'shopping_cart_created_at',
        'shopping_cart_modified_at'
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
     * @var UrlInterface
     */
    private UrlInterface $backendUrl;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var CustomerInterface
     */
    private CustomerInterface $customer;

    /**
     * @var WebsiteInterface
     */
    private WebsiteInterface $website;

    /**
     * @var Timezone
     */
    private Timezone $timezoneConverter;

    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param StatisticsHelper $statisticsHelper
     * @param UrlInterface $backendUrl
     * @param StoreManagerInterface $storeManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param Timezone $timezoneConverter
     * @param array $data
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        StatisticsHelper $statisticsHelper,
        UrlInterface $backendUrl,
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepository,
        Timezone $timezoneConverter,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->escaper = $escaper;
        $this->statisticsHelper = $statisticsHelper;
        $this->backendUrl = $backendUrl;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->timezoneConverter = $timezoneConverter;
    }

    /**
     * @return array
     */
    public function getTableHeaders(): array
    {
        try {
            return ['Statistic Name', 'Statistic Value'];
        } catch (Throwable $e) {
            $this->statisticsHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @return array
     */
    public function getTableRows(): array
    {
        try {
            $customer = $this->getCustomer();
            $statsArr = $this->statisticsHelper
                ->getCustomerStats(
                    $customer->getId(),
                    array_keys($this->getWebsite($customer->getWebsiteId())->getStoreIds())
                );
            $rows = array_intersect_key($statsArr, array_combine(self::COLUMNS, self::COLUMNS));
            $processedArr = [];
            foreach ($rows as $name => $value) {
                $processedArr[$this->getKey($name)] = $value ? $this->getValue($name, $value) : '';
            }
            return $processedArr;
        } catch (Throwable $e) {
            $this->statisticsHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @return string
     *
     */
    public function getCustomerName(): string
    {
        try {
            $customer = $this->getCustomer();
            return sprintf('%s %s', $customer->getFirstname(), $customer->getLastname());
        } catch (Throwable $e) {
            $this->statisticsHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @return string
     */
    public function getCustomerLink(): string
    {
        return $this->backendUrl->getUrl('customer/index/edit', ['id' => $this->getCustomerId()]);
    }

    /**
     * @return string
     */
    public function getWebsiteName(): string
    {
        try {
            return $this->getWebsite($this->getCustomer()->getWebsiteId())->getName();
        } catch (Throwable $e) {
            $this->statisticsHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @return string
     */
    public function getWebsiteCurrency(): string
    {
        try {
            return $this->getWebsite($this->getCustomer()->getWebsiteId())->getBaseCurrencyCode();
        } catch (Throwable $e) {
            $this->statisticsHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return float|string
     */
    private function getValue(string $name, string $value): float|string
    {
        try {
            if ($name === 'active_shopping_cart?') {
                $value = __('Yes');
            } elseif ($name === 'most_used_payment_method') {
                $decodedArr = json_decode($value, true);
                $value = ! empty($decodedArr['method_title']) ? $decodedArr['method_title'] : '';
            } elseif (in_array($name, self::DATE_TIME_COLUMNS) && $value) {
                $value = $this->convertDatetime($value);
            } elseif (is_numeric($value)) {
                $value = round($value, 2);
            }
            return $value ?: '';
        } catch (Throwable $e) {
            $this->statisticsHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function getKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * @return int
     */
    private function getCustomerId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }

    /**
     * @return CustomerInterface
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCustomer(): CustomerInterface
    {
        if (! isset($this->customer)) {
            $this->customer = $this->customerRepository->getById($this->getCustomerId());
        }
        return $this->customer;
    }

    /**
     * @param int $websiteId
     *
     * @return WebsiteInterface
     *
     * @throws LocalizedException
     */
    private function getWebsite(int $websiteId): WebsiteInterface
    {
        if (! isset($this->website)) {
            $this->website = $this->storeManager->getWebsite($websiteId);
        }
        return $this->website;
    }

    /**
     * @param string $dateTime
     *
     * @return string
     */
    private function convertDatetime(string $dateTime): string
    {
        try {
            $configTimeZone = $this->timezoneConverter->getConfigTimezone('website', $this->getCustomer()->getWebsiteId());
            return $this->formatDate($dateTime, IntlDateFormatter::MEDIUM, true, $configTimeZone);
        } catch (Throwable $e) {
            $this->statisticsHelper->logError(__METHOD__, $e);
            return $dateTime;
        }
    }
}
