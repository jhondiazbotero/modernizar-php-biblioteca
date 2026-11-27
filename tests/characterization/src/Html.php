<?php

declare(strict_types=1);

namespace Library\Characterization;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Read-only view over an HTML page, built on DOMDocument and XPath.
 */
final class Html
{
    private readonly DOMXPath $xpath;

    private function __construct(DOMDocument $document)
    {
        $this->xpath = new DOMXPath($document);
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        return self::fromString((string) $response->getBody());
    }

    public static function fromString(string $html): self
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new self($document);
    }

    /**
     * Collapses whitespace between tags and inside text and drops HTML comments.
     * Every tag ends up on its own line so snapshot diffs stay readable.
     */
    public static function normalize(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $html = preg_replace('/\s+/u', ' ', $html) ?? $html;
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;
        $html = str_replace('><', ">\n<", $html);

        return trim($html) . "\n";
    }

    public function title(): string
    {
        return $this->text('//title');
    }

    public function heading(): string
    {
        return $this->text('//h2');
    }

    public function has(string $xpath): bool
    {
        return $this->count($xpath) > 0;
    }

    public function count(string $xpath): int
    {
        return $this->nodes($xpath)->length;
    }

    /** Text of the first node matching the XPath, whitespace collapsed. */
    public function text(string $xpath): string
    {
        $node = $this->nodes($xpath)->item(0);
        if ($node === null) {
            throw new RuntimeException("No node matches {$xpath}");
        }

        return self::clean($node->textContent);
    }

    /** @return list<string> */
    public function texts(string $xpath): array
    {
        $texts = [];
        foreach ($this->nodes($xpath) as $node) {
            $texts[] = self::clean($node->textContent);
        }

        return $texts;
    }

    /** Success or error banner shown after an action, or null when absent. */
    public function message(): ?string
    {
        $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' mensaje ')]";

        return $this->has($xpath) ? $this->text($xpath) : null;
    }

    /** "ok" or "error" for the banner, or null when absent. */
    public function messageKind(): ?string
    {
        $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' mensaje ')]";
        $node = $this->nodes($xpath)->item(0);
        if ($node === null) {
            return null;
        }
        $classes = explode(' ', trim((string) $node->attributes?->getNamedItem('class')?->nodeValue));

        return in_array('error', $classes, true) ? 'error' : 'ok';
    }

    /** @return list<string> */
    public function tableHeaders(int $table = 0): array
    {
        return $this->texts(sprintf('(//table)[%d]//th', $table + 1));
    }

    /**
     * Body rows (the ones with data cells) as lists of cell texts.
     *
     * @return list<list<string>>
     */
    public function tableRows(int $table = 0): array
    {
        $rows = [];
        foreach ($this->nodes(sprintf('(//table)[%d]//tr[td]', $table + 1)) as $row) {
            $cells = [];
            foreach ($this->xpath->query('./td', $row) ?: [] as $cell) {
                $cells[] = self::clean($cell->textContent);
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * Options of a select, keyed by value.
     *
     * @return array<string, string>
     */
    public function selectOptions(string $selectId): array
    {
        $options = [];
        foreach ($this->nodes(sprintf('//select[@id="%s"]/option', $selectId)) as $option) {
            $value = (string) $option->attributes?->getNamedItem('value')?->nodeValue;
            $options[$value] = self::clean($option->textContent);
        }

        return $options;
    }

    /** @return \DOMNodeList<DOMNode> */
    private function nodes(string $xpath): \DOMNodeList
    {
        $nodes = $this->xpath->query($xpath);
        if ($nodes === false) {
            throw new RuntimeException("Invalid XPath: {$xpath}");
        }

        return $nodes;
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
