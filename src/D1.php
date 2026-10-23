<?php

namespace Cloudflare\D1;

class D1
{
    /**
     * Cloudflare D1 query endpoint.
     *
     * Both identifiers are url-encoded before interpolation, so a value coming
     * from user input cannot rewrite the request path ("/../", "?x=", etc.).
     */
    private const API_BASE = 'https://api.cloudflare.com/client/v4/accounts/%s/d1/database/%s/query';

    private string $token;
    private string $url;

    /** Seconds allowed for the TCP/TLS handshake. */
    private int $connectTimeout = 10;

    /** Seconds allowed for the whole request, body included. */
    private int $timeout = 30;

    /**
     * Create a new D1 instance.
     *
     * Credentials are required arguments. There is deliberately no process-wide
     * default: global state cannot express "two databases", it makes tests
     * order-dependent, and a credential assigned after construction used to be
     * silently ignored.
     *
     * @param string $accountId Your Cloudflare account ID
     * @param string $apiToken Your Cloudflare API token with D1 permissions
     * @param string $databaseId Your D1 database ID
     * @throws D1Exception when a credential is an empty string
     */
    public function __construct(string $accountId, string $apiToken, string $databaseId)
    {
        $credentials = [
            'accountId' => $accountId,
            'apiToken' => $apiToken,
            'databaseId' => $databaseId,
        ];

        foreach ($credentials as $name => $value) {
            if ($value === '') {
                // Fail here instead of sending the request to
                // ".../accounts//d1/database//query", which would leak the
                // bearer token to an invalid endpoint and return a confusing
                // 400 from Cloudflare.
                throw new D1Exception(sprintf('D1: %s must not be empty.', $name));
            }
        }

        $this->token = $apiToken;
        $this->url = sprintf(self::API_BASE, rawurlencode($accountId), rawurlencode($databaseId));
    }

    /**
     * Set the total request timeout in seconds.
     *
     * @return $this
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);

        return $this;
    }

    /**
     * Set the connection timeout in seconds.
     *
     * @return $this
     */
    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = max(1, $seconds);

        return $this;
    }

    /**
     * Execute a query against the D1 database.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for prepared statements
     * @return D1Result
     * @throws D1Exception
     */
    public function query(string $query, array $params = []): D1Result
    {
        $body = json_encode(
            [
                'sql' => $query,
                // D1 only understands positional parameters; normalising the
                // array keeps associative input from being sent as an object.
                'params' => array_values($params),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($body === false) {
            // Invalid UTF-8 in a parameter used to produce an empty POST body
            // and an unrelated error from Cloudflare. Say what actually broke.
            throw new D1Exception('D1: query parameters could not be JSON encoded: ' . json_last_error_msg());
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            // Without an explicit timeout a stalled api.cloudflare.com blocks
            // the PHP worker indefinitely and can exhaust the whole pool.
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            // Verify TLS explicitly instead of trusting a php.ini default, and
            // never follow redirects: the Authorization header must not be
            // replayed to a host we did not choose.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            throw new D1Exception('cURL error (' . $errno . '): ' . ($error !== '' ? $error : 'no message'), $errno);
        }

        $decoded = json_decode((string) $response, true);

        // A non-JSON body (HTML error page from an edge node, truncated
        // response, empty body) used to raise "Trying to access array offset
        // on null" warnings before failing with a meaningless message.
        if (!is_array($decoded)) {
            throw new D1Exception(sprintf('D1: response is not valid JSON (HTTP %d).', $httpCode), $httpCode);
        }

        if ($httpCode >= 400 || empty($decoded['success'])) {
            $message = $decoded['errors'][0]['message'] ?? ('HTTP ' . $httpCode);
            throw new D1Exception('D1 API error: ' . $message, $httpCode);
        }

        return new D1Result($this->extractStatement($decoded['result'] ?? null));
    }

    /**
     * Reduce the API envelope to the statement result D1Result expects.
     *
     * Cloudflare wraps each query in a list of statement results:
     * `"result" => [ ["results" => [...], "meta" => [...]] ]`.
     * The previous code handed that whole list to D1Result, which then looked
     * for `$result['results']`, found nothing, and returned an empty set - so
     * every read silently came back empty while the query itself had run.
     *
     * For multi-statement batches only the first statement is exposed, matching
     * the behaviour of the single-statement helpers.
     *
     * @param mixed $result
     * @return array
     * @throws D1Exception when the statement itself failed
     */
    private function extractStatement($result): array
    {
        if (!is_array($result)) {
            return [];
        }

        // Already a single statement - kept so an unwrapped payload still works.
        if (array_key_exists('results', $result) || array_key_exists('meta', $result)) {
            return $result;
        }

        $statement = $result[0] ?? null;

        if (!is_array($statement)) {
            return [];
        }

        if (array_key_exists('success', $statement) && $statement['success'] === false) {
            $message = $statement['error'] ?? 'statement failed';
            throw new D1Exception('D1 statement error: ' . (is_string($message) ? $message : 'unknown'));
        }

        return $statement;
    }

    /**
     * Execute a query and return the first row.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for prepared statements
     * @return array|null
     * @throws D1Exception
     */
    public function first(string $query, array $params = []): ?array
    {
        $result = $this->query($query, $params);

        return $result->first();
    }

    /**
     * Execute a query and get all results.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for prepared statements
     * @return array
     * @throws D1Exception
     */
    public function get(string $query, array $params = []): array
    {
        $result = $this->query($query, $params);

        return $result->all();
    }

    /**
     * Execute a query and get a single value.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for prepared statements
     * @return mixed
     * @throws D1Exception
     */
    public function value(string $query, array $params = [])
    {
        $result = $this->query($query, $params);
        $row = $result->first();

        if (empty($row)) {
            return null;
        }

        return reset($row);
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query and return the number of affected rows.
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for prepared statements
     * @return int
     * @throws D1Exception
     */
    public function execute(string $query, array $params = []): int
    {
        $result = $this->query($query, $params);

        return $result->affectedRows();
    }
}
