<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
if (!$_SESSION['ulogin']['teacher_id']) {
$result = check_user($db, "1");
}
//$hokou_mode=1;
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
function checkAll() {
	var checkBoxes = document.getElementsByName('lessons[]');
	for (var cbox of checkBoxes) { cbox.checked=true; }
}
function clearCheckAll() {
	var checkBoxes = document.getElementsByName('lessons[]');
	for (var cbox of checkBoxes) { cbox.checked=false; }
}
//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<?php

define('LESSON60', 1);
define('LESSON90', 2);
define('LESSON120', 3);

define('STUDENT_TIME_ERROR', 1);
define('STUDENT_DBL_ERROR',  2);
define('TEACHER_TIME_ERROR', 4);
define('TEACHER_DBL_ERROR',  8);
define('LESSON_LENGTH_OVER', 16);
define('SUBJECT_TIME_OVER',  32);
define('LESSON_LENGTH_OVER1',  64);
define('TEACHER_SUBJECT_ERROR',128);

$lesson_length_array = array(60, 90);
$time_end = end($time_list);

$class_type = $_GET['class_type'];
if (!$class_type) $class_type = $_POST['class_type'];
if ($class_type=='sat_sun_class') {
	$date_list = $sat_sun_class_date_list;
	$date_list_string = $sat_sun_class_date_list_string;
}

$errFlag = 0;
$errArray = array();

$year = $_POST['y'];
$month = $_POST['m'];
if (is_null($year) === true || empty($year) === true)   { $year = $_GET['y']; }
if (is_null($month) === true || empty($month) === true) { $month = $_GET['m']; }

if ($class_type=='sat_sun_class') {
	if (is_null($year) === true || empty($year) === true)   { 
		$year = date("Y");
		$month = date("n");
//		if ($month%2 == 0) {
		if ($month%2 == 1) {
			$year = date("Y",strtotime("first day of last month"));
			$month = date("n",strtotime("first day of last month"));
		}
	}

	if (($year==2020 && $month>=5) || $year>2020) {
		$str0 = sprintf('%04d/%02d',$year,$month);
		$date_list = array_values(array_filter( $date_list, function($s)use($str0){$v=substr($s,0,7);return ($v==$str0);} ));
	} else {
//		if ($month%2==0) $month--;
		if ($month%2==1) $month--;
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
}

$edit = $_POST['edit'];
if (!$edit) { $edit = $_GET['edit']; }
$view_type = $_POST['view_type'];
if (!$view_type) { $view_type = $_GET['view_type']; }

$action = $_POST['action'];
$exec = $_POST['exec'];
$auto_flag = $_POST['auto_flag'];
$member_name = $_POST["member_name"];
$subject_name = $_POST["subject_name"];
$teacher_name1 = $_POST["teacher_name1"];
$lessons_array = $_POST["lessons"];
$place_select = $_POST['place_select'];
$teachers_select = $_POST['teachers_select'];

sort($lessons_array);

$member_list = get_member_list($db,array(),array(),array(),1);
$teacher_list= get_teacher_list($db,array(),array(),array(),1);

if ($date_list_string) {
try {
	if ($action == 'mantoman' && $member_name) {
		$stmt = $db->prepare("SELECT no FROM tbl_member WHERE name=? AND del_flag=0");
		$stmt->execute(array($member_name));
		$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
		$member_no = $rslt['no'];
	} else {
		$member_no = 0;
	}

	if ($subject_name) {
		$subject_id = array_search($subject_name, $subject_list);
	}
	
	if ($action == 'mantoman' && $teacher_name) {
		$stmt = $db->prepare("SELECT no FROM tbl_teacher WHERE name=?");
		$stmt->execute(array($teacher_name));
		$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
		$teacher_id = $rslt['no'];
	}

	if ($action == 'exercise' && $teacher_name1) {
		$stmt = $db->prepare("SELECT no FROM tbl_teacher WHERE name=?");
		$stmt->execute(array($teacher_name1));
		$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
		$teacher_id = $rslt['no'];
	}

	// 生徒リスト 
	$sql = "SELECT a.member_id,a.date,a.stime,a.etime,a.season_course_id,a.attend_status,a.furikae_status,a.furikae_flag,a.place ".
					"FROM tbl_season_class_entry_date a, tbl_member b ".
					"WHERE a.date IN $date_list_string AND a.member_id = b.no ORDER BY b.furigana";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $member) {
		$members[$member['member_id']]['no'] = $member['member_id'];
		$members[$member['member_id']]['date'][] = $member['date'];
		$members[$member['member_id']]['stime'][] = $member['stime'];
		$members[$member['member_id']]['etime'][] = $member['etime'];
		$members[$member['member_id']]['season_course_id'][] = $member['season_course_id'];
		$members[$member['member_id']]['attend_status'][$member['date']]  = $member['attend_status'];
		$members[$member['member_id']]['furikae_status'][$member['date']] = $member['furikae_status'];
		$members[$member['member_id']]['furikae_flag'][$member['date']]   = $member['furikae_flag'];
		if ($member['place'])	$places[] = $member['place']; else $places[] = $member['place'] = '校舎登録なし';
		$members[$member['member_id']]['place']   = $member['place'];
	}
	$sql = "SELECT a.member_id,lesson_id,subject_id,subject_time FROM tbl_season_class_entry_date a, tbl_season_class_entry_subject b ".
					" WHERE a.date IN $date_list_string AND a.member_id = b.member_id AND b.date='{$date_list[0]}' GROUP BY subject_id, a.member_id";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $member) {
		$members[$member['member_id']]['subject_id'][] = $member['subject_id'];
		$members[$member['member_id']]['subject_time'][] = $member['subject_time'];
		if ($member['member_id'] == $member_no) $subject_list1[] = $subject_list[$member['subject_id']];
		$members[$member['member_id']]['subject_index'] = -1;
	}
	foreach ($members as $mem_id=>$mem) {
		if (!$members[$mem_id]['subject_index']) {
			unset($members[$mem_id]);
		}
	}
	
	$places = array_unique($places);

//	if ($class_type=='sat_sun_class')
		$sql = "SELECT a.no AS teacher_id,date,times,name FROM tbl_season_class_teacher_entry1 a, tbl_teacher b ".
					" WHERE a.date IN $date_list_string AND a.no=b.no";
//	else
//		$sql = "SELECT a.no AS teacher_id,date,stime,etime,name FROM tbl_season_class_teacher_entry a, tbl_teacher b ".
//					" WHERE a.date IN $date_list_string AND a.no=b.no";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $teacher) {
		$teachers1[$teacher['teacher_id']][$teacher['date']] = $teacher;
		$teachers_name1[] = $teacher['name'];
		$teachers_id1[] = $teacher['teacher_id'];
		$teacher_slot[$teacher['date']][] = $teacher['teacher_id'];
		if ($_POST[teachers_select] === null)	$teachers_select[] = $teacher['teacher_id'];
	}
	$teachers_name1 = array_unique($teachers_name1);

