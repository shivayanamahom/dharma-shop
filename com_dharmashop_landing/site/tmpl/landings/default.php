<?php
/** Список посадочных страниц: карточка — картинка (`landing.card`), заголовок, краткий текст. */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div id="dharma_shop" class="dharmaLandings">
<section class="py-4">
    <?php if ($this->showHeading && $this->heading !== '') : ?>
        <h1 class="mb-4"><?php echo $this->escape($this->heading); ?></h1>
    <?php endif; ?>
    <?php if ($this->items) : ?>
        <div class="<?php echo $this->escape($this->gridClasses); ?>">
            <?php foreach ($this->items as $item) :
                $url = Route::_('index.php?option=com_dharmashop_landing&view=landing&id=' . (int) $item->id);
                $image = trim((string) $item->image);
                $intro = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item->introtext)));
                if (mb_strlen($intro) > 180) { $intro = rtrim(mb_substr($intro, 0, 177)) . '…'; }
                ?>
                <div class="col">
                    <article class="card h-100 dharmaCard dharmaLandingCard">
                        <?php if ($this->showImage && $image !== '') : ?>
                            <a class="d-block" href="<?php echo $this->escape($url); ?>" tabindex="-1" aria-hidden="true">
                                <div class="ratio ratio-3x2 bg-body-tertiary overflow-hidden rounded-top">
                                    <img class="w-100 h-100 object-fit-cover" src="<?php echo $this->escape(ImageUrl::resolve($image, 'landing.card')); ?>"<?php echo ImageUrl::attrs($image, 'landing.card'); ?> alt="<?php echo $this->escape((string) ($item->image_alt ?: $item->title)); ?>" loading="lazy" decoding="async">
                                </div>
                            </a>
                        <?php endif; ?>
                        <div class="card-body">
                            <h2 class="h5 card-title mb-2"><a class="link-body-emphasis text-decoration-none" href="<?php echo $this->escape($url); ?>"><?php echo $this->escape($item->title); ?></a></h2>
                            <?php if ($this->showIntro && $intro !== '') : ?><p class="card-text text-body-secondary mb-0"><?php echo $this->escape($intro); ?></p><?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($this->pagination) : ?><div class="mt-4"><?php echo $this->pagination->getPaginationLinks(); ?></div><?php endif; ?>
    <?php else : ?>
        <p class="text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_LANDING_LIST_EMPTY'); ?></p>
    <?php endif; ?>
</section>
</div>
