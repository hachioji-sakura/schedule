<?php
ini_set( 'display_errors', 0 );
require_once(dirname(__FILE__)."/const/const.inc");
require_once(dirname(__FILE__)."/func.inc");
require_once(dirname(__FILE__)."/const/login_func.inc");
if (!$teacher_acount) { $result = check_user($db, "1"); }

$errFlag = 0;
$errArray = array();
unset($subject_list[0]);

$class_type = $_GET['class_type'];
if (!$class_type) $class_type = $_POST['class_type'];
if ($class_type=='sat_sun_class') {
	$year = date("Y");
	$month = date("n");
//	if ($month%2 == 0) {
//		$year = date("Y",strtotime("first day of last month"));
//		$month = date("n",strtotime("first day of last month"));
//	}
	$str0 = sprintf('%04d/%02d',$year,$month);
	if (!$teacher_acount)
		$date_list1 = $date_list;
	else
		$date_list1 = array();
	foreach ($date_list as $date0)
		if ($date0 >= $str0) { $date_list1 = $date_list; break; }
	$sat_sun_class_date_list = array_values(array_filter( $sat_sun_class_date_list, function($s)use($str0){$v=substr($s,0,7);return ($v>=$str0);} ));
	$date_list = array_unique(array_merge($date_list1,$sat_sun_class_date_list));
	sort($date_list);
	$date_list_string = ($date_list)? "('".implode("','",$date_list)."')" : "";}
if ($class_type == 'sat_sun_class') {
	$page_title = "期間講習・土日講習";
} else {
	$page_title = $season_class_title;
}

if ($teacher_acount) {
	$teacher["no"]	= $_SESSION['ulogin']['teacher_id'];
} else {
	$teacher["no"] = trim($_GET["no"]);
	if ($teacher["no"] == '') { $teacher["no"] = trim($_POST["no"]); }
}
$teacher = reset(get_teacher_list($db, array("tbl_teacher.no = ?"), array($teacher["no"])));

$lesson_array = $_POST["lesson_id"];
$subject_array = $_POST["subject_id"];

if ($class_type == 'sat_sun_class') {

foreach ( $_POST["timecheck"] as $item) {
	$array0 = explode('_',$item);
	$time_array[$array0[0]][] = $array0[1];
}

} else {
$stime_array = $_POST["stime"];
$etime_array = $_POST["etime"];
}

if (isset($_POST['add'])) {
	$action = 'add';
} else {
	$action = "";
}

if ($class_type == 'sat_sun_class') {
	
if ($action == 'add') {
	$db->beginTransaction();
	try{
		$sql = "DELETE FROM tbl_teacher_subject WHERE tbl_teacher_subject.no=?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($teacher['no']));
		$sql = "DELETE FROM tbl_season_class_teacher_entry1 WHERE date IN {$date_list_string} AND no=?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($teacher['no']));
		
		for ($i=0;$i<10;$i++) {
			if ( $lesson_array[$i] != "" && $subject_array[$i] != "" ) {
				$sql = "INSERT INTO tbl_teacher_subject VALUES (?, ?, ?, now(), now())";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($teacher["no"], $lesson_array[$i], $subject_array[$i]));
			}
		}
		foreach ($date_list as $date ) {
			if ( $time_array[$date] ) {
				$sql = "INSERT INTO tbl_season_class_teacher_entry1 VALUES (?, ?, ?, now(), now())";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($teacher["no"], $date, implode(',',$time_array[$date])));
			}
		}
		$db->commit();
	}catch (PDOException $e){
		$errFlag = 1;
		array_push($errArray, "エラーが発生しました。".$e->getMessage());
		$db->rollback();
	}		
}

