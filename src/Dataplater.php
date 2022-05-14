<?php

namespace Le\Dataplater;

use Exception;
use DOMDocument, DOMElement, DOMXPath;
use Le\SMPLang\SMPLang;

class Dataplater
{

    protected DOMDocument $doc;
    protected DOMXPath $xpath;
    protected bool $removeWrapper = true;

    /**
     * @throws Exception
     */
    public function __construct(
        ?string $filename = null,
        ?string $template = null,
        protected array $vars = [],
        protected string $baseDir = '.',
    )
    {
        if($filename === null && $template === null)
            throw new Exception('must provide either the filename or the template string param');

        if($filename !== null && $template !== null)
            throw new Exception('you can either pass the filename or template string params, not both');

        if($filename !== null) {
            $filename = "$this->baseDir/$filename";
            if (!file_exists($filename)) throw new Exception('template file not found');
            $template = file_get_contents($filename);
        }

        if(str_contains($template, '<html'))
            $this->removeWrapper = false;
        else
            $template = "<dataplater>$template</dataplater>";

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
        if(!empty($vars)){
            $instance = clone $this;
            $instance->vars = [...$instance->vars, ...$vars];
            $result = $instance->render();
            unset($instance);
            return $result;
        }

        $this->execute();
        $html = $this->doc->saveHTML();
        libxml_clear_errors();

        if($this->removeWrapper)
            $html = substr($html, 12, -13);

        return $html;
    }

    /**
     * @throws ParseException
     */
    private function execute($context = null): void
    {
        // IF
        $attr = "data-dp-if";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($attr), $elem);

            if($result) $elem->removeAttribute($attr);
            else $elem->parentNode->removeChild($elem);
        }

        // INCLUDE
        $attr = "data-dp-include";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem){
            $includeFile = $elem->getAttribute($attr);
            $includeFile = "$this->baseDir/$includeFile";

            if(!file_exists($includeFile))
                throw new ParseException("include file `$includeFile` not found", $elem);

            $html = file_get_contents($includeFile);
            $doc = $this->domDocumentFromHtml("<dataplater>$html</dataplater>");
            $wrapper = $doc->getElementsByTagName('dataplater')->item(0);

            foreach($wrapper->childNodes as $child){
                $child = $this->doc->importNode($child, true);
                $elem->parentNode->insertBefore($child, $elem);
            }

            $elem->parentNode->removeChild($elem);
        }

        // FOREACH
        $attr = "data-dp-foreach";
        $attrKey = "data-dp-key";
        $attrVar = "data-dp-var";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem){
            $iterable = $this->eval($elem->getAttribute($attr), $elem);
            if(!is_iterable($iterable)) throw new ParseException("expression result not iterable", $elem);

            $varKey = $elem->getAttribute($attrKey);
            $varValue = $elem->getAttribute($attrVar)
                ?: throw new ParseException("missing value variable name in `$attrKey`", $elem);

            foreach($iterable as $key => $value) {
                if(!empty($varKey)) $this->vars[$varKey] = $key;
                $this->vars[$varValue] = $value;

                foreach ($elem->childNodes as $childNode) {
                    if(!$childNode instanceof DOMElement) continue;
                    $clone = $childNode->cloneNode(true);
                    $elem->parentNode->insertBefore($clone, $elem);
                    $this->execute($clone);
                }
            }

            $elem->parentNode->removeChild($elem); // remove the foreach template element
        }

        // TEXT OR ATTRIBUTE
        $attr = "data-dp";
        $selectorAttr = "data-dp-attr";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
            $targetAttr = $elem->getAttribute($selectorAttr) ?: null;
            if($targetAttr !== null) $elem->removeAttribute($selectorAttr);

            $result = $this->eval($elem->getAttribute($attr), $elem);
            $elem->removeAttribute($attr);
            if($result === null) continue;

            if ($targetAttr === null) $elem->nodeValue = $result;
            else $elem->setAttribute($targetAttr, $result);
        }

        // HTML
        $attr = "data-dp-html";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($attr), $elem);
            $elem->removeAttribute($attr);
            if($result === null) continue;

            $elem->nodeValue = '';
            $doc = $this->domDocumentFromHtml("<dataplater>$result</dataplater>");
            $wrapper = $doc->getElementsByTagName('dataplater')->item(0);
            foreach($wrapper->childNodes as $child){
                $child = $this->doc->importNode($child, true);
                $elem->appendChild($child);
            }
        }

        // ATTRIBUTE SHORTCUTS
        $shortcutAttrs = ['id', 'class', 'title', 'alt', 'value', 'href', 'src', 'style'];
        foreach($shortcutAttrs as $targetAttr) {
            $attr = "data-dp-$targetAttr";
            foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
                $result = $this->eval($elem->getAttribute($attr), $elem);
                $elem->removeAttribute($attr);
                if($result !== null) $elem->setAttribute($targetAttr, $result);
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
        }
        catch(\Le\SMPLang\Exception $e) {
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

}