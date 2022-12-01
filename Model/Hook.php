<?php

namespace Fygaro\FygaroPayment\Model;

use Exception;
use Fygaro\FygaroPayment\Model\Api\HookInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Transaction as DBTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception as ApiException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;

class Hook implements HookInterface
{
    /** @var string|null  */
    protected ?string $privateKey = null;
    /** @var string|null  */
    protected ?string $publicKey = null;

    /** @var Request  */
    protected Request $request;

    /** @var ScopeConfigInterface  */
    protected ScopeConfigInterface $scopeConfig;

    /** @var OrderRepositoryInterface  */
    protected OrderRepositoryInterface $orderRepository;

    /** @var TransactionBuilderInterface  */
    protected TransactionBuilderInterface $transactionBuilder;

    /** @var InvoiceService  */
    protected InvoiceService $invoiceService;

    /** @var DBTransaction  */
    protected DBTransaction $dbTransaction;

    /** @var OrderSender  */
    protected OrderSender $orderSender;

    /**
     * @param Request $request
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionBuilderInterface $transactionBuilder
     * @param InvoiceService $invoiceService
     * @param DBTransaction $dbTransaction
     * @param OrderSender $orderSender
     */
    public function __construct(
        Request $request,
        ScopeConfigInterface $scopeConfig,
        OrderRepositoryInterface $orderRepository,
        TransactionBuilderInterface $transactionBuilder,
        InvoiceService $invoiceService,
        DBTransaction $dbTransaction,
        OrderSender $orderSender
    )
    {
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->orderSender = $orderSender;
    }

    /**
     * @throws ApiException
     * @return string
     */
    protected function getPrivateKey(): string {
        if ($this->privateKey) {
            return $this->privateKey;
        }
        $this->privateKey = $this->scopeConfig->getValue('payment/fygaro/private_key');
        if (!$this->privateKey || trim($this->privateKey) === "") {
            throw new ApiException(__('Private key is not set'), 500);
        }
        return $this->privateKey;
    }

    /**
     * @param $jwt
     * @param $orderId
     * @return array
     * @throws ApiException
     */
    protected function validateJwt($jwt, $orderId): array
    {
        $supportedAlgorithms =  array(
            "HS256"=>"SHA256",
            "HS384"=>"SHA384",
            "HS512"=>"SHA512"
        );

        $secret = $this->getPrivateKey();

        // split the jwt
        $tokenParts = explode('.', $jwt);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        //json decode
        $payloadDecode = json_decode($payload);
        $headerDecode = json_decode($header);

        //check the alg
        if (!array_key_exists($headerDecode->alg, $supportedAlgorithms)) {
            throw new ApiException(__("JWT validation failed: Bad decode algorithm"), 400);
        } else {
            $format = $headerDecode->alg;
            $signatureAlgorithm = $supportedAlgorithms["$format"];
        }

        /** For testing purpose */
//        $payloadDecode->customReference = $orderId;

        //check reference exists and matches post data
        if(!isset($payloadDecode->customReference) || ($payloadDecode->customReference != $orderId)){
            throw new ApiException(__("JWT validation failed: Bad decode order_id"), 400);
        }

        //check number of segments
        if (count($tokenParts) !== 3) {
            throw new ApiException(__("JWT validation failed: Bad number of segments"), 400);
        }

        //check not before (nbf)
        if(isset($payloadDecode->nbf)){

            //Date format
            $today = date("Y-m-d");
            $nbf = date("Y-m-d", $payloadDecode->nbf);

            //Convert to unix
            $todayTime = strtotime($today);
            $nbfTime = strtotime($nbf);

            if($nbfTime > $todayTime){
                throw new ApiException(__("JWT validation failed: Bad not before (nbf)"), 400);
            }
        }

        //check token has not been created in the future (iat)
        if(isset($payloadDecode->iat)){
            //Date format
            $today = date("Y-m-d");
            $iat = date("Y-m-d",$payloadDecode->iat);

            //Convert to unix
            $todayTime = strtotime($today);
            $iatTime = strtotime($iat);

            if($iatTime > $todayTime){
                throw new ApiException(__("JWT validation failed: Token has been created in the future"), 400);
            }

        }

        //check expiration date
        if (isset($payloadDecode->exp)) {
            //Date format
            $today = date("Y-m-d");
            $expire = date("Y-m-d",$payloadDecode->exp);

            //Convert to unix
            $todayTime = strtotime($today);
            $expireTime = strtotime($expire);

            //Verify if expire
            if ($expireTime <= $todayTime) {
                //is expired
                $isTokenExpired = true;
            } else {
                //not expired
                $isTokenExpired = false;
            }

        } else {
            //No exp token
            $isTokenExpired = false;
        }

        if ($isTokenExpired) {
            throw new ApiException(__("JWT validation failed: Token is expired"));
        }

        // build a signature based on the header and payload using the secret

        // Encode Header to Base64Url String
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

        // Encode Payload to Base64Url String
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        // Create Signature Hash
        $signature = hash_hmac($signatureAlgorithm, $base64UrlHeader . "." . $base64UrlPayload, $secret, true);

        // Encode Signature to Base64Url String
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        // verify it matches the signature provided in the jwt
        $isSignatureValid = hash_equals($base64UrlSignature, $signatureProvided);

        if (!$isSignatureValid) {
            throw new ApiException(__("JWT validation failed: Signature is not valid"));
        }
        return (array)$payloadDecode;
    }

