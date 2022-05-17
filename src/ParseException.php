<?php

namespace Le\Dataplater;

use Exception, Throwable;
use DOMNode;

class ParseException extends Exception
{

    public function __construct(string $message, DOMNode $node, ?Throwable $previous = null)
    {
        $message = "Template error: `$message` for node <{$node->nodeName}> on line {$node->getLineNo()}";

        parent::__construct($message, previous: $previous);
    }

}