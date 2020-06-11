<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

//$year0 = date('Y',strtotime('-1 month'));
//$month0 = date('n',strtotime('-1 month'));
$year0 = date('Y');
$month0 = date('n');

$course_list = get_course_list($db);
$teacher_list = get_teacher_list($db);

$errArray = array();
//$initArray = array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "course_id"=>"", "teacher_id"=>"", "fee"=>"", "salary_for_an_hour"=>"", "family_minus_price"=>"");
$initArray = array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "course_id"=>"", "teacher_id"=>"", "fee"=>"", "family_minus_price"=>"", "additional_fee"=>"");
$m_initArray = array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "course_id"=>"", "teacher_id"=>"", "fee"=>"", "minus_price"=>"");

$student = array();
$student["no"] = trim($_POST["no"]);

if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['add_fee_fix'])) {
	$action = 'add'; $fee_fix = 1;
} else if (isset($_POST['delete'])) {
	$action = 'delete';
} else if (isset($_POST['delete_fee_no']) && $_POST['delete_fee_no']!='') {
	$action = 'delete_fee';
	$delete_fee_no = $_POST['delete_fee_no'];
} else if (isset($_POST['delete_m_fee_no']) && $_POST['delete_m_fee_no']!='') {
	$action = 'delete_fee';
	$delete_m_fee_no = $_POST['delete_m_fee_no'];
} else {
	$action = "";
}

$student["grade_adj"]=0;

