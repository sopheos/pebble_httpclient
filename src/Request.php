<?php

namespace Pebble\HttpClient;

class Request
{
    use OptionTrait;

    private string $method;
    private string $url;

    // -------------------------------------------------------------------------

    public function __construct(string $method, string $url = "/")
    {
        $this->method = $method;
        $this->url = $url;
    }

    public static function head(string $url = "/"): static
    {
        return new static('HEAD', $url);
    }

    public static function get(string $url = "/"): static
    {
        return new static('GET', $url);
    }

    public static function post(string $url = "/"): static
    {
        return new static('POST', $url);
    }

    public static function put(string $url = "/"): static
    {
        return new static('PUT', $url);
    }

    public static function patch(string $url = "/"): static
    {
        return new static('PATCH', $url);
    }

    public static function delete(string $url = "/"): static
    {
        return new static('DELETE', $url);
    }

    public static function options(string $url = "/"): static
    {
        return new static('OPTIONS', $url);
    }

    // -------------------------------------------------------------------------

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    // -------------------------------------------------------------------------
}