//	if ($action == 'mantoman' && $member_no && $subject_id) {
	if ($action == 'mantoman' && $member_no) {
		// 担任リスト
//		if ($class_type=='sat_sun_class')
			$sql = "SELECT a.teacher_id, a.member_no, a.lesson_id, a.subject_id, d.date, d.times ".
						"FROM tbl_fee a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry1 d ".
						"WHERE d.date IN $date_list_string ".
						"AND a.member_no = $member_no ".
						"AND b.member_id = $member_no ".
						"AND c.member_id = $member_no ".
						"AND c.date='{$date_list[0]}' ".
						"AND b.date = d.date ".
						"AND a.teacher_id = d.no ";
//		else
//			$sql = "SELECT a.teacher_id, a.member_no, a.lesson_id, a.subject_id, d.date, d.stime, d.etime ".
//						"FROM tbl_fee a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry d ".
//						"WHERE d.date IN $date_list_string ".
//						"AND a.member_no = $member_no ".
//						"AND b.member_id = $member_no ".
//						"AND c.member_id = $member_no ".
//						"AND c.date='{$date_list[0]}' ".
//						"AND b.date = d.date ".
//						"AND a.teacher_id = d.no ";
		if ($subject_id) $sql .= 
						"AND a.subject_id = $subject_id ".
						"AND c.subject_id = $subject_id ";
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rslt as $teacher) {
			$teachers[$teacher['teacher_id']][$teacher['date']] = $teacher;
		}
		// 科目担当
//		if ($class_type=='sat_sun_class')
			$sql = "SELECT a.no AS teacher_id, c.member_id AS member_no, a.lesson_id, a.subject_id, d.date, d.times ".
						"FROM tbl_teacher_subject a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry1 d ".
						"WHERE d.date IN $date_list_string ".
						"AND b.member_id = $member_no ".
						"AND c.member_id = $member_no ".
						"AND c.date='{$date_list[0]}' ".
						"AND b.date = d.date ".
						"AND a.no = d.no ";
