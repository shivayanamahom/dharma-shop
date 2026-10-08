<?php
\defined('_JEXEC') or die;
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$d = $displayData;
$o = $d['order'];
$l = $d['labels'];
?>
<?php if ($d['block'] === 'ORDER_DETAILS'): ?>
<h1 style="margin:0 0 12px;font-size:24px;line-height:1.3;color:#26384c"><?= $e($l['ORDER']) ?> №<?= $e($o['ORDER_NUMBER']) ?></h1>
<p style="margin:0 0 24px;font-size:14px;color:#637083"><?= $e($l['DATE']) ?>: <?= $e($o['ORDER_DATE']) ?><br><?= $e($l['STATUS']) ?>: <strong style="color:#26384c"><?= $e($o['STATUS']) ?></strong></p>
<?php elseif ($d['block'] === 'ITEMS'): ?>
<h2 style="margin:24px 0 12px;font-size:17px;color:#26384c"><?= $e($l['ITEMS']) ?></h2>
<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px">
<tr><th scope="col" align="left" style="padding:10px 0;border-bottom:2px solid #e2e7ed;font-weight:normal;color:#637083"><?= $e($l['PRODUCT']) ?></th><th scope="col" align="right" style="padding:10px 0 10px 12px;border-bottom:2px solid #e2e7ed;font-weight:normal;color:#637083"><?= $e($l['AMOUNT']) ?></th></tr>
<?php foreach ($d['items'] as $item): ?><tr>
<td valign="top" style="padding:14px 0;border-bottom:1px solid #e2e7ed;overflow-wrap:anywhere"><strong><?= $e($item['title']) ?></strong><?php if ($item['sku'] !== ''): ?><br><span style="font-size:12px;color:#637083"><?= $e($item['sku']) ?></span><?php endif; ?><br><span style="font-size:13px;color:#637083"><?= $e($item['qty']) ?> × <?= $e($item['price']) ?></span></td>
<td align="right" valign="top" style="padding:14px 0 14px 12px;border-bottom:1px solid #e2e7ed"><?= $e($item['total']) ?></td>
</tr><?php endforeach; ?>
<?php foreach ($d['adjustments'] as $item): ?><tr><td style="padding:10px 0"><?= $e($item['title']) ?></td><td align="right" style="padding:10px 0 10px 12px"><?= $e($item['amount']) ?></td></tr><?php endforeach; ?>
</table>
<?php elseif ($d['block'] === 'CONTACTS'): ?>
<h2 style="margin:24px 0 12px;font-size:17px;color:#26384c"><?= $e($l['CONTACTS']) ?></h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
<?php foreach ($d['contacts'] as $field): ?><tr><td valign="top" style="padding:5px 12px 5px 0;color:#637083"><?= $e($field['label']) ?></td><td valign="top" style="padding:5px 0;overflow-wrap:anywhere"><?= nl2br($e($field['value']), false) ?></td></tr><?php endforeach; ?>
</table>
<?php elseif ($d['block'] === 'CONSENTS'): ?>
<h2 style="margin:24px 0 12px;font-size:17px;color:#26384c"><?= $e($l['CONSENTS']) ?></h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px">
<?php foreach ($d['consents'] as $consent): ?><tr><td style="padding:10px 0;border-bottom:1px solid #e2e7ed;overflow-wrap:anywhere">
<?php if ($consent['url'] !== ''): ?><a href="<?= $e($consent['url']) ?>" style="color:#176b9b"><?= $e($consent['text']) ?></a><?php else: ?><?= $e($consent['text']) ?><?php endif; ?>
<br><strong><?= $e($consent['state']) ?></strong><?php if ($consent['date'] !== ''): ?><br><span style="color:#637083"><?= $e($l['ACCEPTED_AT']) ?>: <?= $e($consent['date']) ?></span><?php endif; ?>
</td></tr><?php endforeach; ?>
</table>
<?php elseif ($d['block'] === 'STATUS_MESSAGE'): ?>
<p style="margin:20px 0;padding:14px 16px;background:#f1f6fa;border-left:3px solid #176b9b"><?= nl2br($e($o['STATUS_MESSAGE']), false) ?></p>
<?php elseif ($d['block'] === 'SHIPPING' || $d['block'] === 'PAYMENT'): $key = $d['block']; ?>
<h2 style="margin:24px 0 8px;font-size:17px;color:#26384c"><?= $e($l[$key]) ?></h2><p style="margin:0 0 16px"><?= $e($o[$key . '_TITLE']) ?></p>
<?php elseif ($d['block'] === 'ORDER_LINK'): ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 4px"><tr><td bgcolor="#176b9b" style="border-radius:6px"><a href="<?= $e($o['ORDER_LINK']) ?>" style="display:inline-block;padding:12px 22px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold"><?= $e($l['OPEN_ORDER']) ?></a></td></tr></table>
<?php endif; ?>
