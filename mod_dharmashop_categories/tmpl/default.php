<?php
\defined('_JEXEC') or die;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if (!$items) { return; }
?>
<div class="dharmaCategoriesModule">
    <?php if ($display === 'accordion') : ?>
        <div class="accordion" id="<?php echo $escape($accordionId); ?>">
            <?php foreach ($items as $index => $item) :
                $panel = $accordionId . '-panel-' . (int) $item->id;
                $heading = $accordionId . '-heading-' . (int) $item->id;
                $expanded = $openFirst && $index === 0;
                $group = $children[(int) $item->id] ?? ['items' => [], 'more' => false];
            ?>
                <div class="accordion-item">
                    <h3 class="accordion-header fs-6 fw-normal" id="<?php echo $escape($heading); ?>">
                        <?php if (!$group['items']) : ?>
                            <a class="d-flex align-items-center gap-3 p-3 fs-6 fw-normal text-body text-decoration-none" href="<?php echo $escape($item->url); ?>"><?php echo $renderHeading($item); ?></a>
                        <?php else : ?>
                        <button class="accordion-button gap-3 p-3 fs-6 fw-normal<?php echo $expanded ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $escape($panel); ?>" aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>" aria-controls="<?php echo $escape($panel); ?>"><?php echo $renderHeading($item); ?></button>
                        <?php endif; ?>
                    </h3>
                    <?php if ($group['items']) : ?>
                    <div class="accordion-collapse collapse<?php echo $expanded ? ' show' : ''; ?>" id="<?php echo $escape($panel); ?>" aria-labelledby="<?php echo $escape($heading); ?>"<?php if (!$multipleOpen) : ?> data-bs-parent="#<?php echo $escape($accordionId); ?>"<?php endif; ?>>
                        <div class="accordion-body">
                            <div class="row row-cols-1 g-3">
                                <?php foreach ($group['items'] as $child) : ?><div class="col"><?php echo $renderCard($child); ?></div><?php endforeach; ?>
                            </div>
                            <div class="mt-3"><a href="<?php echo $escape($item->url); ?>"><?php echo $escape(\Joomla\CMS\Language\Text::_('MOD_DHARMASHOP_CATEGORIES_OPEN_ALL')); ?></a></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($slider) : ?>
        <?php require $sliderTemplate; ?>
    <?php else : ?>
        <div class="<?php echo $escape($gridClass); ?>">
            <?php foreach ($items as $item) : ?>
                <div class="col"><?php echo $renderCard($item); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($showAll) : ?>
        <div class="mt-4"><a class="<?php echo $escape($buttonClass); ?>" href="<?php echo $escape($allUrl); ?>"><?php echo $escape($buttonText); ?></a></div>
    <?php endif; ?>
</div>
