<?php

/**
 * Dharma Shop — магазин для Joomla.
 *
 * @package    com_dharmashop
 * @license    GNU General Public License версии 2 или новее
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Список чатов, которые уже написали боту или в которых есть публикация бота. */
class TelegramchatsField extends ListField
{
    protected $type = 'Telegramchats';

    /** @var array<string, array<string, string>> */
    private static array $chatCache = [];

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $params = ComponentHelper::getParams('com_dharmashop');
        $token = trim((string) $params->get('notify_telegram_bot_token', ''));

        if ($token === '' || !preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $token)) {
            return $options;
        }

        $chats = self::discoverChats($token);

        // Keep saved selections visible even if their original update is no longer queued.
        foreach ((array) $this->value as $savedId) {
            $savedId = (string) $savedId;

            if ($savedId !== '' && !isset($chats[$savedId]) && preg_match('/^-?\d{1,20}$/', $savedId)) {
                $title = self::chatTitle($token, $savedId);

                if ($title !== null) {
                    $chats[$savedId] = $title;
                }
            }
        }

        foreach ($chats as $id => $title) {
            $options[] = HTMLHelper::_('select.option', $id, $title);
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function discoverChats(string $token): array
    {
        $cacheKey = hash('sha256', $token);

        if (isset(self::$chatCache[$cacheKey])) {
            return self::$chatCache[$cacheKey];
        }

        $chats = [];

        try {
            $http = HttpFactory::getHttp(['timeout' => 4]);
            $response = $http->get('https://api.telegram.org/bot' . $token . '/getUpdates?limit=100', ['Accept' => 'application/json'], 4);
            $payload = json_decode((string) $response->body, true);

            if ((int) $response->code === 200 && is_array($payload) && !empty($payload['ok'])) {
                foreach (($payload['result'] ?? []) as $update) {
                    $chat = $update['message']['chat']
                        ?? $update['edited_message']['chat']
                        ?? $update['channel_post']['chat']
                        ?? $update['edited_channel_post']['chat']
                        ?? null;

                    if (!is_array($chat) || !isset($chat['id'])) {
                        continue;
                    }

                    $id = (string) $chat['id'];
                    $chats[$id] = self::formatChatTitle($chat, $id);
                }
            } elseif (is_array($payload) && isset($payload['description'])) {
                // Do not log or expose the token or Telegram's full response in the form.
                self::$chatCache[$cacheKey] = [];

                return [];
            }
        } catch (\Throwable) {
            // A temporarily unavailable Telegram API should not prevent saving shop settings.
        }

        self::$chatCache[$cacheKey] = $chats;

        return $chats;
    }

    private static function chatTitle(string $token, string $chatId): ?string
    {
        try {
            $http = HttpFactory::getHttp(['timeout' => 3]);
            $response = $http->get(
                'https://api.telegram.org/bot' . $token . '/getChat?chat_id=' . rawurlencode($chatId),
                ['Accept' => 'application/json'],
                3
            );
            $payload = json_decode((string) $response->body, true);
            $chat = $payload['result'] ?? null;

            if ((int) $response->code !== 200 || empty($payload['ok']) || !is_array($chat)) {
                return null;
            }

            return self::formatChatTitle($chat, $chatId);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $chat */
    private static function formatChatTitle(array $chat, string $fallback): string
    {
        $title = trim((string) ($chat['title'] ?? ''));

        if ($title === '') {
            $name = trim(implode(' ', array_filter([
                (string) ($chat['first_name'] ?? ''),
                (string) ($chat['last_name'] ?? ''),
            ])));
            $username = trim((string) ($chat['username'] ?? ''));
            $title = $name;

            if ($username !== '') {
                $title .= ($title !== '' ? ' ' : '') . '@' . $username;
            }
        }

        return $title !== '' ? $title . ' (' . $fallback . ')' : $fallback;
    }
}
