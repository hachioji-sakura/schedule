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

	$fix_flag = 1;

	$teacher_list = get_teacher_list($db);
	$staff_list = get_staff_list($db);
	
	$stmt = $dbl->query("select usr.id from common.users usr, common.teachers tch where usr.id=tch.user_id");
	$rslt = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$teacher_ids = "'".implode("','", $rslt)."'"; 

	$stmt = $dbc->query("select mem.user_id, date_format(cal.start_time,'%e') day from lms.user_calendars cal, lms.user_calendar_members mem
 where date_format(start_time,'%Y/%c')='$year/$month'
 and cal.id=mem.calendar_id
 and mem.user_id in ($teacher_ids)
 and ((cal.status='fix' and cal.work<>11) or (cal.status in ('presence','absence','rest','cancel','lecture_cancel') and checked_at is null))
 order by cal.start_time"
			);
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	if ($rslt) {
		$fix_flag = 0;
		$not_yet_fixed_ids = "'".implode("','", array_column($rslt,'user_id'))."'"; 
		
		$stmt = $dbl->query("select concat(tch.name_last, ' ', tch.name_first) name, usr.id from common.users usr, common.teachers tch
	 where usr.id=tch.user_id and usr.id in ($not_yet_fixed_ids) order by tch.kana_last,tch.kana_first");
		$rslt1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		echo "以下の先生に勤務実績未確定があります。<br>";
		echo "<table border=\"1\">";
		echo "<tr><th>名前</th><th>{$month}月 未確定日</th></tr>";
		foreach($rslt1 as $item1) {
			$days = array();
			foreach ($rslt as $item) {
				if ($item['user_id']==$item1['id'])	$days[] = $item['day'];
			}
			sort($days);
			$days = implode(',',array_unique($days));
			echo "<tr><td>{$item1['name']}</td><td>$days</td></tr>";
		}
		echo "</table><br><br>";
	} else {
		echo "全先生の勤務実績は確定しています。<br><br>";
	}

	$stmt = $dbl->query("select usr.id from common.users usr, common.managers mgr where usr.id=mgr.user_id");
	$rslt = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$mgr_ids = "'".implode("','", $rslt)."'"; 

	$stmt = $dbc->query("select mem.user_id, date_format(cal.start_time,'%e') day from lms.user_calendars cal, lms.user_calendar_members mem
 where date_format(start_time,'%Y/%c')='$year/$month'
 and cal.id=mem.calendar_id
 and mem.user_id in ($mgr_ids)
 and (cal.status='fix' or (cal.status in ('presence','absence','rest','cancel','lecture_cancel') and checked_at is null))
 order by cal.start_time"
			);
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	if ($rslt) {
		$fix_flag = 0;
		$not_yet_fixed_ids = "'".implode("','", array_column($rslt,'user_id'))."'"; 
		
		$stmt = $dbl->query("select concat(mgr.name_last, ' ', mgr.name_first) name, usr.id  from common.users usr, common.managers mgr
	 where usr.id=mgr.user_id and usr.id in ($not_yet_fixed_ids) order by mgr.kana_last,mgr.kana_first");
		$rslt1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		echo "以下の事務員に勤務実績未確定があります。<br>";
		echo "<table border=\"1\">";
		echo "<tr><th>名前</th><th>{$month}月 未確定日</th></tr>";
		foreach($rslt1 as $item1) {
			if (array_search($item1['name'], array_column($teacher_list, 'name')) !== false)	continue;
			$days = array();
			foreach ($rslt as $item) {
				if ($item['user_id']==$item1['id'])	$days[] = $item['day'];
			}
			sort($days);
			$days = implode(',',array_unique($days));
			echo "<tr><td>{$item1['name']}</td><td>$days</td></tr>";
		}
		echo "</table><br><br>";
	} else {
		echo "全事務員の勤務実績は確定しています。<br><br>";
	}

	if ($fix_flag && $next_URL) {
		echo "<a href=\"$next_URL\">次の処理へ</a><br>";
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
