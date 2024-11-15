<?php

namespace Pebble\HttpClient;

class Response
{
    private int $status;
    private array $headers;
    private ?string $body = null;

    /**
     * @param integer $status
     * @param array $headers
     * @param string $body
     */
    public function __construct(int $status, array $headers, ?string $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function header(string $name): ?string
    {
        if (!isset($this->headers[$name][0])) {
            return null;
        }
        return mb_strtolower($this->headers[$name][0]);
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function json(): array
    {
        if (!$this->body) {
            return [];
        }

        if (!($type = $this->header('Content-Type'))) {
            return [];
        }

        if (mb_strpos($type, 'application/json') === false) {
            return [];
        }

        $data = json_decode($this->body, true);
        return $data && is_array($data) ? $data : null;
    }
}
