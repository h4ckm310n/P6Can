<?php

use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class InputVisitor extends NodeVisitorAbstract
{
    private $is_input;

    public function __construct()
    {
        $this->is_input = false;
    }

    public function enterNode(Node $node)
    {
        $input_names = ["_GET", "_POST", "_REQUEST", "_COOKIE"];
        if (($node instanceof Expr\ArrayDimFetch) && ($node->var instanceof Expr\Variable))
        {
            $name = $node->var->name;
            if (in_array($name, $input_names))
                $this->is_input = true;
        }
    }
}