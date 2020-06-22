<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
require_once("./array_column.php");
if (!$_SESSION['ulogin']['teacher_id']) {
$result = check_user($db, "1");
}

$errFlag = 0;
$errArray = array();

$member_name = $_POST["member_name"];

$class_type = $_GET['class_type'];
if (!$class_type) $class_type = $_POST['class_type'];
if ($class_type=='sat_sun_class') {
	$date_list = $sat_sun_class_date_list;
	$date_list_string = $sat_sun_class_date_list_string;
}
if ($class_type == 'sat_sun_class') {
	$page_title = "土日講習";
	$year = $_POST['y'];
	$month = $_POST['m'];
	if (is_null($year) === true || empty($year) === true)   { $year = $_GET['y']; }
	if (is_null($month) === true || empty($month) === true) { $month = $_GET['m']; }
	if (is_null($year) === true || empty($year) === true)   { $year = date("Y",strtotime("first day of next month")); }
	if (is_null($month) === true || empty($month) === true) { $month = date("n",strtotime("first day of next month")); }

	if (($year==2020 && $month>=5) || $year>2020) {
		
		$str0 = sprintf('%04d/%02d',$year,$month);
		$date_list = array_values(array_filter( $date_list, function($s)use($str0){$v=substr($s,0,7);return ($v==$str0);} ));
		
	} else {
		
		if ($month%2==0) $month--;
		if ($_POST['button'] == '前月') {
			$y1=$year; $m1=$month-2; if ($m1<1) { $y1--; $m1=11; }
			$year = $y1; $month = $m1;
		}
		if ($_POST['button'] == '翌月') {
			$y2=$year; $m2=$month+2; if ($m2>12) { $y2++; $m2=1; }
			$year = $y2; $month = $m2;
		}

		$str0 = sprintf('%04d/%02d',$year,$month);
		$y3=$year; $m3=$month+1; if ($m3>12) { $y3++; $m3=1; }
		$str1 = sprintf('%04d/%02d',$y3,$m3);
		$array0 = array($str0,$str1);
		$date_list = array_values(array_filter( $date_list, function($s)use($array0){$v=substr($s,0,7);return ($v==$array0[0] || $v==$array0[1]);} ));

	}
	
	$date_list_string = ($date_list)? "('".implode("','",$date_list)."')" : "";

} else {
	$page_title = $season_class_title;
}

$member_list = get_member_list($db);
$teacher_list= get_teacher_list($db);
get_season_fee_table($db);

