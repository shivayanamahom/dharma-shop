<?php
/** Иллюстрация вместо отсутствующего фото. size: сторона миниатюры в px; 0 — заполнить родительский блок. */
\defined('_JEXEC') or die;
$size = max(0, min(256, (int) ($displayData['size'] ?? 0)));
?>
<svg class="d-block rounded bg-body-tertiary text-body-secondary dharmaImagePlaceholder<?php echo $size ? '' : ' w-100 h-100'; ?>"<?php if ($size) : ?> width="<?php echo $size; ?>" height="<?php echo $size; ?>"<?php endif; ?> viewBox="0 0 320 320" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <g opacity="0.5" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
        <rect x="96" y="108" width="128" height="104" rx="12"/>
        <circle cx="133" cy="141" r="11"/>
        <path d="m101 192 37-37 31 31 20-20 30 30"/>
    </g>
</svg>
