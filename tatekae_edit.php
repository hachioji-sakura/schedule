<?php
ini_set( 'display_errors', 0 );
require_once(dirname(__FILE__)."/const/const.inc");
require_once(dirname(__FILE__)."/func.inc");
require_once(dirname(__FILE__)."/const/login_func.inc");
if (!$teacher_acount) { $result = check_user($db, "1"); }

$year = date("Y");
$month = date("n");

$errArray = array();
$message = "";

$initArray = array("tatekae_no"=>"", "year"=>$year, "month"=>$month, "name"=>"", "lesson_id"=>"", "price"=>"", "memo"=>"", "status"=>"承認待ち");

if ($teacher_acount) {
	$employee_type = TEACHER;
	$employee_no	= $_SESSION['ulogin']['teacher_id'];
} else {
	$employee_type = trim($_POST["type"]);
	$employee_no   = trim($_POST["no"]);
}

if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['delete'])) {
	$action = 'delete';
} else if (isset($_POST['selected_tatekae_no'])) {
	$action = 'selected_tatekae_no';
} else {
	$action = "";
}

if ($action == 'add' || $action == 'delete' || $action == 'selected_tatekae_no') {
	$tatekae_array["tatekae_no"] = trim($_POST["tatekae_no"]);
	$tatekae_array["year"]      = trim($_POST["year"]);
	$tatekae_array["month"]     = trim($_POST["month"]);
	$tatekae_array["lesson_id"] = trim($_POST["lesson_id"]);
	$tatekae_array["name"]      = trim($_POST["name"]);
	$tatekae_array["price"]     = trim($_POST["price"]);
	$tatekae_array["memo"]      = trim($_POST["memo"]);
	$tatekae_array["status"]    = trim($_POST["status"]);
}

if ($action == 'add') {
// 更新処理
	if ($teacher_acount && $tatekae_array["status"]=='承認済') {
		$errArray[] = '承認済項目は変更・削除できません。';
	} else {
	// 入力チェック処理
//	$result = check_tatekae_list($errArray, $tatekae_array);
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = edit_tatekae($db, $employee_type, $employee_no, $tatekae_array, $errArray);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
		}
		if ($errFlag == 0) {
			$db->commit();
 			if ($tatekae_array["tatekae_no"] == ""){ 
				// 登録時、フォームを初期化
				$tatekae_array = $initArray;
			}
			$message = "登録できました。";
		} else {
			array_push($errArray, "登録中にエラーが発生しました。");
			$db->rollback();
		}
	}
	}

} else if ($action == 'delete') {

	if ($teacher_acount && $tatekae_array["status"]=='承認済') {
		$errArray[] = '承認済項目は変更・削除できません。';
	} else {
//	$result = check_number($errArray, "項目No", $_POST["tatekae_no"], true);
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_tatekae($db, $_POST["tatekae_no"]);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
		}
		if ($errFlag == 0) {
			$db->commit();
			$tatekae_array = $initArray;
			$message = "削除できました。";
		} else {
			array_push($errArray, "削除中にエラーが発生しました。");
			$db->rollback();
		}
	}
	}

} else if ($action == 'selected_tatekae_no') {

// 選択時
		$tatekae_array = $initArray;
		$tatekae_list = get_tatekae_list($db, array("tbl_tatekae.tatekae_no = ?"), array($_POST["selected_tatekae_no"]));
		if (count($tatekae_list) == 1) {
			$tatekae_array = $tatekae_list[0];
		}

} else {
// 初期表示処理
		$tatekae_array = $initArray;
}

if ($employee_no) {
	if ($employee_type == TEACHER) {
		$employee = (get_teacher_list($db,null,null,null,2))[$employee_no];
		$teacher_id = '1'.str_pad($employee_no, 5, 0, STR_PAD_LEFT);
	} else
	if ($employee_type == STAFF) {
		$employee = (get_staff_list  ($db,null,null,null,2))[$employee_no];
		$staff_id = '2'.str_pad($employee_no, 5, 0, STR_PAD_LEFT);
		if ($staff_id>200048)	$staff_id--;
	}
}

