<?php

namespace Cloudflare\D1;

/**
 * Wraps a single Cloudflare D1 statement result:
 * `["results" => [...], "meta" => [...], "success" => true]`.
 */
class D1Result
{
    private array $result;
    private array $rows;
    private array $meta;

    public function __construct(array $result)
    {
        $this->result = $result;
        $this->rows = isset($result['results']) && is_array($result['results']) ? $result['results'] : [];
        $this->meta = isset($result['meta']) && is_array($result['meta']) ? $result['meta'] : [];
    }

    /**
     * Get all rows from the result.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->rows;
    }

    /**
     * Get the first row from the result.
     *
     * @return array|null
     */
    public function first(): ?array
    {
        return $this->rows[0] ?? null;
    }

    /**
     * Get the number of rows returned by the query.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * Get the number of rows affected by the query.
     *
     * @return int
     */
    public function affectedRows(): int
    {
        return (int) ($this->meta['rows_affected'] ?? 0);
    }

    /**
     * Get the last insert ID.
     *
     * @return int
     */
    public function lastInsertId(): int
    {
        return (int) ($this->meta['last_row_id'] ?? 0);
    }

    /**
     * Get the raw statement metadata (timings, changes, served_by, ...).
     *
     * @return array
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * Get the raw result array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->result;
    }
}
