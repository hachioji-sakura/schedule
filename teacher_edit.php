<?php
ini_set( 'display_errors', 0 );
require_once(dirname(__FILE__)."/const/const.inc");
require_once(dirname(__FILE__)."/func.inc");
require_once(dirname(__FILE__)."/const/login_func.inc");

if (!$teacher_acount) 	$teacher_acount = $_POST['teacher_acount'];
if (!$teacher_acount) 	$result = check_user($db, "1");

if (!$year)		$year =		$_POST['y'];
if (!$month)	$month =	$_POST['m'];
if (!$mode)		$mode =		$_POST['mode'];

$course_list = get_course_list($db);
$teacher_list = get_teacher_list($db);

$errArray = array();

$teacher = array();
if ($teacher_acount) {
	$month0 = str_pad($month, 2, 0, STR_PAD_LEFT);
	$teacher_id = $_SESSION['ulogin']['teacher_id'];
} else {
	$teacher_id = trim($_POST["no"]);
}
$teacher["no"] = $teacher_id;

if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['delete'])) {
	$action = 'delete';
} else if (isset($_POST['pass_reset'])) {
	$action = 'pass_reset';
} else {
	$action = "";
}

if ($action == 'add' || $action == 'delete') {
	$teacher["name"]         = trim($_POST["name"]);
	$teacher["furigana"]     = trim($_POST["furigana"]);
	$teacher["lesson_id"]    = trim($_POST["lesson_id"]);
	$teacher["lesson_id2"]   = trim($_POST["lesson_id2"]);
	$teacher["del_flag"]     = trim($_POST["del_flag"]);
	$teacher["mail_address"] = trim($_POST["mail_address"]);
	$teacher["worker_code"]  = trim($_POST["worker_code"]);
	$crew_type_array	= $_POST["crew_type-".$teacher["lesson_id"]];
	$wage_array			  = $_POST["wage-".$teacher["lesson_id"]];
	$crew_type_array2	= $_POST["crew_type2-".$teacher["lesson_id2"]];
	$wage_array2			= $_POST["wage2-".$teacher["lesson_id2"]];
	$wage_other_array	= $_POST["wage_other"];
	foreach ($crew_type_array as $value) {
		$crew_no_array[] = array_search($value, $crew_list);
	}
	foreach ($crew_type_array2 as $value) {
		$crew_no_array2[] = array_search($value, $crew_list);
	}
//	$teacher["transport_cost"] = trim($_POST["transport_cost"]);
//	$teacher["transport_DOW"]  = implode(',',$_POST["transport_DOW"]);
	$teacher["transport_mcost"] = $_POST["transport_mcost"];
	$teacher["transport_dcost1"] = $_POST["transport_dcost1"];
	$teacher["transport_limit"] = $_POST["transport_limit"];
	$teacher["transport_zero"] = $_POST["transport_zero"];
	$teacher["gennsenn_choushuu_shubetu"] = $_POST["gennsenn_choushuu_shubetu"];
	$teacher["huyou_ninnzuu"] = $_POST["huyou_ninnzuu"];
	$teacher["jyuuminnzei1"] = $_POST["jyuuminnzei1"];
	$teacher["jyuuminnzei2"] = $_POST["jyuuminnzei2"];
	$teacher["bank_no"]          = $_POST["bank_no"];
	$teacher["bank_branch_no"]   = $_POST["bank_branch_no"];
	$teacher["bank_acount_type"] = $_POST["bank_acount_type"];
	$teacher["bank_acount_no"]   = $_POST["bank_acount_no"];
	$teacher["bank_acount_name"] = $_POST["bank_acount_name"];
	
	if ($teacher_acount) {
		foreach ($_POST['date'] as $i=>$date) {
			$teacher["transport_regular_cost"][$date] = $_POST['transport0'][$i];
			if (preg_match('/^\d+$/', $_POST['transport1'][$i]))
				$teacher["transport_correct_cost"][$date] = $_POST['transport1'][$i];
			else
				$teacher["transport_correct_cost"][$date] = null;
			$teacher["transport_comment"][$date] = $_POST['transport_comment'][$i];
		}
	}
}