//		else
//			$sql = "SELECT a.no AS teacher_id, c.member_id AS member_no, a.lesson_id, a.subject_id, d.date, d.stime, d.etime ".
//						"FROM tbl_teacher_subject a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry d ".
//						"WHERE d.date IN $date_list_string ".
//						"AND b.member_id = $member_no ".
//						"AND c.member_id = $member_no ".
//						"AND c.date='{$date_list[0]}' ".
//						"AND b.date = d.date ".
//						"AND a.no = d.no ";
		if ($subject_id) $sql .= 
						"AND a.subject_id = $subject_id ".
						"AND c.subject_id = $subject_id ";
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rslt as $teacher) {
			$teachers[$teacher['teacher_id']][$teacher['date']] = $teacher;
		}
		$teachers_name = array_map(function($p){global $teacher_list;return $teacher_list[$p]['name'];}, array_keys($teachers));
		
		$lesson_length = ($members[$member_no]['season_course_id'][0]==LESSON90)?90:60;
		$lesson_length = floor($lesson_length / 30);
	}
	
	if ($exec == '選択コマ登録' && $action == 'mantoman' && $member_no && $subject_id ) {
		$db->beginTransaction();
		$sql = "INSERT INTO tbl_season_schedule (date,stime,etime,lnum,teacher_no,member_no,lesson_id,subject_id,insert_timestamp,update_timestamp) ".
						"VALUES (?,?,?,?,?,?,?,?,now(),now()) ".
						"ON DUPLICATE KEY UPDATE date=?,stime=?,etime=?,lnum=?,teacher_no=?,member_no=?,lesson_id=?,subject_id=?,update_timestamp=now()";
		$stmt = $db->prepare($sql);
		$date1=''; $etime1=0;
		foreach ($lessons_array as $lesson) {
			$matches = explode("-",$lesson);
			$date  = $matches[0];
			$stime = $matches[1];
			$tid   = $matches[2];
			$mid   = $matches[3];
			if ($date == $date1 && $stime < $etime1) continue;
			if ($action == 'mantoman') {
				$etime = $time_list[array_search($stime,$time_list) + $lesson_length];
				$lesson_id = 1;
			} else {
				$etime = $time_list[array_search($stime,$time_list) + 1];
				$lesson_id = 0;
				$subject_id = 0;
			}
			$date1 = $date;
			$etime1 = $etime;
			$stmt->execute( array($date,$stime,$etime,$lnum,$tid,$mid,$lesson_id,$subject_id,
														$date,$stime,$etime,$lnum,$tid,$mid,$lesson_id,$subject_id));
			$insertLMS_array[] = array($date,$stime,$etime,$lnum,$tid,$mid,$lesson_id,$subject_id);
		}
		$db->commit();
//		insertLMS($insertLMS_array);
	}
	
	if ($exec == '選択コマ削除') {
		$db->beginTransaction();
		$sql = "DELETE FROM tbl_season_schedule WHERE date=? AND stime=? AND teacher_no=? AND member_no=?";
		$stmt = $db->prepare($sql);
		foreach ($lessons_array as $lesson) {
			$matches = explode("-",$lesson);
			$date  = $matches[0];
			$stime = $matches[1];
			$tid   = $matches[2];
			$mid   = $matches[3];
			$stmt->execute(array($date,$stime,$tid,$mid));
			$deleteLMS_array[] = array($date,$stime,$etime,$lnum,$tid,$mid,$lesson_id,$subject_id);
		}
//		deleteLMS($deleteLMS_array);
		$db->commit();
	}

	$time_start = "11:00";
	$time_end   = "19:00";
	
	// スケジュール読み込み
	$sql = "SELECT * FROM tbl_season_schedule WHERE date IN $date_list_string";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $schedule) {
		if (!$members[$schedule['member_no']]) continue;
		if ($schedule['stime']<$time_start) $time_start = $schedule['stime'];
		if ($schedule['etime']>$time_end)   $time_end   = $schedule['etime'];
		$schedule['teacher_name'] = $teacher_list[$schedule['teacher_no']]['name'];
		$schedule['member_name'] = $member_list[$schedule['member_no']]['name'];
		$schedule['subject_name'] = $subject_list[$schedule['subject_id']];
		if ($schedule['member_no'] && $members[$schedule['member_no']]) {
			$schedule['attend_status']  = $members[$schedule['member_no']]['attend_status'][$schedule['date']];
			$schedule['furikae_status'] = $members[$schedule['member_no']]['furikae_status'][$schedule['date']];
			$schedule['furikae_flag']   = $members[$schedule['member_no']]['furikae_flag'][$schedule['date']];
			if (strpos($schedule['attend_status'],'休み')===false) {
				$members[$schedule['member_no']]['lesson_sum'][$schedule['subject_id']] += 
					(array_search($schedule['etime'],$time_list) - array_search($schedule['stime'],$time_list))/2;
			}
		}else {
			$schedule['attend_status']  = '';
			$schedule['furikae_status'] = '';
			$schedule['furikae_flag']   = 0;
		}
		$members[$schedule['member_no']]['lesson'][array_search($schedule['date'],$members[$schedule['member_no']]['date'])]++;
		foreach ($time_list as $time1){
			if ($schedule['stime']<=$time1 && $time1<$schedule['etime'] && ($members[$schedule['member_no']] || $schedule['member_no']==0)) {
				$date = $schedule['date'];
				if (array_search($schedule['teacher_no'],$teacher_slot[$date])===false) 	$teacher_slot[$date][] = $schedule['teacher_no'];
				$schedules[$schedule['date']][$time1][array_search($schedule['teacher_no'],$teacher_slot[$date])] = $schedule;
			}
		}
	}
	
	if ($_POST['time_start']) { $time_start = $_POST['time_start']; }
	if ($_POST['time_end'])   { $time_end = $_POST['time_end']; }
	
	// 自動割り当て
	if ($exec == '自動割り当て実行' || ($exec == '保存' && $auto_flag)) {
		if ($exec == '保存' && $auto_flag) {
			$db->beginTransaction();
			$sql = "INSERT INTO tbl_season_schedule (date,stime,etime,lnum,teacher_no,member_no,lesson_id,subject_id,insert_timestamp,update_timestamp) ".
							"VALUES (?,?,?,?,?,?,?,?,now(),now()) ".
							"ON DUPLICATE KEY UPDATE date=?,stime=?,etime=?,lnum=?,teacher_no=?,member_no=?,lesson_id=?,subject_id=?,update_timestamp=now()";
			$stmt1 = $db->prepare($sql);
		}

		foreach ($members as $mem=>&$member) {
			if ($place_select && $member['place']!=$place_select)	continue;
			$k = count($member['subject_id']);
			for ($j=0;$j<$k;$j++) {$break_flag[$j] = 1;}
			while (1) {
				for ($j=0;$j<$k;$j++) {
					$member['subject_index'] = ($member['subject_index']+1)%$k;
					if ($break_flag[$member['subject_index']]==0) {continue;}
					$subject_id = $member['subject_id'][$member['subject_index']];
					if ($member['lesson_sum'][$subject_id]>=$member['subject_time'][$member['subject_index']]) { $break_flag[$member['subject_index']] = 0; continue; }
					break;
				}
				if ($break_flag[$member['subject_index']]==0) {break;}
				$break_flag[$member['subject_index']] = 0;
				foreach ($date_list as $date) {
					$key = array_search($date,$member['date']);
					if ($key===false) {continue;}
					if ($member['lesson'][$key]>=($member['season_course_id'][$key]==LESSON120?2:1)) {continue;}
					$lesson_length1 = ($member['season_course_id'][$key]==LESSON90?90:60);
					$lesson_length1 = floor($lesson_length1 / 30);
					// 担任リスト
//					if ($class_type=='sat_sun_class')
						$sql = "SELECT a.teacher_id, a.member_no, a.lesson_id, a.subject_id, d.date, d.times ".
									"FROM tbl_fee a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry1 d ".
									"WHERE a.member_no = $mem ".
									"AND b.member_id = $mem ".
									"AND c.member_id = $mem ".
									"AND c.date='{$date_list[0]}' ".
									"AND a.teacher_id = d.no ".
									"AND b.date = \"$date\" ".
									"AND d.date = \"$date\" ".
									"AND a.subject_id = c.subject_id ";
//					else
//						$sql = "SELECT a.teacher_id, a.member_no, a.lesson_id, a.subject_id, d.date, d.stime, d.etime ".
//									"FROM tbl_fee a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry d ".
//									"WHERE a.member_no = $mem ".
//									"AND b.member_id = $mem ".
//									"AND c.member_id = $mem ".
//									"AND c.date='{$date_list[0]}' ".
//									"AND a.teacher_id = d.no ".
//									"AND b.date = \"$date\" ".
//									"AND d.date = \"$date\" ".
//									"AND a.subject_id = c.subject_id ";
					$stmt = $db->prepare($sql);
					$stmt->execute();
					$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
					foreach ($teachers as $teacher) {
						$teacher_id = $teacher['teacher_id'];
						if (array_search($teacher_id, $teachers_select) === false)	continue;
						$i = array_search($teacher_id, $teacher_slot[$date]);
						if ($i===false) continue;
//echo "$i,$date,$mem,$teacher_id.<br>";
//if ($date=='2018/07/27' && $mem=='001000' && $teacher_id==2) { echo "{$teacher['stime']},{$teacher['etime']}.<br>"; }
						$colspan = 0;
						foreach ($time_list as $stimekey=>$stime) {
							if ($colspan) { $colspan--; continue; }
							$etime  = $time_list[$stimekey + $lesson_length1];
							$etime0 = $time_list[$stimekey + $lesson_length1 - 1];
							if (!$etime ) { continue; }
							if ($stime<$member['stime'][$key] || $member['etime'][$key]<$etime) { continue; }
							if ($stime==$time_end){ break; };
							$schedule = $schedules[$date][$stime][$i];
							if ($schedule) {
								$colspan = array_search($schedule['etime'], $time_list) - $stimekey;
								$colspan--;
							} else {
								if ($teacher['member_no']!=$mem) {continue;}
								if ($teacher['subject_id']!=$subject_id) {continue;}
								if (//(($class_type=='sat_sun_class')?
											//(strpos($teacher['times'],$stime)!==false && strpos($teacher['times'],$etime0)!==false):
											//($teacher['stime']<=$stime && $etime0<$teacher['etime'])) &&
										(strpos($teacher['times'],$stime)!==false && strpos($teacher['times'],$etime0)!==false) &&
										!$schedules[$date][$etime0][$i] &&
										!array_filter($schedules[$date][$stime], function($p){global $mem;return  $p['member_no']==$mem;}) &&
										!array_filter($schedules[$date][$etime0],function($p){global $mem;return  $p['member_no']==$mem;}) &&
										!array_filter($schedules[$date][$stime], function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;}) &&
										!array_filter($schedules[$date][$etime0],function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;})) {
									$schedule = array(
										'date'=>$date,'stime'=>$stime,'etime'=>$etime,'lnum'=>$i,'teacher_no'=>$teacher_id,'member_no'=>$mem,'lesson_id'=>$teacher['lesson_id'],'subject_id'=>$teacher['subject_id'],'teacher_name'=>$teacher_list[$teacher_id]['name'],'member_name'=>$member_list[$mem]['name'],'subject_name'=>$subject_list[$teacher['subject_id']],'auto'=>1);
									if ($exec == '保存' && $auto_flag) {
										$stmt1->execute(array($date,$stime,$etime,$i,$teacher_id,$mem,$schedule['lesson_id'],$schedule['subject_id'],
																					$date,$stime,$etime,$i,$teacher_id,$mem,$schedule['lesson_id'],$schedule['subject_id']));
										$schedule['auto'] = 0;
										$insertLMS_array[] = array($date,$stime,$etime,$i,$teacher_id,$mem,$schedule['lesson_id'],$schedule['subject_id']);
									}
									foreach ($time_list as $time1){
										if ($stime<=$time1 && $time1<$etime) { $schedules[$date][$time1][$i] = $schedule; }
									}
									$colspan = array_search($etime, $time_list) - array_search($stime, $time_list);
									if (strpos($schedule['attend_status'],'休み')===false) {
										$member['lesson_sum'][$schedule['subject_id']] += $colspan/2;
										$break_flag[$member['subject_index']] = 1;
										$member['lesson'][$key]++;
									}
									$colspan--;
									$teacher_slot[$date][$i] = $teacher_id;
									break;	
								}
							}
						}
						if ($break_flag[$member['subject_index']]) {break;}
					}
					if ($break_flag[$member['subject_index']]) {break;}
					if ($member['lesson'][$key]>=($member['season_course_id'][$key]==LESSON120?2:1)) {continue;}

					// 科目担当
//					if ($class_type=='sat_sun_class')
						$sql = "SELECT a.no AS teacher_id, c.member_id AS member_no, a.lesson_id, a.subject_id, d.date, d.times ".
									"FROM tbl_teacher_subject a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry1 d ".
									"WHERE c.member_id = $mem ".
									"AND c.date='{$date_list[0]}' ".
									"AND b.member_id = $mem ".
									"AND a.no = d.no ".
									"AND b.date = \"$date\" ".
									"AND d.date = \"$date\" ".
									"AND a.subject_id = c.subject_id ";
//					else	
//						$sql = "SELECT a.no AS teacher_id, c.member_id AS member_no, a.lesson_id, a.subject_id, d.date, d.stime, d.etime ".
//									"FROM tbl_teacher_subject a, tbl_season_class_entry_date b, tbl_season_class_entry_subject c, tbl_season_class_teacher_entry d ".
//									"WHERE c.member_id = $mem ".
//									"AND c.date='{$date_list[0]}' ".
//									"AND b.member_id = $mem ".
//									"AND a.no = d.no ".
//									"AND b.date = \"$date\" ".
//									"AND d.date = \"$date\" ".
//									"AND a.subject_id = c.subject_id ";
					$stmt = $db->prepare($sql);
					$stmt->execute();
					$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
					foreach ($teachers as $teacher) {
						$teacher_id = $teacher['teacher_id'];
						if (array_search($teacher_id, $teachers_select) === false)	continue;
						$i = array_search($teacher_id, $teacher_slot[$date]);
						if ($i===false) continue;
//echo "$i,$date,$mem,$teacher_id.<br>";
//if ($date=='2018/07/24' && $mem=='001000' && $teacher_id==2) { echo "{$teacher['stime']},{$teacher['etime']}.<br>"; }
						$colspan = 0;
						foreach ($time_list as $stimekey=>$stime) {
							if ($colspan) { $colspan--; continue; }
							$etime  = $time_list[$stimekey + $lesson_length1];
							$etime0 = $time_list[$stimekey + $lesson_length1 - 1];
							if (!$etime ) { continue; }
							if ($stime<$member['stime'][$key] || $member['etime'][$key]<$etime) { continue; }
							if ($stime==$time_end){ break; };
							$schedule = $schedules[$date][$stime][$i];
							if ($schedule) {
								$colspan = array_search($schedule['etime'], $time_list) - $stimekey;
								$colspan--;
							} else {
								if ($teacher['member_no']!=$mem) {continue;}
								if ($teacher['subject_id']!=$subject_id) {continue;}
								if (//(($class_type=='sat_sun_class')?
											//(strpos($teacher['times'],$stime)!==false && strpos($teacher['times'],$etime0)!==false):
											//($teacher['stime']<=$stime && $etime0<$teacher['etime'])) &&
										(strpos($teacher['times'],$stime)!==false && strpos($teacher['times'],$etime0)!==false) &&
										!$schedules[$date][$etime0][$i] &&
										!array_filter($schedules[$date][$stime], function($p){global $mem; return $p['member_no']==$mem;}) &&
										!array_filter($schedules[$date][$etime0],function($p){global $mem; return $p['member_no']==$mem;}) &&
										!array_filter($schedules[$date][$stime], function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;}) &&
										!array_filter($schedules[$date][$etime0],function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;})) {
									$schedule = array(
										'date'=>$date,'stime'=>$stime,'etime'=>$etime,'lnum'=>$i,'teacher_no'=>$teacher_id,'member_no'=>$mem,'lesson_id'=>$teacher['lesson_id'],'subject_id'=>$teacher['subject_id'],'teacher_name'=>$teacher_list[$teacher_id]['name'],'member_name'=>$member_list[$mem]['name'],'subject_name'=>$subject_list[$teacher['subject_id']],'auto'=>2);
									if ($exec == '保存' && $auto_flag) {
										$stmt1->execute(array($date,$stime,$etime,$i,$teacher_id,$mem,$schedule['lesson_id'],$schedule['subject_id'],
																					$date,$stime,$etime,$i,$teacher_id,$mem,$schedule['lesson_id'],$schedule['subject_id']));
										$schedule['auto'] = 0;
										$insertLMS_array[] = array($date,$stime,$etime,$i,$teacher_id,$mem,$schedule['lesson_id'],$schedule['subject_id']);
									}
									foreach ($time_list as $time1){
										if ($stime<=$time1 && $time1<$etime) { $schedules[$date][$time1][$i] = $schedule; }
									}
									$colspan = array_search($etime, $time_list) - array_search($stime, $time_list);
									if (strpos($schedule['attend_status'],'休み')===false) {
										$member['lesson_sum'][$schedule['subject_id']] += $colspan/2;
										$break_flag[$member['subject_index']] = 1;
										$member['lesson'][$key]++;
									}
									$colspan--;
									$teacher_slot[$date][$i] = $teacher_id;
									break;	
								}
							}
						}
						if ($break_flag[$member['subject_index']]) {break;}
					}
					if ($break_flag[$member['subject_index']]) {break;}
				}
			}
		unset($member);
		}

		if ($exec == '保存' && $auto_flag) {
//			insertLMS($insertLMS_array);
			$db->commit();
		}
	}
	
	$sql = "SELECT * FROM tbl_teacher_subject WHERE lesson_id=1";
	$stmt = $db->query($sql);
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $item) {
		$teacher_subjects[$item['no']][] = $item['subject_id'];
	}
	
}catch (PDOException $e){
	$errFlag = 1;
	array_push($errArray, "エラーが発生しました。".$e->getMessage());
}

