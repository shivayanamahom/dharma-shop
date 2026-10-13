<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopPromotion\Threshold\Extension;
use Dharma\Component\DharmaShop\Administrator\Promotion\TypeRegistry;
use Dharma\Plugin\DharmaShopPromotion\Threshold\Type\ThresholdType;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
final class Threshold extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected $allowLegacyListeners = false;
    public static function getSubscribedEvents(): array { return ['onDharmaShopPromotionTypesCollect' => 'registerTypes', 'onDharmaShopCartTotals' => 'addProgress']; }
    public function registerTypes(EventInterface $event): void
    {
        $registry = $event->getArgument('registry');
        if ($registry instanceof TypeRegistry) $registry->register(new ThresholdType());
    }

    /** Adds every discount level and progress to the shared cart totals event. */
    public function addProgress(EventInterface $event): void
    {
        $subtotal = max(0.0, (float) $event->getArgument('subtotal', 0));
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select(['title', 'access', 'starts', 'ends', 'params'])
            ->from($db->quoteName('#__dharmashop_promotions'))
            ->where('plugin=' . $db->quote('threshold'))->where('state=1')->where('code IS NULL');
        $levels = array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels());
        if (!$levels) return;
        $query->where('access IN (' . implode(',', $levels) . ')');
        $now = Factory::getDate()->toSql();
        $query->where('(starts IS NULL OR starts <= ' . $db->quote($now) . ')')->where('(ends IS NULL OR ends >= ' . $db->quote($now) . ')');
        $bestNext = null;
        $bestComplete = null;
        $money = new \Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter(\Joomla\CMS\Component\ComponentHelper::getParams('com_dharmashop'));
        foreach ($db->setQuery($query)->loadObjectList() as $campaign) {
            $params = (new Registry((string) $campaign->params))->toArray();
            $tiers = (array) ($params['tiers'] ?? []);
            if ((int) ($params['show_progress'] ?? 1) !== 1) continue;
            $tiers = array_values(array_filter($tiers, 'is_array'));
            usort($tiers, static fn(array $a, array $b): int => (float) ($a['minimum_subtotal'] ?? 0) <=> (float) ($b['minimum_subtotal'] ?? 0));
            if (!$tiers) continue;

            $nextIndex = null;
            $currentIndex = null;
            foreach ($tiers as $index => $tier) {
                if ((float) ($tier['minimum_subtotal'] ?? 0) > $subtotal + 0.0001) {
                    $nextIndex = $index;
                    break;
                }
                $currentIndex = $index;
            }

            foreach ($tiers as $index => &$tier) {
                $discountType = (string) ($tier['discount_type'] ?? 'percent');
                $discountValue = (float) ($tier['discount_value'] ?? $tier['discount_percent'] ?? 0);
                $tier['discount_label'] = $discountType === 'amount'
                    ? $money->format((string) $discountValue)
                    : rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.') . '%';
                $tier['threshold_label'] = $money->format((string) ((float) ($tier['minimum_subtotal'] ?? 0)));
                $tier['reached'] = $index <= ($currentIndex ?? -1);
                $tier['current'] = $index === $currentIndex;
            }
            unset($tier);

            $complete = $nextIndex === null;
            $maximumThreshold = (float) $tiers[array_key_last($tiers)]['minimum_subtotal'];
            $overallProgress = min(100, max(0, (int) floor($subtotal / max(0.01, $maximumThreshold) * 100)));
            if ($complete) {
                $remaining = 0.0;
                $progressPercent = 100;
                $nextDiscountLabel = null;
            } else {
                $nextTier = $tiers[$nextIndex];
                $threshold = (float) $nextTier['minimum_subtotal'];
                $remaining = round(max(0, $threshold - $subtotal), 2);
                $progressPercent = $overallProgress;
                $nextDiscountLabel = (string) $nextTier['discount_label'];
            }

            $candidate = [
                'title' => (string) $campaign->title,
                'tiers' => $tiers,
                'current_discount_label' => $currentIndex === null ? null : (string) $tiers[$currentIndex]['discount_label'],
                'next_discount_label' => $nextDiscountLabel,
                'remaining' => $remaining,
                'progress' => $progressPercent,
                'complete' => $complete,
            ];
            if ($complete) {
                $bestComplete ??= $candidate;
            } elseif ($bestNext === null || $candidate['remaining'] < $bestNext['remaining']) {
                $bestNext = $candidate;
            }
        }
        $best = $bestNext ?? $bestComplete;
        if ($best !== null) $event->setArgument('promotion_progress', $best);
    }
}
