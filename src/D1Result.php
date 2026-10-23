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
     * D1 reports this as `changes`, the SQLite changes() counter.
     * `rows_written` covers payloads that only carry that counter, and
     * `rows_affected` is kept for older envelopes.
     *
     * Reading `rows_affected` alone - as this method used to - returns 0 on the
     * current API, because that key does not exist in the response. Every
     * INSERT, UPDATE and DELETE therefore reported zero affected rows.
     *
     * @return int
     */
    public function affectedRows(): int
    {
        foreach (['changes', 'rows_written', 'rows_affected'] as $key) {
            if (array_key_exists($key, $this->meta)) {
                return (int) $this->meta[$key];
            }
        }

        return 0;
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
