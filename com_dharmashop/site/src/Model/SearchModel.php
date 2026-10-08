<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;

\defined('_JEXEC') or die;

/**
 * Отдельная страница результатов поиска (вид `search`, пункт меню «Поиск»): весь каталог (родитель — корень категорий), состав
 * определяет запрос `q` (логика поиска — в CategoryModel, она же работает на страницах категорий, брендов и бейджей), сортировка по
 * умолчанию — по релевантности.
 */
class SearchModel extends CategoryModel
{
    /** Сортировки поиска: по релевантности (по умолчанию) и обычные. */
    public const SORTS = ['relevance', 'price_asc', 'price_desc', 'new', 'title'];

    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::search();
    }
}
