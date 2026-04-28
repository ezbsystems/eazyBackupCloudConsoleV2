<?php
/**
 * KbSuggester
 *
 * Returns up to N curated/KB hint links for a given backup job log.
 *
 * Strategy (hybrid):
 *   1. Extract the dominant Warning/Error message signatures from the log.
 *   2. Match each signature against a curated JSON map (regex patterns) - first match wins.
 *   3. For any signature still unmatched, score against the GitBook site-index
 *      (https://docs.eazybackup.com/~gitbook/site-index) using keyword overlap on titles.
 *   4. Cache per-signature results on disk (default 6h TTL, 30min for empty results).
 *
 * The site-index endpoint is used instead of /~gitbook/search because GitBook's hosted
 * search endpoint returns 405 to direct GETs. The index is small (~33KB) and cacheable.
 *
 * Configuration (env vars, all optional):
 *   EB_KB_GITBOOK_URL   default https://docs.eazybackup.com
 *   EB_KB_DISABLE       1 to suppress all live calls (curated-only)
 *   EB_KB_CACHE_TTL     hit cache TTL in seconds (default 21600)
 *   EB_KB_CACHE_VER     cache key version, bump to invalidate
 */

namespace EazyBackup\Lib;

class KbSuggester
{
    private const DEFAULT_BASE_URL  = 'https://docs.eazybackup.com';
    private const SITE_INDEX_PATH   = '/~gitbook/site-index';
    private const HTTP_TIMEOUT_SECS = 5;
    private const HIT_TTL_SECS      = 21600;   // 6 hours
    private const EMPTY_TTL_SECS    = 1800;    // 30 minutes
    private const SITE_INDEX_TTL    = 3600;    // 1 hour
    private const FAILURE_WINDOW    = 600;     // 10 minutes
    private const FAILURE_THRESHOLD = 3;
    private const FAILURE_COOLOFF   = 3600;    // 1 hour suppression after threshold
    private const MAX_HINTS         = 3;
    private const MAX_SIGNATURES    = 3;

    /** @var string */
    private $cacheDir;
    /** @var string */
    private $curatedPath;