try {

	$member = array();
	$member_array = array();
	
	// 生徒リスト 
	$sql = "SELECT a.member_id,season_course_id,lesson_id,subject_id FROM tbl_season_class_entry_date a, tbl_season_class_entry_subject b ".
					" WHERE a.date IN $date_list_string AND a.member_id = b.member_id AND b.date='{$date_list[0]}' GROUP BY a.member_id, subject_id";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $item) {
		if (array_search($item['member_id'], array_column($member_array, 'member_no')) === false) { 
			$member_data = array();
			$member_data['member_no'] = $item['member_id'];
			$member_data['name']			= $member_list[$item['member_id']]['name'];
			$member_data['furigana']	= $member_list[$item['member_id']]['furigana'];
			$member_data['grade']			= $grade_list[$member_list[$item['member_id']]['grade']];
			if ($class_type != 'sat_sun_class') {
				if (get_season_fee_type($db, $item['member_id'])) {
					if (date('n')>=4 && strpos($date_list[0],'/03')) $member_data['grade'] = $grade_list[$member_list[$item['member_id']]['grade']-1];
				} else {
					if (date('n')< 4 && strpos($date_list[0],'/03')) $member_data['grade'] = $grade_list[$member_list[$item['member_id']]['grade']+1];
				}
			}
			$member_data['course'] 		= $season_course_list[$item['season_course_id']]['course_name'];
			$sep = '';
			foreach ($rslt as $item1) { if ($item1['member_id']==$item['member_id']) { $member_data['subject'] .= $sep.$subject_list[$item1['subject_id']]; $sep=';'; } }
			$member_array[] = $member_data;
		}
	}
	foreach ((array) $member_array as $key => $value) { $sort1[$key] = $value['furigana']; }
	array_multisort($sort1, SORT_ASC, SORT_NATURAL, $member_array);

	if ($member_name) {
		$stmt = $db->prepare("SELECT no, grade FROM tbl_member WHERE name=? AND del_flag=0");
		$stmt->execute(array($member_name));
		$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
		$member_no = $rslt['no'];
		$season_fee_type = get_season_fee_type($db, $member_no);
		$grade = $rslt['grade'];
		if ($class_type != 'sat_sun_class') {
			if ($season_fee_type) {
				if (date('n')>=4 && strpos($date_list[0],'/03')) $grade--;
			} else {
				if (date('n')< 4 && strpos($date_list[0],'/03')) $grade++;
			}
		}

		$sql = "SELECT member_id,date,season_course_id,stime,etime FROM tbl_season_class_entry_date WHERE date IN $date_list_string AND member_id=?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($member_no));
		$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rslt as $item) {
			$member['date'][] = $item['date'];
			$member['season_course_id'][] = $item['season_course_id'];
			$member['stime'][$item['date']] = $item['stime'];
			$member['etime'][$item['date']] = $item['etime'];
			$stime = sscanf($item['stime'], '%d:%d');
			$etime = sscanf($item['etime'], '%d:%d');
			$lesson_length = (($etime[0]*60+$etime[1]) - ($stime[0]*60+$stime[1]))/60;
			$member['exercise_length'][$item['date']] = $lesson_length;
		}
		$sql = "SELECT a.member_id,lesson_id,subject_id,subject_time FROM tbl_season_class_entry_date a, tbl_season_class_entry_subject b ".
						" WHERE a.date IN $date_list_string AND a.member_id=? AND a.member_id = b.member_id AND b.date='{$date_list[0]}' GROUP BY subject_id, a.member_id";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($member_no));
		$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rslt as $item) {
			$member['subject_id'][]		= $item['subject_id'];
			$member['subject_time'][] = $item['subject_time'];
		}
		
		$total_fee = 0; $total_fee0 = 0; $total_fee1 = 0;
		if ($member_list[$member_no]['jyukensei'])
			$lesson_fee0 = $lesson_fee_table[$season_fee_type][$grade][$member['season_course_id'][0]][1];
		else
			$lesson_fee0 = $lesson_fee_table[$season_fee_type][$grade][$member['season_course_id'][0]][0];

		$stmt = $db->prepare("SELECT min(fee) FROM tbl_fee WHERE member_no=? AND lesson_id=1 AND course_id=1 AND fee!=0");
		$stmt->execute(array($member_no));
		$rslt = $stmt->fetch(PDO::FETCH_NUM);
		if ($rslt[0] && $rslt[0]!=0 && $rslt[0] < $lesson_fee0) { $lesson_fee0 = $rslt[0]; }

		if ($member_list[$member_no]['fee_free']) { $lesson_fee0 = 0; }
				
		// スケジュール読み込み
		$sql = "SELECT * FROM tbl_season_schedule WHERE date IN $date_list_string AND member_no=?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($member_no));
		$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rslt as $schedule) {
			$schedules[$schedule['date']]['stime'][] = $schedule['stime'];
			$schedules[$schedule['date']]['etime'][] = $schedule['etime'];
			$schedules[$schedule['date']]['subject_id'][] = $schedule['subject_id'];
			$schedules[$schedule['date']]['teacher_name'][] = $teacher_list[$schedule['teacher_no']]['name'];
			$lesson_fee = $lesson_fee0;
			// 代表の授業は+1,000円
			if ($schedule['teacher_no']==1 && $member_list[$member_no]['yuge_price']) { $lesson_fee += 1000; }
			$stime = sscanf($schedule['stime'], '%d:%d');
			$etime = sscanf($schedule['etime'], '%d:%d');
			$lesson_length = (($etime[0]*60+$etime[1]) - ($stime[0]*60+$stime[1]))/60;
			$lesson_fee *= $lesson_length;
			$schedules[$schedule['date']]['lesson_fee'][] = $lesson_fee;
			$total_fee += $lesson_fee;
			if ($schedule['date'] < '2019/10/01') $total_fee0 += $lesson_fee; else $total_fee1 += $lesson_fee;
			
			$member['exercise_length'][$schedule['date']] -= $lesson_length;
		}
		$exercise_length_total = 0; $exercise_length_total0 = 0; $exercise_length_total1 = 0;
		$rslt = array_unique(array_column($rslt, 'date'));
		foreach ($rslt as $date) {
			$exercise_length_total += $member['exercise_length'][$date];
			if ($date < '2019/10/01')	$exercise_length_total0 += $member['exercise_length'][$date]; else $exercise_length_total1 += $member['exercise_length'][$date];
		}
		
		$date_count = count($schedules);
		
		if (!($year==2020 && $month==5)) {
			if 			($class_type == 'sat_sun_class')			{ $date_count_index=3; }
			else if	($date_count >= LESSON_DATE_COUNT_2)	{ $date_count_index=2; }
			else if	($date_count >= LESSON_DATE_COUNT_1)	{ $date_count_index=1; }
			else																					{ $date_count_index=0; }
		} else {
			if			($date_count >= 15)	{ $date_count_index=2; }
			else if	($date_count >= 10)	{ $date_count_index=1; }
			else												{ $date_count_index=0; }
		}
		$exercise_fee = $exercise_fee_table[$season_fee_type][$member['season_course_id'][0]][$date_count_index];
		if ($year==2020 && $month==6)	$exercise_fee=1200;
		
		if ($member_list[$member_no]['fee_free']) { $exercise_fee = 0; }
		
		$total_fee += $exercise_fee * $exercise_length_total;
		$cons_tax = floor($total_fee0*CONS_TAX08 + $total_fee1*CONS_TAX10 + $exercise_fee*$exercise_length_total0*CONS_TAX08 + $exercise_fee*$exercise_length_total1*CONS_TAX10);

	}
	
} catch (PDOException $e){
	$errFlag = 1;
	array_push($errArray, "エラーが発生しました。".$e->getMessage());
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
//-->
</script>
<style type="text/css">
<!-- 
td {padding: 5px 10px 5px 10px;}
-->
</style><link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<body>

<?php if ($_POST['mode']==1) { ?>

<div id="header">
	八王子さくらアカデミー 
</div>

<div id="content" align="center">

<h2><?= $page_title ?></h2>
<h3>生徒講習スケジュール</h3>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="5"><?= $error ?></font><br>
<?php
		}
?>
	<br>
<?php
	}

