<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Promotion;

\defined('_JEXEC') or die;

/** Проверенный результат типа акции; сумма корректировки отрицательна для скидки. */
final class Result
{
    /** @param array<int, array{type:string,code:string,title:string,amount:float}> $adjustments */
    public function __construct(
        public readonly array $adjustments,
        public readonly array $snapshot = [],
    ) {}
}