if ($action == 'add' || $action == 'delete' || $action == 'delete_fee') {
	//$student["sei"] = trim($_POST["sei"]);
	//$student["mei"] = trim($_POST["mei"]);
	$student["name"] = trim($_POST["name"]);
	$student["furigana"] = trim($_POST["furigana"]);
	$student["grade"] = array_search($_POST["grade"],$grade_list);
	$student["membership_fee"] = trim($_POST["membership_fee"]);
	$student["sheet_id"] = trim($_POST["sheet_id"]); // 任意項目
	$student["cid"] = trim($_POST["cid"]); // 任意項目
	$student["del_flag"] = trim($_POST["del_flag"]);
	$student["tax_flag"] = trim($_POST["tax_flag"]);
	$student["jyukensei"] = $_POST["jyukensei"]?1:0;

	$student['birth_year'] =  $_POST['birth_year'];
	$student['birth_month'] = $_POST['birth_month'];
	$student['birth_day'] =   $_POST['birth_day'];
	$student['grade_adj'] =   $_POST['grade_adj'];
	$student['fee_free'] =    $_POST['fee_free'];
	$student['yuge_price'] =  $_POST['yuge_price'];
	$student['gender'] =      $_POST['gender'];
	$student['mail_address'] =      trim($_POST['mail_address']);
	
	if (empty($student["grade"]) === true || $student["grade"] == "") {
		$student["grade"] = null;
	}
	if (empty($student["cid"]) === true || $student["cid"] == "") {
		$student["cid"] = null;
	}
	if (empty($student["sheet_id"]) === true || $student["sheet_id"] == "") {
		$student["sheet_id"] = null;
	}

	// 20150813
	//$row_no_array = $_POST["row_no"];
	$fee_no_array = $_POST["fee_no"];
	$lesson_id_array = $_POST["lesson_id"];
	$subject_id_array = $_POST["subject_id"];
	//$type_id_array = $_POST["type_id"];
	$course_id_array = $_POST["course_id"];
	$teacher_id_array = $_POST["teacher_id"];
	$fee_array = $_POST["fee"];
	$family_minus_price_array = $_POST["family_minus_price"];
	// 20160625 1時間未満割増料金を追加
	$additional_fee_array = $_POST["additional_fee"];
	$temp_flag_array = $_POST["temp_flag"];
	
	$fee_list = array();
	//foreach ($row_no_array as $row_no) {
	// 20150813 真ん中の行を取消した後登録しようとすると、必須入力エラーが出て登録できなかったため修正した。
	foreach ($fee_no_array as $no => $fee_no) {
		$row = array();
		// 2015/05/26 暫定対応
		//if ($lesson_id_array[$no] != "" || $fee_array[$no] != "") {
		// 20160525修正 $teacher_id_array[$no]の初期値は""String(0)になっていたため修正
		//if ($lesson_id_array[$no] != "" || $subject_id_array[$no] != "0" || $course_id_array[$no] != "" || $teacher_id_array[$no] != "0" || $fee_array[$no] != "") {
		if ($lesson_id_array[$no] != "" || $subject_id_array[$no] != "0" || $course_id_array[$no] != "" || $teacher_id_array[$no] != "" || $fee_array[$no] != "") {
			$row["fee_no"] = $fee_no_array[$no];
			$row["lesson_id"] = $lesson_id_array[$no];
			$row["subject_id"] = $subject_id_array[$no];
			$row["course_id"] = $course_id_array[$no];
			$row["teacher_id"] = $teacher_id_array[$no];
			$row["fee"] = $fee_array[$no];
			$row["family_minus_price"] = $family_minus_price_array[$no];
			if (isset($row["family_minus_price"]) === false || $row["family_minus_price"] == "") {
				$row["family_minus_price"] = 0;
			}
			// 20160625 1時間未満割増料金を追加
			$row["additional_fee"] = $additional_fee_array[$no];
			if (isset($row["additional_fee"]) === false || $row["additional_fee"] == "") {
				$row["additional_fee"] = 0;
			}
			$row["temp_flag"] = $temp_flag_array[$no];
			if ($fee_fix) $row["temp_flag"] = 0;
			$fee_list[] = $row;
		}
	}
	$student["fee_list"] = $fee_list;

	$m_fee_no_array = $_POST["m_fee_no"];
	$m_lesson_id_array = $_POST["m_lesson_id"];
	$m_subject_id_array = $_POST["m_subject_id"];
	$m_course_id_array = $_POST["m_course_id"];
	$m_fee_array = $_POST["m_fee"];
	$m_minus_price_array = $_POST["m_minus_price"];
	
	$m_fee_list = array();
	foreach ($m_fee_no_array as $no => $fee_no) {
		$row = array();
		if ($m_lesson_id_array[$no] != "" || $m_subject_id_array[$no] != "0" || $m_course_id_array[$no] != "" || $m_fee_array[$no] != "") {
			$row["fee_no"] = $m_fee_no_array[$no];
			$row["lesson_id"] = $m_lesson_id_array[$no];
			$row["subject_id"] = $m_subject_id_array[$no];
			$row["course_id"] = $m_course_id_array[$no];
			$row["fee"] = $m_fee_array[$no];
			$row["minus_price"] = $m_minus_price_array[$no];
			if (isset($row["minus_price"]) === false || $row["minus_price"] == "") {
				$row["minus_price"] = 0;
			}
			$m_fee_list[] = $row;
		}
	}
	$student["m_fee_list"] = $m_fee_list;

}