if ($class_type == 'sat_sun_class') {
?>
<h3>&nbsp;&nbsp;&nbsp;&nbsp;<?= $year?>年<?= $month ?>-<?= $m3 ?>月&nbsp;&nbsp;&nbsp;&nbsp;</h3>
<?php } ?>
<form method="post" name="form1" action="season_class_student_schedule.php">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
<input type="hidden" name="class_type" value="<?= $class_type ?>">
<input type="hidden" name="mode" value="1">
生徒名：　<?php disp_pulldown_menu(array_column($member_array,'name'), "member_name", $member_name, "document.form1.submit()"); ?>
</form>
<table border="1">
<tr><th rowspan=2>日時</th><th colspan=3>マンツーマン</th></tr>
<tr><th>時間</th><th>科目</th><th>講師</th></tr>
<?php
foreach ($member['date'] as $date) { 
	if (!$schedules[$date]['stime'][0]) {continue;}
	$lc = count($schedules[$date]['stime']);
	for ($i=0;$i<$lc;$i++) {
?>
<tr>
<?php if ($i==0) { ?>
<td  rowspan="<?= $lc ?>"><?= $date."（".$weekday_array[(int)date_format(date_create(str_replace('/', '-', $date)),'w')]."）" ?>
<?= $member['stime'][$date] ?>～<?= $member['etime'][$date] ?></td>
<?php } ?>
<td><?= $schedules[$date]['stime'][$i] ?>～<?= $schedules[$date]['etime'][$i] ?></td>
<td><?= $subject_list[$schedules[$date]['subject_id'][$i]] ?></td><td><?= $schedules[$date]['teacher_name'][$i] ?></td>
<?php if ($i>=1) { echo "</tr>"; continue; } ?>
</tr>
<?php
	}
}
?>
</table>

