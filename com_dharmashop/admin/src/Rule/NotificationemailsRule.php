<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Rule;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Mail\MailHelper;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
class NotificationemailsRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $addresses = preg_split('/[\s,;]+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        if ($addresses === false || count($addresses) > 50) return false;
        foreach ($addresses as $address) if (strlen($address) > 254 || !MailHelper::isEmailAddress($address)) return false;
        return true;
    }
}