if ($action == 'add') {
// 更新処理

	// 入力チェック処理
	$result = check_student($db, $errArray, $student);
	$result = check_fee_list($db, $errArray, $student["no"], $student["fee_list"]);
//var_dump($errArray);

	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = update_student($db, $student);
			if (!$result) $errFlag = 1;
			$result = edit_fee($db, $student["no"], $fee_list, $errArray);
			if (!$result) $errFlag = 1;
			$result = edit_m_fee($db, $student["no"], $m_fee_list, $errArray);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, "登録中にエラーが発生しました。.$e->getMessage()");
		}
		if ($errFlag == 0) {
			$db->commit();
			//header('Location: student_fee_list.php?sort_type=2');
			if ($lms_mode) {
				header("Location: student_fee_list.php?student_id={$student['no']}");
			} else {
				header('Location: student_fee_list.php?sort_type=1');
			}
			exit;
		} else {
			$db->rollback();
		}
	}

	// エラーが発生した場合、編集画面を再表示する
	// 再表示時に、料金の新規追加行を表示する
	// 20160525修正 "teacher_id"=>"0"を""に変更
	//array_push($student["fee_list"], array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "course_id"=>"", "teacher_id"=>"0", "fee"=>"", "family_minus_price"=>""));
	array_push($student["fee_list"], $initArray);
	array_push($student["m_fee_list"], $m_initArray);

} else if ($action == 'delete') {
// 削除処理

	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_student($db, $student["no"]);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, "削除中にエラーが発生しました。".$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			header('Location: student_fee_list.php?sort_type=1');
			exit();
		} else {
			$db->rollback();
		}
	}

	// エラーが発生した場合、編集画面を再表示する
	// 再表示時に、料金の新規追加行を表示する
	// 20160525修正 "teacher_id"=>"0"を""に変更
	//array_push($student["fee_list"], array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "course_id"=>"", "teacher_id"=>"0", "fee"=>"", "family_minus_price"=>""));
	array_push($student["fee_list"], $initArray);
	array_push($student["m_fee_list"], $m_initArray);

} else if ($action == 'delete_fee') {
// 料金を削除

	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			if ($delete_fee_no) {
				$result = delete_fee($db, $delete_fee_no);
				if (!$result) $errFlag = 1;
			}
			if ($delete_m_fee_no) {
				$result = delete_m_fee($db, $delete_m_fee_no);
				if (!$result) $errFlag = 1;
			}
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, "削除中にエラーが発生しました。$e->getMessage()");
		}
		if ($errFlag == 0) {
			$db->commit();
			// 料金の行を削除した時、編集画面を再表示する
			$student = get_member($db, array("tbl_member.no = ?"), array($student["no"]));
			//array_push($student["fee_list"], array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "type_id"=>"", "fee"=>""));
		} else {
			$db->rollback();
		}
	}

	// エラーが発生した場合、料金の行を削除した後、編集画面を再表示する
	// 再表示時に、料金の新規追加行を表示する
	// 20160525修正 "teacher_id"=>"0"を""に変更
	//array_push($student["fee_list"], array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "course_id"=>"", "teacher_id"=>"0", "fee"=>"", "family_minus_price"=>""));
	array_push($student["fee_list"], $initArray);
	array_push($student["m_fee_list"], $m_initArray);

} else {
// 初期表示処理
	if ($student["no"] && $student["no"]) {
		$student = get_member($db, array("tbl_member.no = ?"), array($student["no"]));
		//
		//if (count($student["fee_list"]) == 0) {
//var_dump($student);
			array_push($student["fee_list"], $initArray);
			array_push($student["m_fee_list"], $m_initArray);
     	//$student["fee_list"][0] = array("fee_no"=>"", "lesson_id"=>"", "subject_id"=>"0", "type_id"=>"", "fee"=>"");
		//}
	}
}

//$tmp_student = get_member($db, array("tbl_member.no = ?"), array($student["no"]));
//$db_cnt = count($tmp_student["fee_list"]);
//echo $db_cnt."件";

if ($lms_mode) $display = 'style="display:none;"';
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
function delete_student() {
	result = window.confirm("生徒情報とその生徒の料金情報を削除します。\nよろしいですか？");
	if (result) {
		document.forms["student_form"].submit();
	}
}
function delete_fee(row_no, fee_no) {
	document.forms["student_form"].elements["delete_fee_no"].value = fee_no;
	result = window.confirm((row_no+1)+"行目の料金情報を削除します。\nよろしいですか");
	if (result) {
		document.forms["student_form"].submit();
	} else {
		document.forms["student_form"].elements["delete_fee_no"].value = '';
	}
}
function m_delete_fee(row_no, fee_no) {
	document.forms["student_form"].elements["delete_m_fee_no"].value = fee_no;
	result = window.confirm("月謝 "+(row_no+1)+"行目の料金情報を削除します。\nよろしいですか");
	if (result) {
		document.forms["student_form"].submit();
	} else {
		document.forms["student_form"].elements["delete_m_fee_no"].value = '';
	}
}
var grade_list=[<?php foreach($grade_list as $val){echo('"'.$val.'",');} ?> 0];
function set_grade() {
	var birth_year  = document.forms["student_form"].elements["birth_year"].value;
	var birth_month = document.forms["student_form"].elements["birth_month"].value;
	var birth_day   = document.forms["student_form"].elements["birth_day"].value;
	if (birth_year > 0 && birth_month > 0 && birth_day > 0) {
		var cdate = new Date();
		var grade = cdate.getFullYear()-5-birth_year;
		if (birth_month<4) { grade++; }
		if (birth_month==4 && birth_day==1) { grade++; }
		if (cdate.getMonth()<3) { grade--; }
		grade += Number(document.forms["student_form"].elements["grade_adj"].value);
		if (grade<1) { grade=1; }
		if (grade>14) { grade=14; }
		document.forms["student_form"].elements["grade"].value = grade_list[grade];
	} else {
		document.forms["student_form"].elements["grade"].value = grade_list[0];
	}
}

