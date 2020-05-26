<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
require_once("./calculate_fees.php");
require_once("./gennsenn_choushuu_gaku.php");
//$result = check_user($db, "1");

$errArray = array();
$errFlag = 0;

//$log_tid=2; $log_date='6月18日';

// 当日休み時給対応
$crew_list = array_merge( $crew_list, 
array(
"講師勤務時間11",
"講師勤務時間12",
"講師勤務時間13",
"講師勤務時間14",
"講師勤務時間15",
"講師勤務時間16",
"講師勤務時間17",
"講師勤務時間18",
"講師勤務時間19",
"講師勤務時間20"
));

$youbistr = array('日','月','火','水','木','金','土');

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

if (
	($year < GENNSENN_CHOUSHUU_HYOU_NENNDO) ||
	($year > GENNSENN_CHOUSHUU_HYOU_NENNDO) ||
	($year < GENNSENN_CHOUSHUU_HYOU_NENNDO && $month == 1) ||
	($year < GENNSENN_CHOUSHUU_HYOU_NENNDO && $month == 9)
	) {
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="index,follow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<body>
<div id="header">
	事務システム
</div>
<div id="content" align="center">
<h3>講師・事務員の給与計算</h3>
<h3><?= $year ?>年<?= $month ?>月</h3>
<br>
<table>
<tr><td>現在登録されている「<?= GENNSENN_CHOUSHUU_HYOU_MEISHOU?>」の対象年月に該当しません。</td></tr>
<tr><td>税額表更新対応につきましては事務システム開発担当までご連絡ください。</td></tr>
</table>
<br>
<a href="./menu.php">メニューへ戻る</a><br>
</body>
</html>
<?php
	exit();
}

$PDFfile = "./pay_pdf/pay-$year-$month.pdf";
if (file_exists($PDFfile)) {
	// PDFを出力する
	header("Content-Type: application/pdf");
	// ファイルを読み込んで出力
	readfile($PDFfile);
	exit();
}

$pdf_mode = $_GET["pdf"];

$stmt = $db->query("SELECT fixed FROM tbl_fixed WHERE year=\"$year\" AND month=\"$month\"");
$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rslt['fixed']) throw new Exception("{$year}年{$month}月分の出席簿登録を確定してください。");

$year1 = $year-1988;
$month1 = $month;
$year2 = $year1;
$month2 = $month+1;
if ($month2>12) { $year2++; $month2=1; }
$year3 = $year;
$month3 = $month-1;
if ($month3<1) { $year3--; $month3=12; }

if ($year>2020 || ($year==2020 && $month>=3)) {
	foreach ($date_list_array as $dlist)	$date_list = array_merge($date_list,$dlist);
	$date_list = array_unique(array_merge($date_list,$sat_sun_class_date_list));
	$date_list1 = array();
	$date_list_string = "("; $flag=0;
	foreach ($date_list as $item) {
		if (str_replace('/0','/',substr($item,0,7)) != "$year/$month") { continue; }
		$date_list1[] = $item;
		if ($flag==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; }
		$flag = 1;
	}
	$date_list = $date_list1;
	$date_list_string = $date_list_string.")";
	
} else {
	
if ($month!=1 && $month!=4 && $month!=8) {
	$date_list = $sat_sun_class_date_list;
	$date_list1 = array();
	$date_list_string = "("; $flag=0;
	foreach ($date_list as $item) {
		if (str_replace('/0','/',substr($item,0,7)) != "$year/$month") { continue; }
		$date_list1[] = $item;
		if ($flag==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; }
		$flag = 1;
	}
	$date_list = $date_list1;
	$date_list_string = $date_list_string.")";
} else {
	$date_list = $date_list_array["$year3/$month3"];
	$date_list = array_unique(array_merge($date_list,$sat_sun_class_date_list));
	$date_list1 = array();
	$date_list_string = "("; $flag=0;
	foreach ($date_list as $item) {
		if (str_replace('/0','/',substr($item,0,7)) != "$year/$month") {
			if (in_array($item, $sat_sun_class_date_list))	continue;
			$last_month_date_list[] = $item;
		}
		$date_list1[] = $item;
		if ($flag==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; }
		$flag = 1;
	}
	$date_list = $date_list1;
	$date_list_string = $date_list_string.")";
}
}

// 先生一覧を取得
$teacher_list = get_teacher_list($db,array(),array(),array(),1);
$staff_list   = get_staff_list($db,array(),array(),array(),1);

foreach ($teacher_list as &$teacher) {
	$teacher['transport_dcost1'][0] = $teacher['transport_dcost1_Sun'];
	$teacher['transport_dcost1'][1] = $teacher['transport_dcost1_Mon'];
	$teacher['transport_dcost1'][2] = $teacher['transport_dcost1_Tue'];
	$teacher['transport_dcost1'][3] = $teacher['transport_dcost1_Wen'];
	$teacher['transport_dcost1'][4] = $teacher['transport_dcost1_Thr'];
	$teacher['transport_dcost1'][5] = $teacher['transport_dcost1_Fri'];
	$teacher['transport_dcost1'][6] = $teacher['transport_dcost1_Sat'];
	$teacher['transport_dcost2'][0] = $teacher['transport_dcost2_Sun'];
	$teacher['transport_dcost2'][1] = $teacher['transport_dcost2_Mon'];
	$teacher['transport_dcost2'][2] = $teacher['transport_dcost2_Tue'];
	$teacher['transport_dcost2'][3] = $teacher['transport_dcost2_Wen'];
	$teacher['transport_dcost2'][4] = $teacher['transport_dcost2_Thr'];
	$teacher['transport_dcost2'][5] = $teacher['transport_dcost2_Fri'];
	$teacher['transport_dcost2'][6] = $teacher['transport_dcost2_Sat'];
}
unset($teacher);
foreach ($staff_list as &$staff) {
	$staff['transport_dcost1'][0] = $staff['transport_dcost1_Sun'];
	$staff['transport_dcost1'][1] = $staff['transport_dcost1_Mon'];
	$staff['transport_dcost1'][2] = $staff['transport_dcost1_Tue'];
	$staff['transport_dcost1'][3] = $staff['transport_dcost1_Wen'];
	$staff['transport_dcost1'][4] = $staff['transport_dcost1_Thr'];
	$staff['transport_dcost1'][5] = $staff['transport_dcost1_Fri'];
	$staff['transport_dcost1'][6] = $staff['transport_dcost1_Sat'];
	$staff['transport_dcost2'][0] = $staff['transport_dcost2_Sun'];
	$staff['transport_dcost2'][1] = $staff['transport_dcost2_Mon'];
	$staff['transport_dcost2'][2] = $staff['transport_dcost2_Tue'];
	$staff['transport_dcost2'][3] = $staff['transport_dcost2_Wen'];
	$staff['transport_dcost2'][4] = $staff['transport_dcost2_Thr'];
	$staff['transport_dcost2'][5] = $staff['transport_dcost2_Fri'];
	$staff['transport_dcost2'][6] = $staff['transport_dcost2_Sat'];
}
unset($staff);

foreach ($teacher_list as $teacher) {
	foreach ($staff_list as $staff) {
		if ($staff['name'] == $teacher['name']) {
			$teacher_and_staff_list[$teacher['name']] = array('teacher'=>$teacher, 'staff'=>$staff);
		}
	}
}
	
// 生徒一覧を取得
$student_list = array();
$param_array = array();
$value_array = array();
array_push($param_array, "tbl_member.kind = ?");
array_push($value_array, "3");
//if ($cond_name != "") {
// 検索時
//	array_push($param_array," tbl_member.name like concat('%',?,'%') ");
//	array_push($value_array,$cond_name);
//}
// 20150816 ふりがなの50音順にソートする
$order_array = array("tbl_member.furigana asc");
$member_list = get_simple_member_list($db, $param_array, $value_array, $order_array);
if (count($member_list) == 0) {
	$errFlag = 1;
	throw new Exception('生徒が見つかりませんでした。');
}

// 請求書データの作成が終わっているかチェックする
$sql = "SELECT * FROM tbl_statement where seikyu_year=? and seikyu_month=?";
$stmt = $db->prepare($sql);
$stmt->bindParam(1, $tmp_year);
$stmt->bindParam(2, $tmp_month);
$tmp_year = $year;
$tmp_month = $month;
$stmt->execute();
$statement_array = $stmt->fetchAll(PDO::FETCH_BOTH);
if (count($statement_array) < 1) {
	$message = '<br>対象年月の請求データを作成してください。<br>';
	$message .= '<a href="./save_statement.php?y='.$year.'&m='.$month.'">'.$year.'年'.$month.'月の請求データを作成する</a>';
   throw new Exception($message);
}

$db->beginTransaction();

$tmp_teacher_list = array();
$tmp_teacher = array();

// 請求情報を取得
	$param_array = array("tbl_statement.seikyu_year=?", "tbl_statement.seikyu_month=?");
	$value_array = array($year, $month);
	$order_array = array();
	$sql = 
			"SELECT
					tbl_statement_detail.start_timestamp as start_timestamp,
					tbl_statement_detail.end_timestamp as end_timestamp,
					tbl_statement_detail.absent_flag as absent_flag,
					tbl_statement_detail.diff_hours as diff_hours,
					tbl_statement_detail.course_id as course_id,
					tbl_statement_detail.student_id as student_id,
					tbl_statement_detail.teacher_id as teacher_id,
					tbl_statement_detail.comment as comment
			 FROM tbl_statement, tbl_statement_detail";
					//tbl_statement_detail.absent1_num as absent1_num,
					//tbl_statement_detail.absent2_num as absent2_num,
		$sql .= " where tbl_statement.statement_no = tbl_statement_detail.statement_no";
	  if(count($param_array) > 0){
	    $sql .= " and " . join(" and ",$param_array);
	  }
	  if(count($order_array) > 0){
	    $sql .= "	order by " . join(" , ",$order_array);
	  }
		else {
			$sql .= "	order by tbl_statement.seikyu_year, tbl_statement.seikyu_month";
		}
//echo $sql;
		$stmt = $db->prepare($sql);
		$stmt->execute($value_array);
		$statement_list = $stmt->fetchAll(PDO::FETCH_BOTH);
		//$statement_list[$statement_no]['event_list'] = $statement_event_array;
	//}

	$tmp_teacher_list = array();
	foreach ($statement_list as $statement) {
	// 注意：請求書は生徒ごとに発行
	
	if ($statement["course_id"] == "4" || $statement["course_id"] == "5" || $statement["course_id"] == "6") { continue; }
	if (is_null($statement["course_id"])) continue;
	if ($statement["course_id"] == 0) continue;

	$teacher_id = $statement["teacher_id"];
/*
	 if ($statement["teacher_id"] == "0") {
		// 20160607暫定対応
		if (strpos($statement["comment"], "面談") !== FALSE) {
		// 先生の入力がなく面談の時は弓削先生
			$statement["teacher_id"] = "1";
		} else {
			echo "先生IDの取得エラー：".date("n/j", $statement["start_timestamp"])."_".$member_list[$statement["student_id"]]["name"]."<br>";
		}
	 }
	 //if (isset($teacher_list[$statement["teacher_id"]]) === false) {
   //		var_dump($statement);
	 //}
*/

   // 初期化
	 if (isset($tmp_teacher_list[$teacher_id]) === false) {
	 	$tmp_teacher_list[$teacher_id] = array(
				"no"=>$teacher_id, 
				"name"          => $teacher_list[$teacher_id]["name"], 
				"furigana"      => $teacher_list[$teacher_id]["furigana"],
				"worker_code"   => $teacher_list[$teacher_id]["worker_code"],
//				"working"       => 0,
				"present"       => 0, 
				"absent1"       => 0, 
				"absent2"       => 0,
				"absent_group"  => array("absent1"=>array(),"absent2"=>array()),
				"interview"     => array(),
				"transport_cost"=> $teacher_list[$teacher_id]["transport_cost"],
				"transport_DOW" => $teacher_list[$teacher_id]["transport_DOW"],
				"transport_mcost"=> $teacher_list[$teacher_id]["transport_mcost"],
				"transport_dcost1"=> $teacher_list[$teacher_id]["transport_dcost1"],
				"transport_dcost2"=> $teacher_list[$teacher_id]["transport_dcost2"],
				"transport_limit" => $teacher_list[$teacher_id]["transport_limit"],
				"transport_zero"  => $teacher_list[$teacher_id]["transport_zero"],
				"gennsenn_choushuu_shubetu" => $teacher_list[$teacher_id]["gennsenn_choushuu_shubetu"],
				"huyou_ninnzuu" => $teacher_list[$teacher_id]["huyou_ninnzuu"],
				"jyuuminnzei1"  => $teacher_list[$teacher_id]["jyuuminnzei1"],
				"jyuuminnzei2"  => $teacher_list[$teacher_id]["jyuuminnzei2"],
				"bank_no"          => $teacher_list[$teacher_id]["bank_no"],
				"bank_branch_no"   => $teacher_list[$teacher_id]["bank_branch_no"],
				"bank_acount_type" => $teacher_list[$teacher_id]["bank_acount_type"],
				"bank_acount_no"   => $teacher_list[$teacher_id]["bank_acount_no"],
				"bank_acount_name" => $teacher_list[$teacher_id]["bank_acount_name"],
				"lesson_id"        => $teacher_list[$teacher_id]["lesson_id"],
				"lesson_id2"       => $teacher_list[$teacher_id]["lesson_id2"]
				);
		}

		$tmp_teacher = $tmp_teacher_list[$teacher_id];

		// 生徒の出欠時間を取得
		if ($statement["absent_flag"] == "1") {
		// 休み１の場合
			$diff_hours = ($statement["end_timestamp"] - $statement["start_timestamp"]) /  (60*60);
			foreach($tmp_teacher["absent_group"]["absent1"] as $item) {
				if ($item["start_timestamp"] == $statement["start_timestamp"]) { $diff_hours=0; }
			}
			$tmp_teacher["absent1"] = $tmp_teacher["absent1"] + $diff_hours;	// $statement["diff_hours"]に0がセットされているため
		} else if ($statement["absent_flag"] == "2") {
		// 休み２の場合
			$diff_hours = $statement["diff_hours"];
			foreach($tmp_teacher["absent_group"]["absent2"] as $item) {
				if ($item["start_timestamp"] == $statement["start_timestamp"]) { $diff_hours=0; }
			}
			$tmp_teacher["absent2"] = $tmp_teacher["absent2"] + $diff_hours;
		} else {
    // 出席の場合
//var_dump(date('Ymd H:i:s', $statement['start_timestamp'])."-".date('Ymd H:i:s', $statement['end_timestamp'])."=".$statement["diff_hours"]);
			$tmp_teacher["present"] = $tmp_teacher["present"] + $statement["diff_hours"];
		}

		// 生徒の出欠時間を取得
		if ($statement["course_id"] == "2" || $statement["course_id"] == "4" || $statement["course_id"] == "5" || $statement["course_id"] == "6") {
	  // グループの場合
			$tmp_absent["start_timestamp"] = $statement["start_timestamp"];
			$tmp_absent["date"] = date("n/j", $statement["start_timestamp"]);
			$tmp_absent["name"] = $member_list[$statement["student_id"]]["name"];
			$tmp_absent["furigana"] = $member_list[$statement["student_id"]]["furigana"];
			if ($statement["absent_flag"] == "1") {
			// 休み１の場合
				$tmp_teacher["absent_group"]["absent1"][] = $tmp_absent;
			} else if ($statement["absent_flag"] == "2") {
			// 休み２の場合
				$tmp_teacher["absent_group"]["absent2"][] = $tmp_absent;
			}
		}

		// 面談を取得
	  if (strpos($statement["comment"], "面談") !== FALSE) {
		  $tmp_teacher["interview"][] = array("date"=>date("n/j", $statement["start_timestamp"]), "name"=>$member_list[$statement["student_id"]]["name"]);
	  }
/*
		// 先生の授業時間を取得
		if ($statement["course_id"] == "1" || $statement["course_id"] == "3") {
		// マンツーマンとファミリーの場合
			if ($statement["absent_flag"] != "1" && $statement["absent_flag"] != "2") {
				$tmp_teacher["working"] = $tmp_teacher["working"] + $statement["diff_hours"];
			}
		}
*/

		$result = usort($tmp_teacher["absent_group"]["absent1"], "cmp_date_furigana");
		$result = usort($tmp_teacher["absent_group"]["absent2"], "cmp_date_furigana");

 		$tmp_teacher_list[$teacher_id] = $tmp_teacher;

	}
	
