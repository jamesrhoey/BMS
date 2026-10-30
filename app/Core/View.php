<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewsRoot = rtrim((string) Config::get('app.paths.views', BASE_PATH . '/app/Views'), '/\\');
        $viewFile = $viewsRoot . '/' . ltrim($view, '/') . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("View [{$view}] not found.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        Response::sendSecurityHeaders();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = $viewsRoot . '/' . ltrim($layout, '/') . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout [{$layout}] not found.");
        }

        require $layoutFile;
    }
}