/*
function delete_row(row_no) {
	//document.forms["student_form"].elements["delete_fee_no"].value = fee_no;
	//result = window.confirm((row_no+1)+"行目の料金情報を削除します。\nよろしいですか");
alert(row_no);
  if (row_no == 1) {
	// （ＤＢに）未登録でも、1行目であれば削除できない
		alert('1行目は取り消せません。');
	} else {
		document.getElementById("fee_table").deleteRow(row_no);
	}
}
*/

function default_fee(obj,fee) {
	obj.parentNode.previousElementSibling.children[0].value=fee;
}

//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
</head>
<body>

<div id="header">
	事務システム 
</div>

<div id="content" align="center">

<?php if ($lms_mode) { ?>
<h3>生徒の受講料登録</h3>
<?php } else { ?>
<h3>生徒の登録 - 更新・削除</h3>

<a href="student_fee_list.php">生徒一覧へ</a>&nbsp;&nbsp;
<a href="check_cid.php">宛先登録チェックへ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br><br>

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
	}
?>


<form method="post" name="student_form" action="student_fee_edit.php">
	<input type="hidden" name="no" value="<?=$student["no"]?>">
	<input type="hidden" name="del_flag" value="<?=$student["del_flag"]?>">

	<input type="hidden" name="delete_fee_no" value="">
	<input type="hidden" name="delete_m_fee_no" value="">

	<div class="menu_box" <?=$display?>>
		<font color="black" size="-1">
		※&nbsp;編集する場合は、生徒情報とその生徒の料金情報を入力して、登録ボタンを押してください。<br>
		※&nbsp;退塾者は、<font color="red">削除をせずに、</font>ステータスで「前生徒」を選択して登録してください。<br>
		&nbsp;&nbsp;&nbsp;&nbsp;データ取り込み時に誤って登録された不要データのみ、削除してください。
		</font>
	</div>

	<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録" <?=$display?>>
		<input type="submit" name="add_fee_fix" value="受講料確定登録" onclick="return confirm('仮登録受講料を確定します。よろしいですか？')">
		<input type="submit" name="delete" value="削除" onclick="delete_student()" <?=$display?>><?php /* buttonだとname=deleteが送信できないので、submitに*/ ?>
		<input type="reset" value="リセット">
	</td>
	</tr>
	</table>

	<br>

	<table id="form">
<!--
	<tr>
	<th><font color="red">*</font>&nbsp;姓</th><td><input type="text" name="sei" size="20" value="<?=$student["sei"]?>"></td>
	</tr>
	<tr>
	<th><font color="red">*</font>&nbsp;名</th><td><input type="text" name="mei" size="20" value="<?=$student["mei"]?>"></td>
	</tr>
