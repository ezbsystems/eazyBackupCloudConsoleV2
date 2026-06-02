<?php
declare(strict_types=1);

namespace Ms365Backup;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class GraphClient
{
    private Client $http;
    private string $graphBase;

    public function __construct(
        private readonly TokenProvider $tokens,
        ?string $region = null,
    ) {
        $region = $region ?? TenantRepository::credentials()['region'];
        $this->graphBase = rtrim(RegionEndpoints::forRegion($region)['graph'], '/');
        $this->http = new Client([
            'base_uri' => $this->graphBase . '/v1.0/',
            'timeout' => 120,
            'http_errors' => false,
        ]);
    }

    /** @param array<string, string> $extraHeaders */
    public function get(string $path, array $query = [], array $extraHeaders = []): array
    {
        return $this->request('GET', $path, ['query' => $query], $extraHeaders);
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $extraHeaders
     * @return \Generator<array<string, mixed>>
     */
    public function paginate(
        string $path,
        array $query = [],
        array $extraHeaders = [],
        ?PaginationMonitor $monitor = null,
        ?PaginationOutcome $outcome = null,
    ): \Generator {
        $next = $path;
        $first = true;
        $page = 0;
        $stoppedOnDuplicatePage = false;
        /** @var array<string, true> $seenNextLinks exact full URL hashes */
        $seenNextLinks = [];
        /** @var array<string, true> $seenItemIds item ids returned in this pagination session */
        $seenItemIds = [];
        $emptyPagesWithNext = 0;
        $totalItems = 0;
        $newItemsOnLastPage = 0;

        if ($monitor !== null) {
            $monitor->log('info', 'Graph pagination started', [
                'path' => $path,
                'max_pages' => $monitor->maxPages,
            ]);
        }

        while ($next !== '') {
            $page++;
            if ($monitor !== null && $page > $monitor->maxPages) {
                $monitor->log('error', 'Graph pagination safety cap reached', [
                    'page' => $page,
                    'max_pages' => $monitor->maxPages,
                    'total_items' => $totalItems,
                    'last_next_link' => $this->truncateLink($next),
                ]);
                throw new GraphPaginationException(
                    'Graph pagination safety cap reached (' . $monitor->maxPages . ' pages)'
                    . ($monitor->context !== '' ? " [{$monitor->context}]" : '')
                    . '. This may indicate a Microsoft Graph pagination defect; see'
                    . ' https://github.com/microsoftgraph/msgraph-sdk-dotnet/issues/3070'
                );
            }

            if (!$first) {
                // Compare full nextLink URLs. Do NOT strip $skiptoken — Graph advances
                // pagination by changing skiptoken on each page; stripping it causes false positives.
                $linkKey = hash('sha256', $next);
                if (isset($seenNextLinks[$linkKey])) {
                    if ($monitor !== null) {
                        $monitor->log('error', 'Graph pagination loop detected: identical @odata.nextLink URL repeated', [
                            'page' => $page,
                            'next_link' => $this->truncateLink($next),
                            'total_items' => $totalItems,
                        ]);
                    }
                    throw new GraphPaginationException(
                        'Graph pagination loop detected: identical @odata.nextLink URL repeated'
                        . ($monitor?->context !== '' ? " [{$monitor->context}]" : '')
                        . '. Microsoft Graph returned the same next page URL twice; see'
                        . ' https://github.com/microsoftgraph/msgraph-sdk-dotnet/issues/3070'
                    );
                }
                $seenNextLinks[$linkKey] = true;
            }

            if ($first) {
                $data = $this->get($next, $query, $extraHeaders);
                $first = false;
            } else {
                $data = $this->getAbsolute($next, $extraHeaders);
            }

            $items = $data['value'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }
            $itemCount = count($items);
            $nextLink = (string) ($data['@odata.nextLink'] ?? '');

            $newItemsOnLastPage = 0;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemId = $this->extractItemId($item);
                if ($itemId !== '' && isset($seenItemIds[$itemId])) {
                    continue;
                }
                if ($itemId !== '') {
                    $seenItemIds[$itemId] = true;
                }
                $newItemsOnLastPage++;
                $totalItems++;
                yield $item;
            }

            if ($monitor !== null) {
                $monitor->log('info', 'Graph pagination page fetched', [
                    'page' => $page,
                    'items_on_page' => $itemCount,
                    'new_items_on_page' => $newItemsOnLastPage,
                    'total_items' => $totalItems,
                    'has_next_link' => $nextLink !== '',
                    'next_link' => $nextLink !== '' ? $this->truncateLink($nextLink) : null,
                    'skip_token' => $this->extractSkipToken($nextLink),
                ]);
            }

            if ($itemCount > 0 && $newItemsOnLastPage === 0 && $nextLink !== '') {
                $detectOnly = $monitor !== null
                    && $monitor->duplicatePageMode === PaginationDuplicatePageMode::DetectDuplicateOnly;
                if ($detectOnly) {
                    $stoppedOnDuplicatePage = true;
                    $monitor->log('warning', 'Graph pagination stopped: duplicate-only page (known Graph defect)', [
                        'page' => $page,
                        'items_on_page' => $itemCount,
                        'next_link' => $this->truncateLink($nextLink),
                        'total_items' => $totalItems,
                    ]);
                    break;
                }
                if ($monitor !== null) {
                    $monitor->log('error', 'Graph pagination loop detected: page returned only duplicate item IDs', [
                        'page' => $page,
                        'items_on_page' => $itemCount,
                        'next_link' => $this->truncateLink($nextLink),
                    ]);
                }
                throw new GraphPaginationException(
                    'Graph pagination loop detected: page contained only previously seen items'
                    . ($monitor?->context !== '' ? " [{$monitor->context}]" : '')
                );
            }

            if ($itemCount === 0 && $nextLink !== '') {
                $emptyPagesWithNext++;
                if ($monitor !== null && $emptyPagesWithNext >= $monitor->maxEmptyPagesWithNext) {
                    $monitor->log('error', 'Graph pagination loop suspected: empty pages with continuation', [
                        'page' => $page,
                        'consecutive_empty_pages' => $emptyPagesWithNext,
                        'next_link' => $this->truncateLink($nextLink),
                    ]);
                    throw new GraphPaginationException(
                        'Graph pagination loop suspected: '
                        . $emptyPagesWithNext
                        . ' consecutive empty page(s) still have @odata.nextLink'
                        . ($monitor?->context !== '' ? " [{$monitor->context}]" : '')
                    );
                }
            } else {
                $emptyPagesWithNext = 0;
            }

            $next = $nextLink;
        }

        if ($monitor !== null) {
            $monitor->log('info', 'Graph pagination completed', [
                'pages' => $page,
                'total_items' => $totalItems,
                'stopped_on_duplicate_page' => $stoppedOnDuplicatePage,
            ]);
        }

        if ($outcome !== null) {
            $outcome->pages = $page;
            $outcome->totalItems = $totalItems;
            $outcome->stoppedOnDuplicatePage = $stoppedOnDuplicatePage;
            $outcome->completedNaturally = !$stoppedOnDuplicatePage && $next === '';
        }
    }

