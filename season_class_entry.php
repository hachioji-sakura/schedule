<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

$places_array = array("八王子北口校舎","国立校舎","豊田校舎");

$errFlag = 0;
$errArray = array();

$year = $_POST['y'];
$month = $_POST['m'];
if (is_null($year) === true || empty($year) === true)   { $year = $_GET['y']; }
if (is_null($month) === true || empty($month) === true) { $month = $_GET['m']; }
if (is_null($year) === true || empty($year) === true)   { 
	$year = date("Y");
	$month = date("n");
	if ($month%2 == 0) {
		$year = date("Y",strtotime("first day of last month"));
		$month = date("n",strtotime("first day of last month"));
	}
}

if ($_POST['button'] == '前月') {
	$y1=$year; $m1=$month-2; if ($m1<1) { $y1--; $m1=11; }
	$year = $y1; $month = $m1;
}
if ($_POST['button'] == '翌月') {
	$y2=$year; $m2=$month+2; if ($m2>12) { $y2++; $m2=1; }
	$year = $y2; $month = $m2;
}

$class_type = $_GET['class_type'];
if (!$class_type) $class_type = $_POST['class_type'];
if ($class_type=='sat_sun_class') {
	$date_list = $sat_sun_class_date_list;
	$date_list_string = $sat_sun_class_date_list_string;
}
if ($class_type == 'sat_sun_class') {
	$page_title = "土日講習";
} else {
	$page_title = "$season_class_title";
	$default_stime_sat = $default_stime;
	$default_etime_sat = $default_etime;
}

if ($class_type=='sat_sun_class') {
	$str0 = sprintf('%04d/%02d',$year,$month);
	$y3=$year; $m3=$month+1; if ($m3>12) { $y3++; $m3=1; }
	$str1 = sprintf('%04d/%02d',$y3,$m3);
	$array0 = array($str0,$str1);
	$date_list1 = array_values(array_filter( $date_list, function($s)use($array0){$v=substr($s,0,7);return ($v==$array0[0] || $v==$array0[1]);} ));
	$date_list_string1 = ($date_list1)? "('".implode("','",$date_list1)."')" : "";
} else {
	$date_list1 = $date_list;
	$date_list_string1 = $date_list_string;
}

$time_list0 = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40');
$time_list1 = array('0','5');
unset($subject_list[0]);
$attendStatusList = array( "出席", "休み１", "休み２", "休み１休講", "休み２当日" );
$attendStatusList1 = array( "振替", "休み１", "休み２", "休み１休講", "休み２当日" );

$student["no"] = trim($_GET["no"]);
if ($student["no"] == '') { $student["no"] = trim($_POST["no"]); }
$student = get_member($db, array("tbl_member.no = ?"), array($student["no"]));

$teacher_list = get_teacher_list($db);

$season_course_id    = $_POST["season_course_id"];
$season_course_id_array	= $_POST["season_course_id_array"];
$entry_flag_array    = $_POST["entry_flag"];
$furikae_flag_array  = $_POST["furikae_flag"];
$lesson_array        = $_POST["lesson_id"];
$subject_array       = $_POST["subject_id"];
$subject_time0_array = $_POST["subject_time0"];
$subject_time1_array = $_POST["subject_time1"];
$stime_array         = $_POST["stime"];
$etime_array         = $_POST["etime"];
$attend_status_array = $_POST["attend_status"];
$furikae_array       = $_POST["furikae_status"];
$place							 = $_POST["place"];

$subject_time_array = array_map(function($p1,$p2){return $p1+$p2/10;}, $subject_time0_array, $subject_time1_array);

if (isset($_POST['add'])) {
	$action = 'add';
} else {
	$action = "";
}

