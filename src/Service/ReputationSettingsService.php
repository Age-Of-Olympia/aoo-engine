<?php

namespace App\Service;

use App\Database\QueryCache;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * What reputation is worth, set from the admin page Le monde › Réputation:
 * the gains (admin_settings), the titles (reputation_titles) and the forum
 * reward draw (reputation_reward_tiers). All amounts are real PR; a missing
 * setting reads the value the game shipped with.
 */
final class ReputationSettingsService
{
    public const PER_POST = 'reputation_per_post';
    public const PER_COOKIE = 'reputation_per_cookie';
    public const PER_COOKIE_SAME_FACTION = 'reputation_per_cookie_same_faction';
    public const REWARD_EVERY = 'reputation_reward_every';
    public const COOKIE_MAX_DAYS = 'forum_cookie_max_days';
    public const MISSIVE_COOKIES = 'forum_missive_cookies';

    private const DEFAULTS = [
        self::PER_POST => '1',
        self::PER_COOKIE => '0.3',
        self::PER_COOKIE_SAME_FACTION => '0.1',
        self::REWARD_EVERY => '5',
        self::COOKIE_MAX_DAYS => '14',
        self::MISSIVE_COOKIES => '1',
    ];

    /** Title of a balance below every threshold, when the table is empty. */
    private const BASE_TITLE = 'Inconnu';

    /** The images are <directory>/<tier>.png: the tiers are fixed. */
    public const TIERS = [1, 2, 3, 4];

    private Connection $conn;
    private AdminSettingsService $settings;

    public function __construct(?Connection $conn = null, ?AdminSettingsService $settings = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->settings = $settings ?? new AdminSettingsService();
    }

    public function perPost(): float
    {
        return (float) $this->setting(self::PER_POST);
    }

    public function perCookie(): float
    {
        return (float) $this->setting(self::PER_COOKIE);
    }

    public function perCookieSameFaction(): float
    {
        return (float) $this->setting(self::PER_COOKIE_SAME_FACTION);
    }

    /** A forum reward is drawn each time the balance reaches a multiple of this many PR. */
    public function rewardEvery(): int
    {
        return max(1, (int) $this->setting(self::REWARD_EVERY));
    }

    /** How many days after a post a cookie can still be given on it. */
    public function cookieMaxDays(): int
    {
        return max(0, (int) $this->setting(self::COOKIE_MAX_DAYS));
    }

    /** Whether cookies, worth no PR there, can be given on missives. */
    public function missiveCookies(): bool
    {
        return $this->setting(self::MISSIVE_COOKIES) === '1';
    }

    /** @return array<int, string> threshold (PR) => title, highest first */
    public function titles(): array
    {
        return QueryCache::remember(['reputation_titles'], 'reputation.titles', function (): array {
            $titles = [];
            foreach ($this->conn->fetchAllAssociative('SELECT threshold, title FROM reputation_titles ORDER BY threshold DESC') as $row) {
                $titles[(int) $row['threshold']] = (string) $row['title'];
            }

            return $titles;
        });
    }