try{
	$sql = "SELECT * FROM tbl_teacher_subject WHERE no=?";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($teacher['no']));
	$teacher_subject = $stmt->fetchAll(PDO::FETCH_BOTH);
	
	$sql = "SELECT * FROM tbl_season_class_teacher_entry1 WHERE no=? AND date IN ".$date_list_string." ORDER BY date ASC";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($teacher['no']));
	$season_class_teacher_entry = $stmt->fetchAll(PDO::FETCH_BOTH);
	foreach ($season_class_teacher_entry as $entry) { $entry_array[$entry['date']] = $entry; }
}catch (PDOException $e){
	$errFlag = 1;
	array_push($errArray, "エラーが発生しました。".$e->getMessage());
}

} else {
	
if ($action == 'add') {
	$db->beginTransaction();
	try{
		$sql = "DELETE FROM tbl_teacher_subject WHERE tbl_teacher_subject.no=?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($teacher['no']));
		$sql = "DELETE FROM tbl_season_class_teacher_entry WHERE date IN {$date_list_string} AND no=?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($teacher['no']));
		
		for ($i=0;$i<10;$i++) {
			if ( $lesson_array[$i] != "" && $subject_array[$i] != "" ) {
				$sql = "INSERT INTO tbl_teacher_subject VALUES (?, ?, ?, now(), now())";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($teacher["no"], $lesson_array[$i], $subject_array[$i]));
			}
		}
		$i=0;
		foreach ($date_list as $date ) {
			if ( $stime_array[$i] != "" && $etime_array[$i] != "" ) {
				$sql = "INSERT INTO tbl_season_class_teacher_entry VALUES (?, ?, ?, ?, now(), now())";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($teacher["no"], $date, $stime_array[$i], $etime_array[$i]));
			}
			$i++;
		}
		$db->commit();
	}catch (PDOException $e){
		$errFlag = 1;
		array_push($errArray, "エラーが発生しました。".$e->getMessage());
		$db->rollback();
	}		
}

try{
	$sql = "SELECT * FROM tbl_teacher_subject WHERE no=?";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($teacher['no']));
	$teacher_subject = $stmt->fetchAll(PDO::FETCH_BOTH);
	
	$sql = "SELECT * FROM tbl_season_class_teacher_entry WHERE no=? AND date IN ".$date_list_string." ORDER BY date ASC";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($teacher['no']));
	$season_class_teacher_entry = $stmt->fetchAll(PDO::FETCH_BOTH);
	foreach ($season_class_teacher_entry as $entry) { $entry_array[$entry['date']] = $entry; }
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
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
var copy_stime=1,copy_etime=<?= $default_etime+1 ?>;
function time_copy(i) {
	copy_stime = document.getElementsByName('stime[]')[i].selectedIndex;
	copy_etime = document.getElementsByName('etime[]')[i].selectedIndex;
}
function time_paste(i) {
	document.getElementsByName('stime[]')[i].selectedIndex = copy_stime;
	document.getElementsByName('etime[]')[i].selectedIndex = copy_etime;
}
function time_clear(i) {
	document.getElementsByName('stime[]')[i].selectedIndex = 0;
	document.getElementsByName('etime[]')[i].selectedIndex = 0;
}
function normal_select(obj,sat_sun_flag) {
	var timechecks=document.getElementsByName('timecheck[]');
	var dateWOD=obj.parentNode.parentNode.firstElementChild.innerText;
	var date=dateWOD.substr(0,10);
	if (obj.value=='通常時間') { 
		if (sat_sun_flag) {
			if (dateWOD.indexOf('土')!=-1) {
				for (var i=0;i<timechecks.length;i++) {
					if (timechecks[i].value.indexOf(date+'_14')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_15')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_16')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_17')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_18')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
				}
			} else {
				for (var i=0;i<timechecks.length;i++) {
					if (timechecks[i].value.indexOf(date+'_11')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_12')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_13')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_14')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_15')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
				}
			}
		} else {
			if (dateWOD.indexOf('2021/03/20')==-1 && dateWOD.indexOf('日')==-1) {
				for (var i=0;i<timechecks.length;i++) {
					if (timechecks[i].value.indexOf(date+'_14')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_15')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_16')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_17')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_18')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
				}
			} else {
				for (var i=0;i<timechecks.length;i++) {
					if (timechecks[i].value.indexOf(date+'_11')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_12')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_13')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_14')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
					if (timechecks[i].value.indexOf(date+'_15')!=-1) {timechecks[i].checked=true;timechecks[i].parentNode.style='background-color:#ddddff';}
				}
			}
		}
		obj.value='クリア';
	} else {
		for (var i=0;i<timechecks.length;i++) {
			if (timechecks[i].value.indexOf(date)!=-1) {timechecks[i].checked=false;timechecks[i].parentNode.style='';}
		}
		obj.value='通常時間';
	}
}
function tcheck(obj){
	if (obj.checked) {
		obj.parentNode.style='background-color:#ddddff';
	} else {
		obj.parentNode.style='';
	}
}
//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<body>

