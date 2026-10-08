<?php
/** @var array $displayData Только content содержит подготовленный HTML; магазин — обычные строки. */
\defined('_JEXEC') or die;
$shop = $displayData['shop'];
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="<?= $escape(substr($displayData['language'], 0, 2)) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $escape($shop['SHOP_NAME']) ?></title></head>
<body style="margin:0;padding:0;background:#f3f5f7;color:#27313d;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f7"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #e2e7ed;border-radius:10px">
<tr><td style="padding:24px 28px;border-bottom:1px solid #e2e7ed">
<?php if ($shop['SHOP_LOGO'] !== ''): ?><a href="<?= $escape($shop['SHOP_URL']) ?>"><img src="<?= $escape($shop['SHOP_LOGO']) ?>" alt="<?= $escape($shop['SHOP_NAME']) ?>" width="160" style="display:block;width:160px;max-width:100%;height:auto;border:0;margin-bottom:12px"></a><?php endif; ?>
<a href="<?= $escape($shop['SHOP_URL']) ?>" style="color:#26384c;text-decoration:none;font-size:20px;font-weight:bold"><?= $escape($shop['SHOP_NAME']) ?></a>
</td></tr>
<tr><td style="padding:28px"><?= $displayData['content'] ?></td></tr>
<tr><td style="padding:20px 28px;background:#f8fafb;border-top:1px solid #e2e7ed;font-size:13px;color:#637083">
<strong style="color:#344355"><?= $escape($shop['SHOP_NAME']) ?></strong><br>
<?php if ($shop['SHOP_PHONE'] !== ''): ?><?= $escape($shop['SHOP_PHONE']) ?><br><?php endif; ?>
<?php if ($shop['SHOP_EMAIL'] !== ''): ?><a href="mailto:<?= $escape($shop['SHOP_EMAIL']) ?>" style="color:#176b9b"><?= $escape($shop['SHOP_EMAIL']) ?></a><br><?php endif; ?>
<?php if ($shop['SHOP_ADDRESS'] !== ''): ?><?= nl2br($escape($shop['SHOP_ADDRESS']), false) ?><br><?php endif; ?>
<a href="<?= $escape($shop['SHOP_URL']) ?>" style="color:#176b9b"><?= $escape($shop['SHOP_URL']) ?></a>
</td></tr></table>
</td></tr></table>
</body></html>
