<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopNotify\Email\Mail;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Mail\BeforeRenderingMailTemplateEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
\defined('_JEXEC') or die;

/** Нативные записи/редактор MailTemplate, отдельный HTML/text-канал без изменения com_mails. */
final class HtmlTemplate extends MailTemplate
{
    public function send()
    {
        $mail = static::getTemplate($this->template_id, $this->language);
        if ($mail === null) return false;
        $app = Factory::getApplication();
        $lang = $this->languageFactory->createLanguage($this->language, false);
        $lang->load('plg_dharmashopnotify_email', JPATH_ADMINISTRATOR, $this->language, true);
        $config = ComponentHelper::getParams('com_mails');
        $params = $mail->params;
        $reply = (string) $app->get('replyto', '');
        $replyName = (string) $app->get('replytoname', '');
        // Сохраняем штатные индивидуальные настройки транспорта, если владелец их включил.
        if ((int) $config->get('alternative_mailconfig', 0) === 1 && (int) $params->get('alternative_mailconfig', 0) === 1) {
            if ($this->mailer->Mailer === 'smtp' || $params->get('mailer') === 'smtp') {
                $this->mailer->useSmtp($params->get('smtpauth', $app->get('smtpauth')) ? 1 : null,
                    $params->get('smtphost', $app->get('smtphost')), $params->get('smtpuser', $app->get('smtpuser')),
                    $params->get('smtppass', $app->get('smtppass')), $params->get('smtpsecure', $app->get('smtpsecure')),
                    $params->get('smtpport', $app->get('smtpport')));
            }
            if ($params->get('mailer') === 'sendmail') $this->mailer->isSendmail();
            $from = (string) $params->get('mailfrom', $app->get('mailfrom'));
            if (MailHelper::isEmailAddress($from)) $this->mailer->setFrom(MailHelper::cleanLine($from), MailHelper::cleanLine((string) $params->get('fromname', $app->get('fromname'))), false);
            $reply = (string) $params->get('replyto', $reply);
            $replyName = (string) $params->get('replytoname', $replyName);
        }
        $app->getDispatcher()->dispatch('onMailBeforeRendering', new BeforeRenderingMailTemplateEvent('onMailBeforeRendering', ['templateId'=>$this->template_id, 'subject'=>$this]));
        $this->mailer->setSubject(MailHelper::cleanLine($this->renderTags($lang->_($mail->subject), $this->plain_data ?: $this->data)));
        $plain = $this->renderTags($lang->_($mail->body), $this->plain_data ?: $this->data);
        $htmlData = $this->data;
        $shop = $this->plain_data ?: $this->data;
        $logo = $this->embedLogo((string) ($shop['SHOP_LOGO'] ?? ''));
        $htmlData['SHOP_LOGO'] = $shop['SHOP_LOGO'] = $logo;
        // При сохранённом пользовательском тексте без HTML показываем именно его.
        $html = trim((string) $mail->htmlbody) !== ''
            ? $this->renderTags($lang->_($mail->htmlbody), $htmlData, true)
            : nl2br(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $layout = new FileLayout('mail', dirname(__DIR__, 2) . '/tmpl', ['client'=>'site']);
        $body = $layout->render(['content'=>$html, 'shop'=>$shop, 'language'=>$this->language]);
        $this->mailer->isHtml(true);
        $this->mailer->setBody(MailHelper::convertRelativeToAbsoluteUrls($body));
        $this->mailer->AltBody = $plain !== '' ? $plain : html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</tr>'], "\n", $html)), ENT_QUOTES, 'UTF-8');
        foreach ($this->recipients as $recipient) {
            match ($recipient->type) {
                'cc' => $this->mailer->addCc($recipient->mail, $recipient->name),
                'bcc' => $this->mailer->addBcc($recipient->mail, $recipient->name),
                default => $this->mailer->addRecipient($recipient->mail, $recipient->name),
            };
        }
        if ($this->replyto) $this->mailer->addReplyTo($this->replyto->mail, $this->replyto->name);
        elseif ($reply !== '' && MailHelper::isEmailAddress($reply)) $this->mailer->addReplyTo($reply, $replyName);
        if ($config->get('copy_mails') && $params->get('copyto')) $this->mailer->addBcc($params->get('copyto'));
        $folder = trim((string) $config->get('attachment_folder', ''));
        if ($folder !== '') {
            $folder = rtrim(Path::check(JPATH_ROOT . '/' . $folder), DIRECTORY_SEPARATOR);
            if ($folder !== Path::clean(JPATH_ROOT) && is_dir($folder)) foreach ((array) json_decode($mail->attachments) as $attachment) {
                $file = Path::check($folder . '/' . $attachment->file);
                if (is_file($file)) $this->mailer->addAttachment($file, $this->getAttachmentName($file, $attachment->name));
            }
        }
        foreach ($this->attachments as $attachment) {
            if (is_file($attachment->file)) $this->mailer->addAttachment($attachment->file, $this->getAttachmentName($attachment->file, $attachment->name));
            else $this->mailer->addStringAttachment($attachment->file, $attachment->name);
        }
        return $this->mailer->Send();
    }

    /** Свой локальный логотип — inline CID; чужие URL не скачиваем. */
    private function embedLogo(string $url): string
    {
        if ($url === '') return '';
        $image = parse_url($url);
        $root = parse_url(Uri::root());
        if (!$image || !$root || strtolower($image['host'] ?? '') !== strtolower($root['host'] ?? '')
            || ($image['port'] ?? null) !== ($root['port'] ?? null)) return $url;
        $path = rawurldecode($image['path'] ?? '');
        $prefix = rtrim(rawurldecode($root['path'] ?? ''), '/') . '/';
        if (!str_starts_with($path, $prefix) || str_contains($path, "\0")) return $url;
        $site = realpath(JPATH_ROOT);
        $file = realpath(JPATH_ROOT . '/' . substr($path, strlen($prefix)));
        if ($site === false || $file === false || !str_starts_with($file, $site . DIRECTORY_SEPARATOR)
            || !is_file($file) || !is_readable($file) || filesize($file) > 5 * 1024 * 1024) return $url;
        $mime = mime_content_type($file);
        $types = ['image/png'=>'png', 'image/jpeg'=>'jpg', 'image/gif'=>'gif', 'image/webp'=>'webp'];
        if (!isset($types[$mime])) return $url;
        $cid = 'dharma-shop-logo@dharmashop';
        return $this->mailer->addEmbeddedImage($file, $cid, 'shop-logo.' . $types[$mime], 'base64', $mime)
            ? 'cid:' . $cid : $url;
    }

    /** Один проход: пользовательские фигурные скобки не становятся следующими подстановками. */
    private function renderTags(string $text, array $data, bool $html = false): string
    {
        $map = [];
        foreach ($data as $key=>$value) {
            $key = strtoupper($key);
            $value = (string) $value;
            $map['{' . $key . '}'] = $html && in_array($key, $this->unsafe_tags, true)
                ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        }
        return strtr($text, $map);
    }
}
