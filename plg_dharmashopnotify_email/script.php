<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(InstallerScriptInterface::class, new class implements InstallerScriptInterface {
            public function preflight(string $type, InstallerAdapter $adapter): bool { return true; }
            public function install(InstallerAdapter $adapter): bool { return true; }
            public function update(InstallerAdapter $adapter): bool { return true; }
            public function uninstall(InstallerAdapter $adapter): bool
            {
                foreach (['new','status'] as $reason) foreach (['admin','customer'] as $audience) MailTemplate::deleteTemplate('plg_dharmashopnotify_email.order.' . $reason . '.' . $audience);
                return true;
            }
            public function postflight(string $type, InstallerAdapter $adapter): bool
            {
                if ($type === 'uninstall') return true;
                $tags = ['sitename','order_number','order_date','status','status_message','total','items','contacts','order_link',
                    'shop_name','shop_email','shop_phone','shop_address','shop_url','shop_logo','shop_contacts','order_details','shipping_title','payment_title','shipping','payment','consents'];
                $db = Factory::getContainer()->get('DatabaseDriver');
                foreach (['new','status'] as $reason) foreach (['admin','customer'] as $audience) {
                    $id = 'plg_dharmashopnotify_email.order.' . $reason . '.' . $audience;
                    $key = 'PLG_DHARMASHOPNOTIFY_EMAIL_' . strtoupper($reason . '_' . $audience);
                    if (!MailTemplate::getTemplate($id, '')) MailTemplate::createTemplate($id, $key . '_SUBJECT', $key . '_BODY', $tags, $key . '_HTML');
                    $query = $db->createQuery()->select(['template_id','language','body','htmlbody','params'])
                        ->from($db->quoteName('#__mail_templates'))->where('template_id=:id')->bind(':id', $id);
                    foreach ($db->setQuery($query)->loadObjectList() as $row) {
                        $params = new Registry($row->params);
                        $params->set('tags', array_values(array_unique(array_merge((array) $params->get('tags', []), $tags))));
                        $update = (object) ['template_id'=>$row->template_id, 'language'=>$row->language, 'params'=>(string) $params];
                        // Инициализируем только прежний стандартный вариант; правки владельца сохраняются.
                        if (trim((string) $row->htmlbody) === '' && $row->body === $key . '_BODY') $update->htmlbody = $key . '_HTML';
                        $db->updateObject('#__mail_templates', $update, ['template_id','language']);
                    }
                }
                return true;
            }
        });
    }
};