-->
	<tr>
		<th>名前</th>
		<td>
			<input type="hidden" name="name" value="<?=$student["name"]?>"><?=$student["name"]?>
		</td>
	</tr>
	<tr <?=$display?>>
		<th>ふりがな</th>
		<td>
			<input type="hidden" name="furigana" value="<?=$student["furigana"]?>"><?=$student["furigana"]?>
		</td>
	</tr>
	<tr>
	<th><font color="red">*</font>&nbsp;月会費</th>
	<td>
		<input type="text" name="membership_fee" size="20" value="<?=$student["membership_fee"]?>">円
		<font color="red" size="-1">税種別が税込の場合は税込金額を、税種別が税抜の場合は税抜金額を入力してください。</font>
	</td>
	</tr>
	<tr>
	<th>生年月日</th><td>
	<input type="hidden" name="birth_year" value="<?=$student["birth_year"]?>"><?=$student["birth_year"]?>年
	<input type="hidden" name="birth_month" value="<?=$student["birth_month"]?>"><?=$student["birth_month"]?>月
	<input type="hidden" name="birth_day" value="<?=$student["birth_day"]?>"><?=$student["birth_day"]?>日
		&nbsp;&nbsp;
  学年：<input type="hidden" name="grade" value="<?= $grade_list[$student["grade"]] ?>"><?= $grade_list[$student["grade"]] ?>
	（補正
	<input type="hidden" name="grade_adj" value="<?=$student["grade_adj"]?>"><?=$student["grade_adj"]?> ）
		&nbsp;&nbsp;
	<input type="hidden" name="jyukensei" value="<?= $student["jyukensei"] ?>">
	<input type="checkbox" <?= $student["jyukensei"]?"checked":"" ?> disabled>受験生
	</td>
	</tr>
	<tr><th>性別</th><td>
	<input type="hidden" name="gender" value="<?=$student['gender']?>">
	<?= ($student['gender']=='M')?'男':'' ?><?= ($student['gender']=='F')?'女':'' ?>
	</td></tr>
	<tr>
	<th>授業料の税種別</th><td>
	<input type="hidden" name="tax_flag" value="<?=$student["tax_flag"]?>">
	<?= ($student["tax_flag"]==1)?'税抜':'' ?><?= ($student["tax_flag"]==0)?'税込':'' ?>
	</td>
	</tr>
	<tr>
	<th>メールアドレス</th><td><input type="hidden" name="mail_address" value="<?=$student["mail_address"]?>"><?=$student["mail_address"]?></td>
	</tr>
	<tr <?=$display?>>
	<th <?=$display?>>CID</th><td><input type="hidden" name="cid" value="<?=$student["cid"]?>"><?=$student["cid"]?></td>
	</tr>
	<tr <?=$display?>>
	<th>スプレッドシートID</th><td><input type="hidden" name="sheet_id" value="<?=$student["sheet_id"]?>"><?=$student["sheet_id"]?></td>
	</tr>
	<tr>
	<th>ステータス</th><td>
		<input type="hidden" name="del_flag" value="<?=$student["del_flag"]?>">
		<?= ($student["del_flag"] == 0)?'現生徒':'' ?><?= ($student["del_flag"] == 2)?'前生徒':'' ?>
	</td>
	</tr>
	<tr>
	<th>授業料免除</th>
	<td>
	<input type="hidden" name="fee_free" value="<?= $student["fee_free"] ?>">
	<input type="checkbox" <?= $student["fee_free"]?'checked':'' ?> disabled>
	授業料0円を有効とする
	</td>
	</tr>
	<tr>
	<th>弓削先生受講料</th>
	<td>
	<input type="hidden" name="yuge_price" value="<?= $student["yuge_price"] ?>">
	<input type="checkbox" <?= $student["yuge_price"]?'checked':'' ?> disabled>
	期間講習・土日講習プラス1000円
	</td>
	</tr>
	<tr><th>入会月</th><td><?=get_student_join_month($db, $student['no'])?></td></tr>
	<tr><th>コース(<?=$year0?>/<?=$month0?>)</th><td>
	<?php 
		foreach ($student["fee_list"] as $fee) {
			if (!$fee['lesson_id']) continue;
			$list0[] = $lesson_list[$fee['lesson_id']].'-'.$course_list[$fee['course_id']]['course_name'].
				'-週'.get_lesson_count($db, $student['no'], $year0, $month0, $fee['lesson_id'], $fee['course_id']).'回'.
				'-'.get_lesson_length($db, $student['no'], $year0, $month0, $fee['lesson_id'], $fee['course_id']).'分<br>';
		}
		$list0 = array_unique($list0);
		foreach ($list0 as $str0) echo $str0;
	?></td></tr>
	</table>

	<br>

	<div class="menu_box">
		<font color="blue" size="-1">