    public function __construct(string $addonRoot)
    {
        $this->cacheDir    = rtrim($addonRoot, '/') . '/cache/kb-search';
        $this->curatedPath = rtrim($addonRoot, '/') . '/data/kb-curated.json';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    /**
     * Suggest KB hints for the given log rows.
     *
     * @param array $logRows  Each row: {Time, Severity, Message}
     * @param string $jobType Friendly job type label (used as fallback signature seed)
     * @param string $status  Friendly status label
     * @return array          List of hints: {title, url, source, snippet?, matchedPattern?}
     */
    public function suggest(array $logRows, string $jobType = '', string $status = ''): array
    {
        $entries = $this->extractSignatureEntries($logRows);

        // Fallback seed: if the log has no warning/error messages, use job type + status.
        if (empty($entries) && ($jobType !== '' || $status !== '')) {
            $seed = trim($status . ' ' . $jobType);
            $entries[] = ['signature' => $this->normalizeSignature($seed), 'raw' => $seed];
        }
        if (empty($entries)) {
            return [];
        }

        $hints   = [];
        $seenUrl = [];
        foreach ($entries as $entry) {
            if (count($hints) >= self::MAX_HINTS) break;
            $sig = $entry['signature'];
            $raw = $entry['raw'];
            $cached = $this->cacheGet($sig);
            if (is_array($cached)) {
                foreach ($cached as $hint) {
                    if (count($hints) >= self::MAX_HINTS) break;
                    if (!isset($seenUrl[$hint['url']])) {
                        $hints[]               = $hint;
                        $seenUrl[$hint['url']] = true;
                    }
                }
                continue;
            }

            $perSig = $this->lookupCurated($sig, $raw);
            if (empty($perSig)) {
                $perSig = $this->lookupGitBookIndex($raw);
            }

            $this->cachePut($sig, $perSig, empty($perSig) ? self::EMPTY_TTL_SECS : self::cacheTtl());

            foreach ($perSig as $hint) {
                if (count($hints) >= self::MAX_HINTS) break;
                if (!isset($seenUrl[$hint['url']])) {
                    $hints[]               = $hint;
                    $seenUrl[$hint['url']] = true;
                }
            }
        }
        return $hints;
    }

    // ---------- signature extraction ----------

    /**
     * Backward-compatible: return only the normalized signatures (top N by frequency).
     */
    public function extractSignatures(array $logRows): array
    {
        return array_map(function ($e) { return $e['signature']; }, $this->extractSignatureEntries($logRows));
    }

    /**
     * Group warning/error messages by normalized text and return top entries by frequency.
     * Each entry preserves the original (raw) message used for keyword tokenization so
     * that synthetic placeholders never become search terms.
     *
     * @return array<int,array{signature:string,raw:string,count:int}>
     */
    public function extractSignatureEntries(array $logRows): array
    {
        $byKey = [];
        foreach ($logRows as $row) {
            $sev = strtoupper((string)($row['Severity'] ?? ''));
            if ($sev !== 'W' && $sev !== 'E' && $sev !== 'WARNING' && $sev !== 'ERROR') {
                continue;
            }
            $msg = (string)($row['Message'] ?? '');
            $sig = $this->normalizeSignature($msg);
            if ($sig === '') continue;
            if (!isset($byKey[$sig])) {
                $byKey[$sig] = ['signature' => $sig, 'raw' => $msg, 'count' => 0];
            }
            $byKey[$sig]['count']++;
        }
        if (empty($byKey)) return [];
        usort($byKey, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        return array_slice($byKey, 0, self::MAX_SIGNATURES);
    }

    private function normalizeSignature(string $msg): string
    {
        $s = strtolower($msg);
        // Strip Windows paths like C:\foo\bar.ext
        $s = preg_replace('#[a-z]:\\\\[^\s\'"]+#i', '<path>', $s);
        // Strip POSIX paths /usr/local/foo
        $s = preg_replace('#(?<![a-z])/[^\s\'"<>]{2,}#', '<path>', $s);
        // Strip UNC paths \\server\share
        $s = preg_replace('#\\\\\\\\[^\s\'"<>]+#', '<unc>', $s);
        // GUIDs
        $s = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<guid>', $s);
        // Hex 0x...
        $s = preg_replace('/0x[0-9a-f]{4,}/i', '<hex>', $s);
        // Long numeric IDs (> 3 digits)
        $s = preg_replace('/\b\d{4,}\b/', '<n>', $s);
        // Timestamps like 2026-04-28 13:14:22
        $s = preg_replace('/\d{4}-\d{2}-\d{2}([t ]\d{2}:\d{2}(:\d{2})?)?/i', '<ts>', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        if (strlen($s) > 200) $s = substr($s, 0, 200);
        return $s;
    }

    // ---------- curated lookup ----------

    private function lookupCurated(string $signature, string $rawMessage = ''): array
    {
        static $cached = null;
        if ($cached === null) {
            $cached = [];
            if (is_file($this->curatedPath)) {
                $raw = @file_get_contents($this->curatedPath);
                $j   = json_decode($raw ?: '[]', true);
                if (is_array($j)) $cached = $j;
            }
        }
        // Match against both the normalized signature (where placeholders like
        // <unc> live) AND the raw message (which still contains the original
        // wording). This lets curated regex authors target whichever is more
        // convenient, e.g. "warning\s*missing" against the raw text.
        $haystacks = array_filter([
            $signature,
            $rawMessage !== '' ? strtolower($rawMessage) : '',
        ]);
        $hits = [];
        foreach ($cached as $entry) {
            $patterns = (array)($entry['patterns'] ?? []);
            foreach ($patterns as $p) {
                if (!is_string($p) || $p === '') continue;
                $matched = false;
                foreach ($haystacks as $h) {
                    if (@preg_match('/' . $p . '/i', $h)) { $matched = true; break; }
                }
                if ($matched) {
                    $hits[] = [
                        'title'          => (string)($entry['title'] ?? ''),
                        'url'            => (string)($entry['url'] ?? ''),
                        'source'         => 'curated',
                        'matchedPattern' => $p,
                        'snippet'        => (string)($entry['name'] ?? ''),
                    ];
                    break; // one hit per curated entry
                }
            }
            if (count($hits) >= self::MAX_HINTS) break;
        }
        return $hits;
    }

    // ---------- GitBook site-index scoring ----------

    private function lookupGitBookIndex(string $rawMessage): array
    {
        if ($this->isLiveDisabled()) return [];
        $pages = $this->fetchSiteIndex();
        if (empty($pages)) return [];

        $tokens = $this->tokenize($rawMessage);
        if (empty($tokens)) return [];

        $rawLower = strtolower($rawMessage);
        $scored = [];
        foreach ($pages as $p) {
            $title = (string)($p['title'] ?? '');
            $path  = (string)($p['pathname'] ?? '');
            if ($title === '' || $path === '') continue;
            $titleTokens = $this->tokenize($title);
            $pathTokens  = $this->tokenize(str_replace(['-', '/'], ' ', $path));
            if (empty($titleTokens) && empty($pathTokens)) continue;
            $score = 0;
            // Title-token matches are weighted higher than path-only matches.
            foreach ($tokens as $t) {
                if (in_array($t, $titleTokens, true)) {
                    $score += 2;
                } elseif (in_array($t, $pathTokens, true)) {
                    $score += 1;
                }
            }
            // Phrase bonus: if the (lowercased) raw message contains the title
            // verbatim, that is a very strong signal (e.g. "warning missing").
            $titleLower = strtolower($title);
            if ($titleLower !== '' && strpos($rawLower, $titleLower) !== false) {
                $score += 5;
            }
            // Bigram bonus: reward titles whose adjacent word pairs appear in
            // the raw message as a phrase ("warning missing", "items removed").
            for ($i = 0, $n = count($titleTokens) - 1; $i < $n; $i++) {
                $bigram = $titleTokens[$i] . ' ' . $titleTokens[$i + 1];
                if (strpos($rawLower, $bigram) !== false) {
                    $score += 3;
                }
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'title' => $title, 'path' => $path];
            }
        }
        if (empty($scored)) return [];
        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strlen($a['title']) <=> strlen($b['title']); // prefer shorter/more specific titles
            }
            return $b['score'] <=> $a['score'];
        });

