<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopPromotion\Threshold\Type;
use Dharma\Component\DharmaShop\Administrator\Promotion\Context;
use Dharma\Component\DharmaShop\Administrator\Promotion\PromotionTypeInterface;
use Dharma\Component\DharmaShop\Administrator\Promotion\Result;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
final class ThresholdType implements PromotionTypeInterface
{
    public function id(): string { return 'threshold'; }
    public function label(): string { return 'PLG_DHARMASHOPPROMOTION_THRESHOLD'; }
    public function description(): string { return 'PLG_DHARMASHOPPROMOTION_THRESHOLD_DESC'; }
    public function settingsPath(): string { return dirname(__DIR__, 2) . '/forms/settings.xml'; }
    public function requiresCode(): bool { return false; }

    /** Короткое представление порогов для общего списка акций в админке. */
    public function listSummary(array $settings, string $codes = ''): string
    {
        $tiers = array_values(array_filter((array) ($settings['tiers'] ?? []), 'is_array'));
        usort($tiers, static fn(array $a, array $b): int => (float) ($a['minimum_subtotal'] ?? 0) <=> (float) ($b['minimum_subtotal'] ?? 0));
        $money = new \Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter(\Joomla\CMS\Component\ComponentHelper::getParams('com_dharmashop'));
        $summary = [];
        foreach ($tiers as $tier) {
            $minimum = (float) ($tier['minimum_subtotal'] ?? 0);
            $type = (string) ($tier['discount_type'] ?? 'percent');
            $value = (float) ($tier['discount_value'] ?? $tier['discount_percent'] ?? 0);
            if ($minimum <= 0 || $value <= 0) continue;
            $discount = $type === 'amount'
                ? $money->format((string) $value)
                : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
            $from = Text::sprintf('PLG_DHARMASHOPPROMOTION_THRESHOLD_FROM', $money->format((string) $minimum));
            $summary[] = Text::sprintf('PLG_DHARMASHOPPROMOTION_THRESHOLD_SUMMARY_TIER', $from, $discount);
        }

        return $summary ? implode('; ', $summary) : '—';
    }

    public function normalizeSettings(array $settings): array
    {
        $rows = $settings['tiers'] ?? [];
        if (!is_array($rows) || !$rows) throw new \DomainException('PLG_DHARMASHOPPROMOTION_THRESHOLD_INVALID');
        $tiers = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $minimum = filter_var(str_replace(',', '.', trim((string) ($row['minimum_subtotal'] ?? ''))), FILTER_VALIDATE_FLOAT);
            // Keep campaigns created before the amount option readable and editable.
            $discountType = (string) ($row['discount_type'] ?? 'percent');
            $rawValue = $row['discount_value'] ?? $row['discount_percent'] ?? '';
            $value = filter_var(str_replace(',', '.', trim((string) $rawValue)), FILTER_VALIDATE_FLOAT);
            if ($minimum === false || !is_finite((float) $minimum) || $minimum <= 0 || $minimum > 1000000000000
                || !in_array($discountType, ['percent', 'amount'], true)
                || $value === false || !is_finite((float) $value) || $value <= 0
                || ($discountType === 'percent' && $value > 100) || $value > 1000000000000) {
                throw new \DomainException('PLG_DHARMASHOPPROMOTION_THRESHOLD_INVALID');
            }
            $minimum = round((float) $minimum, 2);
            $key = number_format($minimum, 2, '.', '');
            if (isset($seen[$key])) throw new \DomainException('PLG_DHARMASHOPPROMOTION_THRESHOLD_INVALID');
            $seen[$key] = true;
            $tiers[] = ['minimum_subtotal' => $minimum, 'discount_type' => $discountType, 'discount_value' => round((float) $value, 2)];
        }
        if (!$tiers) throw new \DomainException('PLG_DHARMASHOPPROMOTION_THRESHOLD_INVALID');
        usort($tiers, static fn(array $a, array $b): int => $a['minimum_subtotal'] <=> $b['minimum_subtotal']);
        return ['tiers' => $tiers, 'show_progress' => (int) ($settings['show_progress'] ?? 1) === 1 ? 1 : 0];
    }

    public function apply(object $promotion, Context $context): ?Result
    {
        $tiers = (array) (($promotion->params['tiers'] ?? []));
        $qualified = null;
        foreach ($tiers as $tier) {
            if (!is_array($tier)) continue;
            if ($context->subtotal + 0.0001 >= (float) ($tier['minimum_subtotal'] ?? PHP_FLOAT_MAX)) $qualified = $tier;
        }
        if ($qualified === null || $context->subtotal <= 0) return null;
        $discountType = (string) ($qualified['discount_type'] ?? 'percent');
        $value = (float) ($qualified['discount_value'] ?? $qualified['discount_percent'] ?? 0);
        $discount = $discountType === 'amount' ? round($value, 2) : round($context->subtotal * $value / 100, 2);
        if ($discount <= 0) return null;
        $displayValue = $discountType === 'amount'
            ? (new \Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter(\Joomla\CMS\Component\ComponentHelper::getParams('com_dharmashop')))->format((string) $value)
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
        $title = Text::sprintf('PLG_DHARMASHOPPROMOTION_THRESHOLD_DISCOUNT', $displayValue);
        return new Result([['type' => 'discount', 'code' => 'promotion:threshold:' . (int) $promotion->id, 'title' => $title, 'amount' => -$discount]], [
            'minimum_subtotal' => (float) $qualified['minimum_subtotal'],
            'discount_type' => $discountType,
            'discount_value' => $value,
            'discount' => $discount,
        ]);
    }
}
