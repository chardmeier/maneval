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

	if(array_key_exists("corpus1", $_POST) && array_key_exists("corpus2", $_POST) &&
			array_key_exists("line", $_POST) && array_key_exists("judgment", $_POST)) { 
		$corpus1 = $_POST["corpus1"];
		$corpus2 = $_POST["corpus2"];
		$line = $_POST["line"];
		$judgment = $_POST["judgment"];
		switch($judgment) {
		case "Translation 1":
			$j = $corpus1 < $corpus2 ? 1 : 2;
			break;
		case "Translation 2":
			$j = $corpus1 < $corpus2 ? 2 : 1;
			break;
		case "Same quality":
			$j = 0;
			break;
		}
		$query = sprintf("update judgments set judgment=%d where corpus1=%d and corpus2=%d and line=%d",
			$j, min($corpus1, $corpus2), max($corpus1, $corpus2), $line);
		$xquery = $query;
		$db->exec($query);
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
<tr><td>Source:</td><td>Translation 1:</td><td>Translation 2:</td></tr>
<?php
	for($i = 0; $i < count($source); $i++)
		echo "<tr><td valign=\"top\">" . htmlspecialchars($source[$i]) . "</td>" .
			"<td valign=\"top\">" . htmlspecialchars($trans1[$i]) . "</td>" .
			"<td valign=\"top\">" . htmlspecialchars($trans2[$i]) . "</td></tr>\n";
?>
<tr><td></td>
<td align="left"><input type="submit" name="judgment" value="Translation 1" /></td>
<td align="right"><input type="submit" name="judgment" value="Translation 2" /></td></tr>
<tr><td></td>
<td align="center" colspan="2"><input type="submit" name="judgment" value="Same quality" /></td></tr>
</table>
<input type="hidden" name="corpus1" value="<?php echo $corpus1; ?>" />
<input type="hidden" name="corpus2" value="<?php echo $corpus2; ?>" />
<input type="hidden" name="line" value="<?php echo $line; ?>" />
</form>
<?php
	if($new_pair)
		echo "<p>Started new system pair.</p>\n";
?>
<p><?php echo $better1 + $better2 + $equal; ?> judgments collected for this system pair.</p>
<?php
	}
?>
</body>
</html>