if ($date_list_string != '()') {
	$sql = "SELECT DISTINCT teacher_no FROM tbl_season_schedule WHERE date IN {$date_list_string}";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $item) {
		$teacher_id = $item["teacher_no"];
		if ($tmp_teacher_list[$teacher_id]) { continue; }
		$tmp_teacher_list[$teacher_id] = array(
				"no"               => $teacher_id, 
				"name"             => $teacher_list[$teacher_id]["name"], 
				"furigana"         => $teacher_list[$teacher_id]["furigana"],
				"worker_code"      => $teacher_list[$teacher_id]["worker_code"],
//				"working"          => 0,
				"present"          => 0, 
				"absent1"          => 0, 
				"absent2"          => 0,
				"absent_group"     => array("absent1"=>array(),"absent2"=>array()),
				"interview"        => array(),
				"transport_cost"   => $teacher_list[$teacher_id]["transport_cost"],
				"transport_DOW"    => $teacher_list[$teacher_id]["transport_DOW"],
				"transport_mcost"=> $teacher_list[$teacher_id]["transport_mcost"],
				"transport_dcost1"=> $teacher_list[$teacher_id]["transport_dcost1"],
				"transport_dcost2"=> $teacher_list[$teacher_id]["transport_dcost2"],
				"transport_limit"  => $teacher_list[$teacher_id]["transport_limit"],
				"transport_zero"   => $teacher_list[$teacher_id]["transport_zero"],
				"gennsenn_choushuu_shubetu" => $teacher_list[$teacher_id]["gennsenn_choushuu_shubetu"],
				"huyou_ninnzuu"    => $teacher_list[$teacher_id]["huyou_ninnzuu"],
				"jyuuminnzei1"     => $teacher_list[$teacher_id]["jyuuminnzei1"],
				"jyuuminnzei2"     => $teacher_list[$teacher_id]["jyuuminnzei2"],
				"bank_no"          => $teacher_list[$teacher_id]["bank_no"],
				"bank_branch_no"   => $teacher_list[$teacher_id]["bank_branch_no"],
				"bank_acount_type" => $teacher_list[$teacher_id]["bank_acount_type"],
				"bank_acount_no"   => $teacher_list[$teacher_id]["bank_acount_no"],
				"bank_acount_name" => $teacher_list[$teacher_id]["bank_acount_name"],
				"lesson_id"        => $teacher_list[$teacher_id]["lesson_id"],
				"lesson_id2"       => $teacher_list[$teacher_id]["lesson_id2"]
				);
	}
}

uasort($tmp_teacher_list, "cmp_date_furigana");

//var_dump($tmp_teacher_list);
// 先生の授業時間を取得
// グループの場合
foreach ($tmp_teacher_list as &$teacher) {
$teacher_id = $teacher["no"];

$last_month_not_season_days = array();
if ($last_month_date_list) {
	// 先月既に交通費支給されている期間講習・通常レッスン重複日付を取得
	$sql = "SELECT e.event_start_timestamp, e.event_day FROM tbl_event e ".
			"where e.event_year=? and e.event_month=? and e.teacher_id=? and e.absent_flag=0 ";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($year3, $month3, $teacher_id));
	$event_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($event_list as $event) {
			$last_month_not_season_days[] = date("Y/m/d", $event['event_start_timestamp']);
	}
}

// 通常授業（期間講習・土日講習以外の授業）イベント取得
$sql = "SELECT e.cal_evt_summary, e.cal_id, e.course_id, e.event_end_timestamp, e.event_start_timestamp, ".
		"e.lesson_id, e.member_cal_name, e.member_no, e.recurringEvent, e.subject_id, e.trial_flag, ".
		"e.absent_flag, m.name, m.furigana, m.grade, e.grade as tgrade, e.event_year, e.event_month, e.event_day ".
		"FROM tbl_event e LEFT OUTER JOIN tbl_member m ".
		"on e.member_no=m.no where e.event_year=? and e.event_month=? and e.teacher_id=? ".
		"order by e.event_start_timestamp, e.cal_evt_summary";
$stmt = $db->prepare($sql);
$stmt->execute(array($year, $month, $teacher_id));
$event_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($event_list as &$event) {
	
	if ($event['trial_flag']) {
		$event['member_cal_name'] = trim($event['member_cal_name']);
		foreach ($member_list as $item)
			if ($event['member_cal_name'] == $item['name']) {
				$event['tgrade'] = $item['grade'];
				break;
			}
	}
		
	$event["date"] = date("n月j日", $event['event_start_timestamp']);
	$event["time"] = date("H:i", $event['event_start_timestamp']) ." ～ ". date("H:i", $event['event_end_timestamp']);

	// 教室
	$lesson_name = $lesson_list[$event["lesson_id"]];
	// 科目
	if ($event["subject_id"] == "0") {
		$subject_name = "　";
	} else {
		if ($event["subject_id"] == 8) {
			$subject_name = "　";
		} else {
			$subject_name = $subject_list[$event["subject_id"]];
		}
	}
	// タイプ
	if ($event["course_id"] == "0") {
		$course_name = "";
	} else {
		$course_name = $course_list[$event["course_id"]]["course_name"];
	}

	$event["course_name"]  = $course_name;
	$event["lesson_name"]  = $lesson_name;
	$event["subject_name"] = $subject_name;
	
	$name = $event["name"];
	$attendStatusCal[$event['date']][$event['time']][$name] = '';
	$str0 = str_replace(array('　','（','）','：','︰'), array(' ','(',')',':',':'), $event['cal_evt_summary']);
	if (preg_match('/(グループ|Group)/iu',$str0) || preg_match('/(ファミリー|family)/iu',$str0)) {
	$ret = preg_match_all('/\((.*?)\)/u', $str0, $blocks);
	if (!$ret) { $blocks[1]=array($str0); }
	} else {
		$blocks[1]=array($str0);
	}
	if (!preg_match('/(ファミリー|family)/iu',$str0)) {
		foreach ($blocks[1] as $key=>$block) {
			$ret = preg_match( '/([^():]+?)様([A-Za-z ]+)?/u', $block, $name_cal );
			if (!$ret) { continue; }
			if (str_replace(' ','',$name) != str_replace(' ','',$name_cal[1]) && $name!='体験生徒') { continue; }
			$event['eng_name'] = $name_cal[2];
			if (preg_match('/休み[1１]\s*:/u',$block)) { $attendStatusCal[$event['date']][$event['time']][$name] = '休み１'; }
			if (preg_match('/休み[2２]\s*:/u',$block)) { $attendStatusCal[$event['date']][$event['time']][$name] = '休み２'; }
			if (preg_match('/振替\s*:/u',$block))      { $attendStatusCal[$event['date']][$event['time']][$name] = '振替'; }
			if (preg_match('/:\s*当日/u',$block))      { $attendStatusCal[$event['date']][$event['time']][$name] .= '当日'; }
			if (preg_match('/:\s*休講/u',$block))      { $attendStatusCal[$event['date']][$event['time']][$name] .= '休講'; }
			if (preg_match('/absent1\s*:/iu',$block))  { $attendStatusCal[$event['date']][$event['time']][$name] = 'Absent1'; }
			if (preg_match('/absent2\s*:/iu',$block))  { $attendStatusCal[$event['date']][$event['time']][$name] = 'Absent2'; }
			if (preg_match('/alternative\s*:/iu',$block)) { $attendStatusCal[$event['date']][$event['time']][$name] = 'make-up'; }
			if (preg_match('/make.?up\s*:/iu',$block)) { $attendStatusCal[$event['date']][$event['time']][$name] = 'make-up'; }
			if (preg_match('/:\s*today/iu',$block))    { $attendStatusCal[$event['date']][$event['time']][$name] .= 'Today'; }
			if (preg_match('/:\s*No.*class/iu',$block)) { $attendStatusCal[$event['date']][$event['time']][$name] .= ' No class'; }
		}
	} else {
		$allPreFix = '';
		if (preg_match('/^休み[1１]\s*:/u',$blocks[1][0])) { $allPreFix = '休み１'; }
		if (preg_match('/^休み[2２]\s*:/u',$blocks[1][0])) { $allPreFix = '休み２'; }
		if (preg_match('/^振替\s*:/u',$blocks[1][0]))     { $allPreFix = '振替'; }
		if (preg_match('/^absent1\s*:/iu',$blocks[1][0]))     { $allPreFix = 'Absent1'; }
		if (preg_match('/^absent2\s*:/iu',$blocks[1][0]))     { $allPreFix = 'Absent2'; }
		if (preg_match('/^alternative\s*:/iu',$blocks[1][0])) { $allPreFix = 'make-up'; }
		if (preg_match('/^make.?up\s*:/iu',$blocks[1][0]))    { $allPreFix = 'make-up'; }
		$allPostFix = '';
		if (preg_match('/ 様 .*:\s*当日/u',$blocks[1][0])) { $allPostFix = '当日'; }
		if (preg_match('/ 様 .*:\s*休講/u',$blocks[1][0])) { $allPostFix = '休講'; }
		if (preg_match('/ 様 .*:\s*today/iu',$blocks[1][0])) { $allPostFix = ' Today'; }
		if (preg_match('/ 様 .*:\s*No.*class/iu',$blocks[1][0])) { $allPostFix = ' No class'; }
		$str0 = $blocks[1][0];
		$ret = preg_match_all('/(\S+)/u', $str0, $blocks);
		if (!$ret) { $blocks[1]=array($str0); }
		$flag = 0;
		$event['eng_name'] = '';
		foreach ($blocks[1] as $key=>$block) {
			if ($block == '様') { $flag = 1; continue; }
			if ($flag == 0) {
				$name0 = $block;
				$tmp = preg_replace( "/(\s*休み[12１２]\s*:\s*|\s*振替\s*:\s*|:\s*当日|:\s*休講|:\s*規定回数以上)/u", "", $name0 );
				if ($tmp!==false) {$name0=$tmp;}
				$tmp = preg_replace( "/(\s*absent[12]\s*:\s*|\s*alternative\s*:\s*|\s*make.?up\s*:\s*|:\s*today|:\s*over.*?limit|:\s*no.*?class)/iu", "", $name0 );
				if ($tmp!==false) {$name0=$tmp;}
				if ($key==0) {
					$family_name = $name0;
				} else {
					$name0 = $family_name.' '.$name0;
					$attendStatusCal[$event['date']][$event['time']][$name0] = '';
					if ($allPreFix) { $attendStatusCal[$event['date']][$event['time']][$name0] = $allPreFix; }
					else if (preg_match('/休み[1１]\s*:/u',$block)) { $attendStatusCal[$event['date']][$event['time']][$name0] = '休み１'; }
					else if (preg_match('/休み[2２]\s*:/u',$block)) { $attendStatusCal[$event['date']][$event['time']][$name0] = '休み２'; }
					else if (preg_match('/振替\s*:/u',$block))     { $attendStatusCal[$event['date']][$event['time']][$name0] = '振替'; }
					else if (preg_match('/absent1\s*:/iu',$block))  { $attendStatusCal[$event['date']][$event['time']][$name0] = 'Absent1'; }
					else if (preg_match('/absent2\s*:/iu',$block))  { $attendStatusCal[$event['date']][$event['time']][$name0] = 'Absent2'; }
					else if (preg_match('/alternative\s*:/iu',$block)) { $attendStatusCal[$event['date']][$event['time']][$name0] = 'make-up'; }
					else if (preg_match('/make.?up\s*:/iu',$block)) { $attendStatusCal[$event['date']][$event['time']][$name0] = 'make-up'; }
					if ($allPostFix) { $attendStatusCal[$event['date']][$event['time']][$name0] .= $allPostFix; }
					else if (preg_match('/:\s*当日/u',$block))      { $attendStatusCal[$event['date']][$event['time']][$name0] .= '当日'; }
					else if (preg_match('/:\s*休講/u',$block))      { $attendStatusCal[$event['date']][$event['time']][$name0] .= '休講'; }
					else if (preg_match('/:\s*today/iu',$block))    { $attendStatusCal[$event['date']][$event['time']][$name0] .= ' Today'; }
					else if (preg_match('/:\s*No.*class/iu',$block)) { $attendStatusCal[$event['date']][$event['time']][$name0] .= ' No class'; }
				}
			} else {
				$event['eng_name'] .= $block.' ';
			}
		}
	}
			
	$event["comment"] = $comment;
	$event['diff_hours'] = ($event["event_end_timestamp"] - $event["event_start_timestamp"]) /  (60*60);

}
unset($event);

