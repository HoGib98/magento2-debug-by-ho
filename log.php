<?php
/**
 * @license   MIT
 */

declare(strict_types=1);

if (!function_exists('hoLog')) {
    /**
     * a method made globally available to log to fire php
     *
     * @param string $message
     * @param array $context
     */
    function hoLog(string $message, array $context = [])
    {
        \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Ho\Firephp\Logger::class)
            ->debug($message, $context);
    }

    function hoLogBE($data)
    {
        \Ho\Firephp\Kint\Kint::dd($data);
    }
}
