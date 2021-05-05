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
	$member_list = get_member_list($db,array(),array(),array(),4);
	
	$stmt = $dbl->query("select usr.id from common.users usr, common.teachers tch where usr.id=tch.user_id");
	$rslt = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$teacher_ids = "'".implode("','", $rslt)."'"; 

	$stmt = $dbc->query("select mem.user_id, date_format(cal.start_time,'%e') day from lms.user_calendars cal, lms.user_calendar_members mem
 where date_format(start_time,'%Y/%c')='$year/$month'
 and cal.id=mem.calendar_id
 and mem.user_id in ($teacher_ids)
 and ((cal.status='fix' and cal.work<>11) or (cal.status in ('presence','absence','rest') and checked_at is null))
 order by cal.start_time"
			);
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	if ($rslt) {
		$fix_flag = 0;
		$not_yet_fixed_ids = "'".implode("','", array_column($rslt,'user_id'))."'"; 
		
		$stmt = $dbl->query("select concat(tch.name_last, ' ', tch.name_first) name, usr.id from common.users usr, common.teachers tch
	 where usr.id=tch.user_id and usr.id in ($not_yet_fixed_ids) order by tch.kana_last,tch.kana_first");
		$rslt1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		echo "以下の先生に勤務実績未確定があります。<br>\n";
		echo "<table border=\"1\">\n";
		echo "<tr><th>名前</th><th>{$month}月 未確定日</th></tr>\n";
		foreach($rslt1 as $item1) {
			$days = array();
			foreach ($rslt as $item) {
				if ($item['user_id']==$item1['id'])	$days[] = $item['day'];
			}
			sort($days);
			$days = implode(',',array_unique($days));
			echo "<tr><td>{$item1['name']}</td><td>$days</td></tr>\n";
		}
		echo "</table><br><br>";
	} else {
		echo "全先生の勤務実績は確定しています。<br><br>\n";
	}

	$stmt = $dbl->query("select usr.id from common.users usr, common.managers mgr where usr.id=mgr.user_id");
	$rslt = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$mgr_ids = "'".implode("','", $rslt)."'"; 

	$stmt = $dbc->query("select mem.user_id, date_format(cal.start_time,'%e') day from lms.user_calendars cal, lms.user_calendar_members mem
 where date_format(start_time,'%Y/%c')='$year/$month'
 and cal.id=mem.calendar_id
 and mem.user_id in ($mgr_ids)
 and (cal.status='fix' or (cal.status in ('presence','absence','rest') and checked_at is null))
 order by cal.start_time"
			);
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	if ($rslt) {
		$fix_flag = 0;
		$not_yet_fixed_ids = "'".implode("','", array_column($rslt,'user_id'))."'"; 
		
		$stmt = $dbl->query("select concat(mgr.name_last, ' ', mgr.name_first) name, usr.id  from common.users usr, common.managers mgr
	 where usr.id=mgr.user_id and usr.id in ($not_yet_fixed_ids) order by mgr.kana_last,mgr.kana_first");
		$rslt1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		echo "以下の事務員に勤務実績未確定があります。<br>\n";
		echo "<table border=\"1\">\n";
		echo "<tr><th>名前</th><th>{$month}月 未確定日</th></tr>\n";
		foreach($rslt1 as $item1) {
			if (array_search($item1['name'], array_column($teacher_list, 'name')) !== false)	continue;
			$days = array();
			foreach ($rslt as $item) {
				if ($item['user_id']==$item1['id'])	$days[] = $item['day'];
			}
			sort($days);
			$days = implode(',',array_unique($days));
			echo "<tr><td>{$item1['name']}</td><td>$days</td></tr>\n";
		}
		echo "</table><br><br>\n";
	} else {
		echo "全事務員の勤務実績は確定しています。<br><br>\n";
	}

	$stmt = $dbh->query("select * from tbl_work");
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $item) $work_id_list[$item['id']]=$item['explanation'];
	$stmt = $dbh->query("select * from tbl_schedule_onetime where
	date_format(ymd,'%Y/%c')='$year/$month' and delflag=0 and 
	(confirm=null or confirm='') and not (cancel in ('a1','a2','c')) and 
	work_id in (6,7,8,10) and
	user_id<200000 and not (temporary>0 and temporary<110)
	order by ymd,starttime
	");
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($rslt) {
		echo "以下の授業が未確定です。<br><font color=red>請求を確定する場合は、SakuraOneで出欠確定または削除した後カレンダーの読み込みを実行してください。</font>";
		echo "<table border=\"1\">";
		echo "<tr><th>日時</th><th>生徒</th><th>先生</th><th>コース</th><th>科目</th></tr>";
		foreach($rslt as $item) {
			echo "<tr>";
			echo "<td>{$item['ymd']} ".substr($item['starttime'],0,5)."-".substr($item['endtime'],0,5)."</td>";
			echo "<td>{$member_list[str_pad($item['student_no'],6,0,STR_PAD_LEFT)]['name']}</td>";
			echo "<td>{$teacher_list[$item['teacher_id']-100000]['name']}</td>";
			echo "<td>{$work_id_list[$item['work_id']]}</td>";
			echo "<td>{$item['subject_expr']}</td>";
			echo "</tr>\n";
		}
		echo "</table><br><br>\n";
	}
	
	if ($next_URL) {
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
