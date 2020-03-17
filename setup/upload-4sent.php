#! /usr/local/bin/php
<?php

if(count($argv) != 2) {
	echo "Usage: upload-4sent infile\n";
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
$insert_sentence = $db->prepare("insert into sentences(corpus, line, sentence, orderid) values (:corpus, :line, :sentence, :orderid)");

$insert_corpus->execute(array("name" => $infile, "srctgt" => 1));
$corpusid = $db->lastInsertId();

if(!($file = fopen($infile, "rb"))) {
	echo "Error opening " . $file;
	$db->rollBack();
	exit(1);
}

$record = array("corpus" => $corpusid);
$lineno = 0;
while(($line = fgets($file))) {
	$sents = explode(" _eos ", rtrim($line));
	$record["line"] = $lineno++;
	for($i = 0; $i < count($sents); $i++) {
		$record["sentence"] = $sents[$i];
		$record["orderid"] = $i;
		$insert_sentence->execute($record);
	}
}

$db->commit();

?>
