<?php

namespace Le\Dataplater;

use DOMNode;
use LogicException, Throwable;

class ParseException extends LogicException
{

    public function __construct(string $message, DOMNode $node, Throwable $previous = null)
    {
        $message = "Template error: $message for node <{$node->nodeName}> on line {$node->getLineNo()}";

        parent::__construct($message, previous: $previous);
    }

}