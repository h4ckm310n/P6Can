<?php


class CGGenerator
{
    private $cg;

    public function __construct($scope_tree)
    {
        $this->cg = [];
        $this->generate($scope_tree);
    }

    private function generate($scope_node)
    {
        $callees = [];
        $basic_blocks = $scope_node->getCFG()["basic_blocks"];

        // Init callees
        foreach ($basic_blocks as $block)
        {
            $lines = $block->getLines();
            foreach ($lines as $line)
            {
                if ($line->getOp() == "CALL")
                    $callees[$line->getVal1()] = null;
            }
        }

        $parent = $scope_node->getParent();
        $children = $scope_node->getChildren();
        $siblings = ($parent === null) ? [] : $parent->getChildren();
        foreach (array_keys($callees) as $callee)
        {
            if (in_array($callee, array_keys($children), true))
                array_push($this->cg, [$scope_node->getName(), $callee]);

            elseif (in_array($callee, array_keys($siblings), true))
                array_push($this->cg, [$scope_node->getName(), $callee]);
        }

        foreach ($children as $child)
        {
            $this->generate($child);
        }
    }

    public function getCG()
    {
        return $this->cg;
    }
}