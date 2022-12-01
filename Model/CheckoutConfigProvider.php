<?php
namespace Fygaro\FygaroPayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    /** @var ScopeConfigInterface  */
    protected ScopeConfigInterface $scopeConfig;

    /** @var StoreManagerInterface  */
    protected StoreManagerInterface $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {

        return [
            'payment' => [
                'fygaro' => [
//                    'button_url' => $this->scopeConfig->getValue('payment/fygaro/button_url'),
//                    'private_key' => $this->scopeConfig->getValue('payment/fygaro/private_key'),
//                    'public_key' => $this->scopeConfig->getValue('payment/fygaro/public_key'),
//                    'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
//                    'language' => strstr($this->storeManager->getStore()->getLocaleCode(), '_', true),
        ]
            ]
        ];
    }
}
