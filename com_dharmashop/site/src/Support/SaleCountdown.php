<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use DateTimeImmutable;
use DateTimeZone;

\defined('_JEXEC') or die;

/** Готовит абсолютный timestamp для счётчика только пока скидка активна и её срок ещё не истёк. Даты прайса хранятся в UTC. */
final class SaleCountdown
{
    public static function endTimestamp(?string $startsAt, ?string $endsAt, bool $hasActiveDiscount): ?int
    {
        if (!$hasActiveDiscount || !$endsAt || $endsAt === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $utc = new DateTimeZone('UTC');
            $end = new DateTimeImmutable($endsAt, $utc);
            $now = new DateTimeImmutable('now', $utc);

            if ($end <= $now) {
                return null;
            }

            if ($startsAt && $startsAt !== '0000-00-00 00:00:00' && new DateTimeImmutable($startsAt, $utc) > $now) {
                return null;
            }

            return $end->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }
}
