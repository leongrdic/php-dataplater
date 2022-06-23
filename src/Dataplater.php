<?php

namespace Le\Dataplater;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Le\SMPLang\Exception;
use Le\SMPLang\SMPLang;

class Dataplater
{
    const AXIS = 'descendant-or-self::*';

    protected DOMDocument $doc;
    protected DOMXPath $xpath;
    protected bool $removeWrapper = true;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?string          $filename = null,
        ?string          $template = null,
        protected array  $vars = [],
        protected string $attr = 'data-dp',
        protected string $baseDir = '.',
    ) {
        if ($filename === null && $template === null) {
            throw new InvalidArgumentException('must provide either the filename or the template string param');
        }

        if ($filename !== null && $template !== null) {
            throw new InvalidArgumentException('you can either pass the filename or template string params, not both');
        }

        if ($filename !== null) {
            // if an absolute path is given, use it, otherwise prepend baseDir to the filename
            if (!str_starts_with($filename, '/')) {
                $filename = "$this->baseDir/$filename";
            }
            if (!file_exists($filename)) {
                throw new InvalidArgumentException("template file `$filename` not found");
            }
            $template = file_get_contents($filename);
        }

        if (str_contains($template, '<html')) {
            $this->removeWrapper = false;
        } else {
            $template = "<?xml encoding=\"utf-8\" ?><dataplater>$template</dataplater>";
        }

        libxml_use_internal_errors(true);
        $this->doc = $this->domDocumentFromHtml($template);
        $this->xpath = new DOMXpath($this->doc);

        // all php functions can be accessed through this object
        $this->vars['php'] = new PhpProxy();
    }

    /**
     * @throws ParseException
     * @return string rendered HTML as a string
     */
    public function render(array $vars = []): string
    {
        if (!empty($vars)) {
            $instance = clone $this;
            $instance->vars = [...$instance->vars, ...$vars];
            $result = $instance->render();
            unset($instance);
            return $result;
        }

        $this->execute();
        $html = $this->doc->saveHTML();
        libxml_clear_errors();

        if ($this->removeWrapper) {
            $html = str_replace(['<?xml encoding="utf-8" ?>', '<dataplater>', '</dataplater>'], '', $html);
        }

        return $html;
    }

    public function __clone(): void
    {
        $this->doc = clone $this->doc;
        $this->xpath = new DOMXpath($this->doc);
    }

    /**
     * @throws ParseException
     */
    private function execute($context = null): void
    {
        $axis = self::AXIS;

        // INCLUDE
        $attr = "$this->attr-include";
        foreach ($this->xpath->query("{$axis}[@$attr]", $context) as $elem) {
            $includeFile = $elem->getAttribute($attr);
            $includeFile = "$this->baseDir/$includeFile";

            if (!file_exists($includeFile)) {
                throw new ParseException("include file `$includeFile` not found", $elem);
            }

            $html = file_get_contents($includeFile);
            $doc = $this->domDocumentFromHtml("<?xml encoding=\"utf-8\" ?><dataplater>$html</dataplater>");
            $this->importTagChildren($doc, 'dataplater', fn ($child) => $elem->parentNode->insertBefore($child, $elem));

            $elem->parentNode->removeChild($elem);
        }

        // IF
        $attr = "$this->attr-if";
        foreach ($this->xpath->query("{$axis}[@$attr]", $context) as $elem) {
            // is this element inside foreach, if so skip it because not all variables are set yet
            $parent = $elem;
            while ($parent = $parent->parentNode) {
                if (!$parent instanceof DOMDocument && $parent->hasAttribute("$this->attr-foreach")) {
                    continue 2;
                }
            }

            $result = $this->eval($elem->getAttribute($attr), $elem);
            if ($result) {
                $elem->removeAttribute($attr);
            } else {
                $elem->parentNode->removeChild($elem);
            }
        }

        // FOREACH
        $attr = "$this->attr-foreach";
        $attrKey = "$this->attr-key";
        $attrVar = "$this->attr-var";
        foreach ($this->xpath->query("{$axis}[@$attr]", $context) as $elem) {
            $iterable = $this->eval($elem->getAttribute($attr), $elem);
            if (!is_iterable($iterable)) {
                throw new ParseException("expression result not iterable", $elem);
            }

            $varKey = $elem->getAttribute($attrKey);
            $varValue = $elem->getAttribute($attrVar)
                ?: throw new ParseException("missing foreach value variable name in `$attrVar`", $elem);

            foreach ($iterable as $key => $value) {
                if (!empty($varKey)) {
                    $this->vars[$varKey] = $key;
                }
                $this->vars[$varValue] = $value;

                foreach ($elem->childNodes as $childNode) {
                    if (!$childNode instanceof DOMElement) {
                        continue;
                    }
                    $clone = $childNode->cloneNode(true);
                    $elem->parentNode->insertBefore($clone, $elem);
                    $this->execute($clone);
                }
            }

            $elem->parentNode->removeChild($elem); // remove the foreach template element
        }

        // HTML
        $attr = "$this->attr-html";
        foreach ($this->xpath->query("{$axis}[@$attr]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($attr), $elem);
            $elem->removeAttribute($attr);
            if ($result === null) {
                continue;
            }

            $elem->nodeValue = '';
            $doc = $this->domDocumentFromHtml("<?xml encoding=\"utf-8\" ?><dataplater>$result</dataplater>");
            $this->importTagChildren($doc, 'dataplater', fn ($child) => $elem->appendChild($child));
        }

        // TEXT
        $attr = $this->attr;
        foreach ($this->xpath->query("{$axis}[@$attr]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($attr), $elem);
            $elem->removeAttribute($attr);

            if ($result === null) {
                continue;
            }
            $elem->nodeValue = $result;
        }

        // CUSTOM ATTRIBUTE
        $attr = "$this->attr-attr";
        foreach ($this->xpath->query("{$axis}[@$attr]", $context) as $elem) {
            [$targetAttr, $expression] = explode(';', $elem->getAttribute($attr), 2);
            $elem->removeAttribute($attr);

            $targetAttr = trim($targetAttr);
            $result = $this->eval($expression, $elem);

            if ($result === null) {
                continue;
            }
            $elem->setAttribute($targetAttr, $result);
        }

        // ATTRIBUTE SHORTCUTS
        $shortcutAttrs = ['id', 'class', 'title', 'alt', 'value', 'href', 'src', 'style'];
        foreach ($shortcutAttrs as $targetAttr) {
            $attr = "$this->attr-$targetAttr";
            foreach ($this->xpath->query("{$axis}[@$attr]", $context) as $elem) {
                $result = $this->eval($elem->getAttribute($attr), $elem);
                $elem->removeAttribute($attr);
                if ($result !== null) {
                    $elem->setAttribute($targetAttr, $result);
                }
            }
        }
    }

    /**
     * @throws ParseException
     */
    private function eval(string $expression, DOMElement $node)
    {
        try {
            $smpl = new SMPLang($this->vars);
            $result = $smpl->evaluate($expression);
            unset($smpl);

            return is_callable($result) ? $result() : $result;
        } catch (Exception $e) {
            throw new ParseException($e->getMessage(), $node, $e);
        }
    }

    private function domDocumentFromHtml(string $html): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        return $doc;
    }

    private function importTagChildren(DOMDocument $doc, string $tag, callable $forEach): void
    {
        // get all children of the tag, import them into main document
        foreach ($doc->getElementsByTagName($tag)->item(0)->childNodes as $child) {
            $forEach($this->doc->importNode($child, true));
        }
    }
}