if ($class_type == 'sat_sun_class') {
	$page_title = "土日講習スケジュール";
} else {
	$page_title = $season_class_title."　スケジュール";
}
}

function insertLMS($array) {
	foreach ($array as $item) {
		$date=$item[0]; $stime=$item[1]; $etime=$item[2]; $lnum=$item[3]; $tid=$item[4]; $mid=$item[5]; $lesson_id=$item[6]; $subject_id=$item[7];
		$oldid = $LMSlid[$date][$stime][$tid];
		if ($oldid) {
			deleteLMS1($oldid);
		}
		$LMSlid[$date][$stime][$tid] = $insertLMS1($date,$stime,$etime,$lnum,$tid,$mid,$lesson_id,$subject_id);
	}
}

function deleteLMS($array) {
	foreach ($array as $item) {
		$date=$item[0]; $stime=$item[1]; $etime=$item[2]; $lnum=$item[3]; $tid=$item[4]; $mid=$item[5]; $lesson_id=$item[6]; $subject_id=$item[7];
		$oldid = $LMSlid[$date][$stime][$tid];
		if ($oldid) {
			deleteLMS1($oldid);
		}
	}
}

// POST用関数
function http_post ($url, $data)
{
  $data_url = http_build_query ($data);
  $data_len = strlen ($data_url);
 
	$content = file_get_contents (
			$url,
			false,
			stream_context_create (
				array ('http' =>
						array (
								'method'=>'POST',
								'header'=>"Content-Type: application/x-www-form-urlencoded\r\nContent-Length: $data_len\r\n",
								'content'=>$data_url)
						)
				)
			);
	preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
	$statusCode = (int)$matches[1];
	
	if ($statusCode !== 200) return null;
	return $content;
}
 

