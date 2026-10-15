<?php
/** Короткий текст интро в товарных и групповых карточках. */
\defined('_JEXEC') or die;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$intro = trim(strip_tags((string) ($displayData['intro'] ?? '')));
$intro = preg_replace('/[\s\p{Z}]+/u', ' ', $intro) ?? $intro;
$limit = 120;

if ($intro === '') {
    return;
}

if (mb_strlen($intro) > $limit) {
    $intro = mb_substr($intro, 0, $limit);
    $lastSpace = mb_strrpos($intro, ' ');

    if ($lastSpace !== false && $lastSpace >= 90) {
        $intro = mb_substr($intro, 0, $lastSpace);
    }

    $intro = rtrim($intro, " \t\n\r\0\x0B,.;:—-") . '…';
}
?>
<p class="card-text small text-body-secondary mt-2 mb-0 dharmaCardIntro"><?php echo $escape($intro); ?></p>