    /**
     * @param array $data
     * @return array
     */
    private function clearPaymentData(array $data): array
    {
        $newData = [];
        foreach ($data as $key => $value) {
            if (!is_numeric($value) && !is_string($value)) {
                continue;
            }
            $newData[$key] = $value;
        }
        return $newData;
    }

    /**
     * @param Order|null $order
     * @param array $paymentData
     * @return int
     * @throws Exception
     */
    protected function createTransaction(
        OrderInterface $order = null,
        array $paymentData = array()
    ): int
    {
        try {
            //get payment object from order object
            $paymentData = $this->clearPaymentData($paymentData);
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentData['reference']);
            $payment->setTransactionId($paymentData['reference']);
            $formattedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formattedPrice);
            //get the object of builder class
            $trans = $this->transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['reference'])
                ->setFailSafe(true)
                ->build(TransactionInterface::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            throw new ApiException(__("Failed to create transaction"), 503);
        }
    }

    /**
     * @param OrderInterface $order
     * @return OrderInterface
     * @throws LocalizedException
     * @throws Exception
     */
    protected function invoiceOrder(OrderInterface $order, array $paymentData): OrderInterface
    {
        $this->createTransaction($order, $paymentData);
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register()->pay();
        $transactionSave = $this->dbTransaction->addObject(
            $invoice
        )->addObject(
            $invoice->getOrder()
        );
        $transactionSave->save();
        $historyMessage[] = __('Invoice #%1 created.', $invoice->getIncrementId());
        $state = Order::STATE_PROCESSING;

        $order->addStatusHistoryComment(implode(' ', $historyMessage))
            ->setIsCustomerNotified(true);

        $order->setState($state);
        $order->setStatus($state);
        $this->orderRepository->save($order);
        $invoice->save();
        return $order;
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    public function sendNewOrderEmailFor(OrderInterface $order): void
    {
        $this->orderSender->send($order);
    }

    /**
     * @return array
     * @throws ApiException
     */
    public function execute(): array
    {
        $body = $this->request->getBodyParams();
        $orderId = $body['customReference'] ?? null;

        $jwt = $body['jwt'] ?? null;


        if (is_null($orderId)) {
            throw new ApiException(__('customReference is not specified'),400);
        }
        if (is_null($jwt)) {
            throw new ApiException(__('JWT is not specified'),400);
        }

        $paymentData = $this->validateJwt($jwt, $orderId);

        $order = $this->orderRepository->get($orderId);
        if (!$order) {
            throw new ApiException(__("Cannot find order with provided id"));
        }
        try {
            $this->invoiceOrder($order, $paymentData);
        }
        catch (Exception $e) {
            throw new ApiException(__("Cannot invoice order: ".$e->getMessage()));
        }
        if (!$order->getEmailSent()) {
            $this->sendNewOrderEmailFor($order);
        }

        return ['message' => 'Order successfully payed', 'code' => 200];
    }
}