<!--	※&nbsp;科目について、「英会話」と「ピアノ」の場合は、「科目なし」を選択してください。<br>	-->
		※&nbsp;１時間あたりの料金について、「ファミリー」の場合は、全員が出席した時の合計料金を入力してください。<br>
		※&nbsp;ファミリー&nbsp;一人欠席時引く金額は、「ファミリー」で一人が欠席した時に引く金額を入力してください。<br>
		&nbsp;&nbsp;&nbsp;&nbsp;ファミリーでない場合は、空白または「0」としてください。<br>
		</font>
		<font color="black" size="-1">
		&nbsp;&nbsp;&nbsp;&nbsp;ファミリーの例：2人で4000円、1人が欠席で1000円引きの場合、<br>
		&nbsp;&nbsp;&nbsp;&nbsp;「1時間あたりの料金」に「4000」を、「ファミリー&nbsp;1人欠席時引く金額」に「1000」入力してください。
		</font>
		<font color="red" size="-1">
		※１&nbsp;標準料金欄は先月のカレンダー情報をもとにコース情報を判定し表示しています。<br>
		&nbsp;&nbsp;&nbsp;&nbsp;上記のコース欄表示内容が正しい場合のみ有効なのでご注意ください。<br>
		</font>
	</div>
 	<?php
	//$fee_count = count($student["fee_list"]);
	?>
	<table border="1" id="fee_table">
	<tr>
		<th>行</th>
		<th><font color="red">*</font>&nbsp;教室</th>
		<th><font color="red">*</font>&nbsp;科目</th>
		<th><font color="red">*</font>&nbsp;コース</th>
		<th><font color="red">*</font>&nbsp;先生</th>
		<th><font color="red">*</font>&nbsp;1時間あたりの料金<br><font size="-1" color="red" style="font-weight:normal;">赤字表示は仮登録</font></th>
		<th>標準料金<font color="red" size="-1">※１</font></th>
		<th>ファミリー<br>1人欠席時引く金額</th>
		<th>1時間未満割増料金</th>
		<?php //if ($fee_count > 1) { ?>
		<th>&nbsp;</th>
		<?php //} ?>
	</tr>
 	<?php
	$row_no = 0;
	$fee_count = count($student["fee_list"]);
	foreach ($student["fee_list"] as $fee) {
	?>
	<tr>
		<?php
			if ($row_no == 0) { echo "<td id='cell1'>"; } else { echo "<td>"; }
		?>
			<input type="text" name="disp_row_no[]" value="<?=$row_no+1?>" disabled size="4">
			<!-- 20150813 -->
			<!-- <input type="hidden" name="row_no[]" value="<?=$row_no?>"> -->
			<input type="hidden" name="fee_no[]" value="<?=$fee["fee_no"]?>">
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='cell2'>"; } else { echo "<td>"; }
		?>
			<?php disp_lesson_menu($lesson_list, "lesson_id[]", $fee["lesson_id"]); ?>
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='cell3'>"; } else { echo "<td>"; }
		?>
			<?php disp_subject_menu($subject_list, "subject_id[]", $fee["subject_id"]); ?>
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='cell4'>"; } else { echo "<td>"; }
		?>
			<?php disp_course_menu($course_list, "course_id[]", $fee["course_id"]); ?>
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='cell5'>"; } else { echo "<td>"; }
		?>
			<?php disp_teacher_menu($teacher_list, "teacher_id[]", $fee["teacher_id"]); ?>
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='cell6'>"; } else { echo "<td>"; }
		?>
			<input type="text" name="fee[]" size="10" value="<?=str_replace('.00','',$fee["fee"])?>" style="ime-mode: inactive;color:<?= $fee["temp_flag"]?'red':'black' ?>">円
			<input type="hidden" name="temp_flag[]" value="<?= $fee["temp_flag"] ?>">
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='cell10' align='right'>"; } else { echo "<td align='right'>"; }
			$ret = get_default_fee($db, $student, $year0, $month0, $fee["lesson_id"], $fee["course_id"]);
			$str0 = is_numeric($ret)? $ret: '';
		?>
			<?= $str0 ?>円<input type="button" value="選択" onclick="default_fee(this,'<?= $str0 ?>')">
		</td>
		<?php /* if ($fee_count > 1) {*/ ?>
		<?php
			if ($row_no == 0) { echo "<td id='cell7'>"; } else { echo "<td>"; }
		?>
			<input type="text" name="family_minus_price[]" size="10" value="<?=$fee["family_minus_price"]?>" style="ime-mode: inactive;">円
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='cell8'>"; } else { echo "<td>"; }
		?>
			<input type="text" name="additional_fee[]" size="10" value="<?=$fee["additional_fee"]?>" style="ime-mode: inactive;">円
		</td>
		<?php /* if ($fee_count > 1) {*/ ?>
		<?php
			if ($row_no == 0) { echo "<td id='cell9'>"; } else { echo "<td>"; }
		?>
			<?php
				 if ($fee["fee_no"]) { ?>
				<input type="button" value="削除" onclick="delete_fee(<?=$row_no?>,<?=$fee["fee_no"]?>)">
			<?php } else  {?>
				<input type="button" value="取消" onclick="delete_row(<?=($row_no+1)?>)">
			<?php } ?>
		</td>
	</tr>
 	<?php
		$row_no++;
	}
	?>