<?php } else { ?>

<div id="header">
	事務システム 
</div>

<div id="content" align="center">

<h2><?= $page_title ?></h2>
<h3>生徒講習スケジュール・受講料</h3>

<a href="menu.php">メニューへ戻る</a><br><br>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="5"><?= $error ?></font><br>
<?php
		}
?>
	<br>
<?php
	}
?>
	<div class="menu_box">
		<font color="black" size="-1">
		※&nbsp;生徒名を選択してください。
		</font>
	</div>
<br>
<?php
if ($class_type == 'sat_sun_class') {
?>
<form method="post" name="back" action="season_class_student_schedule.php">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
<input type="hidden" name="class_type" value="<?= $class_type ?>">
	<h3><!-- <input type="submit" name="button" value="前月"> -->
	&nbsp;&nbsp;&nbsp;&nbsp;<?= $year?>年<?= $month ?>月&nbsp;&nbsp;&nbsp;&nbsp;
	<!-- <input type="submit" name="button" value="翌月"> --></h3>
</form>
<?php } ?>
<form method="post" name="form1" action="season_class_student_schedule.php">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
<input type="hidden" name="class_type" value="<?= $class_type ?>">
生徒名：　<?php disp_pulldown_menu(array_column($member_array,'name'), "member_name", $member_name, "document.form1.submit()"); ?>
</form>
<br><br>
<table border=1>
<tr><th>学年</th><th>コース</th><th>科目</th></tr>
<?php if ($member_name) { 
$member_data = $member_array[array_search($member_name, array_column($member_array, 'name'))];
?>
<tr><td><?= $member_data['grade'] ?></td><td><?= $member_data['course'] ?></td><td><?= $member_data['subject'] ?></td></tr>
</table>
<br>
<?php } ?>
<table border="1">
<tr><th rowspan=2>日時</th><th colspan=3>マンツーマン</th><th>演習解説</th></tr>
<tr><th>科目</th><th>講師</th><th>受講料</th><th>受講料</th></tr>
<?php
foreach ($date_list as $date) { 
	if (!$schedules[$date]['stime'][0]) {continue;}
	$lesson_fee = 0;
	foreach ($schedules[$date]['lesson_fee'] as $val) { $lesson_fee+=$val; }
	$lc = count($schedules[$date]['stime']);
	for ($i=0;$i<$lc;$i++) {
?>
<tr>
<?php if ($i==0) { ?>
<td  rowspan="<?= $lc ?>"><?= $date."（".$weekday_array[(int)date_format(date_create(str_replace('/', '-', $date)),'w')]."）" ?>
<?= $member['stime'][$date] ?>～<?= $member['etime'][$date] ?>
</td>
<?php } ?>
<td><?= $subject_list[$schedules[$date]['subject_id'][$i]] ?></td>
<td><?= $schedules[$date]['teacher_name'][$i] ?></td>
<?php if ($i==0) { ?>
<td align="right" rowspan="<?= $lc ?>"><?= number_format($lesson_fee) ?> 円</td>
<td align="right" rowspan="<?= $lc ?>"><?= number_format($exercise_fee * $member['exercise_length'][$date]) ?> 円</td>
<?php } ?>
</tr>
<?php
	}
}
?>
</table>
<br>
<table>
<tr><td>受講日数： </td><td align="right"><?= $date_count ?> 日</td></tr>
<tr><td>受講料計： </td><td align="right"><?= number_format($total_fee) ?> 円</td></tr>
<tr><td>消費税：   </td><td align="right"><?= number_format($cons_tax) ?>  円</td></tr>
<tr><td>合計金額： </td><td align="right"><?= number_format($total_fee + $cons_tax) ?>  円</td></tr>
</table>
</body>
</table>
<?php } ?>

</html>