/*
// 期間講習追加
$season_exercise = array();
if ($date_list_string != '()') {
	$sql = "SELECT * FROM tbl_season_schedule s LEFT OUTER JOIN tbl_member m ON s.member_no=m.no WHERE s.date IN {$date_list_string} AND s.teacher_no={$teacher_id}";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($schedules as $schedule) {
		$date = str_replace('/','月',substr(str_replace('/0','/',$schedule['date']),5)).'日';
		$event = array();
		$event["event_year"]   = ltrim(substr($schedule['date'],0,4),'0');
		$event["event_month"]  = ltrim(substr($schedule['date'],5,2),'0');
		$event["event_day"]    = ltrim(substr($schedule['date'],8),'0');
		$event['course_id']    = $season_course_id;
		$event['course_name']  = $course_list[$event["course_id"]]["course_name"];
		$event['lesson_id']    = 1;
		$event['lesson_name']  = $lesson_list[$schedule['lesson_id']];
		$event['subject_id']   = $schedule['subject_id'];
		$event['subject_name'] = $subject_list[$schedule['subject_id']];
		$event['member_no']    = $schedule['member_no'];
		$event['name']         = $schedule['name'];
		$event['furigana']     = $schedule['furigana'];
		$event['grade']        = $schedule['grade'];
		$event['date']         = $date;
		$event['date1']        = $schedule['date'];
		$event['time']         = "{$schedule['stime']} ～ {$schedule['etime']}";
		$event['event_start_timestamp'] = DateTime::createFromFormat('Y/m/d H:i', "{$schedule['date']} {$schedule['stime']}")->getTimestamp();
		$event['event_end_timestamp']   = DateTime::createFromFormat('Y/m/d H:i', "{$schedule['date']} {$schedule['etime']}")->getTimestamp();
		$event['recurringEvent'] = 0;
		$event['trial_flag']     = 0;
		$event['absent_flag']    = 0;
		$event['diff_hours'] = ($event["event_end_timestamp"] - $event["event_start_timestamp"]) / (60*60);
		$event["cal_evt_summary"] = "{$event['course_name']}:{$event['lesson_name']}:{$event['subject_name']}:{$event['name']}";
		if ($event['member_no']) {
			$event_list[] = $event;
		} else {
//			if (!$season_exercise[$date]) {
//				$season_exercise[$date] = array();
//				$event_list[] = $event;
//			}
//			$season_exercise[$date][] = array('stime'=>$schedule['stime'], 'etime'=>$schedule['etime']);
		}
	}
	
	foreach ($date_list as $date) {
		$date0 = str_replace('/','月',substr(str_replace('/0','/',$date),5)).'日';
		$schedules1 = array_filter($schedules, function($s){ global $date,$teacher_id; return ($s['date']==$date && $s['teacher_no']==$teacher_id); });
		$stmt = $db->query("SELECT * FROM tbl_season_class_entry_date WHERE date=\"$date\"");
		$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rslt as $item) {
			foreach ($schedules1 as $schedule) {
				if ($schedule['member_no'] != $item['member_id']) { continue;}
				$time1 = "{$schedule['stime']} ～ {$schedule['etime']}";
				$attendStatusCal[$date0][$time1][$member_list[$schedule['member_no']]['name']] = ($item['attend_status']=='出席')?'':$item['attend_status'];
				foreach ($event_list as &$event) {
					if ($event['date']==$date0 && $event['time']==$time1 && $event['member_no']==$schedule['member_no']) {
						if (strpos($item['attend_status'],'休み１')!==false) { $event['absent_flag'] = 1; $event['diff_hours'] = 0; } else
						if ($item['attend_status']=='休み２当日')             { $event['absent_flag'] = 2; } else
						if (strpos($item['attend_status'],'休み２')!==false) { $event['absent_flag'] = 2; $event['diff_hours'] = 0; }
					}
				}
				unset($event);
			}
		}

		$cmd = "SELECT stime, etime FROM tbl_season_class_teacher_entry WHERE no = \"{$teacher_id}\" AND date = \"{$date}\"";
		$stmt = $db->query($cmd);
		$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
		$schedules1 = array_filter($schedules, function($s){ global $date; return $s['date']==$date; });
		if ($rslt && array_search($date, array_column($event_list,'date1'))!==false) {
			$event = array();
			$event["event_year"]   = ltrim(substr($schedule['date'],0,4),'0');
			$event["event_month"]  = ltrim(substr($schedule['date'],5,2),'0');
			$event["event_day"]    = ltrim(substr($date,8),'0');
			$event['course_id']    = $season_course_id;
			$event['course_name']  = $course_list[$event["course_id"]]["course_name"];
			$event['lesson_id']    = 1;
			$event['lesson_name']  = $lesson_list[1];
			$event['subject_name'] = '演習';
			$event['member_no']    = '';
			$event['date']         = $date0;
			$event['date1']        = $date;
			foreach ($time_list as $stimekey=>$stime) {
				if ($stime<$rslt['stime'] || $stime>=$rslt['etime']) { continue; }
				if (array_filter($schedules1,
							function($s){
								global $date0,$stime,$attendStatusCal,$member_list;
								$time = "{$s['stime']} ～ {$s['etime']}";
								$name = $member_list[$s['member_no']]['name'];
								$st = $attendStatusCal[$date0][$time][$name];
								return ($s['stime']<=$stime && $stime<$s['etime'] && (strpos($st,'休み')===false || strpos($st,'休み２当日')!==false)); }))
						continue;
				$season_exercise[$date][] = array('stime'=>$stime, 'etime'=>$time_list[$stimekey+1]);
			}
			$time_str = ''; $lastetime = '';
			foreach ($season_exercise[$date] as $item) {
				if ($item['stime'] != $lastetime) {
					$time_str .= $lastetime;
					if ($lastetime) { $time_str .= '<br>'; }
					$time_str .= $item['stime'].' ～ ';
				}
				$lastetime = $item['etime'];
			}
			$time_str .= $lastetime;
			$event['time'] = $time_str;
			$event['diff_hours'] = count($season_exercise[$date]) * 0.5;
			$event["cal_evt_summary"] = "{$event['course_name']}:{$event['lesson_name']}:{$event['subject_name']}:{$event['name']}";
			$event['event_start_timestamp'] = DateTime::createFromFormat('Y/m/d H:i', "{$date} {$rslt['stime']}")->getTimestamp();
			$event_list[] = $event;
		}
		
		$cmd = "SELECT times FROM tbl_season_class_teacher_entry1 WHERE no = \"{$teacher_id}\" AND date = \"{$date}\"";
		$stmt = $db->query($cmd);
		$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($rslt && array_search($date, array_column($event_list,'date1'))!==false) {
			$schedules1 = array_filter($schedules, function($s){ global $date; return $s['date']==$date; });
			$event = array();
			$event["event_year"]   = ltrim(substr($schedule['date'],0,4),'0');
			$event["event_month"]  = ltrim(substr($schedule['date'],5,2),'0');
			$event["event_day"]    = ltrim(substr($date,8),'0');
			$event['course_id']    = $season_course_id;
			$event['course_name']  = '土日講習';
			$event['lesson_id']    = 1;
			$event['lesson_name']  = $lesson_list[1];
			$event['subject_name'] = '演習';
			$event['member_no']    = '';
			$event['date']         = $date0;
			$event['date1']        = $date;
			$stime0 = '';
			foreach ($time_list as $stimekey=>$stime) {
				if (strpos($rslt['times'],$stime)===false) { continue; }
				if (!$stime0) $stime0 = $stime;
				if (array_filter($schedules1,
							function($s){
								global $date0,$stime,$attendStatusCal,$member_list;
								$time = "{$s['stime']} ～ {$s['etime']}";
								$name = $member_list[$s['member_no']]['name'];
								$st = $attendStatusCal[$date0][$time][$name];
								return ($s['stime']<=$stime && $stime<$s['etime'] && (strpos($st,'休み')===false || strpos($st,'休み２当日')!==false)); }))
						continue;
				$season_exercise[$date][] = array('stime'=>$stime, 'etime'=>$time_list[$stimekey+1]);
			}
			$time_str = ''; $lastetime = '';
			foreach ($season_exercise[$date] as $item) {
				if ($item['stime'] != $lastetime) {
					$time_str .= $lastetime;
					if ($lastetime) { $time_str .= '<br>'; }
					$time_str .= $item['stime'].' ～ ';
				}
				$lastetime = $item['etime'];
			}
			$time_str .= $lastetime;
			$event['time'] = $time_str;
			$event['diff_hours'] = count($season_exercise[$date]) * 0.5;
			$event["cal_evt_summary"] = "{$event['course_name']}:{$event['lesson_name']}:{$event['subject_name']}:{$event['name']}";
			$event['event_start_timestamp'] = DateTime::createFromFormat('Y/m/d H:i', "{$date} {$stime0}")->getTimestamp();
			$event_list[] = $event;
		}
	}
}
*/

foreach ($event_list as $key => $value) {
    $sort1[$key] = $value['date'];
    $sort2[$key] = $value['time'];
		$sort3[$key] = $value['cal_evt_summary'];
		$sort4[$key] = $value['furigana'];
}

array_multisort(
	$sort1, SORT_ASC, SORT_NATURAL, $sort2, SORT_ASC, SORT_NATURAL,
	$sort3, SORT_ASC, SORT_NATURAL, $sort4, SORT_ASC, SORT_NATURAL, $event_list );
		
$no=0; $i=0; $member_count = 0; $rowspan=1;
$event = reset($event_list);
while ($event) {
/*
	if ($event['course_id'] == 9 || $event['course_id']==10) {
		$event = next($event_list);
		continue;
	}
*/
	$diff_hours = $event['diff_hours'];
	$next_event = $event;
	$absent_flag_min = 2;
	$todayFlag = 0;
	do {
		$event = $next_event;
if ($teacher_id==$log_tid && $event['date']==$log_date) {var_dump($event);echo"<BR>";}
		if ($event["member_no"]) {
			$name = $event["name"];
			if ($event["course_id"] == 3) {
				$tmp0 = explode(' ',$name);
				$family_name = $tmp0[0]; array_shift($tmp0); $names = array();
				foreach ($tmp0 as $str0) { $names[] = $family_name.' '.$str0; }
			} else {
				$names = array($name);
			}
			foreach ($names as $key=>$name) {
				$i++;
				if (!($event['course_id'] == 2 && $event["trial_flag"])) { $member_count++; }
				if (preg_match('/(当日|today)/iu', $attendStatusCal[$event["date"]][$event["time"]][$name])) { $todayFlag = 1; }
			}
		}
		if ($event['absent_flag']<$absent_flag_min) { $absent_flag_min = $event['absent_flag']; }
		$lastdate=$event["date"]; $lasttime=$event["time"]; $last_cal_evt_summary = $event["cal_evt_summary"];
		$lastteacher_id=$event["teacher_id"]; 
		$next_event = next($event_list);
//	} while (($next_event) && ($next_event["date"] == $lastdate) && ($next_event["time"] == $lasttime) && ($next_event["cal_evt_summary"] == $last_cal_evt_summary));
	} while (($next_event) && ($next_event["date"] == $lastdate) && ($next_event["time"] == $lasttime) && ($next_event["teacher_id"] == $last_teacher_id) && $event["course_id"] != 1); // changes for sakuraonenet.
			
	if ($absent_flag_min>0 && !$todayFlag) { $member_count = 0; $event = $next_event; continue; }
	
	if ($event['course_id'] == 2 && $member_count == 0) { $member_count = 1; }
	
	if ($todayFlag) {
		$sql = 
			"SELECT * FROM tbl_event ".
			"WHERE event_year=? AND event_month=? AND teacher_id=? AND absent_flag=0 ".
			"AND cal_evt_summary!=? AND (event_start_timestamp BETWEEN ? AND ?) ";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($year,$month,$teacher_id,$event["cal_evt_summary"],$event['event_start_timestamp'],$event['event_end_timestamp']-1));
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($ret) {
			$array = array();
			for ($i=$event['event_start_timestamp']; $i<$event['event_end_timestamp']; $i+=60) { $array[$i]=1; }
			foreach ($ret as $item) {
				for ($i=$item['event_start_timestamp']; $i<$item['event_end_timestamp']; $i+=60) { $array[$i]=0; }
			}
			$count = 0;
			foreach ($array as $item) { if ($item) { $count++; } }
			$diff_hours = $count/60.0;
		}
	}
	
	$lesson_id = $event['lesson_id'];

	$teacher["working_days"][] = $event["event_month"].'/'.$event["event_day"];
	$teacher["working"][$lesson_id] = $teacher["working"][$lesson_id] + $diff_hours;
	
	if ($teacher["transport_DOW"]) {
		if (strpos($teacher["transport_DOW"], date('w',$event['event_start_timestamp'])) !== false) {
			if (!$event['date1'] || (!in_array($event['date1'], $last_month_not_season_days)))
				$teacher["transport_days"][] = $event["event_year"].'-'.$event["event_month"].'-'.$event["event_day"];
		}
	} else {
		if ($teacher["transport_dcost1"][date('w',$event['event_start_timestamp'])] ||
				$teacher["transport_dcost2"][date('w',$event['event_start_timestamp'])]) {
			if (!$event['date1'] || (!in_array($event['date1'], $last_month_not_season_days)))
				$teacher["transport_days"][] = $event["event_year"].'-'.$event["event_month"].'-'.$event["event_day"];
		} else {
/*
			$tmp1 = 0;
			foreach ($teacher["transport_dcost1"] as $tmp2) $tmp1+=$tmp2;
			foreach ($teacher["transport_dcost2"] as $tmp2) $tmp1+=$tmp2;
			if (!$teacher['transport_mcost'] && $tmp1) {
				$DOW = date('w',$event['event_start_timestamp']);
				$str1 = "交通費0円警告：　{$teacher['name']}先生 {$youbistr[$DOW]}曜日";
				if (array_search($str1, $errArray)===false)	$errArray[] = $str1;
			}
*/
		}
	}

	$wage_no = 0;
	$work_type_flag = 0;
	foreach ($work_type_list as $key=>$work_type) {
		if (!$work_type) { continue; }
		if (strpos($event['cal_evt_summary'], $work_type)!==false) {
			$stmt = $db->query("SELECT * FROM tbl_wage WHERE teacher_id=\"{$teacher_id}\" AND wage_no=\"{$wage_no}\" AND work_type=\"{$key}\"");
			$wage_array = $stmt->fetch(PDO::FETCH_ASSOC);			
			$hourly_wage = $wage_array['hourly_wage'];
			$teacher["wage_worktime1"][$lesson_id][$hourly_wage] += $diff_hours;
			$teacher["wage_worktime2"][$lesson_id][($key+4).':0'] += $diff_hours;
			$work_type_flag = 1;
if ($teacher_id==$log_tid) {echo"$work_type,{$event['date']},{$event['time']},{$hourly_wage},{$diff_hours},".($hourly_wage*$diff_hours)."<BR>";}

			if (!$hourly_wage) {
					$errArray[] = "時給未登録エラー：　{$teacher['name']}先生 {$work_type} ".
						date('Y/m/d',$event['event_start_timestamp'])." ".date('H:i',$event['event_start_timestamp'])."～".date('H:i',$event['event_end_timestamp']).
						" {$event['cal_evt_summary']}";
			}
			break;
		}
	}
	
	$wage_type_list = '';
	if ($work_type_flag) {} else
	if ($event["subject_id"]) {
//	if ($event["member_no"]) {
		
		$wage_no = -1; 
		switch ($lesson_id) {
		case 1:
			$wage_type_list = $jyuku_wage_type_list;
			if ($event['trial_flag']) {
				$grade = $event['tgrade'];
			} else {
				$grade = $event['grade'];
				if ($grade) {
					if ($year==date('Y') && date('n') >= 4 && $month < 4 && $grade > 1) { $grade--; }
				}
			}
			if ($grade) {
				if ($grade < 8) {
					if ($member_count >= 5) {
						$wage_no = 13;
					} else if ($member_count == 4) {
						$wage_no = 12;
					} else if ($member_count == 3) {
						$wage_no = 11;
					} else if ($member_count == 2) {
						$wage_no = 1;
					} else if ($member_count == 1) {
						if ($member_list[$event["member_no"]]['jyukensei']) {
							$wage_no = 14;
						} else {
							$wage_no = 0;
						}
					}
				} else if ($grade <= 10) {
					if ($member_count >= 5) {
						$wage_no = 7;
					} else if ($member_count == 4) {
						$wage_no = 6;
					} else if ($member_count == 3) {
						$wage_no = 5;
					} else if ($member_count == 2) {
						$wage_no = 4;
					} else if ($member_count == 1) {
						if ($grade == 10) {
							$wage_no = 3;
						} else {
							$wage_no = 2;
						}
					}
				} else if ($grade <= 13) {
					if ($member_count == 2) {
						$wage_no = 9;
					} else if ($member_count == 1) {
						if ($grade == 13) {
							$wage_no = 10;
						} else {
							$wage_no = 8;
						}
					}
				} else if ($grade == 14) {
					if ($member_count == 2) {
						$wage_no = 9;
					} else if ($member_count == 1) {
						$wage_no = 8;
					}
				}
			}
			break;
		case 2:
			$wage_type_list = $eng_wage_type_list;
			if ($member_count >= 5) { 
				$wage_no = 2;
			} else if ($member_count == 2) {
				$wage_no = 1;
			} else if ($member_count == 3) {
				$wage_no = 4;
			} else if ($member_count == 4) {
				$wage_no = 5;
			} else if ($member_count == 1 ) {
				$wage_no = 0;
			}
			break;
		case 3:
			$wage_type_list = $piano_wage_type_list;
			$wage_no = 0;
			break;
		case 4:
			$wage_type_list = $naraigoto_wage_type_list;
			$wage_no = 0;
		}
		//echo "$lesson_id,$grade,$member_count,$wage_no<br>";
		if ($wage_no>-1) {
			$stmt = $db->query("SELECT * FROM tbl_wage WHERE teacher_id=\"{$teacher_id}\" AND wage_no=\"{$wage_no}\" AND lesson_id=\"{$lesson_id}\" AND work_type=0");
			$wage_array = $stmt->fetch(PDO::FETCH_ASSOC);			
			if ($wage_array) {
				$hourly_wage = $wage_array["hourly_wage"];
				if ($hourly_wage) {
					$crew_no = $wage_array["crew_no"];
if ($teacher_id==$log_tid) {echo"{$event['date']},$absent_flag_min,$todayFlag<BR>";}
					if ($event['course_id'] != $season_course_id && $absent_flag_min>0 && $todayFlag) {
						$hourly_wage *= 0.6; if ($hourly_wage<1000) { $hourly_wage=1000; }
						$crew_no += 10;
					}
					$teacher["wage"][$wage_no] = $hourly_wage;
					$teacher["wage_worktime1"][$lesson_id][$hourly_wage] += $diff_hours;
					$teacher["wage_worktime2"][$lesson_id][$lesson_id.':'.$wage_no] += $diff_hours;
					$teacher["wage_worktime3"][$lesson_id][$crew_no] += $diff_hours;
if ($teacher_id==$log_tid) {echo"{$event['date']},{$event['time']},{$hourly_wage},{$diff_hours},".($hourly_wage*$diff_hours)."<BR>";}
				} else {
					$errArray[] = "時給未登録エラー：　{$teacher['name']}先生 {$wage_type_list[$wage_no]} ".
						date('Y/m/d',$event['event_start_timestamp'])." ".date('H:i',$event['event_start_timestamp'])."～".date('H:i',$event['event_end_timestamp']).
						" {$event['lesson_name']} {$event['subject_name']} {$event['course_name']} {$event['cal_evt_summary']}";
				}
			} else {
				$errArray[] = "時給未登録エラー：　{$teacher['name']}先生 {$wage_type_list[$wage_no]} ".
					date('Y/m/d',$event['event_start_timestamp'])." ".date('H:i',$event['event_start_timestamp'])."～".date('H:i',$event['event_end_timestamp']).
					" {$event['lesson_name']} {$event['subject_name']} {$event['course_name']} {$event['cal_evt_summary']}";
			}
		} else {
			if ($lesson_id == 1 && $grade == '') {
				$errArray[] = "学年不明エラー：　{$teacher['name']}先生 {$wage_type_list[$wage_no]} ".
					date('Y/m/d',$event['event_start_timestamp'])." ".date('H:i',$event['event_start_timestamp'])."～".date('H:i',$event['event_end_timestamp']).
					" {$event['lesson_name']} {$event['subject_name']} {$event['course_name']} {$event['cal_evt_summary']}";
			} else {
				$errArray[] = "時給未登録エラー：　{$teacher['name']}先生 {$wage_type_list[$wage_no]} ".
					date('Y/m/d',$event['event_start_timestamp'])." ".date('H:i',$event['event_start_timestamp'])."～".date('H:i',$event['event_end_timestamp']).
					" {$event['lesson_name']} {$event['subject_name']} {$event['course_name']} {$event['cal_evt_summary']}";
			}
		}
	} else {
		if ($event['course_id'] == $season_course_id) {
			$key = array_search('演習',$work_type_list);
			$stmt = $db->query("SELECT * FROM tbl_wage WHERE teacher_id=\"{$teacher_id}\" AND wage_no=\"0\" AND work_type=\"{$key}\"");
			$wage_array = $stmt->fetch(PDO::FETCH_ASSOC);			
			$hourly_wage = $wage_array['hourly_wage'];
			$teacher["wage"][$wage_no] = $hourly_wage;
			$teacher["wage_worktime1"][$lesson_id][$hourly_wage] += $diff_hours;
			$teacher["wage_worktime2"][$lesson_id]['0:0'] += $diff_hours;
			$teacher["wage_worktime3"][$lesson_id][$wage_array["crew_no"]] += $diff_hours;
if ($teacher_id==$log_tid) {echo"{$event['date']},{$event['time']},".($hourly_wage*$diff_hours)."<BR>";}
			if (!$hourly_wage) {
				$errArray[] = "時給未登録エラー：　{$teacher['name']}先生 演習 ".
					date('Y/m/d',$event['event_start_timestamp'])." ".date('H:i',$event['event_start_timestamp'])."～".date('H:i',$event['event_end_timestamp']);
			}
		} else {
		}
	}
	$member_count = 0; $event = $next_event;
}