if ($action == 'add') {
// 更新処理

	// 入力チェック処理
	$result = check_teacher($db, $errArray, $teacher);

	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$teacher['password'] = '';
			$result = update_teacher($db, $teacher);
			if (!$result) $errFlag = 1;
			
			$sql = "INSERT tbl_wage (teacher_id, lesson_id, wage_no, crew_no, hourly_wage, insert_timestamp, update_timestamp) ".
							"VALUES (?, ?, ?, ?, ?, now(), now()) ".
							"ON DUPLICATE KEY UPDATE crew_no=?, hourly_wage=?, update_timestamp=now()";
			$stmt = $db->prepare($sql);
			switch ($teacher["lesson_id"]) {
			case 1:
				for ($i=0;$i<count($jyuku_wage_type_list);$i++) {
					$stmt->execute(array($teacher_id, $teacher['lesson_id'], $i, $crew_no_array[$i], $wage_array[$i], $crew_no_array[$i], $wage_array[$i]));
				}
				break;
			case 2:
				for ($i=0;$i<count($eng_wage_type_list);$i++) {
					$stmt->execute(array($teacher_id, $teacher['lesson_id'], $i, $crew_no_array[$i], $wage_array[$i], $crew_no_array[$i], $wage_array[$i]));
				}
				break;
			case 3:
				for ($i=0;$i<count($piano_wage_type_list);$i++) {
					$stmt->execute(array($teacher_id, $teacher['lesson_id'], $i, $crew_no_array[$i], $wage_array[$i], $crew_no_array[$i], $wage_array[$i]));
				}
				break;
			case 4:
				for ($i=0;$i<count($naraigoto_wage_type_list);$i++) {
					$stmt->execute(array($teacher_id, $teacher['lesson_id'], $i, $crew_no_array[$i], $wage_array[$i], $crew_no_array[$i], $wage_array[$i]));
				}
			}
			if ($teacher["lesson_id2"]) {
				$sql = "INSERT tbl_wage (teacher_id, lesson_id, wage_no, crew_no, hourly_wage, insert_timestamp, update_timestamp) ".
								"VALUES (?, ?, ?, ?, ?, now(), now()) ".
								"ON DUPLICATE KEY UPDATE crew_no=?, hourly_wage=?, update_timestamp=now()";
				$stmt = $db->prepare($sql);
				switch ($teacher["lesson_id2"]) {
				case 1:
					for ($i=0;$i<count($jyuku_wage_type_list);$i++) {
						$stmt->execute(array($teacher_id, $teacher['lesson_id2'], $i, $crew_no_array2[$i], $wage_array2[$i], $crew_no_array2[$i], $wage_array2[$i]));
					}
					break;
				case 2:
					for ($i=0;$i<count($eng_wage_type_list);$i++) {
						$stmt->execute(array($teacher_id, $teacher['lesson_id2'], $i, $crew_no_array2[$i], $wage_array2[$i], $crew_no_array2[$i], $wage_array2[$i]));
					}
					break;
				case 3:
					for ($i=0;$i<count($piano_wage_type_list);$i++) {
						$stmt->execute(array($teacher_id, $teacher['lesson_id2'], $i, $crew_no_array2[$i], $wage_array2[$i], $crew_no_array2[$i], $wage_array2[$i]));
					}
					break;
				case 4:
					for ($i=0;$i<count($naraigoto_wage_type_list);$i++) {
						$stmt->execute(array($teacher_id, $teacher['lesson_id2'], $i, $crew_no_array2[$i], $wage_array2[$i], $crew_no_array2[$i], $wage_array2[$i]));
					}
				}
			}
			
			$sql = "INSERT tbl_wage (teacher_id, lesson_id, wage_no, crew_no, hourly_wage, insert_timestamp, update_timestamp, work_type) ".
							"VALUES (?, 0, 0, 0, ?, now(), now(), ?) ".
							"ON DUPLICATE KEY UPDATE hourly_wage=?, update_timestamp=now()";
			$stmt = $db->prepare($sql);
			foreach ($wage_other_array as $key=>$wage) {
				$stmt->execute(array($teacher_id, $wage, $key+1, $wage));
			}
			
			if ($teacher_acount) {
				$sql = "DELETE FROM tbl_transport_cost WHERE teacher_id=\"{$teacher_id}\" AND DATE_FORMAT(date,'%Y%m')=\"$year$month0\"";
				$db->query($sql);
				$sql = "INSERT tbl_transport_cost (teacher_id, date, cost, correct_cost, comment, insert_timestamp, update_timestamp) ".
								"VALUES (?, ?, ?, ?, ?, now(), now())";
				$stmt = $db->prepare($sql);
				foreach ($teacher['transport_regular_cost'] as $date=>$cost)
					$stmt->execute(array($teacher_id, $date, $cost, $teacher['transport_correct_cost'][$date], $teacher['transport_comment'][$date]));
				
				if ($teacher_acount == 1)	$transport_status=1;
				else if ($teacher_acount == 2)	$transport_status=2;
				$sql = "INSERT tbl_transport_status (year, month, teacher_id, status, insert_timestamp, update_timestamp) VALUES ('$year', '$month', '$teacher_id', '$transport_status', now(), now()) ".
								"ON DUPLICATE KEY UPDATE status='$transport_status', update_timestamp=now()";
				$db->query($sql);
			}
			
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			if (!$teacher_acount) {
				if ($lms_mode) {
					header("Location: teacher_list.php?teacher_id={$teacher_id}");
				} else {
					header('Location: teacher_list.php?sort_type=1');
				}
				exit;
			}
		} else {
			$db->rollback();
			array_push($errArray, "登録中にエラーが発生しました。");
		}
	}
	// エラーが発生した場合、編集画面を再表示する

} else if ($action == 'delete') {
// 削除処理

	$result = check_number($errArray, "先生No", $teacher_id, true);
	
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_teacher($db, $teacher_id);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
			array_push($errArray, "削除中にエラーが発生しました。");
		}
		if ($errFlag == 0) {
			$db->commit();
			header('Location: teacher_list.php?sort_type=1');
			exit();
		} else {
			$db->rollback();
			array_push($errArray, "削除中にエラーが発生しました。");
		}
	}
	// エラーが発生した場合、編集画面を再表示する

} else {
// 初期表示処理
	if ($teacher_id > 0) {
		try{
			$cmd = "SELECT * FROM tbl_teacher WHERE no={$teacher_id}";
			$stmt = $db->query($cmd);
			$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if ($teacher["transport_DOW"]) {
				$teacher['transport_dcost1'][0] = (strpos($teacher["transport_DOW"], '0')!==false)? $teacher['transport_cost']: '';
				$teacher['transport_dcost1'][1] = (strpos($teacher["transport_DOW"], '1')!==false)? $teacher['transport_cost']: '';
				$teacher['transport_dcost1'][2] = (strpos($teacher["transport_DOW"], '2')!==false)? $teacher['transport_cost']: '';
				$teacher['transport_dcost1'][3] = (strpos($teacher["transport_DOW"], '3')!==false)? $teacher['transport_cost']: '';
				$teacher['transport_dcost1'][4] = (strpos($teacher["transport_DOW"], '4')!==false)? $teacher['transport_cost']: '';
				$teacher['transport_dcost1'][5] = (strpos($teacher["transport_DOW"], '5')!==false)? $teacher['transport_cost']: '';
				$teacher['transport_dcost1'][6] = (strpos($teacher["transport_DOW"], '6')!==false)? $teacher['transport_cost']: '';
			} else {
				$teacher['transport_dcost1'][0] = $teacher['transport_dcost1_Sun'];
				$teacher['transport_dcost1'][1] = $teacher['transport_dcost1_Mon'];
				$teacher['transport_dcost1'][2] = $teacher['transport_dcost1_Tue'];
				$teacher['transport_dcost1'][3] = $teacher['transport_dcost1_Wen'];
				$teacher['transport_dcost1'][4] = $teacher['transport_dcost1_Thr'];
				$teacher['transport_dcost1'][5] = $teacher['transport_dcost1_Fri'];
				$teacher['transport_dcost1'][6] = $teacher['transport_dcost1_Sat'];
			}
			
			$cmd = "SELECT hourly_wage FROM tbl_wage WHERE teacher_id={$teacher_id} AND lesson_id={$teacher['lesson_id']}";
			$stmt = $db->query($cmd);
			$wage_array = $stmt->fetchAll(PDO::FETCH_NUM);
			$wage_array = array_column($wage_array, '0');
			$cmd = "SELECT hourly_wage FROM tbl_wage WHERE teacher_id={$teacher_id} AND lesson_id={$teacher['lesson_id2']}";
			$stmt = $db->query($cmd);
			$wage_array2 = $stmt->fetchAll(PDO::FETCH_NUM);
			$wage_array2 = array_column($wage_array2, '0');
			$cmd = "SELECT hourly_wage FROM tbl_wage WHERE teacher_id={$teacher_id} AND work_type!=0 ORDER BY work_type";
			$stmt = $db->query($cmd);
			$wage_other_array = $stmt->fetchAll(PDO::FETCH_NUM);
			$wage_other_array = array_column($wage_other_array, '0');
			
			if ($teacher_acount) {
				$cmd = "SELECT * FROM tbl_transport_cost WHERE teacher_id=\"{$teacher_id}\" AND DATE_FORMAT(date,'%Y%m')=\"$year$month0\"";
				$stmt = $db->query($cmd);
				$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rslt as $item) {
					$teacher['transport_correct_cost'][$item['date']] = $item['correct_cost']; 
					$teacher['transport_comment'][$item['date']]      = $item['comment']; 
				}
			}

		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
		}
	}
}

