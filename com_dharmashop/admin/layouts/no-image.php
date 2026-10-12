<?php
/** Compact inline illustration used when a catalog item has no cover image. */
\defined('_JEXEC') or die;

$size = max(32, min(96, (int) ($displayData['size'] ?? 48)));
?>
<svg class="rounded border bg-body-tertiary text-body-secondary flex-shrink-0" width="<?php echo $size; ?>" height="<?php echo $size; ?>" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect x="3.5" y="5.5" width="25" height="21" rx="3" stroke="currentColor" stroke-width="1.8"/>
        <circle cx="11" cy="12" r="2.2" fill="currentColor"/>
        <path d="m5 23 7-7 4.5 4 3.5-3 7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
