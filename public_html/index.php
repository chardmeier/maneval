<?php
	header("Content-Type: text/html; charset=UTF-8");

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

	function enough_judgments($total, $count1, $count2, $equal) {
		if($count1 + $count2 + $equal == $total)
			return true;

		if($count1 + $count2 < 70)
			return false;

		return true;
	}

	$db = new PDO("sqlite:/home/staff/ch/maneval/maneval.db");

	function check_post_key($k) {
		return array_key_exists($k, $_POST);
	}

	function store_judgment($db, $line, $corpus1, $rank1, $corpus2, $rank2) {
		if($rank1 == $rank2)
			$j = 0;
		else if($rank1 < $rank2)
			$j = 1;
		else
			$j = 2;

		$query = sprintf("update judgments set judgment=%d where corpus1=%d and corpus2=%d and line=%d",
			$j, $corpus1, $corpus2, $line);
		$db->exec($query);
	}

	$keys = array("corpus1", "corpus2", "corpus3", "line", "rank1", "rank2", "rank3");
	if(array_product(array_map('check_post_key', $keys))) {
		$rank_pairs = array(
			$_POST["corpus1"] => $_POST["rank1"],
			$_POST["corpus2"] => $_POST["rank2"],
			$_POST["corpus3"] => $_POST["rank3"]
		);
		ksort($rank_pairs);
		$corpora = array_keys($rank_pairs);
		$ranks = array_values($rank_pairs);
		store_judgment($db, $line, $corpora[0], $ranks[0], $corpora[1], $ranks[1]);
		store_judgment($db, $line, $corpora[0], $ranks[0], $corpora[2], $ranks[2]);
		store_judgment($db, $line, $corpora[1], $ranks[1], $corpora[2], $ranks[2]);
	}

	$error = $done = false;

	$res = $db->query("select count(*) from current_task where task is null");
	$record = $res->fetch();
	if($record[0] == 1)
		$done = true;

	$get_task_description = $db->prepare("select tournament.* from tournament, current_task " .
		"where tournament.id=current_task.task");
	$get_stats = $db->prepare("select judgment, count(*) as count from judgments " .
		"where corpus1=:corpus1 and corpus2=:corpus2 group by judgment");
	$create_judgments = $db->prepare("insert into judgments (corpus1, corpus2, line) " .
		"select :corpus1, :corpus2, s1.line from sentences as s1, sentences as s2 " .
		"where s1.corpus=:corpus1 and s2.corpus=:corpus2 and s1.line=s2.line and s1.sentence!=s2.sentence");

	$new_pair = false;
	while(!$error && !$done) {
		if(!$get_task_description->execute()) {
			$error = true;
			break;
		}
		$task_record = $get_task_description->fetch();
		if(!$record) {
			$error = true;
			break;
		}

		$pair = array("corpus1" => $task_record["corpus1"], "corpus2" => $task_record["corpus2"]);
		$get_stats->execute($pair);
		$stats = $get_stats->fetchAll();

		if(count($stats) == 0) {
			$new_pair = true;
			if(!$create_judgments->execute($pair)) {
				echo "Problem creating judgment records.\n";
				$arr = $db->errorInfo();
				print_r($arr);
				exit(1);
			}
			$get_stats->execute($pair);
			$stats = $get_stats->fetchAll();
		}

		$equal = $total = $better1 = $better2 = 0;
		for($i = 0; $i < count($stats); $i++) {
			$total += $stats[$i]["count"];
			if($stats[$i]["judgment"] === "0")
				$equal = $stats[$i]["count"];
			if($stats[$i]["judgment"] === "1")
				$better1 = $stats[$i]["count"];
			if($stats[$i]["judgment"] === "2")
				$better2 = $stats[$i]["count"];
		}

		if(enough_judgments($total, $better1, $better2, $equal)) {
			if($better1 >= $better2)
				$next = $task_record["next1"];
			else
				$next = $task_record["next2"];
			$db->exec(sprintf("update current_task set task=%s", is_null($next) ? "null" : $next));
		} else
			break;
	}

	if(!$error && !$done) {
		$source = $task_record["source"];
		$corpus1 = $task_record["corpus1"];
		$corpus2 = $task_record["corpus2"];

		$query = sprintf("select line from judgments where corpus1=%d and corpus2=%d and judgment is null " .
			"order by random() limit 1", $corpus1, $corpus2);
		$res = $db->query($query);
		if(!$res)
			$error = true;
	}
	if(!$error && !$done) {
		$record = $res->fetch();
		$line = $record["line"];

		$find_start = $db->prepare("select start from documents where corpus=:corpus and start<=:line order by start desc limit 1");
		$res = $find_start->execute(array("corpus" => $source, "line" => $line));
		if(!$res)
			$error = true;
	}
	if(!$error && !$done) {
		$record = $find_start->fetch();
		$docstart = $record["start"];
		if($line - $docstart > 5)
			$minline = $line - 5;
		else
			$minline = $docstart;

		$get_line = $db->prepare("select sentence from sentences where corpus=:corpus and line between :minline and :line order by line");

		$res = $get_line->execute(array("corpus" => $source, "minline" => $minline, "line" => $line));
		if(!$res)
			$error = true;
	}
	if(!$error && !$done) {
		$record = $get_line->fetchAll();
		$source = array();
		foreach($record as $sentence)
			$source[] = $sentence["sentence"];

		$res = $get_line->execute(array("corpus" => $corpus1, "minline" => $minline, "line" => $line));
		if(!$res)
			$error = true;
	}
	if(!$error && !$done) {
		$record = $get_line->fetchAll();
		$trans1 = array();
		foreach($record as $sentence)
			$trans1[] = $sentence["sentence"];

		$res = $get_line->execute(array("corpus" => $corpus2, "minline" => $minline, "line" => $line));
		if(!$res)
			$error = true;
	}
	if(!$error && !$done) {
		$record = $get_line->fetchAll();
		$trans2 = array();
		foreach($record as $sentence)
			$trans2[] = $sentence["sentence"];

		if(mt_rand(0, 1) == 1) {
			$tmp = $corpus2;
			$corpus2 = $corpus1;
			$corpus1 = $tmp;

			$tmp = $trans2;
			$trans2 = $trans1;
			$trans1 = $tmp;
		}
	}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Machine Translation Evaluation</title>