$teacher["working_days"] = array_unique($teacher["working_days"]);
$teacher["transport_days"] = array_unique($teacher["transport_days"]);

}
unset($teacher);
/*
foreach ($tmp_teacher_list as $teacher_no=>$teacher) {
	$param_array=array("view_lesson.teacher_id=?");
	$value_array=array($teacher_no);
	$lesson_list = get_lesson_data($db, $year, $month);
	foreach ($lesson_list as $lesson_data) {
		//var_dump($lesson_data);
		var_dump(date('Ymd H:i:s', $lesson_data['start_timestamp'])."-".date('Ymd H:i:s', $lesson_data['end_timestamp'])."=".$lesson_data['lesson_hours']);
		$tmp_teacher_list[$teacher_no]["working"] = $tmp_teacher_list[$teacher_no]["working"] + $lesson_data['lesson_hours'];
	}
}
*/

$wage_type_list = array(array('期間講習演習'), $jyuku_wage_type_list, $eng_wage_type_list, $piano_wage_type_list, $naraigoto_wage_type_list);
foreach ($work_type_list as $key=>$work_type) {
	if (!$work_type) { continue; }
	$wage_type_list[] = array($work_type);
}
//var_dump($tmp_teacher_list);
//}

$cmd = "SELECT * FROM tbl_payadj WHERE year=$year AND month=$month";
$stmt = $db->query($cmd);
$payadj_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cmd = "SELECT * FROM tbl_tatekae WHERE year=$year AND month=$month AND status='承認済'";
$stmt = $db->query($cmd);
$tatekae_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
	// 処理を中断するほどの致命的なエラー
	array_push($errArray, $e->getMessage());
}

function prop_divide($key, $val, $pay1, $pay2, $pay3, $teacher, $staff){
//	if ($pay1 || !$pay2) $pay1 += $teacher['payadj'] + $teacher['payadj_tax_free'] + $teacher['tatekae_total'];
//	else  $pay2 += $teacher['payadj'] + $teacher['payadj_tax_free'] + $teacher['tatekae_total'];
//	$pay3 += $staff['payadj']   + $staff['payadj_tax_free'] + $staff['tatekae_total'];
	$total = $pay1 + $pay2 + $pay3;
	if (!$total) return 0;
	$val1 = floor($val*$pay1/$total);
	$val2 = floor($val*$pay2/$total);
	$val3 = floor($val*$pay3/$total);
	if ($val3) {
		$val3 = $val - $val1 - $val2; 
	} else if ($val2) {
		$val2 = $val - $val1; 
	} else {
		$val1 = $val;
	}
	switch ($key) {
		case 0: return $val1;
		case 1: return $val2;
		case 2: return $val3;
	}
	return 0;
}

function fwritesjis( $fp, $s) {
	fwrite ($fp, mb_convert_encoding($s,'sjis','utf8'));
}

function cmp_date_furigana($a, $b) {
	if ($a["start_timestamp"] == $b["start_timestamp"]) {
		if ($a["furigana"] == $b["furigana"]) {
			return 0;
		}
		return ($a["furigana"] > $b["furigana"]) ? +1 : -1;
		}
	return ($a["start_timestamp"] > $b["start_timestamp"]) ? +1 : -1;
}

function cmp_worker_code($a, $b) {
	return ($a["worker_code"] > $b["worker_code"]) ? +1 : -1;
}

$rakuten_csvfname = "rakuten_".session_id().".csv";

/*
function get_group_list(&$db, $teacher_no, $start_timestamp, $end_timestamp, $absent_flag="") {
try{
	 	$param_array = array();
		array_push($param_array, "tbl_statement_detail.teacher_id=?");
		array_push($param_array, "(tbl_statement_detail.course_id='2' or tbl_statement_detail.course_id='4' or tbl_statement_detail.course_id='5' or tbl_statement_detail.course_id='6')");
		array_push($param_array, "tbl_statement_detail.start_timestamp=?");
		array_push($param_array, "tbl_statement_detail.end_timestamp=?");
		if ($absent_flag != "") {
			array_push($param_array, "tbl_statement_detail.absent_flag=?");
		}
		$value_array = array($teacher_no, $start_timestamp, $end_timestamp);
		if ($absent_flag == "1") {
			array_push($value_array, "1");
		} else if ($absent_flag == "2") {
			array_push($value_array, "2");
		}
		$order_array = array();
		$sql = 
			"SELECT
					*
			 FROM tbl_statement_detail";
	  if(count($param_array) > 0){
	    $sql .= " where " . join(" and ",$param_array);
	  }
//echo $sql;
		$stmt = $db->prepare($sql);
		$stmt->execute($value_array);
		$group_list = $stmt->fetchAll(PDO::FETCH_BOTH);
		//var_dump($value_array);
} catch (Exception $e) {
	// 処理を中断するほどの致命的なエラー
	var_dump($e->getMessage());
}
return $group_list;
}
*/

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="index,follow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
function search_reset()
{
	document.search_form.cond_name = '';
	document.search_form.submit();
}
function download1(){
	var month1 = document.forms['rakuten_form'].elements['furikomi_month'].value;
	var day1   = document.forms['rakuten_form'].elements['furikomi_day'].value;
	var err_char = month1.replace(/[0-9]/g,'');
	if (err_char) { alert("振込実行日は半角数字で入力してください。"); return false; }
	err_char = day1.replace(/[0-9]/g,'');
	if (err_char) { alert("振込実行日は半角数字で入力してください。"); return false; }
	if (month1<1 || month1>12 || day1<1 || day1>31) { alert("振込実行日が不正です。"); return false; }
	document.location="./download.php?f=<?= $rakuten_csvfname ?>&y=<?= $year ?>&m=<?= $month ?>&m1="+month1+"&d1="+day1;
}
var worktime_detail=0;
var absent_detail=0;
function dispWorktimeDetail() {
	var elems = document.getElementsByName('worktime_detail');
	worktime_detail=!worktime_detail;
	for (var i=0;i<elems.length;i++) {
		elems[i].style.display = (worktime_detail)? "":"none";
	}
}
function dispAbsentDetail() {
	var elems = document.getElementsByName('absent_detail');
	absent_detail=!absent_detail;
	for (var i=0;i<elems.length;i++) {
		elems[i].style.display = (absent_detail)? "":"none";
	}
}
//-->
</script>
</head>
<body>
<div id="header">
	事務システム
</div>
<div id="content" align="center">
<h3>講師・事務員の給与計算</h3>