if ($action == 'pass_reset') {

	try{
		$db->beginTransaction();
		$sql = "UPDATE tbl_teacher SET ".
					" password=? , update_timestamp=now()".
					" WHERE no=?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $password);
		$stmt->bindParam(2, $no);
		$no = $teacher_id;
		$password = openssl_encrypt($teacher["initial_password"], 'AES-128-ECB', PASSWORD_KEY);
		$stmt->execute();
		$db->commit();
	}catch (PDOException $e){
		$db->rollback();
		array_push($errArray, 'Error:'.$e->getMessage());
	}
}

if ($teacher_acount) {
	try {
		$cmd = "SELECT status FROM tbl_transport_status WHERE teacher_id=\"{$teacher_id}\" AND year=\"$year\" AND month=\"$month\"";
		$stmt = $db->query($cmd);
		$transport_status = $stmt->fetch(PDO::FETCH_NUM);
		$transport_status = $transport_status? $transport_status[0] : 0;
	} catch (Exception $e) {
		array_push($errArray, 'Error:'.$e->getMessage());
	}
}

if ($lms_mode) $lms_display = 'style="display:none;"';
if ($teacher_acount) $teacher_display = 'style="display:none;"';

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
function delete_teacher() {
	result = window.confirm("先生情報を削除します。\nよろしいですか？");
	if (result) {
		document.forms["teacher_form"].submit();
	}
}
function passResetCheck() {
	return window.confirm('現在のパスワードを初期パスワードに変更してよろしいですか？');
}
var lesson1_id=<?= $teacher["lesson_id"] ?>;
var lesson2_id=<?= $teacher["lesson_id2"] ?>;
function change_lesson1(obj) {
	var i;
	if (obj.selectedIndex == lesson2_id) {
		alert ("教室２と重複するため選択できません。");
		obj.selectedIndex = lesson1_id;
		return;
	}
	lesson1_id = obj.selectedIndex;
	var title = document.getElementsByName("table-title");
	title[0].textContent = "教室１："+obj.options[obj.selectedIndex].text;
	for (i=1;i<=4;i++) {
		var elem = document.getElementsByName("table-"+i);
		for (j=0;j<elem.length;j++) {
			elem[j].style.display=(i==(obj.selectedIndex))?"":"none";
		}
	}
}
function change_lesson2(obj) {
	var i;
	if (obj.selectedIndex == lesson1_id) {
		alert ("教室１と重複するため選択できません。");
		obj.selectedIndex = lesson2_id;
		return;
	}
	lesson2_id = obj.selectedIndex;
	var title = document.getElementsByName("table2-title");
	title[0].textContent = "教室２："+obj.options[obj.selectedIndex].text;
	for (i=1;i<=4;i++) {
		var elem = document.getElementsByName("table2-"+i);
		for (j=0;j<elem.length;j++) {
			elem[j].style.display=(i==(obj.selectedIndex))?"":"none";
		}
	}
}
function input_check() {
	var str, err_char;
	var form = document.forms['teacher_form'];
	str = form.elements['bank_no'].value;
//	err_char = str.replace(/[0-9]/g,'');
//	if (str && (err_char || str.length!=4)) { alert("銀行番号は半角数字4ケタで入力してください。"); return false; }
//	str = form.elements['bank_branch_no'].value;
//	err_char = str.replace(/[0-9]/g,'');
//	if (str && (err_char || str.length!=3)) { alert("支店番号は半角数字3ケタで入力してください。"); return false; }
//	str = form.elements['bank_acount_no'].value;
//	err_char = str.replace(/[0-9]/g,'');
//	if (str && (err_char || str.length!=7)) { alert("口座番号は半角数字7ケタで入力してください。"); return false; }
//	str = form.elements['bank_acount_name'].value;
//	err_char = str.replace(/[　１２３４５６７８９０ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺアイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワンガギグゲゴザジズゼゾダヂヅデドバビブベボパピプペポヴ（）．－]/g,'');
//	if (err_char) { alert("以下の文字は口座名に使用できません。\n“"+err_char+"”"); return false; }
	
	var i,tcost,elem;
	str = form.elements['transport_mcost'].value;
	err_char = str.replace(/[0-9]/g,'');
	if (str && err_char) { alert("交通費は半角数字で入力してください。"); return false; }
	tcost = (str|0);
	elem = document.getElementsByName("transport_dcost1[]");
	for (i=0;i<elem.length;i++) {
		str = elem[i].value;
		err_char = str.replace(/[0-9]/g,'');
		if (str && err_char) { alert("交通費は半角数字で入力してください。"); return false; }
		tcost += (str|0);
	}
	
	elem = document.getElementsByName("transport1[]");
	if (elem) {
		for (i=0;i<elem.length;i++) {
			str = elem[i].value;
			err_char = str.replace(/[0-9]/g,'');
			if (str && err_char) { alert("交通費は半角数字で入力してください。"); return false; }
			tcost += (str|0);
		}
	}

<?php
if ($teacher_acount == 1) {
	echo 'return window.confirm("申請後の修正は事務への連絡が必要になります。よろしいですか？");';
} else if ($teacher_acount == 2) {
	echo 'return window.confirm("確定してよろしいですか？");';
} else {
	echo 'return;';
}
?>
}
function transport_zero_set(onoff) {
	var form = document.forms['teacher_form'];
	form.elements['transport_mcost'].value = 0;
	form.elements['transport_mcost'].disabled = onoff;
	elem = document.getElementsByName("transport_dcost1[]");
	for (i=0;i<elem.length;i++) {
		elem[i].value = 0; elem[i].disabled = onoff;
	}
	form.elements['transport_limit'].value = 0;
	form.elements['transport_limit'].disabled = onoff;
}
function keyup0(val) {
	var elems = document.getElementsByName('trnsd0[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = val;
}
function keyup1(val) {
	var elems = document.getElementsByName('trnsd1[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = val;
}
function keyup2(val) {
	var elems = document.getElementsByName('trnsd2[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = val;
}
function keyup3(val) {
	var elems = document.getElementsByName('trnsd3[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = val;
}
function keyup4(val) {
	var elems = document.getElementsByName('trnsd4[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = val;
}
function keyup5(val) {
	var elems = document.getElementsByName('trnsd5[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = val;
}
function keyup6(val) {
	var elems = document.getElementsByName('trnsd6[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = val;
}
function tr_reset() {
	var elems0 = document.getElementsByName('transport_dcost1[]');
	var elems = document.getElementsByName('trnsd0[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = elems0[0].value;
	elems = document.getElementsByName('trnsd1[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = elems0[1].value;
	elems = document.getElementsByName('trnsd2[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = elems0[2].value;
	elems = document.getElementsByName('trnsd3[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = elems0[3].value;
	elems = document.getElementsByName('trnsd4[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = elems0[4].value;
	elems = document.getElementsByName('trnsd5[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = elems0[5].value;
	elems = document.getElementsByName('trnsd6[]');
	for (var i=0;i<elems.length;i++)	elems[i].innerHTML = elems0[6].value;
}
//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
</head>
<body>

<?php if (!$teacher_acount) { ?>
<div id="header">
	事務システム 
</div>
<?php } ?>

<div id="content" align="center">

<?php if (!$teacher_acount) { ?>
<h3>交通費登録</h3>
<?php if ($lms_mode) { ?>
<h3>先生の時給・交通費・税金登録</h3>
<?php } else { ?>
<h3>先生の登録 - 更新・削除</h3>

<a href="teacher_list.php">先生一覧へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br><br>

<?php }} else if ($teacher_acount == 1) { ?>

<div id="content" align="center">
<h2>交通費申請</h2>
<h3><?= $year ?>年<?= $month ?>月分</h3>
<a href="menu.php">メニューへ戻る</a><br>

<?php } else { ?>

<div id="content" align="center">
<h2>交通費確定</h2>
<h3><?= $year ?>年<?= $month ?>月分</h3>
<form method="post" name="backform" action="teacher_work_time_check_list.php">
    <input type="hidden" name="y" value="<?=$year?>">
    <input type="hidden" name="m" value="<?=$month?>">
    <a href="javascript:backform.submit()">戻る</a><br>
</form>
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
		echo "<h4><font color=\"blue\">登録しました。</font></h4><br>";
	}
?>


<form method="post" name="teacher_form" action="teacher_edit.php" onreset="setTimeout(tr_reset,1)">
	<input type="hidden" name="y" value="<?=$year?>">
	<input type="hidden" name="m" value="<?=$month?>">
	<input type="hidden" name="no" value="<?=$teacher_id?>">
	<input type="hidden" name="del_flag" value="<?=$teacher["del_flag"]?>">
	<input type="hidden" name="mode" value="<?=$mode?>">
	<input type="hidden" name="teacher_acount" value="<?=$teacher_acount?>">

	<div class="menu_box">
		<font color="black" size="-1">
<?php if ($teacher_acount) { ?>
		※&nbsp;先月分交通費の申請を行います。<br>
		<br>
		※&nbsp;定期代、曜日別の交通費を以下の表に税込み金額で入力・編集してください。<br>
		※&nbsp;定期代のみの場合は曜日別交通費はすべて0（または空）としてください。<br>
		※&nbsp;曜日別交通費は通常授業がある場合の1日分の交通費を入力します。<br>
		※&nbsp;定期を使用しない場合または定期代に入らない交通費がある場合は、曜日別交通費に入力してください。<br>

		※&nbsp;振替授業などにより、曜日別交通費と異なる日がある場合、修正欄に入力してください。<br>
		※&nbsp;修正欄に0を入れるとその日の交通費は0円となります。<br>
		※&nbsp;曜日別交通費の修正が必要ない日は修正欄を空のままにしてください。<br>
		<br>
		※&nbsp;入力完了後「申請」ボタンをクリックし申請します。<br>
		※&nbsp;申請後は編集できなくなります。<br>
		※&nbsp;申請後に修正が必要な場合は事務までご連絡ください。<br>
<?php } else { ?>
		※&nbsp;編集する場合は、先生情報を入力して、登録ボタンを押してください。<br>
		※&nbsp;退塾者は、<font color="red">削除をせずに、</font>ステータスで「前先生」を選択して登録してください。<br>
		&nbsp;&nbsp;&nbsp;&nbsp;データ取り込み時に誤って登録された不要データのみ、削除してください。
<?php } ?>
		</font>
	</div>
<?php
if (($teacher_acount == 1 && $transport_status !=0) || ($teacher_acount == 2 && $transport_status ==2)) { 
	echo $transport_status_str[$transport_status];
} else {
	if ($teacher_acount == 1)
		$button_name = '申請';
	else if ($teacher_acount == 2)
		$button_name = '確定';
	else
		$button_name = '登録';
?>
	<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="<?=$button_name?>" onclick="return input_check()">
		<input type="submit" name="delete" value="削除" onclick="delete_teacher()" <?=$lms_display?><?=$teacher_display?>><?php /* buttonだとname=deleteが送信できないので、submitに*/ ?>
		<input type="reset" value="リセット">
	</td>
	</tr>
	</table>
<?php } ?>
	<br>

	<table id="form">
	<tr>
		<th>名前</th>
		<td>
			<input type="hidden" name="name" value="<?=$teacher["name"]?>"><?=$teacher["name"]?>
		</td>
	</tr>
	<tr <?=$lms_display?><?=$teacher_display?>>
		<th>ふりがな</th>
		<td>
			<input type="hidden" name="furigana" value="<?=$teacher["furigana"]?>"><?=$teacher["furigana"]?>
		</td>
	</tr>
	<tr <?=$teacher_display?>>
	<th>教室１</th><td>
		<input type="hidden" name="lesson_id" value="<?=$teacher["lesson_id"]?>"><?=$lesson_list[$teacher["lesson_id"]]?>
	</td>
	</tr>
	<tr <?=$teacher_display?>>
	<th>教室２</th><td>
		<input type="hidden" name="lesson_id2" value="<?=$teacher["lesson_id2"]?>"><?=$lesson_list[$teacher["lesson_id2"]]?>
	</td>
	</tr>
	<tr <?=$lms_display?><?=$teacher_display?>>
	<th>ステータス</th><td>
		<input type="hidden" name="del_flag" value="<?=$teacher["del_flag"]?>">
		<?= ($teacher["del_flag"] == 0)?'現先生':'' ?><?= ($teacher["del_flag"] == 2)?'前先生':'' ?>
	</td>
	</tr>
	<tr <?=$lms_display?><?=$teacher_display?>>
	<th>メールアドレス</th><td>
		<input type="hidden" name="mail_address" value="<?=$teacher["mail_address"]?>"><?=$teacher["mail_address"]?>
	</td>
	</tr>
	<tr valign="top" <?=$teacher_display?>>
	<th>口座情報</th>
	<td>
		銀行番号<input type="text" name="bank_no" size="4" maxlength="4" value="<?=$teacher["bank_no"]?>">
		支店番号<input type="text" name="bank_branch_no" size="3" maxlength="3" value="<?=$teacher["bank_branch_no"]?>">
		預金種目
		<select name="bank_acount_type">
		<option value="1" <?php if ($teacher["bank_acount_type"] == 1) { echo "selected"; } ?>>普通</option>
		<option value="2" <?php if ($teacher["bank_acount_type"] == 2) { echo "selected"; } ?>>当座</option>
		<option value="4" <?php if ($teacher["bank_acount_type"] == 4) { echo "selected"; } ?>>貯蓄</option>
		</select>
		<br>
		口座番号<input type="text" name="bank_acount_no" size="7" maxlength="7" value="<?=$teacher["bank_acount_no"]?>">
		<font color="red" size="-1">（半角数字７ケタ、7ケタ未満の場合は前をゼロで埋めてください）</font><br>
		口座名　<input type="text" name="bank_acount_name" size="60" maxlength="30" value="<?=$teacher["bank_acount_name"]?>">
		<font color="red" size="-1">（全角カナ　*1）</font>
	</td>
	</tr>
	<tr <?=$lms_display?><?=$teacher_display?>>
	<th>初期パスワード</th><td>
		<?=$teacher["initial_password"]?>&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="pass_reset" value="パスワード初期化" onclick="return passResetCheck()">
	</td>
	</tr>
	</table>
	<br <?=$teacher_display?>>

<table border="1" <?=$teacher_display?>>
<tr><th name="table-title" colspan="4">教室１：<?= ($teacher["lesson_id"])?$lesson_list[$teacher["lesson_id"]]:'' ?></th><tr>
<tr><th>番号</th><th>条件</th><th>時給</th></tr>
<?php
for ($lesson_id=1; $lesson_id<=4;$lesson_id++) {
	switch ($lesson_id) {
	case 1:
		$wage_type_list = $jyuku_wage_type_list;
//		if (count($wage_array)==0) {
//			foreach ($jyuku_default_wage as $key=>$value) { $wage_array[] = array("wage_no"=>$key, "hourly_wage"=>$value, "crew_no"=>$jyuku_default_crew[$key] ); }
//		}
		break;
	case 2:
		$wage_type_list = $eng_wage_type_list;
		break;
	case 3:
		$wage_type_list = $piano_wage_type_list;
		break;
	case 4:
		$wage_type_list = $naraigoto_wage_type_list;
		break;
	default:
		$wage_type_list = array();
	}
	$table_elename = 'table-'.$lesson_id;
	$crew_type_elename = 'crew_type-'.$lesson_id.'[]';
	$wage_elename = 'wage-'.$lesson_id.'[]';
	foreach($wage_type_list as $key=>$wage_type) {
	if ($lesson_id==$teacher["lesson_id"]) { $wage=$wage_array[$key]; $displayFlag='display:'; } else { $wage=''; $displayFlag='display:none'; }
?>
<tr name="<?= $table_elename ?>" style="<?= $displayFlag ?>">
<td><?= $key+1 ?></td>
<td><?= $wage_type ?></td>
<td><input type="text" name="<?= $wage_elename ?>" size="10" value="<?=$wage?>">円</td>
</tr>
<?php
	}
}
?>
</table>
<br <?=$teacher_display?>>

<table border="1" <?=$teacher_display?>>
<tr><th name="table2-title" colspan="4">教室２：<?= ($teacher["lesson_id2"])?$lesson_list[$teacher["lesson_id2"]]:'' ?></th><tr>
<tr><th>番号</th><th>条件</th><th>時給</th></tr>
<?php
for ($lesson_id=1; $lesson_id<=4;$lesson_id++) {
	switch ($lesson_id) {
	case 1:
		$wage_type_list = $jyuku_wage_type_list;
//		if (count($wage_array2)==0) {
//			foreach ($jyuku_default_wage as $key=>$value) { $wage_array2[] = array("wage_no"=>$key, "hourly_wage"=>$value, "crew_no"=>$jyuku_default_crew[$key] ); }
//		}
		break;
	case 2:
		$wage_type_list = $eng_wage_type_list;
		break;
	case 3:
		$wage_type_list = $piano_wage_type_list;
		break;
	case 4:
		$wage_type_list = $naraigoto_wage_type_list;
		break;
	default:
		$wage_type_list = array();
	}
	$table_elename = 'table2-'.$lesson_id;
	$crew_type_elename = 'crew_type2-'.$lesson_id.'[]';
	$wage_elename = 'wage2-'.$lesson_id.'[]';
	foreach($wage_type_list as $key=>$wage_type) {
	if ($lesson_id==$teacher["lesson_id2"]) { $wage=$wage_array2[$key]; $displayFlag='display:'; } else { $wage=''; $displayFlag='display:none'; }
?>
<tr name="<?= $table_elename ?>" style="<?= $displayFlag ?>">
<td><?= $key+1 ?></td>
<td><?= $wage_type ?></td>
<td><input type="text" name="<?= $wage_elename ?>" size="10" value="<?=$wage?>">円</td>
</tr>
<?php
	}
}
?>
</table>
<br <?=$teacher_display?>>

<table border="1" <?=$teacher_display?>>
<tr><th colspan="3">業務その他</th><tr>
<tr><th>番号</th><th>業務内容</th><th>時給</th></tr>
<?php
foreach($work_type_list as $key=>$work_type) {
	if (!$key) { continue; }
?>
<tr>
<td><?= $key ?></td>
<td><?= $work_type ?></td>
<td><input type="text" name="wage_other[]" size="10" value="<?=$wage_other_array[$key-1]?>">円</td>
</tr>
<?php
}
?>
</table>
<br <?=$teacher_display?>>

<table border="1">
<tr><th colspan="9" align="center">交通費</th></tr>
<!--
<tr>
<td>交通費（1日分）</td>
<td><input type="text" name="transport_cost" size="10" value="<?= $teacher['transport_cost'] ?>">円 （半角数字入力）</td>
</tr>
<tr>
<td>支給対象曜日</td>
<td>
<input type="checkbox" name="transport_DOW[]" value="0" <?= (strpos($teacher['transport_DOW'],'0')!==false)?'checked':'' ?>>日　
<input type="checkbox" name="transport_DOW[]" value="1" <?= (strpos($teacher['transport_DOW'],'1')!==false)?'checked':'' ?>>月　
<input type="checkbox" name="transport_DOW[]" value="2" <?= (strpos($teacher['transport_DOW'],'2')!==false)?'checked':'' ?>>火　
<input type="checkbox" name="transport_DOW[]" value="3" <?= (strpos($teacher['transport_DOW'],'3')!==false)?'checked':'' ?>>水　
<input type="checkbox" name="transport_DOW[]" value="4" <?= (strpos($teacher['transport_DOW'],'4')!==false)?'checked':'' ?>>木　
<input type="checkbox" name="transport_DOW[]" value="5" <?= (strpos($teacher['transport_DOW'],'5')!==false)?'checked':'' ?>>金　
<input type="checkbox" name="transport_DOW[]" value="6" <?= (strpos($teacher['transport_DOW'],'6')!==false)?'checked':'' ?>>土　
</td>
</tr>
-->
<tr>
<td>交通費支給</td>
<td colspan="8"><input type="checkbox" name="transport_zero" value="1" <?= $teacher['transport_zero']? 'checked':'' ?> onclick="transport_zero_set(this.checked)">交通費なし</td>
</tr>
<tr>
<td>定期代１月分</td>
<td colspan="8"><input type="text" name="transport_mcost" size="10" value="<?= $teacher['transport_mcost'] ?>">円 （半角数字入力）</td>
</tr>
<tr align="center"><td align="left" rowspan="2">曜日別交通費<br>１日分 <?= ($teacher_acount)?'(*1)':'' ?></td>
<td>日</td><td>月</td><td>火</td><td>水</td><td>木</td><td>金</td><td>土</td></tr>
<tr>
<td><input type="text" name="transport_dcost1[]" size="8" value="<?= $teacher['transport_dcost1'][0] ?>" onkeyup="keyup0(this.value);"></td>
<td><input type="text" name="transport_dcost1[]" size="8" value="<?= $teacher['transport_dcost1'][1] ?>" onkeyup="keyup1(this.value);"></td>
<td><input type="text" name="transport_dcost1[]" size="8" value="<?= $teacher['transport_dcost1'][2] ?>" onkeyup="keyup2(this.value);"></td>
<td><input type="text" name="transport_dcost1[]" size="8" value="<?= $teacher['transport_dcost1'][3] ?>" onkeyup="keyup3(this.value);"></td>
<td><input type="text" name="transport_dcost1[]" size="8" value="<?= $teacher['transport_dcost1'][4] ?>" onkeyup="keyup4(this.value);"></td>
<td><input type="text" name="transport_dcost1[]" size="8" value="<?= $teacher['transport_dcost1'][5] ?>" onkeyup="keyup5(this.value);"></td>
<td><input type="text" name="transport_dcost1[]" size="8" value="<?= $teacher['transport_dcost1'][6] ?>" onkeyup="keyup6(this.value);"></td>
</tr>
<tr>
<td>支給額制限</td>
<td colspan="8">
<?php if ($teacher_acount) { 
if ($teacher['transport_limit']) echo '給与の1割まで';
} else { ?>
<input type="checkbox" name="transport_limit" value="1" <?= $teacher['transport_limit']? 'checked':'' ?>>給与の1割まで</td>
<?php } ?>
</tr>
</table>
<?php if ($teacher_acount) { ?>
<font color="red">*1　曜日別交通費の入力は以下の日別交通費表に即時反映されます。</font><br>
<?php } ?>
<br>

<table border="1" <?=$teacher_display?>>
<tr><th colspan="3"align="center">源泉徴収税・住民税</th></tr>
<tr>
<td>　<input type="radio" name="gennsenn_choushuu_shubetu" value="甲" <?= ($teacher['gennsenn_choushuu_shubetu']=='甲')?'checked':'' ?>>甲　</td>
<td>
<table>
<tr>
<td>扶養人数</td>
<td>
<select name="huyou_ninnzuu">
<option value="0" <?= ($teacher['huyou_ninnzuu']==0)?'selected':'' ?>>0人</option>
<option value="1" <?= ($teacher['huyou_ninnzuu']==1)?'selected':'' ?>>1人</option>
<option value="2" <?= ($teacher['huyou_ninnzuu']==2)?'selected':'' ?>>2人</option>
<option value="3" <?= ($teacher['huyou_ninnzuu']==3)?'selected':'' ?>>3人</option>
<option value="4" <?= ($teacher['huyou_ninnzuu']==4)?'selected':'' ?>>4人</option>
<option value="5" <?= ($teacher['huyou_ninnzuu']==5)?'selected':'' ?>>5人</option>
<option value="6" <?= ($teacher['huyou_ninnzuu']==6)?'selected':'' ?>>6人</option>
<option value="7" <?= ($teacher['huyou_ninnzuu']==7)?'selected':'' ?>>7人</option>
</select>
</td>
</tr>
<tr>
<td>住民税　6月</td>
<td><input type="text" name="jyuuminnzei1" size="10" value="<?= $teacher['jyuuminnzei1'] ?>">円 （半角数字入力）</td>
</tr>
<tr>
<td>住民税　7月～翌年5月</td>
<td><input type="text" name="jyuuminnzei2" size="10" value="<?= $teacher['jyuuminnzei2'] ?>">円 （半角数字入力）</td>
</tr>
</table>
</td>
</tr>
<tr>
<td>　<input type="radio" name="gennsenn_choushuu_shubetu" value="乙" <?= ($teacher['gennsenn_choushuu_shubetu']=='乙')?'checked':'' ?>>乙　</td><td></td>
</tr>
</table>
<br <?=$teacher_display?>>

<table <?=$teacher_display?>>
<tr valign="top">
<th>*1　口座名に使用できる文字 　　</th>
<td>
１２３４５６７８９０<br>
ＡＢＣＤＥＦＧＨＩＪＫＬＭＮ<br>
ＯＰＱＲＳＴＵＶＷＸＹＺ<br>
アイウエオカキクケコ<br>
サシスセソタチツテト<br>
ナニヌネノハヒフヘホ<br>
マミムメモヤユヨ<br>
ラリルレロワン<br>
ガギグゲゴザジズゼゾ<br>
ダヂヅデドバビブベボ<br>
パピプペポ<br>
ヴ<br>
（）．－<br>
</td>
</tr>
</table>

<?php if ($lms_mode) { ?>
<br><input type="button" onclick="document.location='teacher_list.php?teacher_id=<?='1'.str_pad($teacher_id, 5, 0, STR_PAD_LEFT)?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php 
}

if ($teacher_acount == 1) {
	require_once("./check_work_time.php");
} else if ($teacher_acount == 2) {
	require_once("../../sakura-teacher/check_work_time.php");
}
?>

</form>
</div>
<br>
<br>
</body>
</html>
