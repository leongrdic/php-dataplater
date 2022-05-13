<?php

namespace Le\Dataplater;

use Exception;
use DOMDocument, DOMElement, DOMXPath;
use Le\SMPLang\SMPLang;

class Dataplater
{

    protected DOMDocument $doc;
    protected DOMXPath $xpath;

    /**
     * @throws Exception
     */
    public function __construct(
        ?string $filename = null,
        ?string $template = null,
        public array $vars = []
    )
    {
        if($filename === null && $template === null)
            throw new Exception('must provide either the filename or the template string param');

        if($filename !== null && $template !== null)
            throw new Exception('you can either pass the filename or template string params, not both');

        if($filename !== null) {
            if (!file_exists($filename)) throw new Exception('template file not found');
            $template = file_get_contents($filename);
        }

        libxml_use_internal_errors(true);
        $this->doc = new DOMDocument();
        $this->doc->loadHTML($template);
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

        $this->doc->formatOutput = true;
        $html = $this->doc->saveHTML();
        libxml_clear_errors();

        return $html;
    }

    /**
     * @throws ParseException
     */
    private function execute($context = null): void
    {
        $shortcutAttrs = ['id', 'class', 'title', 'alt', 'value', 'href', 'src', 'style'];

        // FOREACH
        $attr = "data-dp-foreach";
        $attrKey = "data-dp-key";
        $attrVar = "data-dp-var";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem){
            $iterable = $this->eval($elem->getAttribute($attr), $elem);
            if(!is_iterable($iterable)) throw new ParseException("expression result not iterable", $elem);

            $varKey = $elem->getAttribute($attrKey);
            $varValue = $elem->getAttribute($attrVar);
            if(empty($varValue)) throw new ParseException("missing value variable name in `$attrKey`", $elem);

            foreach($iterable as $key => $value) {
                if(!empty($varKey)) $this->vars[$varKey] = $key;
                $this->vars[$varValue] = $value;

                foreach ($elem->childNodes as $childNode) {
                    if(!$childNode instanceof DOMElement) continue;

                    $clone = $childNode->cloneNode(true);
                    $elem->parentNode->appendChild($clone);

                    $this->execute($clone);
                }
            }

            $elem->parentNode->removeChild($elem); // remove the foreach template element
        }

        // IF
        $attr = "data-dp-if";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($attr), $elem);

            if($result) $elem->removeAttribute($attr);
            else $elem->parentNode->removeChild($elem);
        }

        // TEXT OR ATTRIBUTE
        $attr = "data-dp";
        $selectorAttr = "data-dp-attr";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($attr), $elem);

            if($elem->hasAttribute($selectorAttr)) {
                if($result === null){
                    $elem->removeAttribute($attr);
                    $elem->removeAttribute($selectorAttr);
                    continue;
                }

                $targetAttr = $elem->getAttribute($selectorAttr);
                if($targetAttr === '') throw new ParseException('target attribute empty', $elem);

                $elem->setAttribute($targetAttr, $result);
                $elem->removeAttribute($attr);
                $elem->removeAttribute($selectorAttr);
            } else {
                if($result === null){
                    $elem->removeAttribute($attr);
                    continue;
                }

                $elem->nodeValue = $result;
                $elem->removeAttribute($attr);
            }
        }

        // HTML
        $attr = "data-dp-html";
        foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($attr), $elem);
            if($result === null){
                $elem->removeAttribute($attr);
                continue;
            }

            $fragment = $this->doc->createDocumentFragment();
            $fragment->appendXML($result);
            $elem->nodeValue = '';
            $elem->appendChild($fragment);

            $elem->removeAttribute($attr);
        }

        // ATTRIBUTE SHORTCUTS
        foreach($shortcutAttrs as $targetAttr) {
            $attr = "data-dp-$targetAttr";
            foreach ($this->xpath->query("descendant-or-self::*[@$attr]", $context) as $elem) {
                $result = $this->eval($elem->getAttribute($attr), $elem);
                if($result === null){
                    $elem->removeAttribute($attr);
                    continue;
                }

                $elem->setAttribute($targetAttr, $result);
                $elem->removeAttribute($attr);
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

}