<?php
if (!$pdf_mode) {
	if (count($errArray) > 0) {
		$errFlag = 1;
?>
<table>
<?php
		foreach( $errArray as $error) {
?>
			<tr><td><font color="red" size="3"><?= $error ?></font></td></tr>
<?php
		}
?>
</table>
<?php
//		exit();
	}
?>
<a href="./menu.php">メニューへ戻る</a><br>
<?php } ?>
<h3><?= $year ?>年<?= $month ?>月</h3>
<table>
<tr><td>■源泉徴収税額の計算は「<?=GENNSENN_CHOUSHUU_HYOU_MEISHOU?>」に基づきます。</td></tr>
</table>
<br>
<?php ob_start(); ?>
<h3>講師</h3>
<?php if (!$pdf_mode) { ?>
<table>
<tr><td>■先生名をクリックすると別ウィンドウに詳細が表示されます。</td></tr>
<tr><td>■体験と面談の時間も含みます。（授業料は発生しませんが給与は発生するためです）</td></tr>
<tr><td>■休み1と休み2の時間は含みません。（先生によっては休み1と休み2で給与が発生する場合があります）</td></tr>
<tr><td>■グループで複数の生徒が１時間授業を受けられた場合、授業時間は「１時間」となります</td></tr>
</table>
<br>
<input type="checkbox" onclick="dispWorktimeDetail()">授業時間詳細表示&nbsp;&nbsp;
<input type="checkbox" onclick="dispAbsentDetail()"  >休み・面談詳細表示&nbsp;&nbsp;
<br>
<?php } ?>
<table border="1">
<tr>
<th>先生名</th><th>教室</th><th>出勤日数</th><th>授業時間</th>
<?php if (!$pdf_mode) { ?>
<th name="worktime_detail" style="display:none;">時給別授業時間</th>
<th name="worktime_detail" style="display:none;">時給条件別授業時間</th>
<?php } ?>
<th>時給×授業時間</th><th>立替経費</th><th>給料調整<br>(課税)</th><th>給料調整<br>(非課税)</th><th>交通費</th><th>支給額合計</th>
<th>源泉徴収税</th><th>住民税</th><th>控除額合計</th><th>差引支給額</th>
<?php if (!$pdf_mode) { ?>
<th name="absent_detail" style="display:none;">休み１の時間</th><th name="absent_detail" style="display:none;">休み２の時間</th><th name="absent_detail" style="display:none;">グループの休み１</th><th name="absent_detail" style="display:none;">グループの休み２</th><th name="absent_detail" style="display:none;">面談</th>
<?php } ?>
</tr>
<?php
try {

foreach ($staff_list as $key=>&$staff) {
	
	$sql = 
		"SELECT sum(event_diff_hours) FROM tbl_event_staff ".
		"WHERE event_year=$year AND event_month=$month AND staff_no={$staff['no']} AND absent_flag=0";
	$stmt = $db->query($sql);
	$work_times = ($stmt->fetch(PDO::FETCH_NUM))[0];
	$staff['work_times'] = $work_times;
	
	$sql = 
		"SELECT count(DISTINCT event_day) FROM tbl_event_staff ".
		"WHERE event_year=$year AND event_month=$month AND staff_no={$staff['no']} AND absent_flag=0";
	$stmt = $db->query($sql);
	$work_days = ($stmt->fetch(PDO::FETCH_NUM))[0];
	$staff['work_days'] = $work_days;
	
	$cmd = "SELECT * FROM tbl_wage_staff WHERE staff_id={$staff['no']}";
	$stmt = $db->query($cmd);
	$wages = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$staff['wages'] = $wages;
	
	$pay = floor($work_times * $wages[0]['hourly_wage']);
	$staff['pay0'] = $pay;

	$payadj_detail   = array_filter($payadj_list, function($item){global $staff; return $item['employee_type']==STAFF && $item['employee_no']==$staff['no'];});
	$payadj          = array_sum(array_column(array_filter($payadj_detail, function($item){return $item['tax_flag']==1;}), 'price'));
	$payadj_tax_free = array_sum(array_column(array_filter($payadj_detail, function($item){return $item['tax_flag']==0;}), 'price'));
	$staff['payadj_detail']   = $payadj_detail;
	$staff['payadj']          = $payadj ;
	$staff['payadj_tax_free'] = $payadj_tax_free;
		
	$tatekae_detail = array_filter($tatekae_list, function($item){global $staff; return $item['employee_type']==STAFF && $item['employee_no']==$staff['no'];} );
	$tatekae_total  = array_sum(array_column($tatekae_detail, 'price'));
	$staff['tatekae_detail'] = $tatekae_detail;
	$staff['tatekae_total']  = $tatekae_total;
	
	if (!$pay && !$tatekae_total && !$payadj && !$payadj_tax_free)
		unset($staff_list[$key]);
	else
		if ($teacher_and_staff_list[$staff['name']])	$teacher_and_staff_list[$staff['name']]['staff'] = $staff;
}
unset($staff);

foreach ($tmp_teacher_list as &$teacher) {
	
	if ($teacher['no']==1) $yuge_flag=1; else $yuge_flag=0;
	
	foreach (array($teacher["lesson_id"], $teacher["lesson_id2"]) as $key1=>$lesson_id) {
		if (!$lesson_id) continue;
		if ($teacher["lesson_id"] && $teacher["lesson_id2"]) $str1='rowspan="2"'; else $str1='';
		$working_hours = $teacher["working"][$teacher["lesson_id"]]+$teacher["working"][$teacher["lesson_id2"]];
		$str0 = "<tr>";
		if (!($str1 && $key1!=0)) {
			$str0 .= "<td align=\"left\" $str1>	<a href=\"../../sakura-teacher/check_work_time.php?y=$year&m=$month&tid={$teacher['no']}\" target=\"_blank\">{$teacher['name']}</a></td>";
		}
		$str0 .= "<td align=\"center\">{$lesson_list[$lesson_id]}</td>";
		if (!($str1 && $key1!=0)) {
			$str0 .= "<td align=\"right\" $str1>".count($teacher["working_days"])."</td>";
			if (!$yuge_flag) $total_working_days += count($teacher["working_days"]);
		}
		$str0 .= "<td align=\"right\">".(floor($teacher['working'][$lesson_id]*100)/100)."</td>";
		if (!$yuge_flag) $total_working_hours += $teacher['working'][$lesson_id];
		if (!$pdf_mode)	{
			$str0 .= "<td align=\"right\" name=\"worktime_detail\" style=\"display:none;\">";
			foreach ($teacher["wage_worktime1"][$lesson_id] as $key=>$val) {
				if ($val) { $str0 .= (floor($val*100)/100)."H ({$key})<br>"; }
			}
			$str0 .= "</td>";
			$str0 .= "<td align=\"right\" name=\"worktime_detail\" style=\"display:none;\">";
			foreach ($teacher["wage_worktime2"][$lesson_id] as $key=>$val) {
				$array = explode(':',$key);
				if ($val) { $str0 .= (floor($val*100)/100)."H ({$wage_type_list[$array[0]][$array[1]]})<br>"; }
			}
			$str0 .= "</td>";
		}
		$str0 .= "<td align=\"right\">";
		
		$pay1 = 0; $pay2 = 0;
		foreach ($teacher["wage_worktime1"][$teacher["lesson_id"]]  as $key=>$val) { $pay1 += $key*$val; }
		foreach ($teacher["wage_worktime1"][$teacher["lesson_id2"]] as $key=>$val) { $pay2 += $key*$val; }
		$pay1 = floor($pay1); $pay2 = floor($pay2);
		$teacher['pay0'] = $pay1 + $pay2;
		$pay = 0;
		foreach ($teacher["wage_worktime1"][$lesson_id] as $key=>$val) { $pay += $key*$val; }
		$pay = floor($pay);
		$str0 .= number_format($pay);
		if (!$yuge_flag) {
			$total_pay1 += $pay;
		}
		$str0 .= "</td>";
		
		$payadj_detail    = array_filter($payadj_list, function($item){global $teacher; return $item['employee_type']==TEACHER && $item['employee_no']==$teacher['no'];} );
		$payadj           = array_sum(array_column(array_filter($payadj_detail, function($item){return $item['tax_flag']==1;}), 'price'));
		$payadj1          = array_sum(array_column(array_filter($payadj_detail, function($item){global $lesson_id; return $item['tax_flag']==1 && $item['lesson_id']==$lesson_id;}), 'price'));
		$payadj_tax_free  = array_sum(array_column(array_filter($payadj_detail, function($item){return $item['tax_flag']==0;}), 'price'));
		$payadj_tax_free1 = array_sum(array_column(array_filter($payadj_detail, function($item){global $lesson_id; return $item['tax_flag']==0 && $item['lesson_id']==$lesson_id;}), 'price'));
		$teacher['payadj_detail']   = $payadj_detail;
		$teacher['payadj']          = $payadj;
		$teacher['payadj_tax_free'] = $payadj_tax_free;
		
		$tatekae_detail = array_filter($tatekae_list, function($item){global $teacher; return $item['employee_type']==TEACHER && $item['employee_no']==$teacher['no'];} );
		$tatekae_total  = array_sum(array_column($tatekae_detail, 'price'));
		$tatekae_total1 = array_sum(array_column(array_filter($tatekae_detail, function($item){global $lesson_id; return $item['lesson_id']==$lesson_id;}), 'price'));
		$teacher['tatekae_detail'] = $tatekae_detail;
		$teacher['tatekae_total']  = $tatekae_total;

		$str0 .= "<td align=\"right\">".number_format($tatekae_total1)."</td>";
		
		if (!$yuge_flag) {
			$total_tatekae_total_teacher += $tatekae_total1;
			foreach ($tatekae_detail as $item0)
				if ($item0['lesson_id']==$lesson_id)	$total_tatekae_detail_teacher[$lesson_id][$item0['name']] += $item0['price'];
		}

		$str0 .= "<td align=\"right\">".number_format($payadj1)."</td>";
		$str0 .= "<td align=\"right\">".number_format($payadj_tax_free1)."</td>";

		$pay += $payadj1;
		
		if (!$yuge_flag) {
			$total_pay_lesson[$lesson_id] += $pay;
			$total_payadj += $payadj1;
			$total_payadj_tax_free += $payadj_tax_free1;
		}
		
		$total_transport_cost = 0; $tax1 = 0; $tax2 = 0;
		$total_transport_cost_unlimit = 0;
		
		if (!$teacher_and_staff_list[$teacher['name']]) {
			$staff = array();
			$pay3 = 0;
			if ($teacher['transport_DOW']) {
				$transport_count = count($teacher["transport_days"]);
			} else {
				$stmt = $db->query("SELECT SUM(IFNULL(correct_cost, cost)) FROM tbl_transport_cost WHERE teacher_id='{$teacher['no']}' AND DATE_FORMAT(date,'%Y-%c')='$year-$month'");
				$ret = $stmt->fetch(PDO::FETCH_NUM);
				$total_transport_cost = $ret[0];
				$total_transport_cost += $teacher['transport_mcost'];
/*
				$total_transport_cost += $teacher['transport_mcost'];
				foreach ($teacher["transport_days"] as $date0) {
					$DOW = date_format(date_create(str_replace('/','-',$date0)),'w');
					$total_transport_cost += $teacher['transport_dcost1'][$DOW];
					$total_transport_cost_unlimit += $teacher['transport_dcost2'][$DOW];
				}
*/
			}
		} else {
			$staff = $teacher_and_staff_list[$teacher['name']]['staff'];
			$pay3 = $staff['pay0'];
			$transport_count = 0;
			if ($teacher['transport_DOW']) {
				$sql = 
					"SELECT CONCAT(event_year, '-', event_month, '-', event_day) FROM tbl_event_staff ".
					"WHERE event_year=$year AND event_month=$month AND staff_no={$staff['no']} ".
					"AND absent_flag=0 AND (DAYOFWEEK(FROM_UNIXTIME(event_start_timestamp))-1) IN ({$teacher['transport_DOW']})";
				$stmt = $db->query($sql);
				$ret = $stmt->fetchAll(PDO::FETCH_NUM);
				if ($ret) {
					if ($teacher['transport_days']) {
						$transport_count = count(array_unique(array_merge($teacher['transport_days'],array_column($ret,0))));
					} else {
						$transport_count = count(array_unique(array_column($ret,0)));
					}
				} else {
					if ($teacher['transport_days']) {
						$transport_count = count(array_unique($teacher['transport_days']));
					}
				}
			} else {
				$stmt = $db->query("SELECT SUM(IFNULL(correct_cost, cost)) FROM tbl_transport_cost WHERE teacher_id='{$teacher['no']}' AND DATE_FORMAT(date,'%Y-%c')='$year-$month'");
				$ret = $stmt->fetch(PDO::FETCH_NUM);
				$total_transport_cost = $ret[0];
				$total_transport_cost += $teacher['transport_mcost'];
/*
				$sql = 
					"SELECT CONCAT(event_year, '-', event_month, '-', event_day) FROM tbl_event_staff ".
					"WHERE event_year=$year AND event_month=$month AND staff_no={$staff['no']} ".
					"AND absent_flag=0 ";
				$stmt = $db->query($sql);
				$ret = $stmt->fetchAll(PDO::FETCH_NUM);
				$total_transport_cost += $teacher['transport_mcost'];
				foreach (array_unique(array_merge($teacher['transport_days'],array_column($ret,0))) as $date0) {
					$DOW = date_format(date_create(str_replace('/','-',$date0)),'w');
					$total_transport_cost += $teacher['transport_dcost1'][$DOW];
					$total_transport_cost_unlimit += $teacher['transport_dcost2'][$DOW];
				}
*/
			}
		}

		$total_pay = $pay1 + $pay2 + $payadj + $payadj_tax_free + $tatekae_total;
		$total_transport_cost_limit = floor( ($total_pay + $pay3 + $staff['payadj'] + $staff['payadj_tax_free'] + $staff['tatekae_total']) * 0.1 );
		if ($teacher['transport_DOW']) $total_transport_cost = $teacher['transport_cost'] * $transport_count;
		if ($teacher['transport_limit'] && $total_transport_cost > $total_transport_cost_limit) {
			$total_transport_cost = $total_transport_cost_limit;
		}
		$total_transport_cost += $total_transport_cost_unlimit;
		
		
		$teacher['total_transport_cost'] = $total_transport_cost;
		$divide_transport_cost = 0;
		$stmt = $db->query("SELECT status FROM tbl_transport_status WHERE teacher_id='{$teacher['no']}' AND year='$year' AND month='$month'");
		$ret = $stmt->fetch(PDO::FETCH_NUM);
		$teacher['total_transport_status'] = $ret[0];
		if ($ret[0] == 2) {
			if ($total_transport_cost > 0 || $teacher['transport_zero']) {
				$divide_transport_cost = prop_divide($key1, $total_transport_cost, $pay1, $pay2, $pay3, $teacher, $staff);
				$str0 .= "<td align=\"right\">".number_format($divide_transport_cost)."</td>";
			} else {
//				$str0 .= "<td><font color=\"red\">未登録</font></td>";
//				$errFlag = 1;
			}
		} else {
			$str0 .= "<td><font color=\"red\">未確定</font></td>";
			$errFlag = 1;
		}
		
		$pay += $divide_transport_cost + $payadj_tax_free1 + $tatekae_total1;
		$str0 .= "<td align=\"right\">".number_format($pay)."</td>";
		
		if (!$yuge_flag) {
			$total_transport_cost_lesson[$lesson_id] += $divide_transport_cost + $payadj_tax_free1;
			$total_transport_cost_sum1 += $divide_transport_cost;
			$total_pay_sum1 += $pay;
		} else {
			$yuge_transport_cost = $divide_transport_cost + $payadj_tax_free1;
			$yuge_pay = $pay;
		}
		
		if (!($str1 && $key1!=0)) {
			if (!$teacher_and_staff_list[$teacher['name']]) {
				$tax1 = gennsenn_choushuu_gaku( $pay1 + $pay2 + $pay3 + $payadj, $teacher['gennsenn_choushuu_shubetu'], $teacher['huyou_ninnzuu'] );
				if ($tax1!==false) {
//					$str0 .= "<td $str1>".$teacher['gennsenn_choushuu_shubetu']."</td>";
					$str0 .= "<td align=\"right\" $str1>".number_format($tax1)."</td>";
					if (!$yuge_flag) $total_tax1_teacher += $tax1;
					if ($teacher['gennsenn_choushuu_shubetu']=='甲') {
						$tax2 = ($month==5)? $teacher['jyuuminnzei1']: $teacher['jyuuminnzei2'];
					} else {
						$tax2 = 0;
					}
					if (!$yuge_flag) $total_tax2_teacher += $tax2;
					$str0 .= "<td align=\"right\" $str1>".number_format($tax2)."</td>";
					$str0 .= "<td align=\"right\" $str1>".number_format($tax1+$tax2)."</td>";
				} else {
					$tax1 = 0;
					$tax2 = 0;
					$str0 .= "<td colspan=\"3\" $str1><font color=\"red\">未登録</font></td>";
					$errFlag = 1;
				}

				$total_pay += prop_divide(0, $total_transport_cost, $pay1, $pay2, $pay3, $teacher, $staff);
				$total_pay += prop_divide(1, $total_transport_cost, $pay1, $pay2, $pay3, $teacher, $staff);
				$teacher['net_payments'] = $total_pay - $tax1 - $tax2;
				$str0 .= "<td align=\"right\" $str1>".number_format($teacher['net_payments'])."</td>";
				
				if (!$yuge_flag) {
					$tax1_sum1 += $tax1;
					$tax2_sum1 += $tax2;
					$tax12_sum1 += $tax1+$tax2;
					$net_payments_sum1 += $teacher['net_payments'];
				} else {
					$yuge_tax1 = $tax1;
					$yuge_tax2 = $tax2;
					$yuge_tax12 = $tax1+$tax2;
					$yuge_net_pay = $teacher['net_payments'];
				}
				
				if ($pdf_mode) {
					$fp = fopen ("../../sakura-teacher/pay_pdf/pay-$year-$month-t{$teacher['no']}.html", "w");
					if (!$fp){ throw new Exception('一時ファイルオープンエラー'); }
					if (!flock($fp, LOCK_EX)){ throw new Exception('一時ファイルオープンエラー'); }

					$htmlout = "<h3>{$year}年{$month}月</h3>";
					$htmlout .= "<h3>{$teacher['name']}</h3>";
					$htmlout .= "<table border=\"1\">";
					$htmlout .= "<tr><th width=\"200\" colspan=\"3\">給料明細</th></tr>\n";
					$htmlout .= "<tr><th width=\"150\" colspan=\"2\">出勤日数</th><td width=\"50\" align=\"right\">"  .count($teacher["working_days"])         ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"150\" colspan=\"2\">勤務時間</th><td width=\"50\" align=\"right\">$working_hours</td></tr>\n";
					$htmlout .= "<tr><th width=\"30\" rowspan=\"".(($payadj?5:4)+($payadj_tax_free?1:0))."\">支給</th>".
														"<th width=\"120\">時間給合計(課税)</th><td width=\"50\" align=\"right\">"    .number_format($teacher['pay0'])        ."</td></tr>\n";
					if ($payadj)
						$htmlout .= "<tr><th width=\"120\">給料調整(課税)</th><td width=\"50\" align=\"right\">"      .number_format($payadj)                 ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"120\">交通費(非課税)</th><td width=\"50\" align=\"right\">"        .number_format($total_transport_cost)   ."</td></tr>\n";
					if ($payadj_tax_free)
						$htmlout .= "<tr><th width=\"120\">給料調整(非課税)</th><td width=\"50\" align=\"right\">"  .number_format($payadj_tax_free)        ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"120\">立替経費(非課税)</th><td width=\"50\" align=\"right\">"      .number_format($tatekae_total)          ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"120\">支給額合計</th><td width=\"50\" align=\"right\">"              .number_format($pay)                    ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"30\" rowspan=\"3\">控除</th><th width=\"120\">源泉徴収税</th><td width=\"50\" align=\"right\">"  .number_format($tax1)    ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"120\">住民税</th><td width=\"50\" align=\"right\">"                  .number_format($tax2)                   ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"120\">控除額合計</th><td width=\"50\" align=\"right\">"              .number_format($tax1+$tax2)             ."</td></tr>\n";
					$htmlout .= "<tr><th width=\"150\" colspan=\"2\">差引支給額</th><td width=\"50\" align=\"right\">".number_format($teacher['net_payments'])."</td></tr>\n";
					$htmlout .= "</table><br><br>";

					$htmlout .= "<table border=\"1\">";
					$htmlout .= "<tr><th width=\"150\" colspan=\"3\">時間給合計内訳</th></tr>\n";
					$htmlout .= "<tr><th width=\"50\">時給単価</th><th width=\"50\">勤務時間</th><th width=\"50\">金額</th></tr>\n";
					$wage_worktime1_list = array();
					foreach ($teacher["wage_worktime1"][$teacher["lesson_id"]]  as $key=>$val) $wage_worktime1_list[$key] += $val;
					foreach ($teacher["wage_worktime1"][$teacher["lesson_id2"]] as $key=>$val) $wage_worktime1_list[$key] += $val;
					foreach ($wage_worktime1_list as $key=>$val)
						$htmlout .= "<tr><td width=\"50\" align=\"right\">".number_format($key)."</td><td width=\"50\" align=\"right\">".($val).
													"</td><td width=\"50\" align=\"right\">".number_format(floor($key*$val))."</td></tr>\n";
					$htmlout .= "</table><br><br>";
					if ($payadj) {
						$payadj_detail0 = array_filter($teacher['payadj_detail'], function($item){return $item['tax_flag']==1;});
						$htmlout .= "<table border=\"1\">";
						$htmlout .= "<tr><th width=\"350\" colspan=\"3\">給料調整(課税)内訳</th></tr>\n";
						$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
						foreach ($payadj_detail0 as $item0) {
							$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
						}
						$htmlout .= "</table><br><br>";
					}
					if ($payadj_tax_free) {
						$payadj_detail0 = array_filter($teacher['payadj_detail'], function($item){return $item['tax_flag']==0;});
						$htmlout .= "<table border=\"1\">";
						$htmlout .= "<tr><th width=\"350\" colspan=\"3\">給料調整(非課税)内訳</th></tr>\n";
						$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
						foreach ($payadj_detail0 as $item0) {
							$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
						}
						$htmlout .= "</table><br><br>";
					}
					if ($teacher['tatekae_detail']) {
						$htmlout .= "<table border=\"1\">";
						$htmlout .= "<tr><th width=\"350\" colspan=\"3\">立替経費内訳</th></tr>\n";
						$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
						foreach ($teacher['tatekae_detail'] as $item0) {
							$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
						}
						$htmlout .= "</table><br><br>";
					}

					fwrite($fp,$htmlout);
					fclose($fp);
				}
				
			} else {
				$str0 .= "<td colspan=\"4\" $str1>兼任表参照</td>";
			}
		}
		if (!$pdf_mode)	{
			$str0 .= "<td align=\"right\" name=\"absent_detail\" style=\"display:none;\">{$teacher['absent1']}</td>";
			$str0 .= "<td align=\"right\" name=\"absent_detail\" style=\"display:none;\">{$teacher['absent2']}</td>";
			$str0 .= "<td align=\"left\"  name=\"absent_detail\" style=\"display:none;\">";
			foreach ($teacher["absent_group"]["absent1"] as $absent) {
				$str0 .= $absent["date"].":".$absent["name"]."<br>";
			}
			$str0 .= "</td>";
			$str0 .= "<td align=\"left\" name=\"absent_detail\" style=\"display:none;\">";
			foreach ($teacher["absent_group"]["absent2"] as $absent) {
				$str0 .= $absent["date"].":".$absent["name"]."<br>";
			}
			$str0 .= "</td>";
			$str0 .= "<td align=\"left\" name=\"absent_detail\" style=\"display:none;\">";
			foreach ($teacher["interview"] as $interview) {
				$str0 .= $interview["date"].":".$interview["name"]."<br>";
			}
			$str0 .= "</td>";
		}
		$str0 .= "</tr>\n";
		if ($teacher_and_staff_list[$teacher['name']]) { $teacher_and_staff_list[$teacher['name']]['teacher'] = $teacher; }
		if (!$yuge_flag) echo $str0; else $yuge_line = $str0;
	}
}
unset($teacher);

$total_payadj_teacher = $total_payadj;
$total_payadj_tax_free_teacher = $total_payadj_tax_free;

?>
<tr>
<td>合計</td><td></td><td align="right"><?=$total_working_days?></td><td align="right"><?=(floor($total_working_hours*100)/100)?></td>
<?php if (!$pdf_mode) { ?>
<td name="worktime_detail" style="display:none;"></td>
<td name="worktime_detail" style="display:none;"></td>
<!-- <td name="worktime_detail" style="display:none;"></td> -->
<?php } ?>
<td align="right"><?=number_format($total_pay1)?></td><td align="right"><?=number_format($total_tatekae_total_teacher)?></td>
<td align="right"><?=number_format($total_payadj)?></td><td align="right"><?=number_format($total_payadj_tax_free)?></td>
<td align="right"><?= number_format($total_transport_cost_sum1) ?></td><td align="right"><?= number_format($total_pay_sum1) ?></td>
<td align="right"><?= number_format($tax1_sum1) ?></td><td align="right"><?= number_format($tax2_sum1) ?></td><td align="right"><?= number_format($tax12_sum1) ?></td>
<td align="right"><?= number_format($net_payments_sum1) ?></td>
<?php if (!$pdf_mode) { ?>
<td name="absent_detail" style="display:none;"></td>
<td name="absent_detail" style="display:none;"></td>
<td name="absent_detail" style="display:none;"></td>
<td name="absent_detail" style="display:none;"></td>
<td name="absent_detail" style="display:none;"></td>
<?php } ?>
</tr>
</table>

<br><br>
<h3>事務員</h3>
<table border="1">
<tr>
<th>名前</th><th>出勤日数</th><th>勤務時間</th><th>時給</th><th>時給×勤務時間</th><th>立替経費</th>
<th>給料調整<br>(課税)</th><th>給料調整<br>(非課税)</th><th>交通費</th><th>支給額合計</th>
<th>源泉徴収税</th><th>住民税</th><th>控除額合計</th><th>差引支給額</th>
</tr>
<?php

$total_working_days=0; $total_working_hours=0; $total_wage=0; $total_pay1=0; $total_payadj=0; $total_payadj_tax_free=0;

foreach ($staff_list as &$staff) {
	
	echo "<tr>";
	echo "<td>{$staff['name']}</td>";

	$work_days = $staff['work_days'];
	echo "<td align=\"right\">{$work_days}</td>";
	$total_working_days += $work_days;
	
	$work_times = $staff['work_times'] + 0;
	echo "<td align=\"right\">".(floor($work_times*100)/100)."</td>";
	$total_working_hours += $work_times;

	$wages = $staff['wages'];
	echo "<td align=\"right\">".number_format($wages[0]['hourly_wage'])."</td>";
	$total_wage += $wages[0]['hourly_wage'];
	
	$pay = $staff['pay0'];
	echo "<td align=\"right\">".number_format($pay)."</td>";
	$total_pay1 += $pay;

	$tatekae_total = $staff['tatekae_total'];
	$tatekae_detail = $staff['tatekae_detail'];
	echo "<td align=\"right\">".number_format($tatekae_total)."</td>";
	$total_tatekae_total_staff += $tatekae_total;
	foreach ($tatekae_detail as $item0)
		$total_tatekae_detail_staff[$item0['name']] += $item0['price'];

	$payadj = $staff['payadj'];
	$pay += $payadj;
	echo "<td align=\"right\">".number_format($payadj)."</td>";
	$total_payadj += $payadj;

	$total_pay_staff += $pay;

	$payadj_tax_free = $staff['payadj_tax_free'];
	echo "<td align=\"right\">".number_format($payadj_tax_free)."</td>";
	$total_payadj_tax_free += $payadj_tax_free;
	
	$total_transport_cost = 0; $tax1 = 0; $tax2 = 0;
	$teacher = $teacher_and_staff_list[$staff['name']]['teacher'];

	if (!$tmp_teacher_list[$teacher['no']]) {
		if ($teacher) {
			// 講師事務兼任でその月に講師業務がない場合
			$total_transport_cost = 0; $tax1 = 0; $tax2 = 0;
			$total_transport_cost_unlimit = 0;
			
			if ($teacher['transport_DOW']) {
				$sql = 
					"SELECT count(DISTINCT event_day) FROM tbl_event_staff ".
					"WHERE event_year=$year AND event_month=$month AND staff_no={$staff['no']} ".
					"AND absent_flag=0 AND (DAYOFWEEK(FROM_UNIXTIME(event_start_timestamp))-1) IN ({$teacher['transport_DOW']})";
				$stmt = $db->query($sql);
				$transport_count = ($stmt->fetch(PDO::FETCH_NUM))[0];
				$total_transport_cost = $teacher['transport_cost'] * $transport_count;
			} else {
				$sql = 
					"SELECT DISTINCT event_day FROM tbl_event_staff ".
					"WHERE event_year=$year AND event_month=$month AND staff_no={$staff['no']} ".
					"AND absent_flag=0 ";
				$stmt = $db->query($sql);
				$rslt = $stmt->fetchAll(PDO::FETCH_NUM);
				foreach ($rslt as $date0) {
					$DOW = date_format(date_create($year.'-'.$month.'-'.$date0),'w');
					$total_transport_cost += $teacher['transport_dcost1'][$DOW];
					$total_transport_cost_unlimit += $teacher['transport_dcost2'][$DOW];
				}
				$total_transport_cost += $teacher['transport_mcost'];
			}
			$total_transport_cost_limit = floor( ($pay + $payadj_tax_free + $tatekae_total) * 0.1 );
			if ($teacher['transport_limit'] && $total_transport_cost > $total_transport_cost_limit) {
				$total_transport_cost = $total_transport_cost_limit;
			}
			$total_transport_cost += $total_transport_cost_unlimit;
			$teacher_and_staff_list[$staff['name']]['teacher']['total_transport_cost'] = $total_transport_cost;
			if ($total_transport_cost > 0 || $teacher['transport_zero']) {
				echo "<td align=\"right\">".number_format($total_transport_cost)."</td>";
			} else {
				echo "<td><font color=\"red\">未登録</font></td>";
				$errFlag = 1;
			}
			
			$total_pay = $pay + $payadj_tax_free + $total_transport_cost + $tatekae_total;
			echo "<td align=\"right\">".number_format($total_pay)."</td>";
				
			echo "<td colspan=\"5\">兼任表参照</td>";
		} else {
			// 事務専任
			if ($staff['transport_DOW']) {
				$sql = 
					"SELECT count(DISTINCT event_day) FROM tbl_event_staff ".
					"WHERE event_year=$year AND event_month=$month AND staff_no={$staff['no']} ".
					"AND absent_flag=0 AND (DAYOFWEEK(FROM_UNIXTIME(event_start_timestamp))-1) IN ({$staff['transport_DOW']})";
				$stmt = $db->query($sql);
				$transport_count = ($stmt->fetch(PDO::FETCH_NUM))[0];
			} else {
				$transport_count = 0;
			}
			
			$total_transport_cost_limit = floor( ($pay + $payadj_tax_free + $tatekae_total)*0.1 );
			$total_transport_cost = $staff['transport_cost'] * $transport_count;
			if ($staff['transport_limit'] && $total_transport_cost > $total_transport_cost_limit) {
				$total_transport_cost = $total_transport_cost_limit;
			}
			if ($total_transport_cost > 0 || $staff['transport_zero']) {
				echo "<td align=\"right\">".number_format($total_transport_cost)."</td>";
			} else {
				echo "<td><font color=\"red\">未登録</font></td>";
				$errFlag = 1;
			}
			
			$total_pay = $pay + $payadj_tax_free + $total_transport_cost + $tatekae_total;
			echo "<td align=\"right\">".number_format($total_pay)."</td>";
		
			$tax1 = gennsenn_choushuu_gaku( $pay, $staff['gennsenn_choushuu_shubetu'], $staff['huyou_ninnzuu'] );
			if ($tax1!==false) {
//				echo "<td>".$staff['gennsenn_choushuu_shubetu']."</td>";
				echo "<td align=\"right\">".number_format($tax1)."</td>";
				if ($staff['gennsenn_choushuu_shubetu']=='甲') {
					$tax2 = ($month==5)? $staff['jyuuminnzei1']: $staff['jyuuminnzei2'];
				} else {
					$tax2 = 0;
				}
				echo "<td align=\"right\">".number_format($tax2)."</td>";
				echo "<td align=\"right\">".number_format($tax1+$tax2)."</td>";
			} else {
				$tax1 = 0;
				$tax2 = 0;
				echo "<td colspan=\"3\"><font color=\"red\">未登録</font></td>";
				$errFlag = 1;
			}

			$staff['net_payments'] = $total_pay-$tax1-$tax2;
			echo "<td align=\"right\">".number_format($staff['net_payments'])."</td>";

			if ($pdf_mode) {
				$fp = fopen ("../../sakura-teacher/pay_pdf/pay-$year-$month-s{$staff['no']}.html", "w");
				if (!$fp){ throw new Exception('一時ファイルオープンエラー'); }
				if (!flock($fp, LOCK_EX)){ throw new Exception('一時ファイルオープンエラー'); }

				$htmlout = "<h3>{$year}年{$month}月</h3>";
				$htmlout .= "<h3>{$staff['name']}</h3>";
				$htmlout .= "<table border=\"1\">";
				$htmlout .= "<tr><th width=\"200\" colspan=\"3\">給料明細</th></tr>\n";
				$htmlout .= "<tr><th width=\"150\" colspan=\"2\">出勤日数</th><td width=\"50\" align=\"right\">"  .$work_days         ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"150\" colspan=\"2\">勤務時間</th><td width=\"50\" align=\"right\">".(floor($work_times*100)/100)."</td></tr>\n";
				$htmlout .= "<tr><th width=\"30\" rowspan=\"".(($payadj?5:4)+($payadj_tax_free?1:0))."\">支給</th>".
													"<th width=\"120\">時間給合計(課税)</th><td width=\"50\" align=\"right\">"    .number_format($staff['pay0'])        ."</td></tr>\n";
				if ($payadj)
					$htmlout .= "<tr><th width=\"120\">給料調整(課税)</th><td width=\"50\" align=\"right\">"      .number_format($payadj)                 ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"120\">交通費(非課税)</th><td width=\"50\" align=\"right\">"        .number_format($total_transport_cost)   ."</td></tr>\n";
				if ($payadj_tax_free)
					$htmlout .= "<tr><th width=\"120\">給料調整(非課税)</th><td width=\"50\" align=\"right\">"  .number_format($payadj_tax_free)        ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"120\">立替経費(非課税)</th><td width=\"50\" align=\"right\">"      .number_format($tatekae_total)          ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"120\">支給額合計</th><td width=\"50\" align=\"right\">"              .number_format($total_pay)                    ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"30\" rowspan=\"3\">控除</th><th width=\"120\">源泉徴収税</th><td width=\"50\" align=\"right\">"  .number_format($tax1)    ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"120\">住民税</th><td width=\"50\" align=\"right\">"                  .number_format($tax2)                   ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"120\">控除額合計</th><td width=\"50\" align=\"right\">"              .number_format($tax1+$tax2)             ."</td></tr>\n";
				$htmlout .= "<tr><th width=\"150\" colspan=\"2\">差引支給額</th><td width=\"50\" align=\"right\">".number_format($staff['net_payments'])."</td></tr>\n";
				$htmlout .= "</table><br><br>";

				$htmlout .= "<table border=\"1\">";
				$htmlout .= "<tr><th width=\"150\" colspan=\"3\">時間給合計内訳</th></tr>\n";
				$htmlout .= "<tr><th width=\"50\">時給単価</th><th width=\"50\">勤務時間</th><th width=\"50\">金額</th></tr>\n";
				$htmlout .= "<tr><td width=\"50\" align=\"right\">".number_format($wages[0]['hourly_wage'])."</td><td width=\"50\" align=\"right\">".($work_times).
											"</td><td width=\"50\" align=\"right\">".number_format($staff['pay0'])."</td></tr>\n";
				$htmlout .= "</table><br><br>";
				if ($payadj) {
					$payadj_detail0 = array_filter($staff['payadj_detail'], function($item){return $item['tax_flag']==1;});
					$htmlout .= "<table border=\"1\">";
					$htmlout .= "<tr><th width=\"350\" colspan=\"3\">給料調整(課税)内訳</th></tr>\n";
					$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
					foreach ($payadj_detail0 as $item0) {
						$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
					}
					$htmlout .= "</table><br><br>";
				}
				if ($payadj_tax_free) {
					$payadj_detail0 = array_filter($staff['payadj_detail'], function($item){return $item['tax_flag']==0;});
					$htmlout .= "<table border=\"1\">";
					$htmlout .= "<tr><th width=\"350\" colspan=\"3\">給料調整(非課税)内訳</th></tr>\n";
					$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
					foreach ($payadj_detail0 as $item0) {
						$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
					}
					$htmlout .= "</table><br><br>";
				}
				if ($tatekae_total) {
					$htmlout .= "<table border=\"1\">";
					$htmlout .= "<tr><th width=\"350\" colspan=\"3\">立替経費内訳</th></tr>\n";
					$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
					foreach ($staff['tatekae_detail'] as $item0) {
						$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
					}
					$htmlout .= "</table><br><br>";
				}

				fwrite($fp,$htmlout);
				fclose($fp);
			}
		}
		$total_transport_cost_sum2 += $total_transport_cost;
		$tax1_sum2 += $tax1;
		$tax2_sum2 += $tax2;
		$tax12_sum2 += $tax1+$tax2;
		$net_payments_sum2 += $staff['net_payments'];
		$total_pay_sum2 += $total_pay;
		
		$total_transport_cost_staff += $total_transport_cost + $payadj_tax_free;
		$total_tax1_staff += $tax1;
		$total_tax2_staff += $tax2;
		
	} else {
		$divide_transport_cost = 0;
		$total_transport_cost = $teacher['total_transport_cost'];
		if ($teacher['total_transport_status'] == 2) {
			if ($total_transport_cost > 0 || $teacher['transport_zero']) {
				$divide_transport_cost = prop_divide(2, $total_transport_cost, $teacher['pay0'], 0, $staff['pay0'], $teacher, $staff);
				echo "<td align=\"right\">".number_format($divide_transport_cost)."</td>";
			} else {
//				echo "<td><font color=\"red\">未登録</font></td>";
//				$errFlag = 1;
			}
		} else {
			echo "<td><font color=\"red\">未確定</font></td>";
			$errFlag = 1;
		}
		$total_transport_cost_sum2 += $divide_transport_cost;

		$total_transport_cost_staff += $divide_transport_cost + $payadj_tax_free;
	
		$total_pay = $pay + $payadj_tax_free + $divide_transport_cost + $tatekae_total;
		echo "<td align=\"right\">".number_format($total_pay)."</td>";
		$total_pay_sum2 += $total_pay;

		echo "<td colspan=\"4\">兼任表参照</td>";
	}

	echo "</tr>\n";
	if ($teacher_and_staff_list[$staff['name']]) { $teacher_and_staff_list[$staff['name']]['staff'] = $staff; }
}
unset($staff);

$total_payadj_staff = $total_payadj;
$total_payadj_tax_free_staff = $total_payadj_tax_free;

?>
<tr>
<td>合計</td><td align="right"><?=$total_working_days?></td><td align="right"><?=(floor($total_working_hours*100)/100)?></td><td align="right"><?=number_format($total_wage)?></td>
<td align="right"><?=number_format($total_pay1)?></td><td align="right"><?=number_format($total_tatekae_total_staff)?></td>
<td align="right"><?=number_format($total_payadj)?></td><td align="right"><?=number_format($total_payadj_tax_free)?></td>
<td align="right"><?= number_format($total_transport_cost_sum2) ?></td><td align="right"><?= number_format($total_pay_sum2) ?></td>
<td align="right"><?= number_format($tax1_sum2) ?></td><td align="right"><?= number_format($tax2_sum2) ?></td><td align="right"><?= number_format($tax12_sum2) ?></td>
<td align="right"><?= number_format($net_payments_sum2) ?></td>
</tr>
</table>
<br><br>

<h3>講師・事務員兼任</h3>
<table border="1">
<tr>
<th>名前</th><th>出勤日数</th><th>勤務時間</th><th>時給×勤務時間</th><th>立替経費</th>
<th>給料調整<br>(課税)</th><th>給料調整<br>(非課税)</th><th>交通費</th><th>支給額合計</th>
<th>源泉徴収税</th><th>住民税</th><th>控除額合計</th><th>差引支給額</th>
</tr>
<?php

$total_working_days=0; $total_working_hours=0; $total_wage=0; $total_pay1=0; $total_payadj=0; $total_payadj_tax_free=0;
$total_tatekae = 0;

foreach ($teacher_and_staff_list as $key_name=>&$teacher_and_staff) {
	
	$teacher = $teacher_and_staff['teacher'];
	$staff   = $teacher_and_staff['staff'];
	
	$work_times = array_sum($teacher['working'])+$staff['work_times'];
	if (!$work_times) { continue; }

	echo "<tr>";
	echo "<td>{$teacher['name']}</td>";

	$sql = 
		"SELECT count(DISTINCT t.event_day) FROM tbl_event AS t ".
		"LEFT OUTER JOIN tbl_event_staff as s ON t.event_day=s.event_day ".
		"WHERE t.event_year=$year AND t.event_month=$month AND t.teacher_id={$teacher['no']} AND t.absent_flag=0 ".
		"AND   s.event_year=$year AND s.event_month=$month AND s.staff_no={$staff['no']} AND s.absent_flag=0 ";
	$stmt = $db->query($sql);
	$ret = ($stmt->fetch(PDO::FETCH_NUM))[0];
	$work_days = count($teacher['working_days'])+$staff['work_days']-$ret;
	echo "<td align=\"right\">{$work_days}</td>";
	$total_working_days += $work_days;
	
	echo "<td align=\"right\">".(floor($work_times*100)/100)."</td>";
	$total_working_hours += $work_times;
	
	$pay = $teacher['pay0']+$staff['pay0'];
	echo "<td align=\"right\">".number_format($pay)."</td>";
	$total_pay1 += $pay;
	
	$tatekae_total = $teacher['tatekae_total']+$staff['tatekae_total'];
	echo "<td align=\"right\">".number_format($tatekae_total)."</td>";
	$total_tatekae += $tatekae_total;

	$payadj = $teacher['payadj']+$staff['payadj'];
	$pay += $payadj;
	echo "<td align=\"right\">".number_format($payadj)."</td>";
	$total_payadj += $payadj;

	$payadj_tax_free = $teacher['payadj_tax_free']+$staff['payadj_tax_free'];
	echo "<td align=\"right\">".number_format($payadj_tax_free)."</td>";
	$total_payadj_tax_free += $payadj_tax_free;
	
	$total_transport_cost = $teacher['total_transport_cost'];
	if ($teacher['total_transport_status'] == 2) {
		if ($total_transport_cost > 0 || $teacher['transport_zero']) {
			echo "<td align=\"right\">".number_format($total_transport_cost)."</td>";
		} else {
//			echo "<td><font color=\"red\">未登録</font></td>";
//			$errFlag = 1;
		}
	} else {
		echo "<td><font color=\"red\">未確定</font></td>";
		$errFlag = 1;
	}
	
	$total_pay = $pay + $total_transport_cost + $payadj_tax_free + $tatekae_total;
	echo "<td align=\"right\">".number_format($total_pay)."</td>";

	$tax1 = gennsenn_choushuu_gaku( $pay, $teacher['gennsenn_choushuu_shubetu'], $teacher['huyou_ninnzuu'] );
	if ($tax1!==false) {
//		echo "<td>".$teacher['gennsenn_choushuu_shubetu']."</td>";
		echo "<td align=\"right\">".number_format($tax1)."</td>";
		if ($teacher['gennsenn_choushuu_shubetu']=='甲') {
			$tax2 = ($month==5)? $teacher['jyuuminnzei1']: $teacher['jyuuminnzei2'];
		} else {
			$tax2 = 0;
		}
		echo "<td align=\"right\">".number_format($tax2)."</td>";
		echo "<td align=\"right\">".number_format($tax1+$tax2)."</td>";
	} else {
		$tax1 = 0;
		$tax2 = 0;
		echo "<td colspan=\"3\"><font color=\"red\">未登録</font></td>";
			$errFlag = 1;
	}

	$teacher_and_staff['net_payments'] = $total_pay-$tax1-$tax2;
	echo "<td align=\"right\">".number_format($teacher_and_staff['net_payments'])."</td>";
	$total_transport_cost_sum3 += $total_transport_cost;
	$tax1_sum3 += $tax1;
	$tax2_sum3 += $tax2;
	$tax12_sum3 += $tax1+$tax2;
	$net_payments_sum3 += $teacher_and_staff['net_payments'];
	$total_pay_sum3 += $total_pay;

	if ($teacher['pay0'] >= $staff['pay0']) {
		$total_tax1_teacher += $tax1;
		$total_tax2_teacher += $tax2;
	} else {
		$total_tax1_staff += $tax1;
		$total_tax2_staff += $tax2;
	}

	if ($pdf_mode) {
		$fp = fopen ("../../sakura-teacher/pay_pdf/pay-$year-$month-t{$teacher['no']}.html", "w");
		if (!$fp){ throw new Exception('一時ファイルオープンエラー'); }
		if (!flock($fp, LOCK_EX)){ throw new Exception('一時ファイルオープンエラー'); }

		$payadj_detail = array_merge($teacher['payadj_detail'], $staff['payadj_detail']);
		$tatekae_detail = array_merge($teacher['tatekae_detail'], $staff['tatekae_detail']);

		$htmlout = "<h3>{$year}年{$month}月</h3>";
		$htmlout .= "<h3>{$teacher['name']}</h3>";
		$htmlout .= "<table border=\"1\">";
		$htmlout .= "<tr><th width=\"200\" colspan=\"3\">給料明細</th></tr>\n";
		$htmlout .= "<tr><th width=\"150\" colspan=\"2\">出勤日数</th><td width=\"50\" align=\"right\">"  .$work_days         ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"150\" colspan=\"2\">勤務時間</th><td width=\"50\" align=\"right\">".(floor($work_times*100)/100)."</td></tr>\n";
		$htmlout .= "<tr><th width=\"30\" rowspan=\"".(($payadj?5:4)+($payadj_tax_free?1:0))."\">支給</th>".
											"<th width=\"120\">時間給合計(課税)</th><td width=\"50\" align=\"right\">"    .number_format($teacher['pay0']+$staff['pay0'])."</td></tr>\n";
		if ($payadj)
			$htmlout .= "<tr><th width=\"120\">給料調整(課税)</th><td width=\"50\" align=\"right\">"      .number_format($payadj)                 ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"120\">交通費(非課税)</th><td width=\"50\" align=\"right\">"        .number_format($total_transport_cost)   ."</td></tr>\n";
		if ($payadj_tax_free)
			$htmlout .= "<tr><th width=\"120\">給料調整(非課税)</th><td width=\"50\" align=\"right\">"  .number_format($payadj_tax_free)        ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"120\">立替経費(非課税)</th><td width=\"50\" align=\"right\">"      .number_format($tatekae_total)          ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"120\">支給額合計</th><td width=\"50\" align=\"right\">"              .number_format($total_pay)              ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"30\" rowspan=\"3\">控除</th><th width=\"120\">源泉徴収税</th><td width=\"50\" align=\"right\">"  .number_format($tax1)    ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"120\">住民税</th><td width=\"50\" align=\"right\">"                  .number_format($tax2)                   ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"120\">控除額合計</th><td width=\"50\" align=\"right\">"              .number_format($tax1+$tax2)             ."</td></tr>\n";
		$htmlout .= "<tr><th width=\"150\" colspan=\"2\">差引支給額</th><td width=\"50\" align=\"right\">".number_format($teacher_and_staff['net_payments'])."</td></tr>\n";
		$htmlout .= "</table><br><br>";

		$htmlout .= "<table border=\"1\">";
		$htmlout .= "<tr><th width=\"150\" colspan=\"3\">時間給合計内訳</th></tr>\n";
		$htmlout .= "<tr><th width=\"50\">時給単価</th><th width=\"50\">勤務時間</th><th width=\"50\">金額</th></tr>\n";
		$wage_worktime1_list = array();
		foreach ($teacher["wage_worktime1"][$teacher["lesson_id"]]  as $key=>$val) $wage_worktime1_list[$key] += $val;
		foreach ($teacher["wage_worktime1"][$teacher["lesson_id2"]] as $key=>$val) $wage_worktime1_list[$key] += $val;
		foreach ($staff["wage_worktime1"][$teacher["lesson_id2"]] as $key=>$val) $wage_worktime1_list[$key] += $val;
		foreach ($wage_worktime1_list as $key=>$val)
			$htmlout .= "<tr><td width=\"50\" align=\"right\">".number_format($key)."</td><td width=\"50\" align=\"right\">".($val).
										"</td><td width=\"50\" align=\"right\">".number_format(floor($key*$val))."</td></tr>\n";
		$htmlout .= "</table><br><br>";
		if ($payadj) {
			$payadj_detail0 = array_filter($payadj_detail, function($item){return $item['tax_flag']==1;});
			$htmlout .= "<table border=\"1\">";
			$htmlout .= "<tr><th width=\"350\" colspan=\"3\">給料調整(課税)内訳</th></tr>\n";
			$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
			foreach ($payadj_detail0 as $item0) {
				$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
			}
			$htmlout .= "</table><br><br>";
		}
		if ($payadj_tax_free) {
			$payadj_detail0 = array_filter($payadj_detail, function($item){return $item['tax_flag']==0;});
			$htmlout .= "<table border=\"1\">";
			$htmlout .= "<tr><th width=\"350\" colspan=\"3\">給料調整(非課税)内訳</th></tr>\n";
			$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
			foreach ($payadj_detail0 as $item0) {
				$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
			}
			$htmlout .= "</table><br><br>";
		}
		if ($tatekae_total) {
			$htmlout .= "<table border=\"1\">";
			$htmlout .= "<tr><th width=\"350\" colspan=\"3\">立替経費内訳</th></tr>\n";
			$htmlout .= "<tr><th width=\"100\">項目</th><th width=\"50\">金額</th><th width=\"200\">備考</th></tr>\n";
			foreach ($teacher['tatekae_detail'] as $item0) {
				$htmlout .= "<tr><td width=\"100\">{$item0['name']}</td><td width=\"50\" align=\"right\">".number_format($item0['price'])."</td><td width=\"200\">{$lesson_list[$item0['lesson_id']]} {$item0['memo']}</td></tr>\n";
			}
			$htmlout .= "</table><br><br>";
		}

		fwrite($fp,$htmlout);
		fclose($fp);
	}

	echo "</tr>\n";
}
unset($teacher_and_staff);

?>
<tr>
<td>合計</td><td align="right"><?=$total_working_days?></td><td align="right"><?=(floor($total_working_hours*100)/100)?></td>
<td align="right"><?=number_format($total_pay1)?></td><td align="right"><?=number_format($total_tatekae)?></td>
<td align="right"><?=number_format($total_payadj)?></td><td align="right"><?=number_format($total_payadj_tax_free)?></td>
<td align="right"><?= number_format($total_transport_cost_sum3) ?></td><td align="right"><?= number_format($total_pay_sum3) ?></td>
<td align="right"><?= number_format($tax1_sum3) ?></td><td align="right"><?= number_format($tax2_sum3) ?></td><td align="right"><?= number_format($tax12_sum3) ?></td>
<td align="right"><?= number_format($net_payments_sum3) ?></td>
</tr>
</table>
<br><br>

<table>
<tr><th align="left">交通費合計</th><td align="right"><?= number_format($total_transport_cost_sum1+$total_transport_cost_sum2) ?></td></tr>
<tr><th align="left">支給額合計</th><td align="right"><?= number_format($total_pay_sum1+$total_pay_sum2) ?></td></tr>
<tr><th align="left">源泉徴収税合計</th><td align="right"><?= number_format($tax1_sum1+$tax1_sum2+$tax1_sum3) ?></td></tr>
<tr><th align="left">住民税合計</th><td align="right"><?= number_format($tax2_sum1+$tax2_sum2+$tax2_sum3) ?></td></tr>
<tr><th align="left">控除額合計</th><td align="right"><?= number_format($tax12_sum1+$tax12_sum2+$tax12_sum3) ?></td></tr>
<tr><th align="left">差引支給額合計</th><td align="right"><?= number_format($net_payments_sum1+$net_payments_sum2+$net_payments_sum3) ?></td></tr>
</table>

<?php
echo "<table style=\"color: red;\">";
$fp = fopen ("./tmp/".$rakuten_csvfname, "w");
if (!$fp){ throw new Exception('一時ファイルオープンエラー'); }
if (!flock($fp, LOCK_EX)){ throw new Exception('一時ファイルオープンエラー'); }
foreach ($tmp_teacher_list as $teacher) {
	if ($teacher_and_staff_list[$teacher['name']]) { continue; }
	if (!$teacher['net_payments']) { continue; }
	// 弓削先生スキップ
	if ($teacher['no']==1) { continue; }
	if (!$teacher['bank_no'] || !$teacher['bank_branch_no'] || !$teacher['bank_acount_type'] ||
			!$teacher['bank_acount_no'] || !$teacher['bank_acount_name']) {
				echo "<tr><td>{$teacher['name']} 先生　</td><td>口座情報未登録</td></tr>\n";
				continue;
			}
	$str =  $teacher['bank_no'].',';
	$str .= $teacher['bank_branch_no'].',';
	$str .= $teacher['bank_acount_type'].',';
	$str .= $teacher['bank_acount_no'].',';
	$str .= $teacher['bank_acount_name'].',';
	$str .= $teacher['net_payments'].',';
	$str .= $teacher['no']."\r\n";
	fwritesjis( $fp, $str );
}
foreach ($staff_list as $staff) {
	if ($teacher_and_staff_list[$staff['name']]) { continue; }
	if (!$staff['net_payments']) { continue; }
	if (!$staff['bank_no'] || !$staff['bank_branch_no'] || !$staff['bank_acount_type'] ||
			!$staff['bank_acount_no'] || !$staff['bank_acount_name']) {
				echo "<tr><td>{$staff['name']} さん　</td><td>口座情報未登録</td></tr>\n";
				continue;
			}
	$str =  $staff['bank_no'].',';
	$str .= $staff['bank_branch_no'].',';
	$str .= $staff['bank_acount_type'].',';
	$str .= $staff['bank_acount_no'].',';
	$str .= $staff['bank_acount_name'].',';
	$str .= $staff['net_payments'].',';
	$str .= $staff['no']."\r\n";
	fwritesjis( $fp, $str );
}
foreach ($teacher_and_staff_list as $teacher_and_staff) {
	$teacher = $teacher_and_staff['teacher'];
	if (!$teacher_and_staff['net_payments']) { continue; }
	// 弓削先生スキップ
	if ($teacher['no']==1) { continue; }
	if (!$teacher['bank_no'] || !$teacher['bank_branch_no'] || !$teacher['bank_acount_type'] ||
			!$teacher['bank_acount_no'] || !$teacher['bank_acount_name']) {
				echo "<tr><td>{$teacher['name']} 先生　</td><td>口座情報未登録</td></tr>\n";
				continue;
			}
	$str =  $teacher['bank_no'].',';
	$str .= $teacher['bank_branch_no'].',';
	$str .= $teacher['bank_acount_type'].',';
	$str .= $teacher['bank_acount_no'].',';
	$str .= $teacher['bank_acount_name'].',';
	$str .= $teacher_and_staff['net_payments'].',';
	$str .= $teacher['no']."\r\n";
	fwritesjis( $fp, $str );
}
fclose ($fp);
echo "</table>";

} catch (Exception $e) {
	echo $e->getMessage().'<br>';
}
?>

