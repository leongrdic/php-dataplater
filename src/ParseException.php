<?php

namespace Le\Dataplater;

use Exception;
use Throwable;
use DOMNode;

class ParseException extends Exception
{
    public function __construct(string $message, DOMNode $node, ?Throwable $previous = null)
    {
        $message = "Template error: '$message' for element <{$node->nodeName}> on line {$node->getLineNo()}";

        parent::__construct($message, previous: $previous);
    }
}