if ($action == 'add' && $date_list_string && $date_list_string1) {
	
	$db->beginTransaction();
	try{
		$sql = "DELETE FROM tbl_season_class_entry_subject WHERE member_id='{$student['no']}' AND date='{$date_list1[0]}'";
		$db->query($sql);
		$sql = "DELETE FROM tbl_season_class_entry_date WHERE member_id='{$student['no']}' AND date IN {$date_list_string}";
		$db->query($sql);
		
		$i=0;
		$sql = "INSERT INTO tbl_season_class_entry_date VALUES (?, ?, ?, ?, ?, now(), now(), ?, ?, ?, ?, ?, ?)";
		$stmt = $db->prepare($sql);
		foreach ($date_list as $datestring ) {
			if (array_search($datestring, $date_list1) !== false) {
				$season_course_id0 = $season_course_id;
			} else{
				$season_course_id0 = $season_course_id_array[$i];
			}
			if ($entry_flag_array && array_search($i, $entry_flag_array) !== false ) {
				$stmt->execute(array($student["no"], $season_course_id0, $datestring, '', '',
									$stime_array[$i], $etime_array[$i], $attend_status_array[$i], $furikae_array[$i], false, $place));
			}
			if ($furikae_flag_array && array_search($i, $furikae_flag_array) !== false ) {
				$stmt->execute(array($student["no"], $season_course_id0, $datestring, '', '',
									$stime_array[$i], $etime_array[$i], $attend_status_array[$i], $furikae_array[$i], true, $place));
			}
			$i++;
		}

		$sql = "INSERT INTO tbl_season_class_entry_subject VALUES (?, ?, ?, ?, ?, now(), now())";
		$stmt = $db->prepare($sql);
		for ($i=0;$i<10;$i++) {
			if ( $lesson_array[$i] != "" && $subject_array[$i] != "" && $subject_time_array[$i] != "" ) {
				$stmt->execute(array($student["no"], $lesson_array[$i], $subject_array[$i], $subject_time_array[$i], $date_list1[0]));
			}
		}
		
		$stmt = $db->query("SELECT date,stime,etime FROM tbl_season_class_entry_date WHERE member_id='{$student['no']}' AND date IN {$date_list_string}");
		$dates0 = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$dates1 = array_column($dates0, 'date');
		$sql = "SELECT * FROM tbl_season_schedule WHERE date IN {$date_list_string1} AND member_no=\"{$student['no']}\"";
		$stmt = $db->query($sql);
		$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($schedules as $sched) {
			$key = array_search($sched['date'], $dates1);
			if ($key !== false && $sched['stime']>=$dates0[$key]['stime'] && $sched['etime']<=$dates0[$key]['etime']) continue;
			$sql = "DELETE FROM tbl_season_schedule WHERE member_no='{$student['no']}' AND date='{$sched['date']}' AND stime='{$sched['stime']}'";
			$db->query($sql);
			$msgArray[] = "{$sched['date']} {$sched['stime']}-{$sched['etime']} {$teacher_list[$sched['teacher_no']]['name']}";
		}
		
		$db->commit();
	}catch (PDOException $e){
		$errFlag = 1;
		array_push($errArray, "エラーが発生しました。".$e->getMessage());
		$db->rollback();
	}		
}

