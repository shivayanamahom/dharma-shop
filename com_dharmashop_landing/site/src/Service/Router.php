<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\Service;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** Адреса без номеров: `<пункт меню>/<алиас страницы>`. */
class Router extends RouterView
{
    protected $name = 'dharmashop_landing';

    public function __construct($app, $menu, $categoryFactory, private readonly DatabaseInterface $db)
    {
        $landings = new RouterViewConfiguration('landings');
        $this->registerView($landings);
        $this->registerView((new RouterViewConfiguration('landing'))->setKey('id')->setParent($landings));
        parent::__construct($app, $menu);
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /** @return array<int, string> номер → алиас */
    public function getLandingSegment($id, $query): array
    {
        $alias = (string) $this->db->setQuery(
            $this->db->createQuery()->select($this->db->quoteName('alias'))->from($this->db->quoteName('#__dharmashop_landing_pages'))
                ->where($this->db->quoteName('id') . ' = :id')->where($this->db->quoteName('state') . ' = 1')->where($this->db->quoteName('broken') . ' = 0')->bind(':id', $id, ParameterType::INTEGER)
        )->loadResult();

        // Страницы нет или она закрыта: алиас не раскрываем, сегмент — номер (при разборе будет 404, а не пустой адрес).
        return [(int) $id => $alias !== '' ? $alias : (string) (int) $id];
    }

    public function getLandingId($segment, $query): int
    {
        return (int) $this->db->setQuery(
            $this->db->createQuery()->select($this->db->quoteName('id'))->from($this->db->quoteName('#__dharmashop_landing_pages'))
                ->where($this->db->quoteName('alias') . ' = :alias')->bind(':alias', $segment, ParameterType::STRING)
        )->loadResult();
    }
}
