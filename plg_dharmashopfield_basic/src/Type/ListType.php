<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
\defined('_JEXEC') or die;
final class ListType extends BaseType
{
    public function id(): string { return 'list'; }
    public function store(): string { return 'option'; }
}