function insertLMS1($date,$stime,$etime,$lnum,$tid,$mid,$lesson_id,$subject_id) {
	http_post1();
}

function deleteLMS1() {
	http_post1();
}

?>
<body>

<div id="header">
	事務システム 
</div>


<div id="content" align="center">

<h2><?= $page_title ?></h2>

<?php
if (!$edit) {
	$myurl = preg_replace('/?.*/', '', $_SERVER['REQUEST_URI']);
	
	$checked = ($view_type=='student')?'checked':'';
	echo "<input type=\"radio\" name=\"view_type\" onclick=\"location.href='$myurl?y=$year&m=$month&view_type=student&class_type=$class_type'\" $checked>生徒スケジュール　　　　";
	$checked = ($view_type!='student')?'checked':'';
	echo "<input type=\"radio\" name=\"view_type\" onclick=\"location.href='$myurl?y=$year&m=$month&view_type=teacher&class_type=$class_type'\" $checked>先生スケジュール　　　　";
}
?>
<a href="menu.php">メニューへ戻る</a>
<br>

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

if ($class_type=='sat_sun_class') {
?>
<form method="post" name="back" action="season_class_schedule.php">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
<input type="hidden" name="class_type" value="<?= $class_type ?>">
<input type="hidden" name="edit" value="<?= $edit ?>">
<input type="hidden" name="view_type" value="<?= $view_type ?>">
<input type="hidden" name="lms_mode" value="<?=$lms_mode?>">
<input type="hidden" name="place_select" value="<?=$place_select?>">
	<h3><?= $year?>年<?= $month ?>月</h3>
</form>
<?php
}
?>

<br>
<form method="post" name="form1" action="season_class_schedule.php">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
<input type="hidden" name="class_type" value="<?= $class_type ?>">
<input type="hidden" name="edit" value="<?= $edit ?>">
<input type="hidden" name="view_type" value="<?= $view_type ?>">
<input type="hidden" name="lms_mode" value="<?=$lms_mode?>">
<input type="hidden" name="place_select" value="<?=$place_select?>">

校舎選択：　
<?php disp_pulldown_menu($places, 'place_select', $place_select,'document.form1.submit()'); ?>
<br>
<br>

<?php
/*
foreach ($schedules as $sched_date) {
foreach ($sched_date as $sched_stime) {
foreach ($sched_stime as $schedule) {
	$no = $schedule['member_no'];
	if (!$members[$no]) {continue;}
	$members[$no]['lesson'][array_search($schedule['date'],$members[$no]['date'])]=1;
}}}
*/

uasort($members, function ($p1, $p2) {
	global $member_list;
	if ($member_list[$p1['no']]['furigana'] > $member_list[$p2['no']]['furigana']) { return 1; } 
	else if ($member_list[$p1['no']]['furigana'] == $member_list[$p2['no']]['furigana']) { return 0; }
	else { return -1; }
	});
$member_list_name = array_column($member_list,'name','no');

if ($edit) {
echo "<table border=\"1\"><tr><th>生徒名</th><th>コース</th><th>授業日数（割り当て済み日数)</th><th>科目別授業時間（割り当て済み時間）</th></tr>";
foreach ($members as $no=>$member) {
	if ($place_select && $member['place'] != $place_select)	continue;
	echo "<tr><td>{$member_list[$no]['name']}</td>";
	echo "<td>";
	if ($member['season_course_id'][0]==LESSON60) { echo "60分授業"; }
	if ($member['season_course_id'][0]==LESSON90) { echo "90分授業"; }
	if ($member['season_course_id'][0]==LESSON120) { echo "120分授業"; }
	echo "</td>";
	echo "<td align=\"center\">";
	$cnt=0; foreach ($member['date'] as $key=>$date) { $cnt++; }
	echo "$cnt (";
	$cnt=0; foreach ($member['lesson'] as $key=>$flag) { if ($flag) { $cnt++; }}
	echo "$cnt)</td>";
	echo "<td>";
	foreach ($member['subject_id'] as $key=>$subject_id0) {
		if (!$member['lesson_sum'][$subject_id0]) { $member['lesson_sum'][$subject_id0]=0; }
		echo "&nbsp;{$subject_list[$subject_id0]}&nbsp;{$member['subject_time'][$key]}（{$member['lesson_sum'][$subject_id0]}）&nbsp;";
	}
	echo "</td></tr>";
	if (!$place_select || $member['place'] == $place_select)	$members_name[] = $member_list[$no]['name'];
}
echo "</table><br>";

if ($exec == '自動割り当て実行') {
	foreach ($teachers_select as $value)
		echo "<input type=\"hidden\" name=\"teachers_select[]\" value=\"$value\">";
?>
<input type="hidden" name="auto_flag" value="1">
<input type="submit" name="exec" value="保存">&nbsp;&nbsp;
<input type="submit" name="exec" value="取り消し">
<?php
} else{
?>
<table>
<tr><td><input type="radio" id="radio1" name="action" value="mantoman" onclick="document.form1.teacher_name1.selectedIndex=0;" <?php if($action=='mantoman'){echo 'checked';} ?>>個別授業登録
　生徒名<?php disp_pulldown_menu($members_name,"member_name", $member_name, "document.form1.radio1.checked=true;document.form1.subject_name.selectedIndex=0;document.form1.submit()"); ?>
　科目<?php disp_pulldown_menu($subject_list1,"subject_name", $subject_name, "document.form1.radio1.checked=true;document.form1.submit()"); ?>
<!--
　講師選択<?php disp_pulldown_menu($teachers_name,"teacher_name", $teacher_name, "document.form1.radio1.checked=true;document.form1.submit()"); ?>
-->
</td></tr>
<!--
<tr><td><input type="radio" id="radio2" name="action" value="exercise" onclick="document.form1.member_name.selectedIndex=0;document.form1.subject_name.selectedIndex=0;document.form1.teacher_name.selectedIndex=0;" <?php if($action=='exercise'){echo 'checked';} ?>>演習登録
　講師選択<?php disp_pulldown_menu($teachers_name1,"teacher_name1", $teacher_name1, "document.form1.radio2.checked=true;document.form1.submit()"); ?>
</td></tr>
-->
</table>
<input type="submit" name="exec" value="選択コマ登録">&nbsp;&nbsp;
<br><br>
自動割り当て対象講師：
<?php
foreach ($teachers_name1 as $key=>$name) {
	$checked_flag = (array_search($teachers_id1[$key], $teachers_select)!==false)? 'checked': '';
	echo "<input type=\"checkbox\" name=\"teachers_select[]\" value=\"{$teachers_id1[$key]}\" $checked_flag>$name &nbsp;&nbsp;";
}
?>
<br>
<input type="submit" name="exec" value="自動割り当て実行">
<br><br>
<input type="submit" name="exec" value="選択コマ削除">&nbsp;&nbsp;
<input type="button" value="全選択解除" onclick="clearCheckAll()">&nbsp;&nbsp;
<br>
<br>
<?php
}
}
?>
スケジュール表　　
<?php disp_pulldown_menu($time_list, "time_start", $time_start,"document.form1.submit()"); ?>
～
<?php disp_pulldown_menu($time_list, "time_end", $time_end,"document.form1.submit()"); ?>
<table border="1">