<?php if (!$pdf_mode) { ?>
<br>
<form name="rakuten_form">
振込実行日：
<input type="text" name="furikomi_month" size="2" maxlength="2" value="<?= $month2 ?>">月
<input type="text" name="furikomi_day"   size="2" maxlength="2" value="25">日&nbsp;&nbsp;
<input type="button" value="&nbsp;楽天銀行振込CSVファイルダウンロード&nbsp;" onclick="download1();">
</form>
<?php } ?>
<br><br>

<h3>弓削先生分</h3>
<table border="1">
<tr>
<th>先生名</th><th>教室</th><th>出勤日数</th><th>授業時間</th>
<?php if (!$pdf_mode) { ?>
<th name="worktime_detail" style="display:none;">時給別授業時間</th>
<th name="worktime_detail" style="display:none;">時給条件別授業時間</th>
<?php } ?>
<th>時給×授業時間</th><th>立替経費</th>
<th>給料調整<br>(課税)</th><th>給料調整<br>(非課税)</th><th>交通費</th><th>支給額合計</th>
<th>源泉徴収税</th><th>住民税</th><th>控除額合計</th><th>差引支給額</th>
<?php if (!$pdf_mode) { ?>
<th name="absent_detail" style="display:none;">休み１の時間</th><th name="absent_detail" style="display:none;">休み２の時間</th><th name="absent_detail" style="display:none;">グループの休み１</th><th name="absent_detail" style="display:none;">グループの休み２</th><th name="absent_detail" style="display:none;">面談</th>
<?php } ?>
</tr>
<?= $yuge_line ?>
</table>

