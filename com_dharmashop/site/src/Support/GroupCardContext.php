<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Signed public listing scope. The AJAX request cannot silently drop the original filter or catalog rules. */
final class GroupCardContext
{
    private const PARAMS = ['product_card_images', 'product_card_cart_mode', 'product_card_qty', 'product_card_intro'];

    public static function encode(array $scope, Registry $params, string $mode, array $columns): string
    {
        $display = [];
        foreach (self::PARAMS as $key) { $display[$key] = $params->get($key); }
        $body = rtrim(strtr(base64_encode(json_encode(['scope' => $scope, 'params' => $display, 'mode' => $mode, 'columns' => $columns], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        return $body . '.' . hash_hmac('sha256', $body, (string) Factory::getApplication()->get('secret'));
    }

    public static function decode(string $token): array
    {
        if (strlen($token) > 32768 || !preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D', $token, $parts)
            || !hash_equals(hash_hmac('sha256', $parts[1], (string) Factory::getApplication()->get('secret')), $parts[2])) {
            throw new \RuntimeException('Invalid group card context', 400);
        }
        $data = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !in_array($data['scope']['view'] ?? '', ['category', 'brand', 'badge', 'search'], true)
            || !in_array($data['mode'] ?? '', ['grid', 'list', 'table'], true)) {
            throw new \RuntimeException('Invalid group card context', 400);
        }
        return $data;
    }
}
