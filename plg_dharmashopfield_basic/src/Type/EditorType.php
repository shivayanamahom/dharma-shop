<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
\defined('_JEXEC') or die;
final class EditorType extends BaseType
{
    public function id(): string { return 'editor'; }
    public function store(): string { return 'text'; }
}
