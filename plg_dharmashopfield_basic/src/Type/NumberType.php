<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
\defined('_JEXEC') or die;
final class NumberType extends BaseType
{
    public function id(): string { return 'number'; }
    public function store(): string { return 'number'; }
    public function normalizeSettings(array $settings): array
    {
        return $this->validateNumberSettings(parent::normalizeSettings($settings), 'number');
    }
}