    /**
     * Delta query: first call uses $path + $query; resume uses stored @odata.deltaLink URL.
     *
     * @param array<string, string> $query
     * @param array<string, string> $extraHeaders
     * @return \Generator<array<string, mixed>>
     */
    public function paginateDelta(
        string $path,
        array $query = [],
        array $extraHeaders = [],
        ?string $resumeDeltaLink = null,
        ?PaginationMonitor $monitor = null,
        ?DeltaPaginationOutcome $outcome = null,
    ): \Generator {
        $next = $resumeDeltaLink !== null && $resumeDeltaLink !== '' ? $resumeDeltaLink : $path;
        $first = $resumeDeltaLink === null || $resumeDeltaLink === '';
        $page = 0;
        $totalItems = 0;
        $deltaLink = '';
        /** @var array<string, true> $seenNextLinks */
        $seenNextLinks = [];

        if ($monitor !== null) {
            $monitor->log('info', 'Graph delta sync started', [
                'path' => $path,
                'resume' => $resumeDeltaLink !== null && $resumeDeltaLink !== '',
            ]);
        }

        while ($next !== '') {
            $page++;
            if ($monitor !== null && $page > $monitor->maxPages) {
                throw new GraphPaginationException(
                    'Graph delta pagination safety cap reached (' . $monitor->maxPages . ' pages)'
                    . ($monitor->context !== '' ? " [{$monitor->context}]" : '')
                );
            }

            if (!$first) {
                $linkKey = hash('sha256', $next);
                if (isset($seenNextLinks[$linkKey])) {
                    throw new GraphPaginationException(
                        'Graph delta pagination loop detected: identical link repeated'
                        . ($monitor?->context !== '' ? " [{$monitor->context}]" : '')
                    );
                }
                $seenNextLinks[$linkKey] = true;
            }

            try {
                if ($first && !str_starts_with($next, 'http')) {
                    $data = $this->get($next, $query, $extraHeaders);
                    $first = false;
                } else {
                    $data = $this->getAbsolute($next, $extraHeaders);
                    $first = false;
                }
            } catch (GraphApiException $e) {
                if ($this->isDeltaResetStatus($e)) {
                    throw new GraphDeltaResetException($e->getMessage(), $e->statusCode, $e);
                }
                throw $e;
            }

            $items = $data['value'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }
            $nextLink = (string) ($data['@odata.nextLink'] ?? '');
            $deltaLink = (string) ($data['@odata.deltaLink'] ?? '');

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $totalItems++;
                yield $item;
            }

            if ($monitor !== null) {
                $monitor->log('info', 'Graph delta page fetched', [
                    'page' => $page,
                    'items_on_page' => count($items),
                    'total_items' => $totalItems,
                    'has_next_link' => $nextLink !== '',
                    'has_delta_link' => $deltaLink !== '',
                ]);
            }

            if ($nextLink !== '') {
                $next = $nextLink;
                continue;
            }

            if ($deltaLink !== '') {
                break;
            }

            break;
        }