    /** @return list<array{id: int, threshold: int, title: string}> lowest first, for the admin */
    public function titleRows(): array
    {
        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'threshold' => (int) $row['threshold'], 'title' => (string) $row['title']],
            $this->conn->fetchAllAssociative('SELECT id, threshold, title FROM reputation_titles ORDER BY threshold')
        );
    }

    public function title(float $pr): string
    {
        foreach ($this->titles() as $threshold => $title) {
            if ($pr >= $threshold) {
                return $title;
            }
        }

        return self::BASE_TITLE;
    }

    /** @return list<array{tier: int, pr: int, weight: float}> */
    public function rewardTiers(): array
    {
        return QueryCache::remember(['reputation_reward_tiers'], 'reputation.tiers', fn (): array => array_map(
            static fn (array $row): array => ['tier' => (int) $row['tier'], 'pr' => (int) $row['pr'], 'weight' => (float) $row['weight']],
            $this->conn->fetchAllAssociative('SELECT tier, pr, weight FROM reputation_reward_tiers ORDER BY tier')
        ));
    }

    /**
     * A weighted draw among the reward tiers. Without a drawable tier, the
     * first one, worth 1 PR, as before the draw was configurable.
     *
     * @return array{tier: int, pr: int}
     */
    public function drawRewardTier(): array
    {
        $tiers = array_values(array_filter($this->rewardTiers(), static fn (array $t): bool => $t['weight'] > 0));
        $total = array_sum(array_column($tiers, 'weight'));
        if ($tiers === [] || $total <= 0) {
            return ['tier' => 1, 'pr' => 1];
        }

        $rand = mt_rand() / mt_getrandmax() * $total;
        foreach ($tiers as $tier) {
            $rand -= $tier['weight'];
            if ($rand < 0) {
                return ['tier' => $tier['tier'], 'pr' => $tier['pr']];
            }
        }

        $last = end($tiers);

        return ['tier' => $last['tier'], 'pr' => $last['pr']];
    }

    public function saveGains(float $perPost, float $perCookie, float $perCookieSameFaction, int $rewardEvery): void
    {
        foreach ([$perPost, $perCookie, $perCookieSameFaction] as $gain) {
            // Stored in tenths: a finer gain would be rounded away
            if ($gain < 0 || abs($gain * 10 - round($gain * 10)) > 1e-9) {
                throw new InvalidArgumentException('Un gain de PR est positif ou nul, au dixième près.');
            }
        }
        if ($rewardEvery < 1) {
            throw new InvalidArgumentException('Le palier de récompense est d’au moins 1 PR.');
        }

        $this->settings->set(self::PER_POST, self::decimal($perPost));
        $this->settings->set(self::PER_COOKIE, self::decimal($perCookie));
        $this->settings->set(self::PER_COOKIE_SAME_FACTION, self::decimal($perCookieSameFaction));
        $this->settings->set(self::REWARD_EVERY, (string) $rewardEvery);
    }

    public function saveCookies(int $maxDays, bool $missiveCookies): void
    {
        if ($maxDays < 0) {
            throw new InvalidArgumentException('Le délai des cookies est positif ou nul.');
        }

        $this->settings->set(self::COOKIE_MAX_DAYS, (string) $maxDays);
        $this->settings->set(self::MISSIVE_COOKIES, $missiveCookies ? '1' : '0');
    }

    public function addTitle(int $threshold, string $title): void
    {
        $title = $this->validTitle($threshold, $title);
        $this->assertFreeThreshold($threshold, null);

        $this->conn->insert('reputation_titles', ['threshold' => $threshold, 'title' => $title]);
    }

    public function updateTitle(int $id, int $threshold, string $title): void
    {
        $current = $this->titleRow($id);
        if ($current['threshold'] === 0 && $threshold !== 0) {
            throw new InvalidArgumentException('Le titre de base reste au seuil 0.');
        }
        $title = $this->validTitle($threshold, $title);
        $this->assertFreeThreshold($threshold, $id);

        $this->conn->update('reputation_titles', ['threshold' => $threshold, 'title' => $title], ['id' => $id]);
    }

    public function removeTitle(int $id): void
    {
        if ($this->titleRow($id)['threshold'] === 0) {
            throw new InvalidArgumentException('Le titre de base ne peut pas être retiré.');
        }

        $this->conn->delete('reputation_titles', ['id' => $id]);
    }

    public function saveRewardTier(int $tier, int $pr, float $weight): void
    {
        if (!in_array($tier, self::TIERS, true)) {
            throw new InvalidArgumentException('Palier de récompense inconnu.');
        }
        if ($pr < 1) {
            throw new InvalidArgumentException('Une récompense vaut au moins 1 PR.');
        }
        if ($weight < 0 || $weight > 9999) {
            throw new InvalidArgumentException('Le poids d’un palier est compris entre 0 et 9999.');
        }

        $others = array_sum(array_column(
            array_filter($this->rewardTiers(), static fn (array $t): bool => $t['tier'] !== $tier),
            'weight'
        ));
        if ($weight + $others <= 0) {
            throw new InvalidArgumentException('Au moins un palier doit pouvoir être tiré.');
        }

        $this->conn->executeStatement(
            'INSERT INTO reputation_reward_tiers (tier, pr, weight) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE pr = VALUES(pr), weight = VALUES(weight)',
            [$tier, $pr, $weight]
        );
    }

    private function setting(string $name): string
    {
        return $this->settings->get($name, self::DEFAULTS[$name]);
    }

    private static function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    private function validTitle(int $threshold, string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 64) {
            throw new InvalidArgumentException('Un titre fait de 1 à 64 caractères.');
        }
        if ($threshold < 0) {
            throw new InvalidArgumentException('Le seuil d’un titre est positif ou nul.');
        }

        return $title;
    }

    private function assertFreeThreshold(int $threshold, ?int $exceptId): void
    {
        $taken = $this->conn->fetchOne(
            'SELECT id FROM reputation_titles WHERE threshold = ? AND id <> ?',
            [$threshold, $exceptId ?? 0]
        );
        if ($taken !== false) {
            throw new InvalidArgumentException(sprintf('Un titre existe déjà au seuil %d PR.', $threshold));
        }
    }

    /** @return array{id: int, threshold: int} */
    private function titleRow(int $id): array
    {
        $threshold = $this->conn->fetchOne('SELECT threshold FROM reputation_titles WHERE id = ?', [$id]);
        if ($threshold === false) {
            throw new InvalidArgumentException('Ce titre n’existe pas.');
        }

        return ['id' => $id, 'threshold' => (int) $threshold];
    }
}
