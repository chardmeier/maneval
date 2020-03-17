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
	#set_exception_handler('exch');

	error_reporting(E_ALL | E_STRICT);

	$db = new PDO("sqlite:/home/staff/ch/maneval-enru/data/maneval.db");
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	function check_post_key($k) {
		return array_key_exists($k, $_POST);
	}

	function store_judgment($db, $task_id, $line, $corpus1, $rank1, $corpus2, $rank2) {
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

	function create_judgments($db, $task_id, $corpus1, $corpus2) {
		$query = $db->prepare("insert into judgments (task_id, corpus1, corpus2, line) " .
			"select :task_id, :corpus1, :corpus2, s1.line from sentences as s1, sentences as s2 " .
			"where s1.corpus=:corpus1 and s2.corpus=:corpus2 and s1.line=s2.line and s1.orderid=0 and s2.orderid=0");
		$params = array("task_id" => $task_id, "corpus1" => $corpus1, "corpus2" => $corpus2);
		if(!$create_judgments->execute($pair)) {
			echo "Problem creating judgment records.\n";
			$arr = $db->errorInfo();
			print_r($arr);
			exit(1);
		}
	}

	function get_lines($db, $corpus, $line) {
		$get_lines = $db->prepare("select sentence from sentences where corpus=:corpus and line=:line order by orderid");
		$res = $get_line->execute(array("corpus" => $corpus, "line" => $line));
		if(!$res)
			return false;
		$recs = $res->fetchAll();
		$text = array();
		foreach($recs as $sentence)
			$text[] = $sentence["sentence"];
		return $text;
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
		store_judgment($db, $task_id, $line, $corpora[0], $ranks[0], $corpora[1], $ranks[1]);
		store_judgment($db, $task_id, $line, $corpora[0], $ranks[0], $corpora[2], $ranks[2]);
		store_judgment($db, $task_id, $line, $corpora[1], $ranks[1], $corpora[2], $ranks[2]);
	}

	$error = $done = false;

	$res = $db->query("select count(*) from current_task where task is null");
	$record = $res->fetch();
	if($record[0] == 1)
		$done = true;

	$get_task_description = $db->prepare("select tasks.* from tasks, current_task " .
		"where tasks.id=current_task.task");
	$check_judgments = $db->prepare("select count(*) as count from judgments where task_id=:task_id");

	$eval_type = "Machine Translation"; # just to have a default value
	while(!$error && !$done) {
		if(!$get_task_description->execute()) {
			$error = 1;
			break;
		}
		$task_record = $get_task_description->fetch();
		if(!$task_record) {
			$error = 2;
			break;
		}

		$task_id = $task_record["id"];
		$source = $task_record["source"];
		$eval_type = $task_record["eval_type"];
		$corpus1 = $task_record["corpus1"];
		$corpus2 = $task_record["corpus2"];
		$corpus3 = $task_record["corpus3"];

		$check_judgments->execute(array("task_id" => $task_id));
		$record = $check_judgments->fetch();

		if($record[0] == 0) {
			create_judgments($db, $task_id, $corpus1, $corpus2);
			create_judgments($db, $task_id, $corpus1, $corpus3);
			create_judgments($db, $task_id, $corpus2, $corpus3);
		}
	}

	if(!$error && !$done) {
		$query = sprintf("select line from judgments where task_id=%d and corpus1=%d and corpus2=%d and judgment is null " .
			"order by random() limit 1", $task_id, $corpus1, $corpus2);
		$res = $db->query($query);
		if(!$res)
			$error = 3;
	}
	if(!$error && !$done) {
		$record = $res->fetch();
		$line = $record["line"];
		do {
			$error = 4;
			$s_lines = get_lines($db, $source, $line);
			if(!$s_lines)
				break;
			$c1_lines = get_lines($db, $corpus1, $line);
			if(!$s_lines)
				break;
			$c2_lines = get_lines($db, $corpus2, $line);
			if(!$s_lines)
				break;
			$c3_lines = get_lines($db, $corpus3, $line);
			if(!$s_lines)
				break;
			$error = false;
		} while(false);

		$corpus_ids = array($corpus1, $corpus2, $corpus3);
		$translations = array($c1_lines, $c2_lines, $c3_lines);
		$perm = array(0, 1, 2);
		shuffle($perm);
	}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Machine Translation Evaluation</title>
</head>
<body>
<h1><?php echo $eval_type; ?> Evaluation</h1>
<?php
	if($done)
		echo "No more translations to evaluate.";
	else if($error) {
		echo "Internal error. Please contact the developer (Christian Hardmeier).\n";
		echo "Error number " . $error;
	} else {
?>
<form action="index.php" method="post">
<p>
<?php
	switch($eval_type) {
	case "Adequacy":
		echo "Please rank the three translation according to <strong>how adequately the translation of the ".
			"last sentence reflects the meaning of the source, given the context.</strong>";
		$show_source = true;
		break;
	case "Fluency":
		echo "Please rank the three translation according to <strong>the fluency of the last sentence, " .
			"given the context of the previous sentences.</strong>";
		$show_source = false;
		break;
	default:
		echo "Unknown evaluation type: " . $eval_type;
		exit(1);
	}
?>
Note: If the quality of two translations is the same, you may assign the same rank to them to indicate a tie.
</p>
<table width="900" cellpadding="10">
<tr>
<?php
	if($show_source)
		echo "<td>Source:</td>";
?>
<td>Translation 1:</td><td>Translation 2:</td><td>Translation 3:</td></tr>
<?php
	for($i = 0; $i < count($source); $i++) {
		echo "<tr>";
		if($show_source)
			echo "<td valign=\"top\">" . htmlspecialchars($source[$i]) . "</td>";
		echo "<td valign=\"top\">" . htmlspecialchars($translations[$perm[0]][$i]) . "</td>" .
			"<td valign=\"top\">" . htmlspecialchars($translations[$perm[1]][$i]) . "</td>" .
			"<td valign=\"top\">" . htmlspecialchars($translations[$perm[2]][$i]) . "</td></tr>\n";
	}
?>
<tr>
<?php
	if($show_source)
		echo "<td></td>";
?>
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
<input type="hidden" name="corpus1" value="<?php echo $corpus_ids[$perm[0]]; ?>" />
<input type="hidden" name="corpus2" value="<?php echo $corpus_ids[$perm[1]]; ?>" />
<input type="hidden" name="corpus3" value="<?php echo $corpus_ids[$perm[2]]; ?>" />
<input type="hidden" name="line" value="<?php echo $line; ?>" />
</form>
<?php
	}
?>
</body>
</html>