<?php
$member = $members[$member_no];
foreach($date_list as $date) {
	
	if ($member_no && array_search($date,$members[$member_no]['date'])===false) continue;
	
	if ($edit) {
		echo '<tr><th>日付</th><th>生徒</th><th>先生</th>';
	} else if ($view_type=='student') {
		echo '<tr><th>日付</th><th>生徒</th>';
	} else {
		echo '<tr><th>日付</th><th>先生</th>';
	}
	foreach ($time_list as $stime) { if ($stime<$time_start){ continue; }; if ($stime==$time_end){ break; }; echo '<th>'.$stime.'</th>'; }
	echo '</tr>';

	$datetime = date_create(str_replace('/','-',$date));
	$week = array("日", "月", "火", "水", "木", "金", "土");
	$youbi = "（".$week[(int)date_format($datetime, 'w')]."）";
	if (strpos($youbi,"土")) { $youbi = "<font color=blue>".$youbi."</font>"; }
	if (strpos($youbi,"日")) { $youbi = "<font color=red>".$youbi."</font>"; }
	$tmpstr = '';
	for($i=0;$i<max(count($teacher_slot[$date]),1);$i++) {
		$tmpstr .= "<tr>";
		if ($i==0) {
			$tmpstr .= "<td align=\"center\" rowspan=\"".max(count($teacher_slot[$date]),1)."\">".substr($date,5,5)."<br>$youbi</td>";
			$tmpstr .= "<td width=100 rowspan=\"".max(count($teacher_slot[$date]),1)."\">";
			foreach ($members as $no=>&$mem) {
				$key = array_search($date,$mem['date']);
				if (!$place_select || $mem['place'] == $place_select) {
					if ($key!==FALSE) {
						if ($mem['lesson'][$key]>=($mem['season_course_id'][$key]==LESSON120?2:1) || $hokou_mode) {
							$tmpstr .= "<font color=black>";
						} else {
							$tmpstr .= "<font color=red>";						
						}
						$tmpstr .= $member_list[$no]['name']."</font><br>";
					}
				}
				$mem['lesson_length'] = 0;
			}
			unset($mem);
			$tmpstr .= "</td>";
		}
		$teacher_id = $teacher_slot[$date][$i];
		if (!$teacher_id) {
			$tmpstr .= "<td></td>";
			foreach ($time_list as $stimekey=>$stime) {
				if ($stime<$time_start){ continue; };
				if ($stime==$time_end){ break; };
				$tmpstr .= "<td></td>";
			}
			$tmpstr .= "</tr>";
			break;
		}
		$tmpstr .= "<td>".$teacher_list[$teacher_slot[$date][$i]]['name']."</td>";
		$colspan = 0;
		foreach ($time_list as $stimekey=>$stime) {
			if ($stime<$time_start){ continue; };
			if ($stime==$time_end){ break; };
			$schedule = $schedules[$date][$stime][$i];
			if ($colspan) { $colspan--; continue; }
			$bgcolor="#ffffff";

			$teacher = $teachers1[$teacher_slot[$date][$i]][$date];
			if ($teacher &&
						//(($class_type=='sat_sun_class')?
							//(strpos($teacher['times'],$stime)!==false):
							//($teacher['stime']<=$stime && $stime<$teacher['etime']))) {
								(strpos($teacher['times'],$stime)!==false)) {
				$bgcolor = '#eeffee';
			} else {
				$bgcolor = '#ffffff';
			}
			if ($schedule) {
				$bgcolor="#eeeeff";
				$colspan = array_search($schedule['etime'], $time_list) - array_search($schedule['stime'], $time_list);
				if ($schedule['auto']) { $bgcolor="#ddddff"; }
//				if ($schedule['auto']==2) { $bgcolor="#ddffdd"; }
				if ($schedule['member_no']) {
					
					$err_st = 0;
					
					if (strpos($schedule['attend_status'],'休み')===false) {
					// 生徒時間帯チェック
					$cmd = "SELECT stime, etime FROM tbl_season_class_entry_date WHERE member_id = \"{$schedule['member_no']}\" AND date = \"{$date}\"";
					$stmt = $db->query($cmd);
					$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
//if ($date=='2018/07/24') { echo "<br>{$member_list[$schedule['member_no']]['name']},$stime,A-{$schedule['member_no']},{$rslt['stime']},{$rslt['etime']}."; }
					if ($rslt['stime']>$schedule['stime'] || $schedule['etime']>$rslt['etime']) { $err_st |= STUDENT_TIME_ERROR; }
					foreach ($schedules[$date] as $stimekey=>$sched0) {
						foreach ($sched0 as $ikey=>$sched1) {
							if ($ikey==$i) { continue; }
							if ($sched1['member_no']==$schedule['member_no'] && strpos($sched1['attend_status'],'休み')===false) {
								if (($sched1['stime']<=$schedule['stime'] && $schedule['stime']<$sched1['etime']) ||
										($sched1['stime']<$schedule['etime'] && $schedule['etime']<=$sched1['etime'])) {
									$err_st |= STUDENT_DBL_ERROR;
								}
							}
						}
					}
					
					// 先生時間帯チェック
//					if ($class_type=='sat_sun_class')
						$cmd = "SELECT times FROM tbl_season_class_teacher_entry1 WHERE no = \"{$schedule['teacher_no']}\" AND date = \"{$date}\"";
//					else
//						$cmd = "SELECT stime, etime FROM tbl_season_class_teacher_entry WHERE no = \"{$schedule['teacher_no']}\" AND date = \"{$date}\"";
					$stmt = $db->query($cmd);
					$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
//if ($date=='2018/07/24') { echo "B-{$schedule['teacher_no']},{$rslt['stime']},{$rslt['etime']}."; }
//					if ($class_type=='sat_sun_class') {
						if (strpos($rslt['times'],$schedule['stime'])===false || strpos($rslt['times'],$time_list[array_search($schedule['etime'],$time_list)-1])===false) { $err_st |= TEACHER_TIME_ERROR; }
//					} else {
//						if ($rslt['stime']>$schedule['stime'] || $schedule['etime']>$rslt['etime']) { $err_st |= TEACHER_TIME_ERROR; }
//					}
					foreach ($schedules[$date] as $stimekey=>$sched0) {
						foreach ($sched0 as $ikey=>$sched1) {
							if ($ikey==$i) { continue; }
							if ($sched1['teacher_no']==$schedule['teacher_no'] && strpos($sched1['attend_status'],'休み')===false) {
								if (($sched1['stime']<=$schedule['stime'] && $schedule['stime']<$sched1['etime']) ||
										($sched1['stime']<$schedule['etime']  && $schedule['etime']<=$sched1['etime'])) {
									$err_st |= TEACHER_DBL_ERROR;
								}
							}
						}
					}
					
					// レッスン数チェック
					$members[$schedule['member_no']]['lesson_length'] += $colspan;
					$season_course_id = $members[$schedule['member_no']]['season_course_id'][array_search($date,$members[$schedule['member_no']]['date'])];
//if ($date=='2018/07/24') { echo "C-{$season_course_id},{$members[$schedule['member_no']]['lesson_length']}."; }
					if ($members[$schedule['member_no']]['lesson_length'] > $season_course_id+1)  { $err_st |= LESSON_LENGTH_OVER; }
					$members[$schedule['member_no']]['subject_time'][array_search($schedule['subject_id'],$members[$schedule['member_no']]['subject_id'])] -= $colspan/2;
//if ($schedule['member_no']=='001018') { echo "D-$date,{$schedule['subject_id']},".$members[$schedule['member_no']]['subject_time'][array_search($schedule['subject_id'],$members[$schedule['member_no']]['subject_id'])].",{$members[$schedule['member_no']]['lesson_sum'][$schedule['subject_id']]}.<br>"; }
					if ($members[$schedule['member_no']]['subject_time'][array_search($schedule['subject_id'],$members[$schedule['member_no']]['subject_id'])]<0) { $err_st |= SUBJECT_TIME_OVER; }
					
					} else {
						$bgcolor="#eeeeee";   // 休み
					}
					
					switch ($season_course_id) {
						case LESSON60:	if ($colspan != 2) { $err_st |= LESSON_LENGTH_OVER1; } break;
						case LESSON90:	if ($colspan != 3) { $err_st |= LESSON_LENGTH_OVER1; } break;
						case LESSON120:	if ($colspan != 2) { $err_st |= LESSON_LENGTH_OVER1; } break;
					}
					
					// 担当科目チェック
					if (array_search($schedule['subject_id'], $teacher_subjects[$schedule['teacher_no']]) === false) {
						$err_st |= TEACHER_SUBJECT_ERROR;
					}
					
					$disp_option = (!$place_select || $members[$schedule['member_no']]['place']==$place_select)? '': 'style="visibility:hidden"';
					$tmpstr .= "<td bgcolor=\"$bgcolor\" colspan=\"$colspan\" $disp_option>";
					if ($err_st) { $tmpstr .= '<font color=red>'; }
					$tmpstr .= "<input type=\"checkbox\" name=\"lessons[]\" value=\"{$date}-{$stime}-{$schedule['teacher_no']}-{$schedule['member_no']}\" onclick=\"lclick(this.checked)\">";
					$tmpstr .= "{$schedule['member_name']}／{$schedule['subject_name']}／{$schedule['teacher_name']}";
					if ($err_st) {
						$tmpstr .= '(';
						if ($err_st & STUDENT_TIME_ERROR) { $tmpstr .= "E1"; }
						if ($err_st & STUDENT_DBL_ERROR)  { $tmpstr .= "E2"; }
						if ($err_st & TEACHER_TIME_ERROR) { $tmpstr .= "E3"; }
						if ($err_st & TEACHER_DBL_ERROR)  { $tmpstr .= "E4"; }
						if ($err_st & LESSON_LENGTH_OVER) { $tmpstr .= "E5"; }
						if ($err_st & SUBJECT_TIME_OVER)  { $tmpstr .= "E6"; }
						if ($err_st & LESSON_LENGTH_OVER1){ $tmpstr .= "E7"; }
						if ($err_st & TEACHER_SUBJECT_ERROR){ $tmpstr .= "E8"; }
						$tmpstr .= ')</font>';
					}
					$tmpstr .= '</td>';
				} else {
					$tmpstr .= "<td bgcolor=\"$bgcolor\" >";
					$tmpstr .= "<input type=\"checkbox\" name=\"lessons[]\" value=\"{$date}-{$stime}-{$schedule['teacher_no']}-{$schedule['member_no']}\" onclick=\"lclick(this.checked)\">";
					$tmpstr .= "演習／{$schedule['teacher_name']}</td>";
				}
				$colspan--;
			} else {
//				foreach ($teachers as $teacher_id=>$teacher) {
//				$teacher = $teacher[$date];
//				if (!$teacher) continue;
				if ($teacher_id && $action == 'mantoman' && $subject_id) {
					$lcnt=0;
					array_walk_recursive($schedules[$date],function($val,$key){global $member_no,$lcnt;if ($key=='member_no' && $val==$member_no){$lcnt++;};});
					$key = array_search($date,$member['date']);
					$teacher = $teachers[$teacher_id][$date];
					$etime0 = $time_list[$stimekey + $lesson_length - 1];
/*
echo"$stime,$etime0,$lesson_length";echo"<BR>";					
var_dump($teacher);echo"<BR>";					
var_dump((strpos($teacher['times'],$stime)!==false && strpos($teacher['times'],$etime0)!==false)) ;
var_dump($key) ;
var_dump($member) ;
var_dump(	$member['stime'][$key]<=$stime && $etime0<$member['etime'][$key] );
var_dump(	!$schedules[$date][$etime0][$i] );
var_dump(	(($key===FALSE || $lcnt<($member['season_course_id'][$key]==LESSON120?4:1)) || $hokou_mode) );
var_dump(	!array_filter($schedules[$date][$stime], function($p){global $member_no; return $p['member_no']==$member_no;}) );
var_dump(	!array_filter($schedules[$date][$etime0],function($p){global $member_no; return $p['member_no']==$member_no;}) );
var_dump(	!array_filter($schedules[$date][$stime], function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;}) );
var_dump(	!array_filter($schedules[$date][$etime0],function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;}) );
var_dump(	array_search($date,$member['date'])!==FALSE );
echo"<BR>";
echo"<BR>";
*/							
					if (//(($class_type=='sat_sun_class')?
								//(strpos($teacher['times'],$stime)!==false && strpos($teacher['times'],$etime0)!==false):
								//($teacher['stime']<=$stime && $etime0<$teacher['etime'])) &&
							(strpos($teacher['times'],$stime)!==false && strpos($teacher['times'],$etime0)!==false) &&
							$member['stime'][$key]<=$stime && $etime0<$member['etime'][$key] &&
							!$schedules[$date][$etime0][$i] &&
							(($key===FALSE || $lcnt<($member['season_course_id'][$key]==LESSON120?4:1)) || $hokou_mode) &&
							!array_filter($schedules[$date][$stime], function($p){global $member_no; return $p['member_no']==$member_no;}) &&
							!array_filter($schedules[$date][$etime0],function($p){global $member_no; return $p['member_no']==$member_no;}) &&
							!array_filter($schedules[$date][$stime], function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;}) &&
							!array_filter($schedules[$date][$etime0],function($p){global $teacher_id;return $p['teacher_no']==$teacher_id && strpos($p['attend_status'],'休み')===false;}) &&
							array_search($date,$member['date'])!==FALSE ) { 
						$bgcolor="#8888ff";
					}
				}
				if ($teacher_id && $action == 'exercise') {
					$teacher = $teachers1[$teacher_id][$date];
					if ($teacher && 
							//(($class_type=='sat_sun_class')?
								//(strpos($teacher['times'],$stime)!==false):
								//($teacher['stime']<=$stime && $stime<$teacher['etime'])) &&
							(strpos($teacher['times'],$stime)!==false) &&
							!array_filter($schedules[$date][$stime],function($p){global $teacher_id;return $p['teacher_no']==$teacher_id;})) {
						$bgcolor="#ddddff";
					}
				}
//				}
				$tmpstr .= "<td bgcolor=\"$bgcolor\"><input type=\"checkbox\" name=\"lessons[]\" value=\"{$date}-{$stime}-{$teacher_id}-{$member_no}\" onclick=\"lclick(this.checked)\">";
				$tmpstr .= "</td>";
			}
		}
		$tmpstr .= "</tr>";
		
	}
	if ($edit) {
		echo preg_replace('/／(.+?)／[^(<]+/u','<br>　$1',$tmpstr);
	} else {
		preg_match_all( '|<td[^>]*>(.*?)</td>|', $tmpstr, $td_array );
		if ($view_type == 'student') {
			$names = preg_split('/<br>/', $td_array[1][1]);
			$rowspan = (count($names)>1)?count($names)-1:1;
			echo preg_replace('/rowspan="\d+"/',"rowspan=\"$rowspan\"",$td_array[0][0]);
			if (count($names)) {
				foreach ($names as $name) {
					if (!$name) { continue; }
					echo "<td>$name</td>";
					$name = preg_replace('/<[^>]+>/','',$name);
					$member = $members[array_search($name, $member_list_name)];
					$key= array_search($date,$member['date']);
					$colspan = 1;
					foreach ($time_list as $stimekey=>$stime) {
						if ($stime<$time_start){ continue; };
						if ($stime==$time_end){ break; };
						if ((--$colspan)>0) { continue; }
						if ($member['stime'][$key]<=$stime && $stime<$member['etime'][$key]) {
							if (strpos($member['attend_status'][$date],'休み')!==false) {
								$bgcolor1 = '#eeeeee';
								$bgcolor2 = '#eeeeee';
							} else {
								$bgcolor1 = '#eeeeff';
								$bgcolor2 = '#eeffee';
							}
						} else {
							$bgcolor1 = '#ffffff';
							$bgcolor2 = '#ffffff';
						}
						foreach ($td_array[0] as $td) {
							if (preg_match("|colspan=\"(\d)\".+value=\"$date-$stime-.+{$name}／|u", $td, $match)) {
								$colspan = $match[1];
								$td = preg_replace('/bgcolor=".+?"/', "bgcolor=\"$bgcolor1\"", $td);
								$td = preg_replace("|<input.*{$name}／|u", '', $td);
								echo $td;
								break;
							}
						}
						if ($colspan==0) { echo "<td bgcolor=\"$bgcolor2\"></td>"; $colspan = 1; }
					}
					echo "</tr>";
				}
			} else {
				echo "<td></td>";
				foreach ($time_list as $stimekey=>$stime) {
					if ($stime<$time_start){ continue; };
					if ($stime==$time_end){ break; };
					echo "<td></td>";
				}
				echo "</tr>";
			}
		} else {
//			if ($class_type=='sat_sun_class')
				$sql = "SELECT no, times FROM tbl_season_class_teacher_entry1 WHERE date = \"$date\"";
//			else
//				$sql = "SELECT no, stime, etime FROM tbl_season_class_teacher_entry WHERE date = \"$date\"";
			$stmt = $db->prepare($sql);
			$stmt->execute();
			$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
			uasort($teachers, function($p1,$p2) {
				global $teacher_list;
				if ($teacher_list[$p1['no']]['furigana'] > $teacher_list[$p2['no']]['furigana']) { return 1; }
				else if ($teacher_list[$p1['no']]['furigana'] == $teacher_list[$p2['no']]['furigana']) { return 0; }
				else {return -1; }
				});
			$rowspan = count($teachers)?count($teachers):1;
			echo preg_replace('/rowspan="\d+"/',"rowspan=\"$rowspan\"",$td_array[0][0]);
			if (count($teachers)) {
				foreach ($teachers as $teacher) {
					$teacher_no = $teacher['no'];
					$name = $teacher_list[$teacher_no]['name'];
					echo "<td>$name</td>";
					$colspan = 1;
					foreach ($time_list as $stimekey=>$stime) {
						if ($stime<$time_start){ continue; };
						if ($stime==$time_end){ break; };
						if ((--$colspan)>0) { continue; }
						if (//($class_type=='sat_sun_class')?
								//(strpos($teacher['times'],$stime)!==false):
								//($teacher['stime']<=$stime && $stime<$teacher['etime'])) {
								(strpos($teacher['times'],$stime)!==false)) {
							$bgcolor1 = '#eeeeff';
							$bgcolor2 = '#eeffee';
						} else {
							$bgcolor1 = '#ffffff';
							$bgcolor2 = '#ffffff';
						}
						foreach ($td_array[0] as $td) {
							if (preg_match("|bgcolor=\"(.......)\" colspan=\"(\d)\".+value=\"$date-$stime-.+／{$name}|u", $td, $match)) {
								if ($match[1]=='#eeeeee') { continue; }
								$colspan = $match[2];
								$td = preg_replace('/bgcolor=".+?"/', "bgcolor=\"$bgcolor1\"", $td);
								$td = preg_replace("|<input[^>]*>|u", '', $td);
								$td = preg_replace("|／{$name}|u", '', $td);
								echo $td;
								break;
							}
						}
						if ($colspan==0) { echo "<td bgcolor=\"$bgcolor2\"></td>"; $colspan = 1; }
					}
					echo "</tr>";
				}
			} else {
				echo "<td></td>";
				foreach ($time_list as $stimekey=>$stime) {
					if ($stime<$time_start){ continue; };
					if ($stime==$time_end){ break; };
					echo "<td></td>";
				}
				echo "</tr>";
			}
		}
	}
}
?>
</table>
<br><br>
<table>
<tr><th>エラーコード</th><td>エラー内容</td></tr>
<tr><th>E1</th><td>生徒の登録授業時間外です。</td></tr>
<tr><th>E2</th><td>生徒の授業時間が重複しています。</td></tr>
<tr><th>E3</th><td>講師の登録勤務時間外です。</td></tr>
<tr><th>E4</th><td>講師の授業時間が重複しています。</td></tr>
<tr><th>E5</th><td>1日の授業時間を超えています。</td></tr>
<tr><th>E6</th><td>科目別割り当て授業時間を超えています。</td></tr>
<tr><th>E7</th><td>授業時間がコース指定と異なります。</td></tr>
<tr><th>E8</th><td>講師の担当科目ではありません。</td></tr>
</table>
<br>
</body>
</table>
<?php if ($lms_mode) { ?>
<br><input type="button" onclick="window.close()" value="閉じる">
<?php } ?>
</html>