// 一覧表示
$param_array = array("tbl_tatekae.employee_no = ?");
$value_array = array($employee_no);
$order_array = array("tbl_tatekae.year DESC, tbl_tatekae.month DESC, tbl_tatekae.tatekae_no DESC");
$tatekae_list = get_tatekae_list($db, $param_array, $value_array, $order_array);


function edit_tatekae(&$db, $employee_type, $employee_no, $tatekae_array, &$errArray) {
	$errFlag = 0;
		try {
				if ($tatekae_array["tatekae_no"] && $tatekae_array["tatekae_no"] > 0) {
				// 更新時
					$result = update_tatekae($db, $employee_type, $employee_no, $tatekae_array);
					if (!$result) {
						$errFlag = 1;
					}
				} else {
		    // 新規登録時
					$result = insert_tatekae($db, $employee_type, $employee_no, $tatekae_array);
					if (!$result) {
						$errFlag = 1;
					}
				}
		}catch (PDOException $e){
			$errFlag = 1;
			$errArray[] = $e->getMessage();
		}
	if ($errFlag == 0) {
		return true;
	} else {
		return false;
	}

}

function insert_tatekae(&$db, $employee_type, $employee_no, $tatekae_array) {
	$errFlag = 0;
	try{
		$sql = "INSERT INTO tbl_tatekae (employee_type, employee_no, year, month, name, lesson_id, price, memo, status, insert_timestamp, update_timestamp".
					" ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now())";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $employee_type);
		$stmt->bindParam(2, $employee_no);
		$stmt->bindParam(3, $year);
		$stmt->bindParam(4, $month);
		$stmt->bindParam(5, $name);
		$stmt->bindParam(6, $lesson_id);
		$stmt->bindParam(7, $price);
		$stmt->bindParam(8, $memo);
		$stmt->bindParam(9, $status);
		$year      = $tatekae_array["year"];
		$month     = $tatekae_array["month"];
		$name      = $tatekae_array["name"];
		$lesson_id = $tatekae_array["lesson_id"];
		$price     = $tatekae_array["price"];
		$memo      = $tatekae_array["memo"];
		$status    = $tatekae_array["status"];
		$stmt->execute();
	}catch (PDOException $e){
		$errFlag = 1;
		throw $e;
	}
	if ($errFlag == 0) {
		return true;
	} else {
		return false;
	}
}

function update_tatekae(&$db, $employee_type, $employee_no, $tatekae_array) {
	$errFlag = 0;
	try{
		$sql = "UPDATE tbl_tatekae SET employee_type=?, employee_no=?, year=?, month=?, name=?, lesson_id=?, price=?, memo=?, status=?, update_timestamp=now() ".
					 "WHERE tbl_tatekae.tatekae_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $employee_type);
		$stmt->bindParam(2, $employee_no);
		$stmt->bindParam(3, $year);
		$stmt->bindParam(4, $month);
		$stmt->bindParam(5, $name);
		$stmt->bindParam(6, $lesson_id);
		$stmt->bindParam(7, $price);
		$stmt->bindParam(8, $memo);
		$stmt->bindParam(9, $status);
		$stmt->bindParam(10, $no);
		$year  = $tatekae_array["year"];
		$month = $tatekae_array["month"];
		$name  = $tatekae_array["name"];
		$lesson_id = $tatekae_array["lesson_id"];
		$price = $tatekae_array["price"];
		$memo  = $tatekae_array["memo"];
		$status= $tatekae_array["status"];
		$no    = $tatekae_array["tatekae_no"];
		$stmt->execute();
	}catch (PDOException $e){
		$errFlag = 1;
		throw $e;
	}
	if ($errFlag == 0) {
		return true;
	} else {
		return false;
	}
}

