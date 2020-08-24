<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type = "text/javascript">
</script>
</head>
<body>
<div id="header">
	事務システム
</div>
<div id="content">
<center>
		<h3>SakuraOne 勤務実績確認</h3>
<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");

try {
	
	$year = $_POST["y"];
	$month = $_POST["m"];

	if (is_null($year) == true || $year == "") {
		$year = $_GET["y"];
	}
	if (is_null($month) == true || $month == "") {
		$month = $_GET["m"];
	}
	if ((is_null($year) == true || $year == "") || (is_null($month) == true || $month == "")) {
		throw new Exception('年月が不明です。');
	}
	$month = str_pad($month, 2, '0', STR_PAD_LEFT);
	
	$stmt = $dbl->query("select usr.id from common.users usr, common.teachers tch where usr.id=tch.user_id");
	$rslt = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$teacher_ids = "'".implode("','", $rslt)."'"; 

	$stmt = $dbc->query("select mem.user_id from lms.user_calendars cal, lms.user_calendar_members mem
 where date_format(start_time,'%Y/%m')='$year/$month'
 and cal.id=mem.calendar_id
 and mem.user_id in ($teacher_ids)
 and (cal.status='fix' or (cal.status in ('presence','absence','rest','cancel','lecture_cancel') and checked_at is null))
 order by cal.start_time"
			);
	$rslt = $stmt->fetchAll(PDO::FETCH_COLUMN);
	
	if ($rslt) {
		$not_yet_fixed_ids = "'".implode("','", $rslt)."'"; 
		
		$stmt = $dbl->query("select concat(tch.name_last, ' ', tch.name_first) from common.users usr, common.teachers tch
	 where usr.id=tch.user_id and usr.id in ($not_yet_fixed_ids) order by tch.kana_last,tch.kana_first");
		$rslt = $stmt->fetchAll(PDO::FETCH_COLUMN);
		
		echo "以下の先生が勤務実績未確定です。<br>";
		echo "<table>";
		foreach($rslt as $name)	echo "<tr><td>$name</td></tr>";
		echo "</table>";
	} else {
		echo "全先生の勤務実績は確定しています。<br>";
		echo "<a href=\"./save_statement.php?y=$year&m=$month&go=1\">次の処理へ</a>";
	}

} catch (Exception $e) {
	echo $e->getMessage().'<br>';
}

?>
<br>
<a href="./menu.php">メニューへ戻る</a>
<br>
</body>
</html>
