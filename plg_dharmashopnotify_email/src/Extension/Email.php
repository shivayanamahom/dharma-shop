<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopNotify\Email\Extension;

use Dharma\Component\DharmaShop\Administrator\Notification\OrderNotification;
use Dharma\Plugin\DharmaShopNotify\Email\Mail\HtmlTemplate;
use Dharma\Plugin\DharmaShopNotify\Email\Mail\MessageData;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Один канал: Joomla MailTemplate/SMTP; собственных SMTP-паролей нет. */
final class Email extends CMSPlugin implements SubscriberInterface
{
    public function __construct(array $config, private readonly DatabaseInterface $db, private readonly MailerFactoryInterface $mailers)
    {
        parent::__construct($config);
    }
    public static function getSubscribedEvents(): array
    {
        return ['onDharmaShopOrderNotify'=>'sendNotification'];
    }
    public function sendNotification(EventInterface $event): void
    {
        $settings = $event->getArgument('settings');
        $message = $event->getArgument('notification');
        if (!$settings instanceof Registry || !$message instanceof OrderNotification || (int) $settings->get('notify_email_enabled', 0) !== 1) return;
        $app = $this->getApplication();
        $language = (string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        $mailLanguage = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($language, false);
        $mailLanguage->load('plg_dharmashopnotify_email', JPATH_ADMINISTRATOR, $language, true);
        $service = $app->bootComponent('com_dharmashop')->getServices()->notifications();
        $suffix = $message->reason === 'created' ? 'created' : 'status';
        $recipients = [];
        if ((int) $settings->get('notify_admin_' . $suffix, $suffix === 'created' ? 1 : 0) === 1) {
            $admins = preg_split('/[\s,;]+/u', (string) $settings->get('notify_admin_emails', ''), -1, PREG_SPLIT_NO_EMPTY);
            if ((int) $settings->get('notify_admin_system', 1) === 1) {
                $admins = array_merge($admins, $this->db->setQuery($this->db->createQuery()->select('email')->from($this->db->quoteName('#__users'))->where('sendEmail=1 AND block=0'))->loadColumn());
            }
            foreach (array_unique(array_map('strtolower', $admins)) as $email) {
                if (strlen($email) <= 254 && MailHelper::isEmailAddress($email)) $recipients['admin'][$email] = '';
            }
            if (empty($recipients['admin'])) Log::add('Order ' . (int) $message->order['id'] . ': no admin email recipients', Log::WARNING, 'com_dharmashop');
        }
        if ((int) $settings->get('notify_customer_' . $suffix, 1) === 1) {
            $email = trim((string) $message->order['contact_email']);
            if (MailHelper::isEmailAddress($email)) $recipients['customer'][strtolower($email)] = (string) $message->order['contact_name'];
            else Log::add('Order ' . (int) $message->order['id'] . ': no customer email', Log::WARNING, 'com_dharmashop');
        }
        foreach ($recipients as $audience=>$addresses) {
            foreach ($addresses as $email=>$name) {
                $deliveryId = $service->claim($message, 'email', $audience, $email, (bool) $event->getArgument('retry', false));
                if ($deliveryId === null) continue;
                $sent = false; $errorCode = '';
                try {
                    $data = (new MessageData())->build($message, $settings, $audience, (string) $app->get('sitename'), $mailLanguage);
                    $template = new HtmlTemplate('plg_dharmashopnotify_email.order.' . ($suffix === 'created' ? 'new' : 'status') . '.' . $audience, $language, $this->mailers->createMailer());
                    $template->addTemplateData($data['html']);
                    $template->addTemplateData($data['plain'], true);
                    $template->addUnsafeTags($data['unsafe']);
                    $template->addRecipient($email, MailHelper::cleanLine($name));
                    $reply = trim((string) $settings->get('notify_replyto', ''));
                    if ($reply === '') $reply = trim((string) $settings->get('shop_email', ''));
                    if ($reply !== '' && MailHelper::isEmailAddress($reply)) $template->setReplyTo($reply);
                    if ($template->send() !== true) throw new \RuntimeException('mail_not_accepted');
                    $sent = true;
                } catch (\Throwable $error) {
                    $errorCode = get_class($error);
                    Log::add('Email delivery ' . $deliveryId . ' failed: ' . $errorCode, Log::ERROR, 'com_dharmashop');
                }
                // Если запись результата оборвётся после SMTP, остаётся state=0: слепой повтор запрещён.
                $service->finish($deliveryId, $message->historyId, $audience, $sent, $errorCode);
            }
        }
    }

}
