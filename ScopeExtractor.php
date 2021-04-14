<?php

require "lib/ScopeNode.php";

class ScopeExtractor
{
    private $lines;
    private $top_tree_node;

    public function __construct($tacs)
    {
        $filename = $tacs["filename"];
        $this->top_tree_node = new ScopeNode(null, $filename);
        $this->lines = $tacs["lines"];
        $this->extract();
    }

    private function extract()
    {
        $curr_node = $this->top_tree_node;
        foreach ($this->lines as $line)
        {
            if ($line->getOp() == "FUNC")
            {
                $curr_node = new ScopeNode($curr_node, $line->getResult());
                $curr_node->getParent()->setChild($curr_node);
                $curr_node->newLine($line);
            }
            elseif ($line->getOp() == "ENDFUNC")
            {
                $curr_node->newLine($line);
                $curr_node = $curr_node->getParent();
            }
            else
                $curr_node->newLine($line);
        }
    }

    public function getTopTreeNode()
    {
        return $this->top_tree_node;
    }
}