</table>
<table>
	<tr>
		<td align="center">
			<input type="button" value="最後に行を追加する" onclick="add_new_row()">&nbsp;&nbsp;
<!--
			<input type="button" value="最後に行を追加する" onclick="add_new_row(<?=$fee_count?>)">&nbsp;&nbsp;
			<input type="button" value="行の追加を取り消す" onclick="delete_last_row(<?=$db_count?>)">
-->
		</td>
	</tr>
</table>
<br>
<?php
if (count($student['m_fee_list']) > 1) {
?>
<table><tr><th>月謝</th></tr></table>
	<table border="1" id="m_fee_table">
	<tr>
		<th>行</th>
		<th><font color="red">*</font>&nbsp;教室</th>
		<th><font color="red">*</font>&nbsp;科目</th>
		<th><font color="red">*</font>&nbsp;コース</th>
		<th><font color="red">*</font>&nbsp;月謝</th>
		<th>休講1回割引金額</th>
		<th>&nbsp;</th>
	</tr>
 	<?php
	$row_no = 0;
	foreach ($student["m_fee_list"] as $fee) {
	?>
	<tr>
		<?php
			if ($row_no == 0) { echo "<td id='m_cell1'>"; } else { echo "<td>"; }
		?>
			<input type="text" name="m_disp_row_no[]" value="<?=$row_no+1?>" disabled size="4">
			<input type="hidden" name="m_fee_no[]" value="<?=$fee["fee_no"]?>">
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='m_cell2'>"; } else { echo "<td>"; }
		?>
			<?php disp_lesson_menu($lesson_list, "m_lesson_id[]", $fee["lesson_id"]); ?>
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='m_cell3'>"; } else { echo "<td>"; }
		?>
			<?php disp_subject_menu($subject_list, "m_subject_id[]", $fee["subject_id"]); ?>
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='m_cell4'>"; } else { echo "<td>"; }
		?>
			<?php disp_course_menu($course_list, "m_course_id[]", $fee["course_id"]); ?>
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='m_cell5'>"; } else { echo "<td>"; }
		?>
			<input type="text" name="m_fee[]" size="10" value="<?=$fee["fee"]?>" style="ime-mode: inactive;">円
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='m_cell6'>"; } else { echo "<td>"; }
		?>
			<input type="text" name="m_minus_price[]" size="10" value="<?=$fee["minus_price"]?>" style="ime-mode: inactive;">円
		</td>
		<?php
			if ($row_no == 0) { echo "<td id='m_cell7'>"; } else { echo "<td>"; }
		?>
			<?php
				 if ($fee["fee_no"]) { ?>
				<input type="button" value="削除" onclick="m_delete_fee(<?=$row_no?>,<?=$fee["fee_no"]?>)">
			<?php } else  {?>
				<input type="button" value="取消" onclick="m_delete_row(<?=($row_no+1)?>)">
			<?php } ?>
		</td>
	</tr>
 	<?php
		$row_no++;
	}
	?>
</table>
<table>
	<tr>
		<td align="center">
			<input type="button" value="最後に行を追加する" onclick="m_add_new_row()">&nbsp;&nbsp;
		</td>
	</tr>
</table>
<?php
}
?>
</form>

<?php if ($lms_mode) { ?>
<br><input type="button" onclick="document.location='student_fee_list.php?student_id=<?=$student["no"]?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php } ?>
</div>

</body>
</html>
