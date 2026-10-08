<?php
/** Согласия из настроек: текст экранируется, флажки изначально сняты. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<?php if ($this->agreements) : ?>
<section class="card shadow-sm dharmaCheckoutAgreements" aria-label="<?php echo $this->escape(Text::_('COM_DHARMASHOP_CHECKOUT_AGREEMENTS')); ?>">
    <div class="card-body p-3 d-flex flex-column gap-3">
        <?php foreach ($this->agreements as $agreement) : ?>
            <?php
            $id = 'dharma-agreement-' . $agreement['name'];
            $url = trim((string) ($agreement['url'] ?? ''));
            // Дополнительно ограничиваем адрес при выводе старых/импортированных params.
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $safeUrl = $url !== '' && !str_starts_with($url, '//') && !str_contains($url, '\\')
                && !preg_match('/[\x00-\x20]/', $url) && ($scheme === null || in_array(strtolower((string) $scheme), ['http', 'https'], true));
            $linkLabel = trim((string) ($agreement['link_label'] ?? '')) ?: Text::_('COM_DHARMASHOP_CHECKOUT_READ_MORE');
            ?>
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="<?php echo $this->escape($id); ?>" name="consents[<?php echo $this->escape($agreement['name']); ?>]" value="1"<?php echo (int) ($agreement['required'] ?? 1) === 1 ? ' required' : ''; ?>>
                <label class="form-check-label small text-break" for="<?php echo $this->escape($id); ?>"><?php echo nl2br($this->escape((string) $agreement['text'])); ?><?php if ($safeUrl) : ?> <a href="<?php echo $this->escape($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape($linkLabel); ?></a><?php endif; ?></label>
                <?php if (!empty($this->errors['consent_' . $agreement['name']])) : ?><div class="small text-danger"><?php echo $this->escape($this->errors['consent_' . $agreement['name']]); ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
