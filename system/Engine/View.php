<?php

namespace System\Engine;

class View
{
    public function __construct(private readonly string $viewPath)
    {
    }

    public function render(string $template, array $data = [], ?string $layout = 'layout/main'): string
    {
        $templateFile = $this->resolve($template);
        if (!is_file($templateFile)) {
            return 'Template não encontrada: ' . $template;
        }

        $content = $this->capture($templateFile, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutFile = $this->resolve($layout);
        if (!is_file($layoutFile)) {
            return $content;
        }

        $layoutData = $data;
        $layoutData['content'] = $content;

        return $this->capture($layoutFile, $layoutData);
    }

    private function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        return rtrim($this->viewPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';
    }
}
