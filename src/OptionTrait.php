<?php

namespace Pebble\HttpClient;

use GuzzleHttp\RequestOptions;

trait OptionTrait
{
    private array $options = [];

    // -------------------------------------------------------------------------

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options = []): static
    {
        $this->options = $options;
        return $this;
    }

    public function addOptions(array $options): static
    {
        foreach ($options as $name => $value) {
            $this->addOption($name, $value);
        }
        return $this;
    }

    public function addOption(string $name, mixed $value): static
    {
        $this->options[$name] = $value;
        return $this;
    }

    public function queryParams(array $data): static
    {
        return $this->addOption(RequestOptions::QUERY, $data);
    }

    public function jsonParams(array $data): static
    {
        return $this->addOption(RequestOptions::JSON, $data);
    }

    public function fileParams(array $data): static
    {
        return $this->addOption(RequestOptions::MULTIPART, $data);
    }

    public function formParams(array $data): static
    {
        return $this->addOption(RequestOptions::FORM_PARAMS, $data);
    }

    public function body(mixed $data): static
    {
        return $this->addOption(RequestOptions::BODY, $data);
    }

    public function timeout(int $seconds): static
    {
        return $this->addOption(RequestOptions::TIMEOUT, $seconds);
    }

    // -------------------------------------------------------------------------

    public function getHeaders(): array
    {
        return $this->options[RequestOptions::HEADERS] ?? [];
    }

    public function setHeaders(array $headers = []): static
    {
        $this->options[RequestOptions::HEADERS] = $headers;
        return $this;
    }

    public function addHeaders(array $headers = []): static
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }
        return $this;
    }

    public function addHeader(string $name, mixed $value): static
    {
        $this->options[RequestOptions::HEADERS][$name] = $value;
        return $this;
    }

    public function auth(string $token, string $type = 'Bearer'): static
    {
        return $this->addHeader("Authorization", trim("{$type} {$token}"));
    }

    public function userAgent(string $userAgent): static
    {
        return $this->addHeader('User-Agent', $userAgent);
    }

    // -------------------------------------------------------------------------
}