        if ($monitor !== null) {
            $monitor->log('info', 'Graph delta sync completed', [
                'pages' => $page,
                'total_items' => $totalItems,
                'has_delta_link' => $deltaLink !== '',
            ]);
        }

        if ($outcome !== null) {
            $outcome->pages = $page;
            $outcome->totalItems = $totalItems;
            $outcome->deltaLink = $deltaLink;
        }
    }

    private function isDeltaResetStatus(GraphApiException $e): bool
    {
        if ($e->statusCode === 410) {
            return true;
        }
        $code = strtolower($e->errorCode);
        if (in_array($code, ['syncstatenotfound', 'resyncrequired', 'fullsyncrequired'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, string> $extraHeaders
     */
    private function request(string $method, string $path, array $options = [], array $extraHeaders = []): array
    {
        $path = ltrim($path, '/');
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->tokens->getAccessToken(),
            'Accept' => 'application/json',
        ], $extraHeaders);

        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->http->request($method, $path, array_merge($options, ['headers' => $headers]));
            } catch (GuzzleException $e) {
                throw new \RuntimeException('Graph request failed: ' . $e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);
            if ($status === 429 && $attempt < $maxAttempts) {
                $retryAfter = $this->parseRetryAfterSeconds($response->getHeaderLine('Retry-After'));
                sleep($retryAfter);
                continue;
            }
            if ($status >= 400) {
                $ex = GraphApiException::fromResponse($status, is_array($body) ? $body : null);
                if ($method === 'GET' && $this->isDeltaResetStatus($ex)) {
                    throw new GraphDeltaResetException($ex->getMessage(), $ex->statusCode, $ex);
                }
                throw $ex;
            }

            return is_array($body) ? $body : [];
        }

        throw new GraphApiException('Graph API throttled (429) after retries', 429);
    }

    /** @param array<string, string> $extraHeaders */
    private function getAbsolute(string $url, array $extraHeaders = []): array
    {
        if (!str_starts_with($url, 'http')) {
            return $this->get($url, [], $extraHeaders);
        }
        $client = new Client(['timeout' => 120, 'http_errors' => false]);
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->tokens->getAccessToken(),
            'Accept' => 'application/json',
        ], $extraHeaders);

        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $client->get($url, ['headers' => $headers]);
            $status = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);
            if ($status === 429 && $attempt < $maxAttempts) {
                $retryAfter = $this->parseRetryAfterSeconds($response->getHeaderLine('Retry-After'));
                sleep($retryAfter);
                continue;
            }
            if ($status >= 400) {
                $ex = GraphApiException::fromResponse($status, is_array($body) ? $body : null);
                if ($this->isDeltaResetStatus($ex)) {
                    throw new GraphDeltaResetException($ex->getMessage(), $ex->statusCode, $ex);
                }
                throw $ex;
            }

            return is_array($body) ? $body : [];
        }

        throw new GraphApiException('Graph API throttled (429) after retries', 429);
    }

    private function parseRetryAfterSeconds(string $header): int
    {
        $header = trim($header);
        if ($header === '') {
            return 5;
        }
        if (ctype_digit($header)) {
            return min(max((int) $header, 1), 120);
        }
        $ts = strtotime($header);
        if ($ts !== false) {
            return min(max($ts - time(), 1), 120);
        }

        return 5;
    }

    /**
     * Stream binary content from a Graph path to a local file.
     *
     * @param array<string, string> $extraHeaders
     * @return array{bytes: int, sha256: string}
     */
    public function downloadToFile(string $path, string $destPath, array $extraHeaders = [], int $timeoutSeconds = 600): array
    {
        $path = ltrim($path, '/');
        $this->ensureParentDir($destPath);

        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->tokens->getAccessToken(),
        ], $extraHeaders);

        try {
            $response = $this->http->request('GET', $path, [
                'headers' => $headers,
                'sink' => $destPath,
                'timeout' => $timeoutSeconds,
            ]);
        } catch (GuzzleException $e) {
            if (is_file($destPath)) {
                @unlink($destPath);
            }
            throw new \RuntimeException('Graph download failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            if (is_file($destPath)) {
                @unlink($destPath);
            }
            throw GraphApiException::fromResponse($status, null);
        }

        $bytes = is_file($destPath) ? (int) filesize($destPath) : 0;
        $hash = $bytes > 0 ? hash_file('sha256', $destPath) : '';

        return ['bytes' => $bytes, 'sha256' => $hash !== false ? $hash : ''];
    }

    private function ensureParentDir(string $path): void
    {
        $dir = dirname($path);
        if ($dir !== '' && !is_dir($dir)) {
            if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new \RuntimeException('Failed to create directory: ' . $dir);
            }
        }
    }

    /** @param array<string, mixed> $item */
    private function extractItemId(array $item): string
    {
        foreach (['id', '@odata.id'] as $key) {
            if (!empty($item[$key]) && is_string($item[$key])) {
                return $item[$key];
            }
        }
        return '';
    }

    private function extractSkipToken(string $url): ?string
    {
        if ($url === '') {
            return null;
        }
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = $query['$skiptoken'] ?? $query['$skip'] ?? null;
        if ($token === null || $token === '') {
            return null;
        }
        $s = (string) $token;
        return strlen($s) > 40 ? substr($s, 0, 40) . '…' : $s;
    }

    private function truncateLink(string $url): string
    {
        if (strlen($url) <= 200) {
            return $url;
        }
        return substr($url, 0, 200) . '…';
    }
}
