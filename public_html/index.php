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

	function validate_rank($r) {
		return $r == 1 || $r == 2 || $r == 3;
	}

	function store_judgment($db, $task_id, $item, $corpus1, $rank1, $corpus2, $rank2) {
		if($rank1 == $rank2)
			$j = 0;
		else if($rank1 < $rank2)
			$j = 1;
		else
			$j = 2;

		$query = sprintf("update judgments set judgment=%d where corpus1=%d and corpus2=%d and item=%d",
			$j, $corpus1, $corpus2, $item);
		$db->exec($query);
	}

# 	function create_judgments($db, $task_id, $corpus1, $corpus2, $orderid_to_compare) {
# 		$query = $db->prepare("insert into judgments (task_id, corpus1, corpus2, line) " .
# 			"select :task_id, :corpus1, :corpus2, s1.line from sentences as s1, sentences as s2 " .
# 			"where s1.corpus=:corpus1 and s2.corpus=:corpus2 and s1.line=s2.line " .
# 			"and s1.orderid=:orderid and s2.orderid=:orderid and s1.sentence != s2.sentence");
# 		$params = array("task_id" => $task_id, "corpus1" => $corpus1, "corpus2" => $corpus2,
# 				"orderid" => $orderid_to_compare);
# 		if(!$query->execute($params)) {
# 			echo "Problem creating judgment records.\n";
# 			$arr = $db->errorInfo();
# 			print_r($arr);
# 			exit(1);
# 		}
# 	}

	function get_lines($db, $corpus, $line) {
		$get_lines = $db->prepare("select sentence from sentences where corpus=:corpus and line=:line order by orderid");
		$res = $get_lines->execute(array("corpus" => $corpus, "line" => $line));
		if(!$res)
			return false;
		$recs = $get_lines->fetchAll();
		$text = array();
		foreach($recs as $sentence)
			$text[] = $sentence["sentence"];
		return $text;
	}

	$keys = array("corpus1", "corpus2", "corpus3", "item", "rank1", "rank2", "rank3");
	if(array_product(array_map('check_post_key', $keys))) {
		$rank_pairs = array(
			$_POST["corpus1"] => $_POST["rank1"],
			$_POST["corpus2"] => $_POST["rank2"],
			$_POST["corpus3"] => $_POST["rank3"]
		);
		ksort($rank_pairs);
		$corpora = array_keys($rank_pairs);
		$ranks = array_values($rank_pairs);
		if(array_product(array_map('validate_rank', $rank_pairs))) {
			$task_id = $_POST["task_id"];
			$item = $_POST["item"];
			store_judgment($db, $task_id, $item, $corpora[0], $ranks[0], $corpora[1], $ranks[1]);
			store_judgment($db, $task_id, $item, $corpora[0], $ranks[0], $corpora[2], $ranks[2]);
			store_judgment($db, $task_id, $item, $corpora[1], $ranks[1], $corpora[2], $ranks[2]);
		}
	}

	$error = $done = false;

	$key = $_REQUEST["key"];

	$get_task_description = $db->prepare("select tasks.* from tasks, current_task " .
		"where tasks.id=current_task.task and current_task.key=:key");
	$check_judgments = $db->prepare("select count(*) as count from judgments where task_id=:task_id");
	$count_done = $db->prepare("select count(*) as count from judgments where task_id=:task_id and judgment is not null");

	$eval_type = "Machine Translation"; # just to have a default value
	if(!$error)
		if(!$get_task_description->execute(array("key" => $key)))
			$error = 1;

	if(!$error) {
		$task_record = $get_task_description->fetch();
		if(!$task_record)
			$error = 2;
	}

	if(!$error) {
		$task_id = $task_record["id"];
		$source = $task_record["source"];
		$eval_type = $task_record["eval_type"];
		$corpus1 = $task_record["corpus1"];
		$corpus2 = $task_record["corpus2"];
		$corpus3 = $task_record["corpus3"];

		$check_judgments->execute(array("task_id" => $task_id));
		$record = $check_judgments->fetch();

		# divide by 3 because each item has 3 judgment records
		$total_judgments = $record[0] / 3;
		if($record[0] == 0) {
			$error = 5;
			# create_judgments($db, $task_id, $corpus1, $corpus2, 3);
			# create_judgments($db, $task_id, $corpus1, $corpus3, 3);
			# create_judgments($db, $task_id, $corpus2, $corpus3, 3);
		}

		$count_done->execute(array("task_id" => $task_id));
		$record = $count_done->fetch();
		$number_done = $record[0] / 3;

		if($number_done == $total_judgments)
			$done = true;
	}

	if(!$error && !$done) {
		$query = sprintf("select item, line from judgments where task_id=%d and judgment is null " .
			"order by random() limit 1", $task_id, $corpus1, $corpus2);
		$res = $db->query($query);
		if(!$res)
			$error = 3;
	}
	if(!$error && !$done) {
		$record = $res->fetch();
		$item = $record["item"];
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
		echo "Please rank the three translations according to <strong>how adequately the translation of the ".
			"last sentence reflects the meaning of the source, given the context.</strong>";
		$show_source = true;
		break;
	case "Fluency":
		echo "Please rank the three translations according to " .
			"<strong>how fluent the last sentence is, in terms of grammaticality, naturalness and consistency, " .
			"taking into account the context of the previous sentences.</strong>";
		$show_source = false;
		break;
	default:
		echo "Unknown evaluation type: " . $eval_type;
		exit(1);
	}
