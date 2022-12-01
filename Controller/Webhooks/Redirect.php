<?php
namespace Fygaro\FygaroPayment\Controller\Webhooks;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;

/**
 * Class Index
 */
class Redirect implements HttpGetActionInterface
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

    /** @var Session  */
    protected Session $checkoutSession;

    /**
     * @param PageFactory $pageFactory
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct
    (
        PageFactory $pageFactory,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        ResultFactory $resultFactory,
        UrlInterface $url,
        OrderRepositoryInterface $orderRepository,
        Session $checkoutSession
    )
    {
        $this->pageFactory = $pageFactory;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @inheritdoc
     * @throws NoSuchEntityException
     */
    public function execute(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId()) {
            $redirect->setUrl($this->url->getUrl('noroute'));
            return $redirect;
        }

        $store = $this->storeManager->getStore();
        $baseUrl = $store->getBaseUrl();
        if ($order->getStatus() !== Order::STATE_PROCESSING && $order->getStatus() !== Order::STATE_COMPLETE) {
            $redirect->setUrl($baseUrl.'checkout/onepage/failure');
        }
        else {
            $redirect->setUrl($baseUrl.'checkout/onepage/success');
        }
        return $redirect;
    }
}
