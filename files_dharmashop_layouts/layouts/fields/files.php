<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/files.php */
\defined('_JEXEC') or die;
$field = $displayData['field'];
$settings = (array) $field->file_settings;
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$size = static function (int $bytes): string {
    $units = ['B','KiB','MiB','GiB']; $index = 0; $number = (float) $bytes;
    while ($number >= 1024 && $index < 3) { $number /= 1024; $index++; }
    return number_format($number, $index > 0 ? 1 : 0, ',', ' ') . ' ' . $units[$index];
};
?>
<ul class="list-unstyled mb-0 dharmaFiles">
    <?php foreach ($field->values as $file) : ?>
        <li class="mb-2 dharmaFile">
            <a class="text-break" href="<?php echo $escape($file->url); ?>"<?php echo !empty($settings['files_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo $escape($file->text); ?></a>
            <?php if (!empty($settings['files_show_extension']) || (!empty($settings['files_show_size']) && $file->bytes > 0)) : ?>
                <small class="d-block text-body-secondary">
                    <?php echo !empty($settings['files_show_extension']) ? $escape($file->extension) : ''; ?>
                    <?php if (!empty($settings['files_show_size']) && $file->bytes > 0) : ?><?php echo !empty($settings['files_show_extension']) ? ' · ' : ''; ?><?php echo $escape($size($file->bytes)); ?><?php endif; ?>
                </small>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
