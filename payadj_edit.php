<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

$errArray = array();
$message = "";

$initArray = array("payadj_no"=>"", "year"=>"", "month"=>"", "name"=>"", "tax_flag"=>"1", "price"=>"", "memo"=>"");

$employee_type = trim($_POST["type"]);
$employee_no   = trim($_POST["no"]);

if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['delete'])) {
	$action = 'delete';
} else if (isset($_POST['selected_payadj_no'])) {
	$action = 'selected_payadj_no';
} else {
	$action = "";
}

if ($action == 'add' || $action == 'delete' || $action == 'selected_payadj_no') {
	$payadj_array["payadj_no"] = trim($_POST["payadj_no"]);
	$payadj_array["year"]      = trim($_POST["year"]);
	$payadj_array["month"]     = trim($_POST["month"]);
	$payadj_array["name"]      = trim($_POST["name"]);
	$payadj_array["lesson_id"] = array_search(trim($_POST["lesson_id"]), $lesson_list);
	$payadj_array["tax_flag"]  = trim($_POST["tax_flag"]);
	$payadj_array["price"]     = trim($_POST["price"]);
	$payadj_array["memo"]      = trim($_POST["memo"]);
}

if ($action == 'add') {
// 更新処理

	// 入力チェック処理
//	$result = check_payadj_list($errArray, $payadj_array);
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = edit_payadj($db, $employee_type, $employee_no, $payadj_array, $errArray);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
		}
		if ($errFlag == 0) {
			$db->commit();
 			if ($payadj_array["payadj_no"] == ""){ 
				// 登録時、フォームを初期化
				$payadj_array = $initArray;
			}
			$message = "登録できました。";
		} else {
			array_push($errArray, "登録中にエラーが発生しました。");
			$db->rollback();
		}
	}

} else if ($action == 'delete') {

//	$result = check_number($errArray, "項目No", $_POST["payadj_no"], true);
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_payadj($db, $_POST["payadj_no"]);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
		}
		if ($errFlag == 0) {
			$db->commit();
			$payadj_array = $initArray;
			$message = "削除できました。";
		} else {
			array_push($errArray, "削除中にエラーが発生しました。");
			$db->rollback();
		}
	}

} else if ($action == 'selected_payadj_no') {

// 選択時
		$payadj_array = $initArray;
		$payadj_list = get_payadj_list($db, array("tbl_payadj.payadj_no = ?"), array($_POST["selected_payadj_no"]));
		if (count($payadj_list) == 1) {
			$payadj_array = $payadj_list[0];
		}

} else {
// 初期表示処理
		$payadj_array = $initArray;
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
$param_array = array("tbl_payadj.employee_no = ?");
$value_array = array($employee_no);
$order_array = array("tbl_payadj.year, tbl_payadj.month, tbl_payadj.payadj_no");
$payadj_list = get_payadj_list($db, $param_array, $value_array, $order_array);


function edit_payadj(&$db, $employee_type, $employee_no, $payadj_array, &$errArray) {
	$errFlag = 0;
		try {
				if ($payadj_array["payadj_no"] && $payadj_array["payadj_no"] > 0) {
				// 更新時
					$result = update_payadj($db, $employee_type, $employee_no, $payadj_array);
					if (!$result) {
						$errFlag = 1;
					}
				} else {
		    // 新規登録時
					$result = insert_payadj($db, $employee_type, $employee_no, $payadj_array);
					if (!$result) {
						$errFlag = 1;
					}
				}
		}catch (PDOException $e){
			$errFlag = 1;
		}
	if ($errFlag == 0) {
		return true;
	} else {
		return false;
	}

}

function insert_payadj(&$db, $employee_type, $employee_no, $payadj_array) {
	$errFlag = 0;
	try{
		$sql = "INSERT INTO tbl_payadj (employee_type, employee_no, year, month, name, tax_flag, price, memo, lesson_id, insert_timestamp, update_timestamp".
					" ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now())";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $employee_type);
		$stmt->bindParam(2, $employee_no);
		$stmt->bindParam(3, $year);
		$stmt->bindParam(4, $month);
		$stmt->bindParam(5, $name);
		$stmt->bindParam(6, $tax_flag);
		$stmt->bindParam(7, $price);
		$stmt->bindParam(8, $memo);
		$stmt->bindParam(9, $lesson_id);
		$year     = $payadj_array["year"];
		$month    = $payadj_array["month"];
		$name     = $payadj_array["name"];
		$tax_flag = $payadj_array["tax_flag"];
		$price    = $payadj_array["price"];
		$memo     = $payadj_array["memo"];
		$lesson_id = $payadj_array["lesson_id"];
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

function update_payadj(&$db, $employee_type, $employee_no, $payadj_array) {
	$errFlag = 0;
	try{
		$sql = "UPDATE tbl_payadj SET employee_type=?, employee_no=?, year=?, month=?, name=?, tax_flag=?, price=?, memo=?, lesson_id=?, update_timestamp=now() ".
					 "WHERE tbl_payadj.payadj_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $employee_type);
		$stmt->bindParam(2, $employee_no);
		$stmt->bindParam(3, $year);
		$stmt->bindParam(4, $month);
		$stmt->bindParam(5, $name);
		$stmt->bindParam(6, $tax_flag);
		$stmt->bindParam(7, $price);
		$stmt->bindParam(8, $memo);
		$stmt->bindParam(9, $lesson_id);
		$stmt->bindParam(10, $no);
		$year  = $payadj_array["year"];
		$month = $payadj_array["month"];
		$name  = $payadj_array["name"];
		$tax_flag = $payadj_array["tax_flag"];
		$price = $payadj_array["price"];
		$memo  = $payadj_array["memo"];
		$lesson_id = $payadj_array["lesson_id"];
		$no    = $payadj_array["payadj_no"];
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

function delete_payadj(&$db, $payadj_no) {
	$errFlag = 0;
	try{
		$sql = "DELETE FROM tbl_payadj ".
					 "WHERE tbl_payadj.payadj_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($payadj_no));
	}catch (PDOException $e){
		$errFlag = 1;
	}
	if ($errFlag == 0) {
		return true;
	} else {
		return false;
	}
}

foreach ($lesson_list as $key=>$item)
	if ($key != $employee['lesson_id'] && $key != $employee['lesson_id2'])	unset($lesson_list[$key]);

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
function delete_payadj() {
	result = window.confirm("フォームに表示中の情報を削除します。\nよろしいですか");
	if (result) {
		document.forms["payadj_form"].submit();
	} else {
		return false;
	}
}

function selected_payadj(payadj_no) {
		document.forms["payadj_form"].selected_payadj_no.value=payadj_no;
		document.forms["payadj_form"].submit();
}

function reset_form() {
		document.forms["payadj_form"].year.value="";
		document.forms["payadj_form"].month.value="";
		document.forms["payadj_form"].payadj_no.value="";
		document.forms["payadj_form"].name.value="";
		document.forms["payadj_form"].price.value="";
		document.forms["payadj_form"].memo.value="";
}

function input_check() {
	var form  = document.forms["payadj_form"];
	var year  = form.year.value;
	var month = form.month.value;
	var price = form.price.value;
	var match = year.replace(/[0-9]+/,'OK')+month.replace(/[0-9]+/,'OK');
	if (match!='OKOK') { alert("年月は半角数字で入力してください。"); return false; }
	if (year<2016 || year>2050 || month<1 || month>12) { alert("年月が不正です。"); return false; }
	if (!form.name.value) { alert("項目名を入力してください。"); return false; }
	match = price.replace(/-?[0-9]+/,'OK');
	if (match!='OK') { alert("金額を半角数字で入力してください。"); return false; }
	return true;
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


<h3>先生・事務員の登録 - 給与調整金</h3>

<?php if (!$lms_mode) { ?>
<a href="teacher_list.php">先生一覧へ</a>&nbsp;&nbsp;
<a href="staff_list.php">事務員一覧へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br><br>

<?php
}
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

<form method="post" name="payadj_form" action="payadj_edit.php">
	<input type="hidden" name="type" value="<?=$employee_type?>">
	<input type="hidden" name="no" value="<?=$employee_no?>">
	<h3><?=$employee["name"]?>
	</h3>
	<table border="1" id="payadj_table">
	<tr>
		<th><font color="red">*</font>年</th>
		<th><font color="red">*</font>月</th>
		<th><font color="red">*</font>項目名</th>
		<?php if ($employee_type == TEACHER)	echo '<th><font color="red">*</font>教室</th>'; ?>
		<th><font color="red">*</font>税金</th>
		<th><font color="red">*</font>金額</th>
		<th>備考</th>
	</tr>
	<tr>
		<td align="center">
			<input type="text" name="year" value="<?=$payadj_array["year"]?>" size="5">
		</td>
		<td align="center">
			<input type="text" name="month" value="<?=$payadj_array["month"]?>" size="5">
		</td>
		<td align="center">
			<input type="hidden" name="payadj_no" value="<?=$payadj_array["payadj_no"]?>">
			<input type="text" name="name" size="40" value="<?=$payadj_array["name"]?>">
		</td>
		<?php
			if ($employee_type == TEACHER) {
				echo "<td>";
				disp_pulldown_menu1($lesson_list, "lesson_id", $lesson_list[$payadj_array["lesson_id"]]);
				echo "</td>";
			}
		?>
		<td align="center">
		<select name="tax_flag">
		<option value="1" <?= ($payadj_array["tax_flag"])?"selected":"" ?>>課税</option>
		<option value="0" <?= ($payadj_array["tax_flag"])?"":"selected" ?>>非課税</option>
		</select>
		</td>
		<td align="right">
			<input type="text" name="price" size="10" value="<?=$payadj_array["price"]?>">
		</td>
		<td align="left">
			<input type="text" name="memo" size="80" value="<?=$payadj_array["memo"]?>">
		</td>
	</tr>
</table>
<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録" onclick="return input_check()">
		<input type="reset" value="リセット">
<?php if ($payadj_array["payadj_no"]) { ?>
		<input type="submit" name="delete" value="削除" onclick="delete_payadj()">
		<a href="#" onclick="javascript:reset_form();">新規登録</a>
<?php } ?>
	</td>
	</tr>
</table>

<input type="hidden" name="selected_payadj_no" value="">
</form>

<?php
if (count($payadj_list) > 0) {
?>
<hr>
<br>
<table border="1">
	<tr>
		<th>年月</th><th>項目名</th>
		<?php if ($employee_type == TEACHER)	echo '<th>教室</th>'; ?>
		<th>税金</th><th>金額</th><th>備考</th><th>&nbsp;</th>
	</tr>
 	<?php
	foreach ($payadj_list as $item) {
	?>
	<tr>
		<td align="center" width="110"><input type="hidden" name="payadj_no[]" value="<?=$item["payadj_no"]?>"><?=$item["year"]?>年<?=$item["month"]?>月</td>
		<td align="left" width="200"><?=$item["name"]?></td>
		<?php	if ($employee_type == TEACHER) { ?>
		<td align="center" width="80"><?=$lesson_list[$item["lesson_id"]]?></td>
		<?php } ?>
		<td align="center"><?= $item["tax_flag"]?"課税":"非課税" ?></td>
		<td align="right" width="80"><?=number_format($item["price"])?> 円</td>
		<td align="left" width="550"><?=$item["memo"]?></td>
		<td align="center" width="60">
			<?php if ($item["payadj_no"]) { ?>
				<input type="button" value="選択" onclick="selected_payadj(<?=$item["payadj_no"]?>)">
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
