<?php

namespace System\Engine;

class Response
{
    private array $headers = [];
    private int $statusCode = 200;
    private string $output = '';

    public function addHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function setOutput(string $output): void
    {
        $this->output = $output;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            if (!$this->hasHeaderPrefix('Content-Type:')) {
                header('Content-Type: text/html; charset=UTF-8', true);
            }

            foreach ($this->headers as $header) {
                header($header, true);
            }
        }

        echo $this->output;
    }

    public function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
        }
        exit;
    }

    private function hasHeaderPrefix(string $prefix): bool
    {
        $prefixLength = strlen($prefix);

        foreach ($this->headers as $header) {
            if (strncasecmp($header, $prefix, $prefixLength) === 0) {
                return true;
            }
        }

        return false;
    }
}
