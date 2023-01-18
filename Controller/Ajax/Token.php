<?php
namespace Fygaro\FygaroPayment\Controller\Ajax;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Token
 */
class Token implements HttpGetActionInterface
{
    /** @var PageFactory  */
    protected PageFactory $pageFactory;

    /** @var RequestInterface  */
    protected RequestInterface $request;

    /** @var StoreManagerInterface  */
    protected StoreManagerInterface $storeManager;

    /** @var ResultFactory  */
    protected ResultFactory $resultFactory;

    /** @var UrlInterface  */
    protected UrlInterface $url;

    /** @var OrderRepositoryInterface  */
    protected OrderRepositoryInterface $orderRepository;

    /** @var JsonFactory  */
    protected JsonFactory $jsonFactory;

    /** @var Session  */
    protected Session $checkoutSession;

    /** @var ScopeConfigInterface  */
    protected ScopeConfigInterface $scopeConfig;

    /** @var Resolver  */
    protected Resolver $locale;

    /**
     * @param PageFactory $pageFactory
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param OrderRepositoryInterface $orderRepository
     * @param JsonFactory $jsonFactory
     * @param Session $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param Resolver $locale
     */
    public function __construct
    (
        PageFactory $pageFactory,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        ResultFactory $resultFactory,
        UrlInterface $url,
        OrderRepositoryInterface $orderRepository,
        JsonFactory $jsonFactory,
        Session $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        Resolver $locale
    )
    {
        $this->pageFactory = $pageFactory;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->locale = $locale;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $jsonResult = $this->jsonFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId()) {
            return $jsonResult
                ->setData(['message' => "Cannot find order"])
                ->setHttpResponseCode(500);
        }

        //Get Order Total
        $total = number_format($order->getGrandTotal(), 2, '.', '');
        $currency = $order->getOrderCurrency()->getCode();

        //Get BaseURL
        $baseUrl = $this->scopeConfig->getValue('payment/fygaro/button_url');

        $publicKey = $this->scopeConfig->getValue('payment/fygaro/public_key');
        if (!$publicKey || empty(trim($publicKey))) {
            return $jsonResult
                ->setData(['message' => "Fygaro payment is not set correctly. Please, contact support"])
                ->setHttpResponseCode(500);
        }
        $privateKey = $this->scopeConfig->getValue('payment/fygaro/private_key');
        if (!$privateKey || empty(trim($privateKey))) {
            return $jsonResult
                ->setData(['message' => "Fygaro payment is not set correctly. Please, contact support"])
                ->setHttpResponseCode(500);
        }

        //Update language

        $buttonId = substr($baseUrl, strrpos($baseUrl, '/pb/' )+1);

        $currentLanguage = $this->locale->getLocale();
        if( str_contains($currentLanguage, "es") ){
            $baseUrl = "https://www.fygaro.com/es/".$buttonId;
        }else {
            $baseUrl = "https://www.fygaro.com/en/".$buttonId;
        }

        /*
         * Create the JWT
        */

        // Create token header as a JSON string
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256', 'kid' => $publicKey]);

        $billingAddress = $order->getBillingAddress();

        // Create token payload as a JSON string
        $payload = json_encode(
            [
                'amount' => $total,
                'currency' => $currency,
                'custom_reference' => (string)$order->getId(),
                'clientData' => [
                    'email' => $order->getCustomerEmail(),
                    'name' => $billingAddress->getFirstname()." ".$billingAddress->getLastname(),
                    'phone' => $billingAddress->getTelephone()
                ]
            ]
        );

        // Encode Header to Base64Url String
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

        // Encode Payload to Base64Url String
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        // Create Signature Hash
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $privateKey, true);

        // Encode Signature to Base64Url String
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        // Create JWT
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        return $jsonResult->setData(['url' => $baseUrl."?jwt=".$jwt]);
    }
}
