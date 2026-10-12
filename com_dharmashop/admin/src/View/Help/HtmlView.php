<?php

/**
 * Dharma Shop — справка администратора.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Help;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/** Лёгкая стартовая страница пользовательской справки. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    /** @var array<int, array{titleKey: string, items: array<int, array{slug: string, title: string}>}> */
    public array $helpGroups = [];

    public string $articleSlug = 'HELP';

    public string $articleTitle = '';

    public string $articleHtml = '';

    public function display($tpl = null): void
    {
        $directory = JPATH_ADMINISTRATOR . '/components/com_dharmashop/help';
        $manifestPath = $directory . '/manifest.json';
        $manifest = is_file($manifestPath)
            ? json_decode((string) file_get_contents($manifestPath), true)
            : null;

        $this->helpGroups = is_array($manifest['groups'] ?? null) ? $manifest['groups'] : [];
        $this->articleSlug = strtoupper(Factory::getApplication()->getInput()->getCmd('article', 'HELP'));

        foreach ($this->helpGroups as $group) {
            foreach ($group['items'] ?? [] as $article) {
                if (($article['slug'] ?? '') !== $this->articleSlug) {
                    continue;
                }

                $this->articleTitle = (string) ($article['title'] ?? $this->articleSlug);
                $articlePath = $directory . '/articles/' . $this->articleSlug . '.html';
                $this->articleHtml = is_file($articlePath) ? (string) file_get_contents($articlePath) : '';
                break 2;
            }
        }

        if ($this->articleTitle === '' && $this->helpGroups) {
            $firstArticle = $this->helpGroups[0]['items'][0] ?? null;
            if (is_array($firstArticle)) {
                $this->articleSlug = (string) $firstArticle['slug'];
                $this->articleTitle = (string) $firstArticle['title'];
                $articlePath = $directory . '/articles/' . $this->articleSlug . '.html';
                $this->articleHtml = is_file($articlePath) ? (string) file_get_contents($articlePath) : '';
            }
        }

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_HELP_TITLE'), 'question-circle');

        parent::display($tpl);
    }
}
