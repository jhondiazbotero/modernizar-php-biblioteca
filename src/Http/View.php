<?php

declare(strict_types=1);

namespace Library\Http;

/** Renders the plain PHP templates in templates/ and returns the HTML. */
final class View
{
    /** @var string */
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
    }

    /**
     * @param string               $template name inside the templates directory, e.g. "members/list.php"
     * @param array<string, mixed> $data     becomes the variables of the template
     */
    public function render(string $template, array $data = []): string
    {
        if (preg_match('#\A[a-z0-9_]+(/[a-z0-9_]+)*\.php\z#', $template) !== 1) {
            throw new \InvalidArgumentException('Invalid template name: ' . $template);
        }
        $file = $this->directory . '/' . $template;
        if (!is_file($file)) {
            throw new \InvalidArgumentException('Template not found: ' . $template);
        }

        $render = static function (string $templateFile, array $templateData): string {
            extract($templateData, EXTR_SKIP);
            ob_start();
            try {
                include $templateFile;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render($file, $data);
    }
}
