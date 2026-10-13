<?php
/** Заглушки вместо Joomla для `dev/acl-checks.php`: проверка трейта защиты контроллера без запуска Joomla. */

namespace Joomla\CMS\Language {
    if (!class_exists(Text::class, false)) {
        class Text
        {
            public static function _(string $key): string
            {
                return $key;
            }
        }
    }
}

namespace DharmaAclStub {
    class Identity
    {
        public function __construct(private array $allowed)
        {
        }

        public function authorise(string $action, string $asset): bool
        {
            return in_array($action . '@' . $asset, $this->allowed, true);
        }
    }

    class App
    {
        public function __construct(private Identity $identity)
        {
        }

        public function getIdentity(): Identity
        {
            return $this->identity;
        }
    }

    class Base
    {
        public $app;

        public function execute($task)
        {
            return 'ran:' . $task;
        }
    }

    class Guarded extends Base
    {
        use \Dharma\Component\DharmaShop\Administrator\Controller\RequiresActionTrait;

        protected function requiredAction(): string
        {
            return 'dharmashop.fields.manage';
        }
    }
}
