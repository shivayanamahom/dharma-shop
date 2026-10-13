<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Promotion;

\defined('_JEXEC') or die;

/** Тип акции, регистрируемый включённым плагином группы dharmashoppromotion. */
interface PromotionTypeInterface
{
    public function id(): string;
    public function label(): string;
    public function description(): string;
    public function settingsPath(): string;
    /** True для акций, которые покупатель должен активировать кодом; false для автоматических типов. */
    public function requiresCode(): bool;
    /** Реализация может дополнительно предоставить listSummary(array $settings, string $codes): string для списка акций. */
    /** @throws \DomainException с языковым ключом при некорректных параметрах */
    public function normalizeSettings(array $settings): array;
    /** null означает, что акция не может примениться к этому снимку корзины. */
    public function apply(object $promotion, Context $context): ?Result;
}