</head>
<body>
<h1>Machine Translation Evaluation</h1>
<?php
	if($done)
		echo "No more translations to evaluate.";
	else if($error)
		echo "Internal error. Please contact the developer (Christian Hardmeier).";
	else {
?>
<form action="index.php" method="post">
<p>
Which translation of the last sentence is better?
</p>
<table width="900" cellpadding="10">
<tr><td>Source:</td><td>Translation 1:</td><td>Translation 2:</td><td>Translation 3:</td></tr>
<?php
	for($i = 0; $i < count($source); $i++)
		echo "<tr><td valign=\"top\">" . htmlspecialchars($source[$i]) . "</td>" .
			"<td valign=\"top\">" . htmlspecialchars($trans1[$i]) . "</td>" .
			"<td valign=\"top\">" . htmlspecialchars($trans2[$i]) . "</td>" .
			"<td valign=\"top\">" . htmlspecialchars($trans3[$i]) . "</td></tr>\n";
?>
<tr><td></td>
<td align="left">
  <fieldset>
    <input type="radio" id="r11" name="rank1" value="high"/>
    <label for="r11"> best</label>
    <input type="radio" id="r12" name="rank1" value="mid"/>
    <label for="r12"> middle</label>
    <input type="radio" id="r13" name="rank1" value="low"/>
    <label for="r13"> worst</label>
  </fieldset>
</td>
<td align="left">
  <fieldset>
    <input type="radio" id="r21" name="rank2" value="high"/>
    <label for="r21"> best</label>
    <input type="radio" id="r22" name="rank2" value="mid"/>
    <label for="r22"> middle</label>
    <input type="radio" id="r23" name="rank2" value="low"/>
    <label for="r23"> worst</label>
  </fieldset>
</td>
<td align="left">
  <fieldset>
    <input type="radio" id="r31" name="rank3" value="high"/>
    <label for="r31"> best</label>
    <input type="radio" id="r32" name="rank3" value="mid"/>
    <label for="r32"> middle</label>
    <input type="radio" id="r33" name="rank3" value="low"/>
    <label for="r33"> worst</label>
  </fieldset>
</td>
</tr>
</table>
<input type="hidden" name="corpus1" value="<?php echo $corpus1; ?>" />
<input type="hidden" name="corpus2" value="<?php echo $corpus2; ?>" />
<input type="hidden" name="corpus3" value="<?php echo $corpus3; ?>" />
<input type="hidden" name="line" value="<?php echo $line; ?>" />
</form>
<?php
	}
?>
</body>
</html>

