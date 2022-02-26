<?php

namespace Le\Dataplater;

use DOMDocument, DOMElement, DOMXPath;
use InvalidArgumentException, ArgumentCountError, Exception;

class Dataplater
{

    private DOMDocument $doc;
    private DOMXPath $xpath;

    /**
     * @throws Exception
     */
    public function __construct(
        string $filename,
        public array $vars = []
    )
    {
        if(!file_exists($filename)) throw new Exception('template file not found');
        $html = file_get_contents($filename);

        libxml_use_internal_errors(true);
        $this->doc = new DOMDocument();
        $this->doc->loadHTML($html);
        $this->xpath = new DOMXpath($this->doc);

        $this->defineBuiltInFunctions();
    }

    /**
     * @throws ParseException
     * @throws EvaluationException
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

    private function defineBuiltInFunctions(): void
    {
        $this->vars['logic']['and'] =           fn($a, $b) => $a && $b;
        $this->vars['logic']['or'] =            fn($a, $b) => $a || $b;
        $this->vars['logic']['not'] =           fn($a) => !$a;

        $this->vars['compare']['larger'] =        fn($a, $b) => $a > $b;
        $this->vars['compare']['largerEqual'] =   fn($a, $b) => $a >= $b;
        $this->vars['compare']['smaller'] =       fn($a, $b) => $a < $b;
        $this->vars['compare']['smallerEqual'] =  fn($a, $b) => $a <= $b;
        $this->vars['compare']['equal'] =         fn($a, $b) => $a == $b;
        $this->vars['compare']['different'] =     fn($a, $b) => $a != $b;
        $this->vars['compare']['exact'] =         fn($a, $b) => $a === $b;
        $this->vars['compare']['notExact'] =      fn($a, $b) => $a !== $b;

        $this->vars['array']['count'] =     fn($a) => count($a);
        $this->vars['array']['reverse'] =   fn($a) => array_reverse($a);

        $this->vars['text']['length'] =     fn($a) => strlen($a);
        $this->vars['text']['concat'] =     fn(...$a) => implode('', $a);
        $this->vars['text']['implode'] =    fn($a, $b) => implode($a, $b);
        $this->vars['text']['explode'] =    fn($a, $b, $c = PHP_INT_MAX) => explode($a, $b, $c);

        $this->vars['json']['encode'] =     fn($a) => json_encode($a);
        $this->vars['json']['decode'] =     fn($a) => json_decode($a, true);
    }

    /**
     * @throws ParseException
     * @throws EvaluationException
     */
    private function eval(string $expression, DOMElement $node, bool $returnCallable = false)
    {
        $expression = trim($expression, ' ');

        if(str_starts_with($expression, 'v/')){
            $json = substr($expression, 2);
            $value = json_decode($json);
            if(json_last_error() !== 0) throw new ParseException("malformed json", $node);
            return $value;
        }

        if(str_starts_with($expression, 'f/')){
            $value = substr($expression, 2);
            $parts = str_getcsv($value, ',', '|');

            if(empty($parts)) throw new ParseException("missing function (variable name and parameters)", $node);
            $functionName = array_shift($parts);
            $function = $this->eval($functionName, $node, true);

            if(!is_callable($function)) throw new EvaluationException("variable $functionName isn't callable", $node);

            foreach($parts as &$part) $part = $this->eval($part, $node);

            try {
                return $function(...$parts);
            }
            catch(InvalidArgumentException|ArgumentCountError $e){
                throw new ParseException("function $functionName parameter count mismatch", $node, $e);
            }
        }

        if(str_contains($expression, '.')){
            $parts = explode('.', $expression);
            $afterLastDot = array_pop($parts);
            $beforeLastDot = implode('.', $parts);

            $var = $this->eval($beforeLastDot, $node);
            if(is_array($var) && isset($var[$afterLastDot])) $final = $var[$afterLastDot];
            else if(is_object($var) && isset($var->{$afterLastDot})) $final = $var->{$afterLastDot};
            else throw new EvaluationException("element $afterLastDot in $beforeLastDot not found", $node);

            if(is_callable($final) && !$returnCallable) return $final();
            else return $final;
        }

        if(!isset($this->vars[$expression])) throw new EvaluationException("variable $expression not found", $node);

        $value = $this->vars[$expression];

        if(is_callable($value) && !$returnCallable) return $value();
        else return $value;
    }

