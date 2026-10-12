<?php

/**
 * Dharma Shop — пользовательская и разработческая справка.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Help\HtmlView $this */
?>
<div class="container-fluid px-0">
    <?php if (!$this->helpGroups) : ?>
        <div class="alert alert-warning" role="alert">
            <?php echo Text::_('COM_DHARMASHOP_HELP_DOCS_MISSING'); ?>
        </div>
    <?php else : ?>
        <p class="text-muted"><?php echo Text::_('COM_DHARMASHOP_HELP_LIBRARY_INTRO'); ?></p>
        <div class="row g-4">
            <aside class="col-12 col-xl-3" aria-label="<?php echo Text::_('COM_DHARMASHOP_HELP_TOC_LABEL', true); ?>">
                <div class="card">
                    <div class="card-body p-0">
                        <?php foreach ($this->helpGroups as $group) : ?>
                            <section class="p-3">
                                <h2 class="h5"><?php echo Text::_((string) ($group['titleKey'] ?? '')); ?></h2>
                                <nav class="list-group list-group-flush">
                                    <?php foreach ($group['items'] ?? [] as $article) :
                                        $slug = (string) ($article['slug'] ?? '');
                                        if ($slug === '') {
                                            continue;
                                        }
                                        $url = Route::_('index.php?option=com_dharmashop&view=help&article=' . rawurlencode($slug));
                                        $active = $slug === $this->articleSlug;
                                        ?>
                                        <a class="list-group-item list-group-item-action<?php echo $active ? ' active' : ''; ?>"
                                           href="<?php echo $url; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
                                            <?php echo $this->escape((string) ($article['title'] ?? $slug)); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </nav>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <main class="col-12 col-xl-9">
                <article class="card">
                    <div class="card-body p-4">
                        <?php if ($this->articleTitle !== '' && $this->articleHtml !== '') : ?>
                            <h2 class="card-title mb-4"><?php echo $this->escape($this->articleTitle); ?></h2>
                            <div class="dharmashop-help-article">
                                <?php echo $this->articleHtml; // Generated from repository Markdown and HTML-escaped by the build tool. ?>
                            </div>
                        <?php else : ?>
                            <div class="alert alert-info mb-0" role="status">
                                <?php echo Text::_('COM_DHARMASHOP_HELP_ARTICLE_MISSING'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            </main>
        </div>
    <?php endif; ?>
</div>
