<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Installer;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Начальные настройки первой установки: без контактных полей оформление не получает ни имени, ни телефона, ни e-mail покупателя
 * (заказ нельзя обработать, письма покупателю не уйдут). Поля создаются один раз, только если параметр `checkout_fields` не задан;
 * позже владелец меняет их в «Настройки → Оформление». Согласие на обработку персональных данных не добавляется: нужен свой текст и
 * ссылка на документ. Подписи — на языке интерфейса, с которого ставят пакет (русский или английский).
 */
final class DefaultSettings
{
    /** @return array<string, array<string, mixed>> строки подформы `checkout_fields` */
    public static function checkoutFields(bool $russian): array
    {
        $labels = $russian
            ? ['name' => 'Имя', 'phone' => 'Телефон', 'email' => 'E-mail', 'comment' => 'Комментарий к заказу']
            : ['name' => 'Name', 'phone' => 'Phone', 'email' => 'Email', 'comment' => 'Order comment'];
        $rows = [];

        foreach ([['text', 'name', 1], ['tel', 'phone', 1], ['email', 'email', 1], ['textarea', 'comment', 0]] as $index => [$type, $name, $required]) {
            $row = ['type' => $type, 'name' => $name, 'label' => $labels[$name], 'show_label' => 1, 'required' => $required, 'enabled' => 1];

            if ($type === 'tel') {
                $row['mask'] = '+7 (###) ###-##-##';
            }

            $rows['checkout_fields' . $index] = $row;
        }

        return $rows;
    }

    /** Добавляет контактные поля, если владелец ещё ни разу не сохранял их определение. */
    public static function apply(DatabaseInterface $db): bool
    {
        $where = [$db->quoteName('type') . ' = ' . $db->quote('component'), $db->quoteName('element') . ' = ' . $db->quote('com_dharmashop')];
        $params = json_decode((string) $db->setQuery($db->getQuery(true)->select($db->quoteName('params'))->from($db->quoteName('#__extensions'))->where($where))->loadResult(), true);
        $params = is_array($params) ? $params : [];

        if (!empty($params['checkout_fields'])) {
            return false;
        }

        $tag = (string) Factory::getApplication()->getLanguage()->getTag();
        $params['checkout_fields'] = self::checkoutFields(str_starts_with($tag, 'ru'));
        $db->setQuery(
            $db->getQuery(true)->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params, JSON_UNESCAPED_UNICODE)))
                ->where($where)
        )->execute();

        return true;
    }
}