function delete_tatekae(&$db, $tatekae_no) {
	$errFlag = 0;
	try{
		$sql = "DELETE FROM tbl_tatekae ".
					 "WHERE tbl_tatekae.tatekae_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($tatekae_no));
	}catch (PDOException $e){
		$errFlag = 1;
	}
	if ($errFlag == 0) {
		return true;
	} else {
		return false;
	}
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
function delete_tatekae() {
	result = window.confirm("フォームに表示中の情報を削除します。\nよろしいですか");
	if (result) {
		document.forms["tatekae_form"].submit();
	} else {
		return false;
	}
}

function selected_tatekae(tatekae_no) {
	var form  = document.forms["tatekae_form"];
	form.selected_tatekae_no.value=tatekae_no;
	form.submit();
}

function reset_form() {
	var form  = document.forms["tatekae_form"];
	form.year.value="<?= $year ?>";
	form.month.value="<?= $month ?>";
	form.tatekae_no.value="";
	form.name.value="";
	form.price.value="";
	form.memo.value="";
<?php	if ($employee_type == TEACHER) { ?>
	form.lesson_id.value="";
<?php } ?>
}

function input_check() {
	var form  = document.forms["tatekae_form"];
	var year  = form.year.value;
	var month = form.month.value;
	var price = form.price.value;
	var match = year.replace(/[0-9]+/,'OK')+month.replace(/[0-9]+/,'OK');
	if (match!='OKOK') { alert("年月は半角数字で入力してください。"); return false; }
	if (year<2016 || year>2050 || month<1 || month>12) { alert("年月が不正です。"); return false; }
	if (!form.name.value) { alert("項目名を選択してください。"); return false; }
	match = price.replace(/-?[0-9]+/,'OK');
	if (match!='OK') { alert("金額を半角数字で入力してください。"); return false; }
<?php	if ($employee_type == TEACHER) { ?>
	if (!form.lesson_id.value) { alert("教室を選択してください。"); return false; }
<?php } ?>
	return true;
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

<div id="content" align="center">


<h3>先生・事務員の登録 - 立替経費</h3>

<?php if (!$lms_mode) { ?>
<a href="teacher_list.php">先生一覧へ</a>&nbsp;&nbsp;
<a href="staff_list.php">事務員一覧へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br><br>

<?php }} else { ?>

<div id="content" align="center">
<h2><?= 立替経費申請 ?></h2>
<a href="menu.php">メニューへ戻る</a><br>

<?php } ?>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br>
<?php
		}
?>
<!--	<br>-->
<?php
	}
?>
<?php
	if ($message) {
?>
			<font color="blue" size="3"><?= $message ?></font><br>
<?php
	}
?>

<?php if ($teacher_acount) { ?>
<div class="menu_box">
■新規登録：<br>
　<font color="red">*</font>項目を入力し、登録ボタンをクリックして下さい。<br>
　今月分の給料に立替経費として「承認待ち」状態で登録されます。<br>
　事務が承認すると「承認済」となり、承認できない場合「不可」となります。<br>
■登録済み項目の変更・削除：<br>
　下の一覧から対象とする項目の選択ボタンをクリックして下さい。<br>
　上の編集欄に表示される内容を修正し、登録ボタンをクリックすると変更されます。<br>
　削除ボタンをクリックするとその項目が削除されます。<br>
　承認済み項目は変更・削除できません。<br>
　新規入力ボタンをクリックすると、新規登録状態に戻ります。<br>
</div>
<?php } ?>

<form method="post" name="tatekae_form" action="tatekae_edit.php">
	<input type="hidden" name="type" value="<?=$employee_type?>">
	<input type="hidden" name="no" value="<?=$employee_no?>">
	<h3><?=$employee["name"]?>
	</h3>
	<table border="1" id="tatekae_table">
	<tr>
		<th>年月</th>
		<?php if ($employee_type == TEACHER)	echo '<th><font color="red">*</font>教室</th>'; ?>
		<th><font color="red">*</font>項目名</th>
		<th><font color="red">*</font>金額（税込）</th>
		<th>備考</th><th>承認状況</th>
	</tr>
	<tr>
		<td align="center">