if ($date_list_string && $date_list_string1) {
try{
	$sql = "SELECT * FROM tbl_season_class_entry_subject WHERE date='{$date_list1[0]}' AND member_id=?";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($student['no']));
	$entry_subject = $stmt->fetchAll(PDO::FETCH_BOTH);
	
	$sql = "SELECT * FROM tbl_season_schedule WHERE date IN {$date_list_string1} AND member_no=\"{$student['no']}\"";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $db->query("SELECT * FROM tbl_teacher_presence_report WHERE member_no=\"{$student['no']}\"");
	$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rslt as $item) {
		$date = $item['year'].'/'.str_replace(array('月','日'),array('/',''),$item['date']);
		$date = preg_replace('|/(\d)/|', '/0$1/', $date);
		$date = preg_replace('|/(\d)$|', '/0$1', $date);
		if (array_search($date, $date_list)===false) continue;
		if (!$item['presence']) continue;
		$time = $item['time'];
		if (!array_filter($schedules, function($s){ global $date,$time; return ($s['date']==$date && $time=="{$s['stime']} ～ {$s['etime']}"); })) continue;
		$item['date'] = $date;
		$teacher_attend_report[] = $item;
	}
	
	$teacher_list = get_teacher_list($db, array(), array(), array(), 1);
	
	$sql = "SELECT * FROM tbl_season_class_entry_date WHERE member_id=? AND date IN ".$date_list_string." ORDER BY date ASC";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($student['no']));
	$dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$place = $dates[0]['place'];
	if ($place && array_search($place, $places_array)===false)	$places_array[]=$place;
	foreach ($dates as $entry) {
		if (array_search($entry['date'], $date_list1) !== false) $season_course_id = $entry['season_course_id'];
		$keys = array_keys(array_column($teacher_attend_report,'date'), $entry['date']);
		if (!count($keys)) {
		} else if (count($keys)==1) {
			$key = $keys[0];
			$entry['teacher_attend_status'] = $teacher_attend_report[$key]['presence'];
			if ($entry['attend_status'] && $entry['attend_status'] != $teacher_attend_report[$key]['presence']) {
				$errArray[] = "出席簿登録不一致：{$entry['date']}　".
						"事務データベース-{$entry['attend_status']} <--> ".
						"{$teacher_attend_report[$key]['time']}-{$teacher_list[$teacher_attend_report[$key]['teacher_id']]['name']}-{$teacher_attend_report[$key]['presence']}";
			}
		} else {
			$tmp_st = '';
			$tmp_st1 = '';
			foreach ($keys as $key) {
				if ($tmp_st1) {
					if ($tmp_st1 != $teacher_attend_report[$key]['presence']) {
						$tmp_st = '';
						$errFlag = 1;
						$errArray[] = "出席簿登録不一致：{$entry['date']}　".
								"{$tmp_time}-{$tmp_name}-{$tmp_st1} <-->".
								"{$teacher_attend_report[$key]['time']}-{$teacher_list[$teacher_attend_report[$key]['teacher_id']]['name']}-{$teacher_attend_report[$key]['presence']}";
					}
				} else {
					$tmp_st = $teacher_attend_report[$key]['presence']; $tmp_st1 = $tmp_st;
					$tmp_name = $teacher_list[$teacher_attend_report[$key]['teacher_id']]['name'];
					$tmp_time = $teacher_attend_report[$key]['time'];
				}
			}
			$entry['teacher_attend_status'] = $tmp_st;
			if ($entry['attend_status'] && $tmp_st && $entry['attend_status'] != $tmp_st) {
				$errArray[] = "出席簿登録不一致：{$entry['date']}　".
						"事務データベース-{$entry['attend_status']} <--> ".
						"{$tmp_time}-{$tmp_name}-{$tmp_st}";
			}
		}
		$entry_data[$entry['date']] = $entry; 
		$entry_data[$entry['date']]['entry_flag'] = $entry['furikae_flag']?0:1; 
		if ($entry['furikae_flag'] && $entry['attend_status']=='出席') $entry['attend_status'] = '振替';
	}
}catch (PDOException $e){
	$errFlag = 1;
	array_push($errArray, "エラーが発生しました。".$e->getMessage());
}
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
var current_stime1=1;
var current_stime2=2;
function selectChange(obj,num)
{
	var value = obj.options[obj.selectedIndex].value;
	var dates = document.getElementsByName('dates[]');
	var default_stime, default_etime;
	if (dates[num].innerHTML.indexOf('土') < 0) {
		default_stime = <?= $default_stime+1 ?>;
		default_etime = <?= $default_etime+1 ?>;
	} else {
		default_stime = <?= $default_stime_sat+1 ?>;
		default_etime = <?= $default_etime_sat+1 ?>;
	}
	if (obj.selectedIndex == 0) {
		document.getElementsByName('stime[]')[num].selectedIndex=0;
		document.getElementsByName('etime[]')[num].selectedIndex=0;
	} else {
		document.getElementsByName('stime[]')[num].selectedIndex=default_stime;
		document.getElementsByName('etime[]')[num].selectedIndex=default_etime;
	}
}
function stime1Change(obj)
{
	current_stime1 = obj.selectedIndex;
}
function stime2Change(obj)
{
	current_stime2 = obj.selectedIndex;
}
function test()
{
	var dates = document.getElementsByName('dates[]');
	var attend_st = document.getElementsByName('attend_status[]');
	var furikae_st = document.getElementsByName('furikae_status[]');
	var str='';
	alert('>'+str.substr(3)+'<');
}
function updateLessonTime()
{
	var entry_flag = document.getElementsByName('entry_flag[]');
	var furikae_flag = document.getElementsByName('furikae_flag[]');
	var stimes = document.getElementsByName('stime[]');
	var etimes = document.getElementsByName('etime[]');
	var dates = document.getElementsByName('dates[]');
	var default_stime, default_etime;
	for (var i=0;i<entry_flag.length;i++) {
		if (dates[i].innerHTML.indexOf('土') < 0) {
			default_stime = <?= $default_stime+1 ?>;
			default_etime = <?= $default_etime+1 ?>;
		} else {
			default_stime = <?= $default_stime_sat+1 ?>;
			default_etime = <?= $default_etime_sat+1 ?>;
		}
		if (entry_flag[i].checked || furikae_flag[i].checked) {
			if ((stimes[i].selectedIndex!=default_stime) || (etimes[i].selectedIndex!=default_etime)) {
				stimes[i].parentNode.style='background-color:#FFFFAA;';
			} else {
				stimes[i].parentNode.style='background-color:#FFFFFF;';
			}
		}
	}
}
function entryCheck( obj, num )
{
	var furikae_flag = document.getElementsByName('furikae_flag[]');
	var stimes = document.getElementsByName('stime[]');
	var etimes = document.getElementsByName('etime[]');
	var attend_st = document.getElementsByName('attend_status[]');
	var t_attend_st = document.getElementsByName('teacher_attend_status[]');
	var dates = document.getElementsByName('dates[]');
	var default_stime, default_etime;
	if (dates[num].innerHTML.indexOf('土') < 0) {
		default_stime = <?= $default_stime+1 ?>;
		default_etime = <?= $default_etime+1 ?>;
	} else {
		default_stime = <?= $default_stime_sat+1 ?>;
		default_etime = <?= $default_etime_sat+1 ?>;
	}
	if (obj.checked) {
		furikae_flag[num].style='display:none;';
		stimes[num].parentNode.style='';
		attend_st[num].parentNode.style='';
		stimes[num].selectedIndex=default_stime;
		etimes[num].selectedIndex=default_etime;
		t_attend_st[num].style='';
	} else {
		furikae_flag[num].style='';
		stimes[num].selectedIndex=0;
		etimes[num].selectedIndex=0;
		attend_st[num].selectedIndex=0;
		attendChange(attend_st[num],num,1);
		stimes[num].parentNode.style='display:none;';
		attend_st[num].parentNode.style='display:none;';
		t_attend_st[num].style='display:none;';
	}
}
function furikaeCheck( obj, num )
{
	var entry_flag = document.getElementsByName('entry_flag[]');
	var stimes = document.getElementsByName('stime[]');
	var etimes = document.getElementsByName('etime[]');
	var attend_st = document.getElementsByName('attend_status[]');
	var t_attend_st = document.getElementsByName('teacher_attend_status[]');
	var furikae_st = document.getElementsByName('furikae_status[]');
	var dates = document.getElementsByName('dates[]');
	var default_stime, default_etime;
	if (dates[num].innerHTML.indexOf('土') < 0) {
		default_stime = <?= $default_stime+1 ?>;
		default_etime = <?= $default_etime+1 ?>;
	} else {
		default_stime = <?= $default_stime_sat+1 ?>;
		default_etime = <?= $default_etime_sat+1 ?>;
	}
	if (obj.checked) {
		entry_flag[num].style='display:none;';
		stimes[num].parentNode.style='';
		attend_st[num].options[1].value='振替';
		attend_st[num].options[1].text='振替';
		attend_st[num].parentNode.style='';
		furikae_st[num].parentNode.style='';
		stimes[num].selectedIndex=default_stime;
		etimes[num].selectedIndex=default_etime;
		entry_flag[num].parentNode.parentNode.style='background-color:#CCCCFF;';
		t_attend_st[num].style='';
	} else {
		entry_flag[num].style='';
		stimes[num].selectedIndex=0;
		etimes[num].selectedIndex=0;
		attend_st[num].options[1].value='出席';
		attend_st[num].options[1].text='出席';
		attend_st[num].selectedIndex=0;
		attendChange(attend_st[num],num,0);
		furikae_st[num].selectedIndex=0;
		furikaeChange(furikae_st[num],num,0);
		updateFurikae();
		stimes[num].parentNode.style='display:none;';
		attend_st[num].parentNode.style='display:none;';
		furikae_st[num].parentNode.style='display:none;';
		entry_flag[num].parentNode.parentNode.style='background-color:#FFFFFF;';
		t_attend_st[num].style='display:none;';
	}
}
function updateFurikae()
{
	var dates = document.getElementsByName('dates[]');
	var attend_st = document.getElementsByName('attend_status[]');
	var furikae_st = document.getElementsByName('furikae_status[]');
	var furikae_menu = [];
	for (var i=0;i<attend_st.length;i++) {
		if (attend_st[i].value.indexOf('休み２')!=-1 && furikae_st[i].value=='') {
			furikae_menu.push('<= '+dates[i].innerHTML.substr(5,5));
		}
	}
	for (var i=0;i<furikae_st.length;i++) {
		if (attend_st[i].value.indexOf('休み')==-1) {
			var curval = furikae_st[i].value;
			var newval = '';
			while (furikae_st[i].firstChild) {
				furikae_st[i].removeChild(furikae_st[i].firstChild);
			}
			var op = document.createElement("option");
			op.value = "";
			op.text = "";
			furikae_st[i].appendChild(op);
			for (var j=0;j<furikae_menu.length;j++) {
				op = document.createElement("option");
				op.value = furikae_menu[j];
				op.text = furikae_menu[j];
				furikae_st[i].appendChild(op);
				if (curval == furikae_menu[j]) { newval=curval; }
			}
			if (newval!=curval) {
				op = document.createElement("option");
				op.value = curval;
				op.text = curval;
				furikae_st[i].appendChild(op);
				newval=curval;
			}
			furikae_st[i].value = newval;
		}
	}
}
function attendChange(obj,num,updateFlag)
{
	var furikae_flag = document.getElementsByName('furikae_flag[]');
	var dates = document.getElementsByName('dates[]');
	var furikae_st = document.getElementsByName('furikae_status[]');
	if (obj.value.indexOf('休み２')!=-1 && furikae_st[num].value.substr(0,3)!='=> ') {
		furikae_st[num].parentNode.style='';
		while (furikae_st[num].firstChild) {
			furikae_st[num].removeChild(furikae_st[num].firstChild);
		}
		var op = document.createElement("option");
		op.value = "";
		op.text = "";
		furikae_st[num].appendChild(op);
		furikae_st[num].value = '';
	}
	if (obj.value.indexOf('休み２')==-1 && furikae_st[num].value.substr(0,3)=='=> ') {
		var date1 = furikae_st[num].value.substr(3);
		var i;
		for (i=0;i<furikae_st.length;i++) {
			if (dates[i].innerHTML.substr(5,5)==date1) { break; }
		}
		furikae_st[i].firstElementChild.value = '';
		furikae_st[i].firstElementChild.text = '';
		furikae_st[i].value = '';
		furikae_st[num].firstElementChild.value = '';
		furikae_st[num].firstElementChild.text = '';
		furikae_st[num].value = '';
		furikae_st[num].parentNode.style='display:none;';
	}
	if (obj.value.indexOf('休み')==-1) {
		if (furikae_flag[num].checked) {
			obj.parentNode.parentNode.style = 'background-color:#CCCCFF;';
		} else {
			obj.parentNode.parentNode.style = 'background-color:#FFFFFF;';
		}
	}else {
		obj.parentNode.parentNode.style = 'background-color:#FFCCCC;';
	}
	if (updateFlag) { updateFurikae(); }
}
function furikaeChange(obj,num,updateFlag)
{
	var dates = document.getElementsByName('dates[]');
	var attend_st = document.getElementsByName('attend_status[]');
	var furikae_st = document.getElementsByName('furikae_status[]');
	var str = obj.value;
	var date1 = dates[num].innerHTML.substr(5,5);
	var i;
	for (i=0;i<furikae_st.length;i++) {
		if (furikae_st[i].value=='=> '+date1) {
			furikae_st[i].firstElementChild.value = '';
			furikae_st[i].firstElementChild.text = '';
			furikae_st[i].value = '';
		}
	}
	if (str) {
		var date0 = str.substr(3);
		var i;
		for	(i=0;i<dates.length;i++){
			if (dates[i].innerHTML.substr(5,5)==date0) { break; }
		}
		while (furikae_st[i].firstChild) {
			furikae_st[i].removeChild(furikae_st[i].firstChild);
		}
		var op = document.createElement("option");
		str = '=> '+date1;
		op.value = str;
		op.text = str;
		furikae_st[i].appendChild(op);
		furikae_st[i].value = str;
	} else {
	}
	if (updateFlag) { updateFurikae(); }
}
function inputCheck()
{
	var lesson_ids = document.getElementsByName('lesson_id[]');
	var subject_ids = document.getElementsByName('subject_id[]');
	var subject_time0s = document.getElementsByName('subject_time0[]');
	var i,j=0;
	if (!document.entry_form.season_course_id.selectedIndex) {
		alert("コース名を選択して下さい。"); return false;
	}
	for (i=0;i<10;i++) {
		if ((lesson_ids[i].selectedIndex) && (subject_ids[i].selectedIndex) && (subject_time0s[i].selectedIndex)) { j++;continue; }
		if ((!lesson_ids[i].selectedIndex) && (!subject_ids[i].selectedIndex) && (!subject_time0s[i].selectedIndex)) { continue; }
		j=0;
	}
	if (j==0) {
		alert("教室・科目・時間を選択して下さい。"); 
		return false;
	}
	return true;
}
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<body>