?>
</p>
<p>
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
	$bgcol = "";
	for($i = 0; $i < count($s_lines); $i++) {
		if($i == count($s_lines) - 1)
			$bgcol = "bgcolor=\"#faec9d\"";

		echo "<tr>";

		if($show_source)
			echo "<td valign=\"top\" " . $bgcol . ">" . htmlspecialchars($s_lines[$i]) . "</td>";
		echo "<td valign=\"top\" " . $bgcol . ">" . htmlspecialchars($translations[$perm[0]][$i]) . "</td>" .
			"<td valign=\"top\" " . $bgcol . ">" . htmlspecialchars($translations[$perm[1]][$i]) . "</td>" .
			"<td valign=\"top\" " . $bgcol . ">" . htmlspecialchars($translations[$perm[2]][$i]) . "</td></tr>\n";
	}
?>
<tr>
<?php
	if($show_source)
		echo "<td></td>";
?>
<td align="left">
  <fieldset>
    <input type="radio" id="r11" name="rank1" value="1" required="required" />
    <label for="r11"> best</label><br />
    <input type="radio" id="r12" name="rank1" value="2" required="required" />
    <label for="r12"> middle</label><br />
    <input type="radio" id="r13" name="rank1" value="3" required="required" />
    <label for="r13"> worst</label>
  </fieldset>
</td>
<td align="left">
  <fieldset>
    <input type="radio" id="r21" name="rank2" value="1" required="required" />
    <label for="r21"> best</label><br />
    <input type="radio" id="r22" name="rank2" value="2" required="required" />
    <label for="r22"> middle</label><br />
    <input type="radio" id="r23" name="rank2" value="3" required="required" />
    <label for="r23"> worst</label>
  </fieldset>
</td>
<td align="left">
  <fieldset>
    <input type="radio" id="r31" name="rank3" value="1" required="required" />
    <label for="r31"> best</label><br />
    <input type="radio" id="r32" name="rank3" value="2" required="required" />
    <label for="r32"> middle</label><br />
    <input type="radio" id="r33" name="rank3" value="3" required="required" />
    <label for="r33"> worst</label>
  </fieldset>
</td>
</tr>
<tr>
<?php
	if($show_source)
		echo "<td></td>";
?>
<td></td><td></td><td align="right"><button>Submit</button></td>
</table>
<input type="hidden" name="key" value="<?php echo $key; ?>" />
<input type="hidden" name="task_id" value="<?php echo $task_id; ?>" />
<input type="hidden" name="corpus1" value="<?php echo $corpus_ids[$perm[0]]; ?>" />
<input type="hidden" name="corpus2" value="<?php echo $corpus_ids[$perm[1]]; ?>" />
<input type="hidden" name="corpus3" value="<?php echo $corpus_ids[$perm[2]]; ?>" />
<input type="hidden" name="item" value="<?php echo $item; ?>" />
</form>
<p>
<?php echo $number_done . "/" . $total_judgments; ?> items completed.
</p>
<?php
	}
?>
</body>
</html>

