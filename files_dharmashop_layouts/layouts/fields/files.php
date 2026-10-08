<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/files.php */
\defined('_JEXEC') or die;
$field = $displayData['field'];
$settings = (array) ($field->file_settings ?? []);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isGrid = ($settings['files_layout'] ?? 'list') === 'grid';
$columns = max(2, min(6, (int) ($settings['files_columns'] ?? 3)));
$size = static function (int $bytes): string {
    $units = ['B','KiB','MiB','GiB']; $index = 0; $number = (float) $bytes;
    while ($number >= 1024 && $index < 3) { $number /= 1024; $index++; }
    return number_format($number, $index > 0 ? 1 : 0, ',', ' ') . ' ' . $units[$index];
};
?>
<ul class="list-unstyled row row-cols-1<?php echo $isGrid ? ' row-cols-md-2 row-cols-xl-' . $columns : ''; ?> g-3 mb-0 dharmaFiles">
    <?php foreach ($field->values as $file) : ?>
        <li class="col dharmaFile">
            <div class="card h-100">
                <div class="card-body d-flex align-items-start gap-3">
                    <svg class="flex-shrink-0 text-body-secondary" width="24" height="28" viewBox="0 0 24 28" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M3 1h11l7 7v19H3z"/><path d="M14 1v7h7M7 14h10M7 18h10M7 22h6"/></svg>
                    <div class="flex-grow-1 text-break">
                        <a class="fw-semibold stretched-link" href="<?php echo $escape($file->url); ?>"<?php echo !empty($settings['files_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo $escape($file->text); ?></a>
                        <?php if (!empty($settings['files_show_extension']) || (!empty($settings['files_show_size']) && $file->bytes > 0)) : ?>
                            <small class="d-block text-body-secondary mt-1">
                                <?php echo !empty($settings['files_show_extension']) ? $escape($file->extension) : ''; ?>
                                <?php if (!empty($settings['files_show_size']) && $file->bytes > 0) : ?><?php echo !empty($settings['files_show_extension']) ? ' · ' : ''; ?><?php echo $escape($size($file->bytes)); ?><?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
