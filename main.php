<?php


use PhpParser\NodeDumper;

require __DIR__.'/vendor/autoload.php';

require "IncludeAnalyzer.php";
require "ASTParser.php";
require "IRGenerator.php";
require "ScopeExtractor.php";
require "SymTabBuilder.php";
require "CFGGenerator.php";
require "SSAConstructor.php";
require "CGGenerator.php";
require "TaintAnalyzer.php";


$proj_path = "./projects/taint_test2";

// Parse AST
$ast_parser = new ASTParser($proj_path);
$asts = $ast_parser->getASTs();
//$dumper = new NodeDumper();
//echo $dumper->dump($asts[0]["nodes"]);
//print_r($asts[0]["nodes"]);
//return;

//$inc_analyzer = new IncludeAnalyzer($proj_path, $asts);
//return;

/*// Build symbol tables
$symtabs = [];
foreach ($asts as $ast)
{
    $symtab_builder = new SymTabBuilder($ast);
    array_push($symtabs, ["filename" => $ast["filename"], "symtabs" => $symtab_builder->getSymTabs()]);
}*/

//ir
$tacs = [];
foreach ($asts as $ast)
{
    echo "Converting ".$ast["filename"]." to IRs\n";
    $ir_gen = new IRGenerator($ast);
    array_push($tacs, ["filename" => $ast["filename"], "lines" => $ir_gen->getTacs()]);
}

//scope tree
$scope_trees = [];
foreach ($tacs as $tac)
{
    echo "Extracting functions of ".$tac["filename"]."\n";
    $scope_ext = new ScopeExtractor($tac);
    array_push($scope_trees, ["filename" => $tac["filename"], "scope_tree" => $scope_ext->getTopTreeNode()]);
}

//CFG
for ($i=0; $i<count($scope_trees); ++$i)
{
    echo "Generating CFGs for ".$scope_trees[$i]["filename"]."\n";
    $cfg_gen = new CFGGenerator($scope_trees[$i]["scope_tree"]);
    $scope_trees[$i]["scope_tree"] = $cfg_gen->getScopeNode();
}

//SSA
for ($i=0; $i<count($scope_trees); ++$i)
{
    echo "Converting IRs of ".$scope_trees[$i]["filename"]." to SSA form.\n";
    $ssa_con = new SSAConstructor($scope_trees[$i]["scope_tree"]);
    $scope_trees[$i]["scope_tree"] = $ssa_con->getScopeNode();
}

//CG
/*$cgs = [];
for ($i=0; $i<count($scope_trees); ++$i)
{
    echo "Generating CG for ".$scope_trees[$i]["filename"]."\n";
    $cg_gen = new CGGenerator($scope_trees[$i]["scope_tree"]);
    array_push($cgs, ["filename" => $scope_trees[$i]["filename"], "cg" => $cg_gen->getCG()]);
}*/

//Taint Analysis
for ($i=0; $i<count($scope_trees); ++$i)
{
    echo "Performing taint analysis for ".$scope_trees[$i]["filename"]."\n";
    $taint_analyzer = new TaintAnalyzer([], $scope_trees[$i]["scope_tree"]);
    $taint_analyzer->setCalleeName("{main}");
    echo "\nTaint Result:\n";
    $taint_analyzer->output_result(0);
}
