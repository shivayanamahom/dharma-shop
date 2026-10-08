<?php
/** Контакты из настроек; при ошибке значения из сессии, без SQL в макете. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<?php if ($this->contacts) : ?>
<section class="card shadow-sm mb-4 dharmaCheckoutContacts" aria-labelledby="dharma-checkout-contacts-title">
    <div class="card-header bg-body-tertiary p-3">
        <h2 id="dharma-checkout-contacts-title" class="fs-6 fw-semibold mb-0 d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-circle" aria-hidden="true">1</span>
            <?php echo Text::_('COM_DHARMASHOP_CHECKOUT_CONTACTS'); ?>
        </h2>
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            <?php foreach ($this->contacts as $field) : ?>
                <?php
                $type = (string) $field['type'];
                $id = 'dharma-recipient-' . $field['name'];
                $required = (int) ($field['required'] ?? 0) === 1;
                $hint = (string) ($field['hint'] ?? '');
                $showLabel = (int) ($field['show_label'] ?? 1) === 1;
                $placeholder = $hint !== '' ? $hint : (!$showLabel ? (string) $field['label'] : '');
                if (!$showLabel && $required) {
                    $placeholder .= ' *';
                }
                $mask = trim((string) ($field['mask'] ?? '')) ?: '+7 (###) ###-##-##';
                $limit = match ($type) { 'textarea' => 2000, 'email' => 254, 'tel' => 64, default => 150 };
                $value = (string) ($this->values[$field['name']] ?? '');
                $error = (string) ($this->errors[$field['name']] ?? '');
                ?>
                <div class="<?php echo $type === 'textarea' ? 'col-12' : 'col-md-6'; ?> dharmaCheckoutField">
                    <label class="<?php echo $showLabel ? 'form-label small fw-medium mb-1' : 'visually-hidden'; ?>" for="<?php echo $this->escape($id); ?>"><?php echo $this->escape((string) $field['label']); ?><?php if ($required) : ?> <span class="text-danger" aria-hidden="true">*</span><?php endif; ?></label>
                    <?php if ($type === 'textarea') : ?>
                        <textarea class="form-control fs-6<?php echo $error ? ' is-invalid' : ''; ?>" id="<?php echo $this->escape($id); ?>" name="recipient[<?php echo $this->escape($field['name']); ?>]" rows="3" maxlength="<?php echo $limit; ?>" placeholder="<?php echo $this->escape($placeholder); ?>"<?php echo $required ? ' required' : ''; ?>><?php echo $this->escape($value); ?></textarea>
                    <?php else : ?>
                        <input class="form-control fs-6<?php echo $error ? ' is-invalid' : ''; ?>" id="<?php echo $this->escape($id); ?>" type="<?php echo $this->escape($type); ?>" name="recipient[<?php echo $this->escape($field['name']); ?>]" value="<?php echo $this->escape($value); ?>" maxlength="<?php echo $limit; ?>" placeholder="<?php echo $this->escape($placeholder !== '' ? $placeholder : ($type === 'tel' ? $mask : '')); ?>"<?php echo $required ? ' required' : ''; ?><?php if ($type === 'tel') : ?> inputmode="tel" autocomplete="tel" data-dharmashop-phone-mask="<?php echo $this->escape($mask); ?>"<?php elseif ($type === 'email') : ?> autocomplete="email"<?php endif; ?>>
                    <?php endif; ?>
                    <?php if ($error) : ?><div class="invalid-feedback d-block"><?php echo $this->escape($error); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