<table>
<tr><th align="left">交通費合計</th><td align="right"><?= number_format($total_transport_cost_sum1+$total_transport_cost_sum2+$yuge_transport_cost) ?></td></tr>
<tr><th align="left">支給額合計</th><td align="right"><?= number_format($total_pay_sum1+$total_pay_sum2+$yuge_pay) ?></td></tr>
<tr><th align="left">源泉徴収税合計</th><td align="right"><?= number_format($tax1_sum1+$tax1_sum2+$tax1_sum3+$yuge_tax1) ?></td></tr>
<tr><th align="left">住民税合計</th><td align="right"><?= number_format($tax2_sum1+$tax2_sum2+$tax2_sum3+$yuge_tax2) ?></td></tr>
<tr><th align="left">控除額合計</th><td align="right"><?= number_format($tax12_sum1+$tax12_sum2+$tax12_sum3+$yuge_tax12) ?></td></tr>
<tr><th align="left">差引支給額合計</th><td align="right"><?= number_format($net_payments_sum1+$net_payments_sum2+$net_payments_sum3+$yuge_net_pay) ?></td></tr>
</table>

<?php
$output = ob_get_contents();
ob_end_clean(); 
?>

<table border="1" width="400">
<tr><th colspan="3">講師給料</th><th>金額</th></tr>
<?php	foreach ($lesson_list as $lesson_id0=>$item0) {
	$tatekae_count = count($total_tatekae_detail_teacher[$lesson_id0]);
?>
<tr><th rowspan="<?= $tatekae_count+2 ?>"><?= $lesson_list[$lesson_id0] ?></th><th colspan="2">給料</th><td align="right"><?=number_format($total_pay_lesson[$lesson_id0])?></td></tr>
<tr><th colspan="2">交通費</th><td align="right"><?=number_format($total_transport_cost_lesson[$lesson_id0])?></td></tr>
<?php
	if ($tatekae_count) {
		echo '<tr><th rowspan="'.$tatekae_count.'">立替経費</th>';
		$flag0 = 0;
		foreach ($total_tatekae_detail_teacher[$lesson_id0] as $name=>$price) {
			if ($flag0) echo '<tr>'; else $flag0 = 1;
			echo "<th>{$name}</th><td align=\"right\">{$price}</td></tr>\n";
		}
	}
}
?>
<tr><th colspan="3">源泉所得税</th><td align="right"><?=number_format($total_tax1_teacher)?></td></tr>
<tr><th colspan="3">住民税</th><td align="right"><?=number_format($total_tax2_teacher)?></td></tr>
<tr><th colspan="3">差引支給額</th><td align="right"><?=number_format($total_pay_sum1-$total_tax1_teacher-$total_tax2_teacher)?></td></tr>
</table>
<br><br>