<div id="header">
	事務システム 
</div>

<div id="content" align="center">

<h2><?= $page_title ?>　生徒スケジュール・出欠</h2>

<?php if ($class_type == 'sat_sun_class') { ?>
<form method="post" name="back" action="season_class_entry.php">
	<input type="hidden" name="y" value="<?= $year ?>">
	<input type="hidden" name="m" value="<?= $month ?>">
	<input type="hidden" name="class_type" value="<?= $class_type ?>">
	<input type="hidden" name="no" value="<?=$student["no"]?>">
	<h3><input type="submit" name="button" value="前月">
	&nbsp;&nbsp;&nbsp;&nbsp;<?= $year?>年<?= $month ?>-<?= $m3 ?>月&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="submit" name="button" value="翌月"></h3>
</form>
<?php } ?>

<?php if (!$lms_mode) { ?>
<a href="student_fee_list.php">生徒一覧へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a>
<!--
<input type="button" value="test" onclick="test()">
-->
<br>

<?php
}
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red"><?= $error ?></font><br>
<?php
		}
?>
	<br>
<?php
	} else if ($action == 'add') {
		echo "<h4><font color=\"blue\">登録しました。</font></h4>";
		if ($msgArray) {
			echo "<font color=\"blue\">この登録変更により以下のスケジュールが削除されました。スケジュール調整してください。<br>";
			foreach ($msgArray as $msg) echo $msg."<br>";
			echo "<font>";
		}
	}
