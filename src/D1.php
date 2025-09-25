<?php

namespace Cloudflare\D1;

class D1
{
    private $accountId;
    private $apiToken;
    private $databaseId;
    private $baseUrl = 'https://api.cloudflare.com/client/v4/accounts/%s/d1/database/%s/query';

    /**
     * Create a new D1 instance.
     *
     * @param string $accountId Your Cloudflare account ID
     * @param string $apiToken Your Cloudflare API token with D1 permissions
     * @param string $databaseId Your D1 database ID
     */
    public function __construct(string $accountId, string $apiToken, string $databaseId)
    {
        $this->accountId = $accountId;
        $this->apiToken = $apiToken;
        $this->databaseId = $databaseId;
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
        $url = sprintf($this->baseUrl, $this->accountId, $this->databaseId);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
        ];

        $data = [
            'sql' => $query,
            'params' => $params,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new D1Exception("cURL error: " . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400 || !$result['success']) {
            $error = $result['errors'][0]['message'] ?? 'Unknown error';
            throw new D1Exception("D1 API error: " . $error, $httpCode);
        }

        return new D1Result($result['result']);
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
