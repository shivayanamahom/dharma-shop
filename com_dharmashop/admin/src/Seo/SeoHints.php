<?php

/**
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma
 * @license    GNU General Public License version 2 or later
 */

namespace Dharma\Component\DharmaShop\Administrator\Seo;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Подсказки «Из компонента (шаблон)» в пустых SEO-полях формы записи. */
final class SeoHints
{
    /** Поле формы => часть шаблона. Группа полей: у metadesc — корень, у остальных — params. */
    private const FIELDS = [
        ['meta_title', 'params', 'title'],
        ['metadesc', null, 'description'],
        ['breadcrumb_title', 'params', 'breadcrumb'],
        ['h1', 'params', 'h1'],
    ];

    public static function apply(Form $form, SeoSettings $seo, string $type): void
    {
        foreach (self::FIELDS as [$name, $group, $part]) {
            if (!$form->getField($name, $group)) {
                continue;
            }

            $form->setFieldAttribute($name, 'hint', Text::sprintf('COM_DHARMASHOP_SEO_FROM_COMPONENT', $seo->template($type, $part)), $group);
        }

        if ($field = $form->getField('robots', 'metadata')) {
            $form->setFieldAttribute('robots', 'description', Text::sprintf('COM_DHARMASHOP_SEO_ROBOTS_DESC', $seo->robots()), 'metadata');
        }
    }
}