    /**
     * @throws ParseException
     * @throws EvaluationException
     */
    private function execute($context = null): void
    {
        $attributes = ['id', 'class', 'title', 'alt', 'value', 'href', 'src', 'style'];

        // LOOP

        $selector = "data-var-foreach";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem){
            $params = str_getcsv($elem->getAttribute($selector), ';', "'");
            array_walk($params, fn(&$param) => $param = trim($param, ' '));
            if(empty($params[0])) throw new ParseException('missing iterable expression', $elem);
            if(empty($params[1])) throw new ParseException('missing value variable name', $elem);

            $iterable = $this->eval($params[0], $elem);
            if(!is_iterable($iterable)) throw new ParseException("expression result not iterable", $elem);

            $varValue = $params[2] ?? $params[1];
            if(isset($params[2])) $varIndex = $params[1];

            foreach($iterable as $index => $value) {

                if(isset($varIndex)) $this->vars[$varIndex] = $index;
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

        $selector = "data-var-if";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            if ($this->eval($elem->getAttribute($selector), $elem) != true)
                $elem->parentNode->removeChild($elem);
            else
                $elem->removeAttribute($selector);
        }

        $selector = "data-var-if-html";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            $params = str_getcsv($elem->getAttribute($selector), ';', "'");
            array_walk($params, fn(&$param) => $param = trim($param, ' '));
            if(empty($params[0])) throw new ParseException('missing condition expression', $elem);
            if(empty($params[1])) throw new ParseException('missing value expression if true', $elem);

            unset($value);
            if ($this->eval($params[0], $elem) == true)
                $value = $params[1];
            else if (isset($params[2]))
                $value = $params[2];

            if(!isset($value)){
                $elem->removeAttribute($selector);
                continue;
            }

            $fragment = $this->doc->createDocumentFragment()->appendXML(
                $this->eval($value, $elem)
            );
            $elem->nodeValue = '';
            $elem->appendChild($fragment);

            $elem->removeAttribute($selector);
        }

        $selector = "data-var-if-text";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            $params = str_getcsv($elem->getAttribute($selector), ';', "'");
            array_walk($params, fn(&$param) => $param = trim($param, ' '));
            if(empty($params[0])) throw new ParseException('missing condition expression', $elem);
            if(empty($params[1])) throw new ParseException('missing value expression if true', $elem);

            if ($this->eval($params[0], $elem) == true)
                $elem->nodeValue = $this->eval($params[1], $elem);
            else if (isset($params[2]))
                $elem->nodeValue = $this->eval($params[2], $elem);

            $elem->removeAttribute($selector);
        }

        foreach($attributes as $attribute) {
            $selector = "data-var-if-$attribute";
            foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
                $params = str_getcsv($elem->getAttribute($selector), ';', "'");
                array_walk($params, fn(&$param) => $param = trim($param, ' '));
                if(empty($params[0])) throw new ParseException('missing condition expression', $elem);
                if(empty($params[1])) throw new ParseException('missing value expression if true', $elem);

                if ($this->eval($params[0], $elem) == true)
                    $elem->setAttribute($attribute, $this->eval($params[1], $elem));
                else if (isset($params[2]))
                    $elem->setAttribute($attribute, $this->eval($params[2], $elem));

                $elem->removeAttribute($selector);
            }
        }

        $selector = "data-var-if-attr";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem){
            $params = str_getcsv($elem->getAttribute($selector), ';', "'");
            array_walk($params, fn(&$param) => $param = trim($param, ' '));
            if(empty($params[0])) throw new ParseException('missing attribute name', $elem);
            if(empty($params[1])) throw new ParseException('missing condition expression', $elem);
            if(empty($params[2])) throw new ParseException('missing value expression if true', $elem);

            if($this->eval($params[1], $elem) == true)
                $elem->setAttribute($params[0], $this->eval($params[3], $elem));
            else if (isset($params[3]))
                $elem->setAttribute($params[0], $this->eval($params[3], $elem));

            $elem->removeAttribute($selector);
        }

        // HTML

        $selector = "data-var-html";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            $fragment = $this->doc->createDocumentFragment()->appendXML(
                $this->eval($elem->getAttribute($selector), $elem)
            );
            $elem->nodeValue = '';
            $elem->appendChild($fragment);

            $elem->removeAttribute($selector);
        }

        // TEXT
        $selector = "data-var-text";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
            $elem->nodeValue = $this->eval($elem->getAttribute($selector), $elem);
            $elem->removeAttribute($selector);
        }

        // ATTRIBUTES

        foreach($attributes as $attribute) {
            $selector = "data-var-$attribute";
            foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem) {
                $elem->setAttribute($attribute, $this->eval($elem->getAttribute($selector), $elem));
                $elem->removeAttribute($selector);
            }
        }

        $selector = "data-var-attr";
        foreach ($this->xpath->query("descendant-or-self::*[@$selector]", $context) as $elem){
            $params = str_getcsv($elem->getAttribute($selector), ';', "'");
            array_walk($params, fn(&$param) => $param = trim($param, ' '));
            if(empty($params[0])) throw new ParseException('missing attribute name', $elem);
            if(empty($params[1])) throw new ParseException('missing value expression', $elem);

            $elem->setAttribute($params[0], $this->eval($params[1], $elem));
            $elem->removeAttribute($selector);
        }
    }

}