<?php if (!$teacher_acount) { ?>

<div id="header">
	事務システム 
</div>
<div id="content" align="center">
<h2><?= $page_title ?></h2>
<h3>講師登録</h3>
<?php if (!$lms_mode) { ?>
<a href="teacher_list.php">講師一覧へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br>

<?php }} else { ?>

<div id="content" align="center">
<h2><?= $page_title ?></h2>
<a href="menu.php">メニューへ戻る</a><br>

<?php } ?>

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
	} else if ($action == 'add') {
		echo "<h4><font color=\"blue\">登録しました。</font></h4>";
	}
?>


<form method="post" name="student_form" action="season_class_teacher_entry.php">
	<input type="hidden" name="no" value="<?=$teacher["no"]?>">
	<input type="hidden" name="class_type" value="<?=$class_type?>">

	<div class="menu_box">
		<font color="black" size="-1">
		※&nbsp;編集完了後、登録ボタンを押してください。<br>
		</font>
	</div>


	<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録">
		<input type="reset" value="リセット">
	</td>
	</tr>
	</table>

	<br>

	<table id="form">
	<tr>
		<th>　講師名：　</th>
		<td><?=$teacher["name"]?></td>
	</tr>
	</table>

	<br>
	<table border="1" id="table1">
	<tr>
		<th colspan=3>担当科目</th>
	</tr>
	<tr>
		<th>教室</th>
		<th>科目</th>
	</tr>
 	<?php
	for ($i=0;$i<10;$i++) {
	?>
	<tr>
		<td>
			<?php disp_lesson_menu($lesson_list, "lesson_id[]", $teacher_subject[$i]["lesson_id"]); ?>
		</td>
		<td>
			<?php disp_subject_menu($subject_list, "subject_id[]", $teacher_subject[$i]["subject_id"]); ?>
		</td>
	</tr>
 	<?php
	}
	?>
	</table>
	<br>
	<table border="1" id="table2">
 	<?php
	if ($class_type == 'sat_sun_class') {
	?>
	<tr>
		<th colspan=22>勤務可能日時</th>
	</tr>
	<tr>
		<th>日付</th><th></th>
		<th>11:00</th><th>11:30</th><th>12:00</th><th>12:30</th><th>13:00</th><th>13:30</th><th>14:00</th><th>14:30</th>
		<th>15:00</th><th>15:30</th><th>16:00</th><th>16:30</th><th>17:00</th><th>17:30</th>
	<?php if ($date_list[0] <= '2020/10/31') { ?>
		<th>18:00</th><th>18:30</th><th>19:00</th><th>19:30</th><th>20:00</th><th>20:30</th> 
	<?php } else { ?>
		<th>18:00</th><th>18:30</th>
 	<?php } ?>
	</tr>
 	<?php
	} else {
	?>
	<tr>
		<th colspan=5>勤務可能日時</th>
	</tr>
	<tr>
		<th>日付</th>
		<th colspan=4>時間帯</th>
	</tr>
 	<?php
	}
	$i = 0;
	foreach ($date_list as $date) {
		$date0 = str_replace('/','-',$date);
		$datetime = date_create($date0);
		$week = array("日", "月", "火", "水", "木", "金", "土");
		$youbi = "(".$week[(int)date_format($datetime, 'w')].")";
		if (strpos($youbi,"土")) { $youbi = "<font color=blue>".$youbi."</font>"; }
		if (strpos($youbi,"日")) { $youbi = "<font color=red>".$youbi."</font>"; }
		
		if ($class_type == 'sat_sun_class') {
			$time_array=array();
			$array0=explode(',',$entry_array[$date]['times']);
			foreach ($array0 as $time0) $time_array[$time0]=1;
			$sat_sun_flag = (in_array($date,$sat_sun_class_date_list))? 1: 0;
	?>
	<tr>
		<td><?= $date ?><?= $youbi ?></td>
		<td><input type="button" value="通常時間" onclick="normal_select(this,<?= $sat_sun_flag ?>)"></td>
	<?php
		if ($date <= '2020/10/31') {
	?>
		<td style="<?= $time_array['11:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_11:00" onclick="tcheck(this)" <?= $time_array['11:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['11:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_11:30" onclick="tcheck(this)" <?= $time_array['11:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['12:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_12:00" onclick="tcheck(this)" <?= $time_array['12:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['12:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_12:30" onclick="tcheck(this)" <?= $time_array['12:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['13:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_13:00" onclick="tcheck(this)" <?= $time_array['13:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['13:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_13:30" onclick="tcheck(this)" <?= $time_array['13:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['14:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_14:00" onclick="tcheck(this)" <?= $time_array['14:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['14:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_14:30" onclick="tcheck(this)" <?= $time_array['14:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['15:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_15:00" onclick="tcheck(this)" <?= $time_array['15:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['15:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_15:30" onclick="tcheck(this)" <?= $time_array['15:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['16:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_16:00" onclick="tcheck(this)" <?= $time_array['16:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['16:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_16:30" onclick="tcheck(this)" <?= $time_array['16:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['17:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_17:00" onclick="tcheck(this)" <?= $time_array['17:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['17:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_17:30" onclick="tcheck(this)" <?= $time_array['17:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['18:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_18:00" onclick="tcheck(this)" <?= $time_array['18:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['18:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_18:30" onclick="tcheck(this)" <?= $time_array['18:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['19:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_19:00" onclick="tcheck(this)" <?= $time_array['19:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['19:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_19:30" onclick="tcheck(this)" <?= $time_array['19:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['20:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_20:00" onclick="tcheck(this)" <?= $time_array['20:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['20:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_20:30" onclick="tcheck(this)" <?= $time_array['20:30']?"checked":"" ?>></td>
	<?php
		} else if ((strpos($youbi,"土") && $sat_sun_flag) ||
			(!$sat_sun_flag && (!strpos($youbi,"日") && $date!="2021/03/20")))
		{
	?>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td style="<?= $time_array['14:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_14:00" onclick="tcheck(this)" <?= $time_array['14:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['14:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_14:30" onclick="tcheck(this)" <?= $time_array['14:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['15:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_15:00" onclick="tcheck(this)" <?= $time_array['15:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['15:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_15:30" onclick="tcheck(this)" <?= $time_array['15:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['16:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_16:00" onclick="tcheck(this)" <?= $time_array['16:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['16:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_16:30" onclick="tcheck(this)" <?= $time_array['16:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['17:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_17:00" onclick="tcheck(this)" <?= $time_array['17:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['17:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_17:30" onclick="tcheck(this)" <?= $time_array['17:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['18:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_18:00" onclick="tcheck(this)" <?= $time_array['18:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['18:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_18:30" onclick="tcheck(this)" <?= $time_array['18:30']?"checked":"" ?>></td>
	<?php
		} else {
	?>
		<td style="<?= $time_array['11:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_11:00" onclick="tcheck(this)" <?= $time_array['11:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['11:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_11:30" onclick="tcheck(this)" <?= $time_array['11:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['12:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_12:00" onclick="tcheck(this)" <?= $time_array['12:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['12:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_12:30" onclick="tcheck(this)" <?= $time_array['12:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['13:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_13:00" onclick="tcheck(this)" <?= $time_array['13:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['13:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_13:30" onclick="tcheck(this)" <?= $time_array['13:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['14:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_14:00" onclick="tcheck(this)" <?= $time_array['14:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['14:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_14:30" onclick="tcheck(this)" <?= $time_array['14:30']?"checked":"" ?>></td>
		<td style="<?= $time_array['15:00']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_15:00" onclick="tcheck(this)" <?= $time_array['15:00']?"checked":"" ?>></td>
		<td style="<?= $time_array['15:30']?"background-color:#ddddff":"" ?>"><input type="checkbox" name="timecheck[]" value="<?= $date ?>_15:30" onclick="tcheck(this)" <?= $time_array['15:30']?"checked":"" ?>></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
		<td></td>
	<?php
		}
	?>
	</tr>
	<?php
		} else {
	?>
	<tr>
		<td>
			<?= $date ?><?= $youbi ?>
		</td>
		<td>
			<?php disp_pulldown_menu($time_list, "stime[]", $entry_array[$date]["stime"]); ?>
			～
			<?php disp_pulldown_menu($time_list, "etime[]", $entry_array[$date]["etime"]); ?>
		</td>
		<td><input type="button" value="コピー" onclick="time_copy(<?= $i ?>)"></td>
		<td><input type="button" value="貼付" onclick="time_paste(<?= $i ?>)"></td>
		<td><input type="button" value="削除" onclick="time_clear(<?= $i ?>)"></td>
	</tr>
 	<?php
		}
		$i++;
	}
	?>
	</table>
	
<?php if ($lms_mode) { ?>
<br><input type="button" onclick="document.location='teacher_list.php?teacher_id=<?='1'.str_pad($teacher["no"], 5, 0, STR_PAD_LEFT)?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php } ?>

</form>

</div>

</body>
</html>
