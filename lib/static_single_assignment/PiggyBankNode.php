<?php


class PiggyBankNode
{
    private $block;
    private $visited;
    private $alpha;
    private $inphi;
    private $level;
    private $next;

    public function __construct($block, $level)
    {
        $this->block = $block;
        $this->level = $level;
        $this->visited = false;
        $this->inphi = false;
        $this->alpha = false;
    }

    public function setAlpha($alpha)
    {
        $this->alpha = $alpha;
    }

    public function isAlpha()
    {
        return $this->alpha;
    }

    public function setInphi($inphi)
    {
        $this->inphi = $inphi;
    }

    public function isInphi()
    {
        return $this->inphi;
    }

    public function setVisited($visited)
    {
        $this->visited = $visited;
    }

    public function isVisited()
    {
        return $this->visited;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setNext($next)
    {
        $this->next = $next;
    }

    public function getNext()
    {
        return $this->next;
    }
}