<?php if ($teacher_acount) { ?>
			<input type="hidden" name="year" value="<?=$tatekae_array['year']?>">
			<input type="hidden" name="month" value="<?=$tatekae_array['month']?>">
			<?=$tatekae_array["year"]?>年<?=$tatekae_array["month"]?>月
<?php } else { ?>
			<input type="text" name="year" value="<?=$tatekae_array['year']?>" size="4">年
			<input type="text" name="month" value="<?=$tatekae_array['month']?>" size="2">月
<?php } ?>
		</td>
		<?php
			if ($employee_type == TEACHER) {
				echo "<td>";
				disp_lesson_menu($lesson_list, "lesson_id", $tatekae_array["lesson_id"]);
				echo "</td>";
			}
		?>
		<td align="center">
			<input type="hidden" name="tatekae_no" value="<?=$tatekae_array["tatekae_no"]?>">
			<?php disp_pulldown_menu( $tatekae_item_list, "name", $tatekae_array["name"] ); ?>
		</td>
		<td align="right">
			<input type="text" name="price" size="10" value="<?=$tatekae_array["price"]?>">
		</td>
		<td align="left">
			<input type="text" name="memo" size="80" value="<?=$tatekae_array["memo"]?>">
		</td>
		<td align="center">
		<?php
		if ($teacher_acount) {
			echo $tatekae_array["status"];
			echo "<input type=hidden name=\"status\" value=\"{$tatekae_array["status"]}\">";
		} else {
			echo "<select name=\"status\">";
			echo "<option value=\"承認待ち\" ".($tatekae_array["status"]=='承認待ち'?'selected':'').">承認待ち</option>";
			echo "<option value=\"承認済\" ".($tatekae_array["status"]=='承認済'?'selected':'').">承認済</option>";
			echo "<option value=\"不可\" ".($tatekae_array["status"]=='不可'?'selected':'').">不可</option>";
			echo "</select>";
		}
		?>
		</td>
	</tr>
</table>
<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録" onclick="return input_check()">
		<input type="reset" value="リセット">
<?php if ($tatekae_array["tatekae_no"]) { ?>
		<input type="submit" name="delete" value="削除" onclick="delete_tatekae()">
		<input type="button" value="新規入力" onclick="javascript:reset_form();">
<?php } ?>
	</td>
	</tr>
</table>

<input type="hidden" name="selected_tatekae_no" value="">
</form>

<?php
if (count($tatekae_list) > 0) {
?>
<hr>
<br>
<table border="1">
	<tr>
		<th>年月</th>
		<?php if ($employee_type == TEACHER) echo "<th>教室</th>"; ?>
		<th>項目名</th><th>金額（税込）</th><th>備考</th><th>承認状況</th><th>&nbsp;</th>
	</tr>
 	<?php
	foreach ($tatekae_list as $item) {
	?>
	<tr>
		<td align="center" width="110"><input type="hidden" name="tatekae_no[]" value="<?=$item["tatekae_no"]?>"><?=$item["year"]?>年<?=$item["month"]?>月</td>
		<?php	if ($employee_type == TEACHER) { ?>
		<td align="center" width="50"><?=$lesson_list[$item["lesson_id"]]?></td>
		<?php } ?>
		<td align="left" width="120"><?=$item["name"]?></td>
		<td align="right" width="80"><?=number_format($item["price"])?> 円</td>
		<td align="left" width="550"><?=$item["memo"]?></td>
		<td align="left"><?=$item["status"]?></td>
		<td align="center" width="60">
			<?php if ($item["tatekae_no"]) { ?>
				<input type="button" value="選択" onclick="selected_tatekae(<?=$item["tatekae_no"]?>)">
			<?php } ?>
		</td>
	</tr>
 	<?php
	}
	?>
</table>
<?php
}
?>

<?php if ($lms_mode && $employee_type == TEACHER) { ?>
<br><input type="button" onclick="document.location='teacher_list.php?teacher_id=<?=$teacher_id?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php } ?>
<?php if ($lms_mode && $employee_type == STAFF) { ?>
<br><input type="button" onclick="document.location='staff_list.php?staff_id=<?=$staff_id?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php } ?>

</div>

</body>
</html>