?>
<br>

<form method="post" name="entry_form" action="season_class_entry.php">
	<input type="hidden" name="y" value="<?= $year ?>">
	<input type="hidden" name="m" value="<?= $month ?>">
	<input type="hidden" name="class_type" value="<?= $class_type ?>">
	<input type="hidden" name="no" value="<?=$student["no"]?>">

	<div class="menu_box">
		<font color="black" size="-1">
		※&nbsp;編集完了後、登録ボタンを押してください。<br>
		</font>
	</div>

	<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録" onclick="return inputCheck()">
		<input type="reset" value="リセット">
	</td>
	</tr>
	</table>
	<br>
	<table id="form">
	<tr>
		<th>　生徒名：　</th>
		<td><?=$student["name"]?></td>
	</tr>
	</table>
	<table>
	<tr>
		<th>　コース：　</th>
		<td><?php disp_course_menu($season_course_list, "season_course_id", $season_course_id) ?></td>
	</tr>
	<tr>
		<th>　校舎：　</th>
		<td><?php disp_pulldown_menu($places_array, "place", $place) ?></td>
	</tr>
	</table>
	<br>
	<table border="1" id="table2">
	<tr>
		<th>受講</th>
		<th>振替</th>
		<th>日付</th>
		<th>授業時間</th>
		<th>出席簿</th>
		<th>出欠</th>
		<th width="90">振替</th>
	</tr>
 	<?php
	$i=0;
	foreach ($date_list as $datestring) {
		$entry_data0 = $entry_data[$datestring];
		if ($entry_data0["furikae_status"]) {
			if (substr($entry_data0["furikae_status"],0,3)=='=> ') {
				$furikae_list = array($entry_data0["furikae_status"]);
			} else {
				$furikae_list = array('',$entry_data0["furikae_status"]);
			}
		} else {
			$furikae_list = array('');
		}
		$bkcolor = "#FFFFFF";
		if ($entry_data0['furikae_flag']) { $bkcolor = "#CCCCFF"; }
		if (strpos($entry_data0["attend_status"],'休み')!==false) { $bkcolor = "#FFCCCC"; }
		$DOW = (int)date_format(date_create(str_replace('/', '-', $datestring)),'w');
		switch ($DOW) {
		case 0: $DOW = "<font color=red>(".$weekday_array[$DOW].")</font>"; break;
		case 6: $DOW = "<font color=blue>(".$weekday_array[$DOW].")</font>"; break;
		default: $DOW = "(".$weekday_array[$DOW].")";
		}
		
		if ($class_type == 'sat_sun_class' && array_search($datestring, $date_list1) === false) {
			$notdisplay = "display:none;";
		} else {
			$notdisplay = "";
		}
	?>
	<tr style="background-color:<?= $bkcolor ?>;<?= $notdisplay ?>">
		<td><input type="checkbox" name="entry_flag[]" value="<?= $i ?>"  <?= $entry_data0['furikae_flag']?"style=\"display:none;\"":"" ?> <?= $entry_data0['entry_flag']?"checked":"" ?> onclick="entryCheck(this,<?= $i ?>)"></td>
		<td><input type="checkbox" name="furikae_flag[]" value="<?= $i ?>"  <?= $entry_data0['entry_flag']?"style=\"display:none;\"":"" ?> <?= $entry_data0['furikae_flag']?"checked":"" ?> onclick="furikaeCheck(this,<?= $i ?>)"></td>
		<td name="dates[]" style="padding: 0px 10px 0px 10px;"><?= $datestring.' '.$DOW ?></td>
		<td <?= ($entry_data0['entry_flag'] || $entry_data0['furikae_flag'])?"":"style=\"display:none;\"" ?>>
			<?php disp_pulldown_menu($time_list, "stime[]", $entry_data0["stime"], "updateLessonTime()") ?>
			～
			<?php disp_pulldown_menu($time_list, "etime[]", $entry_data0["etime"], "updateLessonTime()") ?>
		</td>
		<td name="teacher_attend_status[]" <?= ($entry_data0['entry_flag'] || $entry_data0['furikae_flag'])?"":"style=\"display:none;\"" ?>>
			<?= $entry_data0['teacher_attend_status'] ?>
		</td>
		<td <?= ($entry_data0['entry_flag'] || $entry_data0['furikae_flag'])?"":"style=\"display:none;\"" ?>>
			<?php
				if ($entry_data0['furikae_flag'])
					disp_pulldown_menu($attendStatusList1, "attend_status[]", $entry_data0["attend_status"], "attendChange(this,$i,1)"); 
				else
					disp_pulldown_menu($attendStatusList,  "attend_status[]", $entry_data0["attend_status"], "attendChange(this,$i,1)"); 
			?>
		</td>
		<td <?= ($entry_data0['furikae_flag'] || strpos($entry_data0["attend_status"],'休み２')!==false)?"":"style=\"display:none;\"" ?>>
			<?php disp_pulldown_menu1($furikae_list, "furikae_status[]", $entry_data0["furikae_status"], "furikaeChange(this,$i,1)") ?>
		</td>
		<input type="hidden" name="season_course_id_array[]" value="<?= $entry_data0['season_course_id'] ?>">
	</tr>
	<?php
		$i++;
	}
	?>
	</table>
	<br>
	<div class="menu_box">
	<font color="black" size="-1">
	<span style="background-color:#FFCCCC;">＊ 背景色薄い赤は休みの日です。</span><br>
	<span style="background-color:#CCCCFF;">＊ 背景色薄い青は振替日です。</span><br>
	<span style="background-color:#FFFFAA;">＊ 背景色薄い黄色は"11:00～16:00"、"13:00～18:00"以外の時間帯です。</span><br>
	</font>
	</div>
	<br>
	<br>
	<table border="1" id="table1">
	<tr>
		<th colspan=3>個別授業科目別時間配分</th>
	</tr>
	<tr>
		<th>教室</th>
		<th>科目</th>
		<th>時間</th>
	</tr>
 	<?php
	if ($date_list_string1) {
	for ($i=0;$i<10;$i++) {
	?>
	<tr>
		<td>
			<?php disp_lesson_menu($lesson_list, "lesson_id[]", $entry_subject[$i]["lesson_id"]); ?>
		</td>
		<td>
			<?php disp_subject_menu($subject_list, "subject_id[]", $entry_subject[$i]["subject_id"]); ?>
		</td>
		<td>
			<?php disp_pulldown_menu($time_list0, "subject_time0[]", floor($entry_subject[$i]["subject_time"])); ?>.
			<?php disp_pulldown_menu($time_list1, "subject_time1[]", $entry_subject[$i]["subject_time"]*10%10); ?>
		</td>
	</tr>
 	<?php
	}}
	?>
	</table>

</form>

<?php if ($lms_mode) { ?>
<br><input type="button" onclick="document.location='student_fee_list.php?student_id=<?=$student["no"]?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php } ?>
</div>
<script type = "text/javascript">
updateFurikae();
updateLessonTime();
</script>
</body>
</html>
