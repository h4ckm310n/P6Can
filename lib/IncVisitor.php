<?php

use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class IncVisitor extends NodeVisitorAbstract
{
    private $inc_pos = [];

    public function enterNode(Node $node)
    {
        if (($node instanceof Include_) && ($node->expr instanceof String_))
            //array_push($this->inc_pos, [$node->expr->value, $node->getLine()]);
            $this->inc_pos[$node->getLine()] = $node->expr->value;
    }

    public function getIncPos()
    {
        return $this->inc_pos;
    }
}
