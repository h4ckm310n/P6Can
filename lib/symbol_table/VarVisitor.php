<?php

use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class VarVisitor extends NodeVisitorAbstract
{
    private $vars = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Variable)
            array_push($this->vars, $node);
    }

    public function getVars()
    {
        return $this->vars;
    }
}