<?php

use PhpParser\NodeTraverser;

require "lib/IncVisitor.php";

class IncludeAnalyzer
{
    private $path;
    private $files;

    public function __construct($path, $files)
    {
        $this->path = $path;
        $this->files = $files;

        foreach (array_keys($this->files) as $fn)
            $this->files[$fn]["visited"] = false;

        foreach (array_keys($this->files) as $fn)
        {
            if ($this->files[$fn]["visited"])
                continue;
            $this->analyze($fn);
        }
    }

    private function analyze($fn)
    {
        $traverser = new NodeTraverser();
        $visitor = new IncVisitor;
        $traverser->addVisitor($visitor);
        $traverser->traverse($this->files[$fn]["nodes"]);
        $inc_pos = $visitor->getIncPos();
        //$this->files[$fn][$inc_pos] = $inc_pos;
        //echo json_encode($inc_pos)."\n";
        //$file =
    }
}