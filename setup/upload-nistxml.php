#! /usr/bin/php
<?php

function errh($errno, $errstr, $errfile, $errline) {
	echo "{$errfile} ({$errline}): {$errstr}\n";
	die();
}
function exch($ex) {
	echo $ex->getMessage();
	die();
}
set_error_handler('errh');
set_exception_handler('exch');

error_reporting(E_ALL | E_STRICT);

if(count($argv) != 2) {
	echo "Usage: upload-translations infile\n";
	exit(1);
}
$infile = $argv[1];

$db = new PDO("sqlite:/home/staff/ch/maneval/maneval.db");

$find_corpus = $db->prepare("select id from corpora where name=:name");
$find_corpus->execute(array("name" => $infile));
if(($res = $find_corpus->fetch())) {
	echo "Corpus " . $infile . " already exists (ID = " . $res["id"] . ")";
	exit(1);
}

$db->beginTransaction();

$insert_corpus = $db->prepare("insert into corpora(name, srctgt) values (:name, :srctgt)");
$insert_sentence = $db->prepare("insert into sentences(corpus, line, sentence) values (:corpus, :line, :sentence)");

$insert_corpus->execute(array("name" => $infile, "srctgt" => 1));
$corpusid = $db->lastInsertId();

$doc = new DOMDocument();

if(!($doc->load($infile))) {
	echo "Error loading " . $infile;
	$db->rollBack();
	exit(1);
}

$xpath = new DOMXPath($doc);
$segs = $xpath->query("//seg");

$record = array("corpus" => $corpusid);
$lineno = 0;
foreach($segs as $segnode) {
	$line = $segnode->nodeValue;
	$record["line"] = $lineno++;
	$record["sentence"] = trim($line);
	$insert_sentence->execute($record);
}

$docs = $xpath->query("//doc");
$docstart = 0;
$insert_doc = $db->prepare("insert into documents(corpus, start, end) values (:corpus, :start, :end)");
foreach($docs as $docnode) {
	$cnt = $xpath->evaluate("count(.//seg)", $docnode);
	$insert_doc->execute(array("corpus" => $corpusid, "start" => $docstart, "end" => $docstart + $cnt - 1));
	$docstart += $cnt;
}

$db->commit();

?>
