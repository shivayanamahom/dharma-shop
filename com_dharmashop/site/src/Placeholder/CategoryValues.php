<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Placeholder;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Model\CategoriesModel;

\defined('_JEXEC') or die;

/** Значения подстановок страницы категории (реестр — docs/SHORTCODES.md); цены и счётчик вычисляются, только если код есть в шаблоне. */
final class CategoryValues
{
    /**
     * @param   string           $area       category, brand или badge
     * @param   object[]         $ancestors  предки от корня к родителю
     *
     * @return  array<string, string|callable>
     */
    public static function build(string $area, object $item, array $ancestors, CategoriesModel $model, MoneyFormatter $money): array
    {
        $id = (int) $item->id;
        $range = null;
        $count = null;
        $getRange = static function () use (&$range, $model, $id): array {
            return $range ??= $model->getPriceRange($id);
        };

        return [
            $area . '.title' => (string) $item->title,
            $area . '.parent' => $ancestors ? (string) end($ancestors)->title : '',
            $area . '.introtext' => static fn (): string => mb_substr(trim(strip_tags((string) $item->params->get('introtext', ''))), 0, 200),
            $area . '.price_min' => static fn (): string => $money->price($getRange()['min']),
            $area . '.price_max' => static fn (): string => $money->price($getRange()['max']),
            $area . '.count' => static fn (): string => $model->getCountLabel($id),
            // Краткое описание без разметки и сущностей, до 200 символов.
            $area . '.introtext' => static function () use ($item): string {
                $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $item->params->get('introtext', ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));

                return mb_strlen($plain) > 200 ? rtrim(mb_substr($plain, 0, 199)) . '…' : $plain;
            },
        ];
    }
}
