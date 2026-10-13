<?php
/**
 * Уровни автоматической скидки: общий Bootstrap-макет корзины и checkout.
 * displayData: progress (данные onDharmaShopCartTotals), money (MoneyFormatter).
 * Переопределение: html/layouts/components/dharmashop/cart/promotion-progress.php.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$progress = (array) ($displayData['progress'] ?? []);
if (empty($progress['tiers'])) {
    return;
}
$money = $displayData['money'];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tiers = array_merge([[
    'discount_label' => '0%',
    'threshold_label' => $money->format('0'),
    'reached' => true,
    'current' => $progress['current_discount_label'] === null,
]], $progress['tiers']);
$last = count($tiers) - 1;
?>
<section class="card border-0 rounded-3 shadow-sm mb-3 fs-6 dharmaPromotionProgress" role="status" aria-label="<?php echo $escape(Text::_('PLG_DHARMASHOPPROMOTION_THRESHOLD_TITLE')); ?>">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h3 class="fs-6 fw-bold mb-0"><?php echo Text::_('PLG_DHARMASHOPPROMOTION_THRESHOLD_TITLE'); ?></h3>
            <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2"><?php echo $progress['current_discount_label'] !== null
                ? Text::sprintf('PLG_DHARMASHOPPROMOTION_THRESHOLD_CURRENT', $escape((string) $progress['current_discount_label']))
                : Text::_('PLG_DHARMASHOPPROMOTION_THRESHOLD_NOT_REACHED'); ?></span>
        </div>
        <p class="small text-body-secondary mb-4"><?php echo Text::_('PLG_DHARMASHOPPROMOTION_THRESHOLD_SUBTITLE'); ?></p>
        <div class="overflow-x-auto mb-3 pb-1">
            <ol class="list-unstyled d-flex flex-nowrap mb-0" aria-label="<?php echo $escape(Text::_('PLG_DHARMASHOPPROMOTION_THRESHOLD_TIERS_LABEL')); ?>">
                <?php foreach ($tiers as $index => $tier) : ?>
                    <li class="flex-grow-1 flex-shrink-0 text-center"<?php echo $tier['current'] ? ' aria-current="step"' : ''; ?>>
                        <div class="small fw-bold px-1 mb-2 text-nowrap <?php echo $tier['current'] ? 'text-primary' : 'text-body-secondary'; ?>"><?php echo $escape((string) $tier['discount_label']); ?></div>
                        <div class="d-flex align-items-center mb-2" aria-hidden="true">
                            <span class="flex-grow-1 border-top border-2 <?php echo $index === 0 ? 'invisible' : ($tier['reached'] ? 'border-primary' : 'border-secondary-subtle'); ?>"></span>
                            <span class="d-inline-block flex-shrink-0 rounded-circle border border-3 p-2 <?php echo $tier['current']
                                ? 'bg-primary border-primary-subtle shadow-sm'
                                : ($tier['reached'] ? 'bg-body border-primary-subtle' : 'bg-secondary-subtle border-light'); ?>"></span>
                            <span class="flex-grow-1 border-top border-2 <?php echo $index === $last ? 'invisible' : (!empty($tiers[$index + 1]['reached']) ? 'border-primary' : 'border-secondary-subtle'); ?>"></span>
                        </div>
                        <div class="small text-nowrap <?php echo $tier['current'] ? 'text-primary' : 'text-body-secondary'; ?>"><?php echo $tier['current']
                            ? Text::_('PLG_DHARMASHOPPROMOTION_THRESHOLD_YOUR_LEVEL')
                            : Text::sprintf('PLG_DHARMASHOPPROMOTION_THRESHOLD_FROM', $escape((string) $tier['threshold_label'])); ?></div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="d-flex align-items-center gap-3 rounded-3 bg-primary-subtle p-3">
            <span class="d-inline-flex flex-shrink-0 rounded-circle bg-primary bg-opacity-10 text-primary p-3" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M5 14h22v14H5zM3 9h26v5H3zM16 9v19"/><path d="M16 9H10a4 4 0 1 1 4-4l2 4Zm0 0h6a4 4 0 1 0-4-4l-2 4Z"/></svg>
            </span>
            <div class="border-start border-primary-subtle ps-3">
                <div class="small text-body-secondary"><?php echo $progress['complete']
                    ? Text::_('PLG_DHARMASHOPPROMOTION_THRESHOLD_MAXIMUM')
                    : Text::sprintf('PLG_DHARMASHOPPROMOTION_THRESHOLD_REMAINING', $escape((string) $progress['next_discount_label'])); ?></div>
                <div class="fs-4 fw-bold text-primary lh-sm mt-1 text-break"><?php echo $escape($progress['complete']
                    ? (string) $progress['current_discount_label']
                    : $money->format((string) $progress['remaining'])); ?></div>
            </div>
        </div>
    </div>
</section>
