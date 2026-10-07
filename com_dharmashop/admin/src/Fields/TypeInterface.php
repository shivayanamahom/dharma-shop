<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Fields;
\defined('_JEXEC') or die;
/** Definition contract. Providers with value inputs also implement ValueTypeInterface. */
interface TypeInterface
{
    public function id(): string;
    public function label(): string;
    public function store(): string;
    public function settingsPath(): string;
    public function normalizeSettings(array $settings): array;
}