        $base = $this->baseUrl();
        $out  = [];
        foreach (array_slice($scored, 0, self::MAX_HINTS) as $row) {
            $url = (strpos($row['path'], 'http') === 0) ? $row['path'] : ($base . $row['path']);
            $out[] = [
                'title'   => $row['title'],
                'url'     => $url,
                'source'  => 'gitbook',
                'snippet' => '',
            ];
        }
        return $out;
    }

    private function tokenize(string $s): array
    {
        // Split CamelCase / PascalCase boundaries so e.g. "WarningMissing" -> "Warning Missing".
        // Two passes: lower->Upper boundary, and Upper->Upper+lower boundary (e.g. "HTTPRequest").
        $s = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $s);
        $s = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $s);
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        // Stop words + synthetic placeholders left over from normalizeSignature()
        // ("unc", "path", "guid", "hex", "ts", "n") so they never become search terms.
        $stop = [
            'a','an','and','or','of','the','to','for','in','on','with','from','at','by','is','it',
            'was','be','this','that','i','my','we','our','as','if','can','could','would','should',
            'have','has','had','not',
            'unc','path','guid','hex',
        ];
        $out = [];
        foreach (preg_split('/\s+/', trim($s)) as $tok) {
            if ($tok === '') continue;
            if (strlen($tok) < 3) continue;
            if (in_array($tok, $stop, true)) continue;
            if (!in_array($tok, $out, true)) $out[] = $tok;
        }
        return $out;
    }

    private function fetchSiteIndex(): array
    {
        $cacheKey = 'site-index-' . self::cacheVer();
        $cached   = $this->cacheGet($cacheKey, self::SITE_INDEX_TTL);
        if (is_array($cached) && isset($cached['pages'])) return $cached['pages'];

        $url = $this->baseUrl() . self::SITE_INDEX_PATH;
        $raw = $this->httpGet($url);
        if ($raw === null) {
            $this->recordFailure();
            return [];
        }
        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['pages']) || !is_array($j['pages'])) {
            $this->recordFailure();
            return [];
        }
        $this->cachePut($cacheKey, ['pages' => $j['pages']], self::SITE_INDEX_TTL);
        return $j['pages'];
    }

    private function httpGet(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => self::HTTP_TIMEOUT_SECS,
                'header'        => "User-Agent: eazyBackup-KB/1.0\r\nAccept: application/json\r\n",
                'ignore_errors' => true,
            ],
            'https' => [
                'method'        => 'GET',
                'timeout'       => self::HTTP_TIMEOUT_SECS,
                'header'        => "User-Agent: eazyBackup-KB/1.0\r\nAccept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') return null;
        return $body;
    }

    // ---------- cache + circuit breaker ----------

    private function cachePath(string $key): string
    {
        return $this->cacheDir . '/' . hash('sha256', self::cacheVer() . '|' . $key) . '.json';
    }

    private function cacheGet(string $key, ?int $maxAgeOverride = null): ?array
    {
        $p = $this->cachePath($key);
        if (!is_file($p)) return null;
        $raw = @file_get_contents($p);
        if ($raw === false) return null;
        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['expires_at'])) return null;
        $now = time();
        if ($maxAgeOverride !== null) {
            // Re-evaluate using a different ceiling (used for the site-index entry).
            $written = (int)($j['written_at'] ?? 0);
            if ($written > 0 && ($now - $written) > $maxAgeOverride) return null;
        } else {
            if ($now > (int)$j['expires_at']) return null;
        }
        return $j['data'] ?? null;
    }

    private function cachePut(string $key, $data, int $ttl): void
    {
        $p = $this->cachePath($key);
        $payload = json_encode([
            'written_at' => time(),
            'expires_at' => time() + $ttl,
            'data'       => $data,
        ], JSON_UNESCAPED_SLASHES);
        @file_put_contents($p, $payload, LOCK_EX);
    }

    private function isLiveDisabled(): bool
    {
        if ((string)getenv('EB_KB_DISABLE') === '1') return true;
        $state = $this->cacheGet('failure-state');
        if (is_array($state) && (int)($state['suppressed_until'] ?? 0) > time()) return true;
        return false;
    }

    private function recordFailure(): void
    {
        $state = $this->cacheGet('failure-state') ?: ['failures' => [], 'suppressed_until' => 0];
        $state['failures'][] = time();
        $cutoff = time() - self::FAILURE_WINDOW;
        $state['failures'] = array_values(array_filter($state['failures'], function ($t) use ($cutoff) {
            return $t >= $cutoff;
        }));
        if (count($state['failures']) >= self::FAILURE_THRESHOLD) {
            $state['suppressed_until'] = time() + self::FAILURE_COOLOFF;
            $state['failures']         = [];
        }
        $this->cachePut('failure-state', $state, self::FAILURE_COOLOFF);
    }

    // ---------- env helpers ----------

    private function baseUrl(): string
    {
        $u = (string)getenv('EB_KB_GITBOOK_URL');
        if ($u === '') $u = self::DEFAULT_BASE_URL;
        return rtrim($u, '/');
    }

    private static function cacheTtl(): int
    {
        $v = (int)getenv('EB_KB_CACHE_TTL');
        return $v > 0 ? $v : self::HIT_TTL_SECS;
    }

    private static function cacheVer(): string
    {
        $v = (string)getenv('EB_KB_CACHE_VER');
        return $v !== '' ? $v : '1';
    }
}
