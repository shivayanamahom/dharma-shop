<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
\defined('_JEXEC') or die;
final class RangeType extends BaseType
{
    public function id(): string { return 'range'; }
    public function store(): string { return 'range'; }
    public function normalizeSettings(array $settings): array
    {
        return $this->validateNumberSettings(parent::normalizeSettings($settings), 'range');
    }
}
