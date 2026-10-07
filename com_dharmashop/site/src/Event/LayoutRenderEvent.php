<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Event;

use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeStringAware;

\defined('_JEXEC') or die;

/** Точка вывода макета: плагины добавляют строки HTML через addResult(). */
final class LayoutRenderEvent extends AbstractImmutableEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeStringAware;

    public function __construct(string $name, array $arguments)
    {
        $this->preventSetArgumentResult = true;
        parent::__construct($name, $arguments);
    }
}
