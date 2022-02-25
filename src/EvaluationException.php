<?php

namespace Le\Dataplater;

use DOMNode;
use RuntimeException;

class EvaluationException extends RuntimeException
{

    public function __construct(string $message, DOMNode $node)
    {
        $message = "Evaluation error: $message for node <{$node->nodeName}> on line {$node->getLineNo()}";

        parent::__construct($message);
    }

}