<?php
namespace Fygaro\FygaroPayment\Model\Api;

use Magento\Framework\Controller\Result\Json;

/**
 * @api
 */
interface HookInterface
{
    /**
     * Execute webhook
     *
     * @return array
     */
    public function execute(): array;
}
