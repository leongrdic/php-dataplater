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

        // used to parse foreach attributes
        $this->vars['dataplater_foreach'] = $this->foreachParser(...);
    }

    /**
     * @throws ParseException
     * @return string rendered HTML as a string
     */
    public function render(?array $vars = null): string
    {
        if(is_array($vars)){
            $instance = clone $this;
            $instance->vars = array_merge($instance->vars, $vars);
            return $instance->render();
        }

        $this->execute();

        $html = $this->doc->saveHTML();
        libxml_clear_errors();

        return $html;
    }

    /**
     * @throws ParseException
     */
    private function execute($context = null): void
    {
        $attributes = ['id', 'class', 'title', 'alt', 'value', 'href', 'src', 'style'];

        // LOOP

        $selector = "data-dp-foreach";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem){
            $params = $elem->getAttribute($selector);

            $smpl = new SMPLang($this->vars);
            [$iterable, $varIndex, $varValue] = $smpl->evaluate("dataplater_foreach($params)");

            if(!is_iterable($iterable)) throw new ParseException("expression result not iterable", $elem);
            if($varValue) throw new ParseException('missing value variable name', $elem);

            foreach($params[0] as $index => $value) {
                if(!empty($varIndex)) $this->vars[$varIndex] = $index;
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
        $selector = "data-dp-if";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($selector), $elem);

            if($result) $elem->removeAttribute($selector);
            else $elem->parentNode->removeChild($elem);
        }

        // TEXT OR ATTRIBUTE
        $selector = "data-dp";
        $selectorAttr = "data-dp-attr";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($selector), $elem);

            if($elem->hasAttribute($selectorAttr)) {
                if($result === null){
                    $elem->removeAttribute($selector);
                    $elem->removeAttribute($selectorAttr);
                    continue;
                }

                $targetAttr = $elem->getAttribute($selectorAttr);
                if($targetAttr === '') throw new ParseException('target attribute empty', $elem);

                $elem->setAttribute($targetAttr, $result);
                $elem->removeAttribute($selector);
                $elem->removeAttribute($selectorAttr);
            } else {
                if($result === null){
                    $elem->removeAttribute($selector);
                    continue;
                }

                $elem->nodeValue = $result;
                $elem->removeAttribute($selector);
            }
        }

        // HTML
        $selector = "data-dp-html";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            $result = $this->eval($elem->getAttribute($selector), $elem);
            if($result === null){
                $elem->removeAttribute($selector);
                continue;
            }

            $fragment = $this->doc->createDocumentFragment();
            $fragment->appendXML($result);
            $elem->nodeValue = '';
            $elem->appendChild($fragment);

            $elem->removeAttribute($selector);
        }

        // ATTRIBUTE SHORTCUTS
        foreach($attributes as $attribute) {
            $selector = "data-dp-$attribute";
            foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
                $result = $this->eval($elem->getAttribute($selector), $elem);
                if($result === null){
                    $elem->removeAttribute($selector);
                    continue;
                }

                $elem->setAttribute($attribute, $result);
                $elem->removeAttribute($selector);
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
        }
        catch(\Le\SMPLang\Exception $e) {
            throw new ParseException($e->getMessage(), $node, $e);
        }

        return is_callable($result) ? $result() : $result;
    }

    private function foreachParser(array $iterable, string $valueOrKey, ?string $value = null): array
    {
        return [
            $iterable,
            ($value === null ? null : $valueOrKey),
            ($value === null ? $valueOrKey : $value),
        ];
    }

}