<table border="1" width="400">
<tr><th colspan="2">事務員給料</th><th>金額</th></tr>
<tr><th colspan="2">給料賃金</th><td align="right"><?=number_format($total_pay_staff)?></td></tr>
<tr><th colspan="2">交通費</th><td align="right"><?=number_format($total_transport_cost_staff)?></td></tr>
<?php
$tatekae_count = count($total_tatekae_detail_staff);
if ($tatekae_count) {
	echo '<tr><th rowspan="'.$tatekae_count.'">立替経費</th>';
	$flag0 = 0;
	foreach ($total_tatekae_detail_staff as $name=>$price) {
		if ($flag0) echo '<tr>'; else $flag0 = 1;
		echo "<th>{$name}</th><td align=\"right\">{$price}</td></tr>\n";
	}
}
?>
<tr><th colspan="2">源泉所得税</th><td align="right"><?=number_format($total_tax1_staff)?></td></tr>
<tr><th colspan="2">住民税</th><td align="right"><?=number_format($total_tax2_staff)?></td></tr>
<tr><th colspan="2">差引支給額</th><td align="right"><?=number_format($total_pay_sum2-$total_tax1_staff-$total_tax2_staff)?></td></tr>
</table>
<br><br>

<?php
echo $output;
?>
<br><br>

<?php if (!$pdf_mode) { ?>
<form action="./check_list_fix.php" method="POST">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
<input type="submit" value="確定" onclick="if (<?= $errFlag ?>) { alert('エラーまたは未登録（赤字表示）があります。'); return false; } else return true;">
<table>
<tr><td>＊振込CSVファイルは、確定する前にダウンロードしてください。</td></tr>
<tr><td>＊確定後、この月の画面表示はPDF表示となり固定されます。</td></tr>
<tr><td>＊確定後、講師ログイン画面から各自の給与明細PDFを参照できるようになります。</td></tr>
</table>
</form>
<?php } ?>

<br><br>
</div>
</body>
</html>
