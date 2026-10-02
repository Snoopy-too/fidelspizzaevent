<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class OrderWindowService
{
    public const MODE_AUTO = 'auto';
    public const MODE_FORCE_OPEN = 'force_open';
    public const MODE_FORCE_CLOSED = 'force_closed';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_FORCE_OPEN = 'force_open';
    public const STATUS_FORCE_CLOSED = 'force_closed';

    private DateTimeZone $timeZone;

    public function __construct(?DateTimeZone $timeZone = null)
    {
        $this->timeZone = $timeZone ?? new DateTimeZone(date_default_timezone_get() ?: 'Asia/Tokyo');
    }

    /**
     * Determine if ordering is currently open based on site configuration and current time.
     *
     * @param array<string, mixed> $siteConfig
     */
    public function isOrderingOpen(array $siteConfig, ?DateTimeInterface $now = null): bool
    {
        $mode = (string)($siteConfig['order_status_override'] ?? self::MODE_AUTO);

        if ($mode === self::MODE_FORCE_CLOSED) {
            return false;
        }

        if ($mode === self::MODE_FORCE_OPEN) {
            return true;
        }

        // Automatic mode based on order_deadline or event_date
        $current = $this->resolveNow($now);
        $deadline = $this->getDeadlineDateTime($siteConfig);

        if ($deadline !== null) {
            return $current <= $deadline;
        }

        // Fallback: If only event_date is set, close at 23:59:59 of event_date
        $eventDate = trim((string)($siteConfig['event_date'] ?? ''));
        if ($eventDate !== '') {
            try {
                $eventEndOfDay = new DateTimeImmutable($eventDate . ' 23:59:59', $this->timeZone);
                return $current <= $eventEndOfDay;
            } catch (\Throwable) {
                return true;
            }
        }

        return true;
    }

    /**
     * Get specific ordering status: open, closed, force_open, or force_closed.
     *
     * @param array<string, mixed> $siteConfig
     */
    public function getOrderingStatus(array $siteConfig, ?DateTimeInterface $now = null): string
    {
        $mode = (string)($siteConfig['order_status_override'] ?? self::MODE_AUTO);

        if ($mode === self::MODE_FORCE_CLOSED) {
            return self::STATUS_FORCE_CLOSED;
        }

        if ($mode === self::MODE_FORCE_OPEN) {
            return self::STATUS_FORCE_OPEN;
        }

        return $this->isOrderingOpen($siteConfig, $now) ? self::STATUS_OPEN : self::STATUS_CLOSED;
    }

    /**
     * Extract order deadline as a DateTimeImmutable instance if configured.
     *
     * @param array<string, mixed> $siteConfig
     */
    public function getDeadlineDateTime(array $siteConfig): ?DateTimeImmutable
    {
        $raw = trim((string)($siteConfig['order_deadline'] ?? ''));
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw, $this->timeZone);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns remaining seconds until the deadline if ordering is open, 0 if closed, or null if no deadline.
     *
     * @param array<string, mixed> $siteConfig
     */
    public function getSecondsRemaining(array $siteConfig, ?DateTimeInterface $now = null): ?int
    {
        if (!$this->isOrderingOpen($siteConfig, $now)) {
            return 0;
        }

        $deadline = $this->getDeadlineDateTime($siteConfig);
        if ($deadline === null) {
            return null;
        }

        $current = $this->resolveNow($now);
        $diff = $deadline->getTimestamp() - $current->getTimestamp();

        return max(0, $diff);
    }

    /**
     * Formats the order deadline in a friendly, localized string.
     *
     * @param array<string, mixed> $siteConfig
     */
    public function getFormattedDeadline(array $siteConfig, string $lang = 'ja'): ?string
    {
        $deadline = $this->getDeadlineDateTime($siteConfig);
        if ($deadline === null) {
            return null;
        }

        if ($lang === 'en') {
            return $deadline->format('l, M j, Y \a\t H:i');
        }

        $jaDays = ['日', '月', '火', '水', '木', '金', '土'];
        $dayOfWeek = $jaDays[(int)$deadline->format('w')];

        return $deadline->format("Y年n月j日({$dayOfWeek}) H:i");
    }

    /**
     * Human-friendly formatted countdown string (e.g., "2 days 4 hours 15 mins" or "2日 4時間 15分").
     */
    public function formatRemainingCountdown(int $secondsRemaining, string $lang = 'ja'): string
    {
        if ($secondsRemaining <= 0) {
            return $lang === 'en' ? 'Closed' : '締切終了';
        }

        $days = (int)floor($secondsRemaining / 86400);
        $hours = (int)floor(($secondsRemaining % 86400) / 3600);
        $minutes = (int)floor(($secondsRemaining % 3600) / 60);

        if ($lang === 'en') {
            $parts = [];
            if ($days > 0) {
                $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
            }
            if ($hours > 0 || $days > 0) {
                $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
            }
            $parts[] = $minutes . ' ' . ($minutes === 1 ? 'min' : 'mins');
            return implode(' ', $parts);
        }

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}日";
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = "{$hours}時間";
        }
        $parts[] = "{$minutes}分";

        return implode(' ', $parts);
    }

    private function resolveNow(?DateTimeInterface $now): DateTimeImmutable
    {
        if ($now instanceof DateTimeImmutable) {
            return $now->setTimezone($this->timeZone);
        }

        if ($now instanceof \DateTime) {
            return DateTimeImmutable::createFromMutable($now)->setTimezone($this->timeZone);
        }

        return new DateTimeImmutable('now', $this->timeZone);
    }
}
