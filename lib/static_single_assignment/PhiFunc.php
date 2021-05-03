<?php


class PhiFunc
{
    private $params;
    private $origin;
    private $var;

    public function __construct($var, $n)
    {
        $this->origin = $var;
        $this->var = $var;
        $this->params = [];
        for ($i=0; $i<$n; ++$i)
            array_push($this->params, null);
    }

    public function setVar($var)
    {
        $this->var = $var;
    }

    public function getVar()
    {
        return $this->var;
    }

    public function changeParam($v, $i)
    {
        $this->params[$i] = $v;
    }

    public function getOrigin()
    {
        return $this->origin;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function __toString()
    {
        return $this->var." = PHI".json_encode($this->params);
    }
}