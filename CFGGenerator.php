<?php

require "lib/BasicBlock.php";

class CFGGenerator
{
    private $scope_tree;

    public function __construct($scope_tree)
    {
        $this->scope_tree = $scope_tree;
        $this->generate();
    }

    private function generate()
    {
        $basic_blocks = $this->buildBasicBlock();
        $cfg = $this->buildCFG($this->scope_tree->getName(), $basic_blocks);
        $this->scope_tree->setCFG($cfg);
        $children = $this->scope_tree->getChildren();
        foreach ($children as $child)
        {
            $cfg_gen = new CFGGenerator($child);
            $this->scope_tree->setChild($cfg_gen->getScopeNode());
        }
    }

    private function buildBasicBlock()
    {
        /*
         * Find leaders:
         * 1. First instruction
         * 2. LAB
         * 3. Instructions next to IF / GOTO
         */

        $leaders = [];
        $basic_blocks = [];
        $lines = $this->scope_tree->getLines();
        for ($i=0; $i<count($lines); ++$i)
        {
            $line = $lines[$i];
            $op = $line->getOp();
            if ($i == 0 ||
                $op == "LAB")
                array_push($leaders, $i);
            elseif ($i > 0)
            {
                $last = $lines[$i-1];
                $op = $last->getOp();
                if ($op == "GOTO" ||
                    $op == "IF" ||
                    $op == "ELSEIF" ||
                    $op == "ELSE" ||
                    $op == "CALL")
                    array_push($leaders, $i);
            }
        }
        array_push($leaders, count($lines));

        for ($i=0; $i<count($leaders)-1; ++$i)
        {
            $start = $leaders[$i];
            $length = $leaders[$i+1]  - $start;
            $block_lines = array_slice($lines, $start, $length, false);
            $block = new BasicBlock($i, $block_lines);
            array_push($basic_blocks, $block);
        }

        return $basic_blocks;
    }

    private function buildCFG($name, $basic_blocks)
    {
        $edges = [];

        for ($i=0; $i<count($basic_blocks); ++$i)
        {
            $curr_lines = $basic_blocks[$i]->getLines();
            $op = $curr_lines[count($curr_lines)-1]->getOp();

            //jump
            if ($op == "GOTO" ||
                $op == "IF" ||
                $op == "ELSEIF" ||
                $op == "ELSE" ||
                $op == "CALL")
            {
                $lab = $curr_lines[count($curr_lines)-1]->getResult();
                for ($j=0; $j<count($basic_blocks); ++$j)
                {
                    $next_lines = $basic_blocks[$j]->getLines();
                    if ($next_lines[0]->getOp() == "LAB" && $next_lines[0]->getResult() == $lab)
                    {
                        array_push($edges, [$i, $j]);
                        $basic_blocks[$i]->addSuccessor($j);
                        $basic_blocks[$j]->addPredecessor($i);
                    }
                }
            }

            //origin order
            for ($j=0; $j<count($basic_blocks); ++$j)
            {
                $next_lines = $basic_blocks[$j]->getLines();
                if ($curr_lines[count($curr_lines)-1]->getId() == $next_lines[0]->getId() - 1 &&
                    $op != "GOTO" && $op != "ELSE" && $op != "ENDFUNC" && $next_lines[0]->getOp() != "FUNC")
                {
                    array_push($edges, [$i, $j]);
                    $basic_blocks[$i]->addSuccessor($j);
                    $basic_blocks[$j]->addPredecessor($i);
                }
            }
        }

        // end
        $basic_blocks["END"] = new BasicBlock("END", []);
        for ($i=0; $i<count($basic_blocks)-1; ++$i)
        {
            if (count($basic_blocks[$i]->getSuccessors()) == 0)
            {
                array_push($edges, [$i, "END"]);
                $basic_blocks[$i]->addSuccessor("END");
                $basic_blocks["END"]->addPredecessor($i);
            }
        }

        // start
        $basic_blocks["START"] = new BasicBlock("START", []);
        array_push($edges, ["START", 0]);
        $basic_blocks["START"]->addSuccessor(0);
        $basic_blocks[0]->addPredecessor("START");

        return ["name" => $name, "basic_blocks" => $basic_blocks, "edges" => $edges];
    }

    public function getScopeNode()
    {
        return $this->scope_tree;
    }
}
