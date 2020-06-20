<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

$errArray = array();
$payment = array();
$message = "";

$payment["payment_no"] = trim($_GET["pno"]);
//$student_no = trim($_GET["no"]);
//$year = trim($_GET["y"]);
//$month = trim($_GET["m"]);

if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['delete_payment'])) {
	$action = 'delete_payment';
} else if (isset($_POST['delete_time_no'])) {
	$action = 'delete_payment_detail';
	$delete_time_no = $_POST['delete_time_no'];
} else {
	$action = "";
}
if ($action == 'add' || $action == 'delete_payment_detail') {
/*
	$student_no = trim($_POST["no"]);
	$year = trim($_POST["year"]);
	$month = trim($_POST["month"]);
	$payment["payment_no"] = trim($_POST["payment_no"]);
	$time = trim($_POST["time"]);
	//$row_no_array = $_POST["row_no"];
*/
	$payment["payment_no"] = trim($_POST["payment_no"]);
	$payment["delete_time_no"] = trim($_POST["delete_time_no"]);

	$time_no_array = $_POST["time_no"];
	$payment_year_array = $_POST["payment_year"];
	$payment_month_array = $_POST["payment_month"];
	$price_array = $_POST["price"];

	$devided_payment_list = array();
	foreach ($time_no_array as $i => $time_no) {
		$row = array();
		if ($time_no_array[$i] != "") {
			$row["time_no"] = $time_no_array[$i];
			$row["payment_year"] = $payment_year_array[$i];
			$row["payment_month"] = $payment_month_array[$i];
			$row["price"] = $price_array[$i];
			$payment["devided_payment_list"][] = $row;
		}
	}
	//$payment["devided_payment_list"] = $devided_payment_list;
//} else if ($action == 'delete_divided_payment') {
//	$payment["payment_no"] = trim($_POST["payment_no"]);
} else if ($action == 'delete_payment') {
	//$student_no = trim($_POST["no"]);
	//$year = trim($_POST["year"]);
	//$month = trim($_POST["month"]);
	//$payment_array["year"] = $year;
	//$payment_array["month"] = $month;
	//$payment_array["member_no"] = $student_no;
	$payment["payment_no"] = trim($_POST["payment_no"]);
}

//$result1 = check_number($errArray, "生徒No", $student_no, true);
$result = check_number($errArray, "支払No", $payment["payment_no"], true);
if ($result === false) {
	header('location: menu.php');
}

// 分割支払情報を取得
//$param_array = array("tbl_divided_payment.member_no = ?",
//											"tbl_divided_payment.year = ?",
//											"tbl_divided_payment.month = ?");
//$value_array = array($student_no, $year, $month);
$param_array = array("tbl_divided_payment.payment_no = ?");
$value_array = array($payment["payment_no"]);
$payment_list = get_divided_payment_list($db, $param_array, $value_array, array());

//var_dump($payment_list[0]["member_no"]);
//var_dump($payment_list);

if (count($payment_list) == 1) {
	$payment["member_no"] = $payment_list[0]["member_no"];
	$payment["year"] = $payment_list[0]["year"];
	$payment["month"] = $payment_list[0]["month"];
	$payment["payment_no"] = $payment_list[0]["payment_no"];
	$payment["lesson_id"] = $payment_list[0]["lesson_id"];
	$payment["type_id"] = $payment_list[0]["type_id"];
	$payment["time"] = $payment_list[0]["time"];
	$payment["payment_price"] = $payment_list[0]["payment_price"];
	$payment["memo"] = $payment_list[0]["memo"];
} else {
	array_push($errArray, "分割支払情報を取得中にエラーが発生しました。");
/*
	$payment["member_no"] = "";
	$payment["year"] = "";
	$payment["month"] = "";
	$payment["payment_no"] = "";
	$payment["time"] = "";
	$payment["payment_price"] = "";
	$payment["memo"] = "";
*/
}

// メンバー情報を取得
$member = null;
if ($payment["member_no"] != "") {
	$member = get_member($db, array("tbl_member.no = ?"), array($payment["member_no"]));
}
if ($member == null) {
	array_push($errArray, "生徒情報を取得中にエラーが発生しました。");
}

if ($action == 'add') {
// 更新処理

	// 入力チェック処理
	//$result = check_student($db, $errArray, $student);
	//$result = check_devided_payment_list($db, $errArray, $student["no"], $payment["devided_payment_list"]);
	$total_price = 0;
	foreach ($payment["devided_payment_list"] as $payment_detail) {
		$result = check_divided_payment_detail($db, $errArray, $payment_detail);
		if ($result === false) break;
		$total_price = $total_price + $payment_detail["price"];
	}

	// 分割設定時の分割支払合計金額とそれぞれの支払金額の合計が一致しない場合エラーとする
	if ($total_price != $payment["payment_price"]) {
		array_push($errArray, "支払金額の合計を合計授業料金と同じにしてください。");
	}

	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = edit_divided_payment_detail($db, $payment);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, "分割詳細情報を登録中にエラーが発生しました。");
		  //print('Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$message = "登録できました。";
			$db->commit();
		} else {
			$db->rollback();
		}
	}

/*
} else if ($action == 'delete_devided_payment') {
// 料金を削除

	$result = check_number($errArray, "分割回数No", $delete_time_no, true);
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_payment_detail($db, $payment["payment_no"], $delete_time_no);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, $delete_time_no."回目の支払を削除中にエラーが発生しました。");
		  //print('Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
		} else {
			$db->rollback();
		}
	}
*/

// divided_payment_list.phpへ
/*
} else if ($action == 'delete_payment') {
// 削除処理

	$errFlag = 0;
	// すでにチェック済み
	//$result = check_number($errArray, "支払No", $payment["payment_no"], true);
	$param_array = array("tbl_divided_payment.payment_no = ?");
	$value_array = array($payment["payment_no"]);
	$payment_list = get_divided_payment_list($db, $param_array, $value_array, array());
	foreach ($payment_list as $payment) {
		$last_month_date = mktime(0,0,0,0,1,date("Y"));
echo date("Y-m-d", $last_month_date);
		if ($payment["payment_year"] < date("Y",$last_month_date)
			 || $payment["payment_mont"] < date("m",$last_month_date)) {
			array_push($errArray, "支払済みの分割払いがあり削除できません。");
			break;
		}
	}
exit();
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		try{
			$db->beginTransaction();
			$result = delete_payment($db, $payment["payment_no"]);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, "支払情報を削除中にエラーが発生しました。");
		  //print('Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			header("Location: divided_payment_list.php?no=".$payment["member_no"]."&y=".$payment["year"]."&m=".$payment["month"]);
			exit();
		} else {
			$db->rollback();
		}
	}
*/

} else if ($action == 'delete_payment') {
// 削除処理

	$result = check_number($errArray, "支払No", $payment["payment_no"], true);
	if ($result === false) {
		header('location: menu.php');
		exit();
	}

	$param_array = array("tbl_divided_payment.payment_no = ?");
	$value_array = array($payment["payment_no"]);
	$payment_list = get_divided_payment_list($db, $param_array, $value_array, array());
	if (count($payment_list) == 1) {
		$payment_array = $payment_list[0];
	} else {
		header('location: menu.php');
		exit();
	}

	$param_array = array("tbl_divided_payment_detail.payment_no = ?");
	$value_array = array($payment["payment_no"]);
	$detail_list = get_divided_payment_detail_list($db, $param_array, $value_array, array());
	foreach ($detail_list as $tmp_payment) {
		$last_month_date = mktime(0,0,0,date("n"),0,date("Y"));
//echo date("Y-m-d", $last_month_date);
		if (($tmp_payment["payment_year"] != "" && $tmp_payment["payment_year"] < date("Y",$last_month_date))
		 || ($tmp_payment["payment_month"] != "" && $tmp_payment["payment_month"] < date("m",$last_month_date))) {
			array_push($errArray, "支払済みの分割払いがあり削除できません。");
			break;
			//header("Location: divided_payment_detail.php?pno=".$payment_array["payment_no"]);
			//exit();
		}
	}
	//}
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_payment($db, $payment["payment_no"]);
			if (!$result) $errFlag = 1;
			$result = delete_payment_detail($db, $payment["payment_no"]);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, "支払情報を削除中にエラーが発生しました。");
		  //print('Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			header("Location: divided_payment_list.php?no=".$payment["member_no"]);
			exit();
		} else {
			$db->rollback();
			//header("Location: divided_payment_detail.php?pno=".$payment_array["payment_no"]);
			//exit();
		}
	}

} else {
// 初期表示処理
/*
	$param_array = array("tbl_divided_payment_detail.payment_no = ?");
	$value_array = array($payment["payment_no"]);
	$order_array = array("tbl_divided_payment_detail.time_no");
	$devided_payment_detail_list = get_divided_payment_detail_list($db, $param_array, $value_array, $order_array);
	$payment["devided_payment_list"] = $devided_payment_detail_list;
//var_dump($devided_payment_detail_list);
*/
}

	$param_array = array("tbl_divided_payment_detail.payment_no = ?");
	$value_array = array($payment["payment_no"]);
	$order_array = array("tbl_divided_payment_detail.time_no");
	$devided_payment_detail_list = get_divided_payment_detail_list($db, $param_array, $value_array, $order_array);
	$payment["devided_payment_list"] = $devided_payment_detail_list;
//var_dump($devided_payment_detail_list);



function edit_divided_payment_detail(&$db, $payment) {
	$errFlag = 0;
	foreach ($payment["devided_payment_list"] as $key => $item) {
		try {
			// 更新時のみ
			$result = update_divided_payment_detail($db, $payment["payment_no"], $item);
			if (!$result) {
				$errFlag = 1;
      	break;
			}
		}catch (PDOException $e){
			$errFlag = 1;
			//throw $e;
		  //print('Error:'.$e->getMessage());
		}
	}
	if ($errFlag == 0) {
		return true;
	} else {
		return false;
	}
}

function update_divided_payment_detail(&$db, $payment_no, $divided_payment_detail) {
	//$errFlag = 0;
	try{
		$sql = "UPDATE tbl_divided_payment_detail SET payment_year=?, payment_month=?, price=?, update_timestamp=now() ".
					 "WHERE tbl_divided_payment_detail.payment_no = ? AND tbl_divided_payment_detail.time_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $payment_year);
		$stmt->bindParam(2, $payment_month);
		$stmt->bindParam(3, $price);
		$stmt->bindParam(4, $tmp_payment_no);
		$stmt->bindParam(5, $time_no);
		$payment_year = $divided_payment_detail["payment_year"];
		$payment_month = $divided_payment_detail["payment_month"];
		$price = $divided_payment_detail["price"];
		$tmp_payment_no = $payment_no;
		$time_no = $divided_payment_detail["time_no"];
		$stmt->execute();
	}catch (PDOException $e){
		//$errFlag = 1;
		//throw $e;
	  //print('Error:'.$e->getMessage());
		return false;
	}
	//if ($errFlag == 0) {
		return true;
	//} else {
	//	return false;
	//}
}

// 分割情報を削除する
function delete_payment(&$db, $delete_payment_no) {
	//$errFlag = 0;
	try{
		$sql = "DELETE FROM tbl_divided_payment ".
					 "WHERE tbl_divided_payment.payment_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($delete_payment_no));
	}catch (PDOException $e){
		return false;
		//$errFlag = 1;
		//throw $e;
	  //print('Error:'.$e->getMessage());
	}
	//if ($errFlag == 0) {
		return true;
	//} else {
	//	return false;
	//}
}

function delete_payment_detail(&$db, $delete_payment_no) {
	//$errFlag = 0;
	try{
		$sql = "DELETE FROM tbl_divided_payment_detail ".
					 "WHERE tbl_divided_payment_detail.payment_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($delete_payment_no));
	}catch (PDOException $e){
		return false;
		//$errFlag = 1;
		//throw $e;
	  //print('Error:'.$e->getMessage());
	}
	//if ($errFlag == 0) {
		return true;
	//} else {
	//	return false;
	//}
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<title>事務システム</title>
<script type = "text/javascript">
<!--

//function delete_payment_detail(time_no) {
//	document.forms["divided_payment_detail_form"].elements["delete_time_no"].value = time_no;
//	result = window.confirm(time_no+"回目の支払いを削除します。\nよろしいですか");
//	if (result) {
//		document.forms["divided_payment_detail_form"].submit();
//	} else {
//		document.forms["divided_payment_detail_form"].elements["delete_time_no"].value = '';
//	}
//}

function delete_payment() {
	result = window.confirm("分割情報とそれぞれの支払情報をを削除します。\nよろしいですか");
	if (result) {
		document.forms["divided_payment_detail_form"].action = "divided_payment_list.php";
		document.forms["divided_payment_detail_form"].elements["delete"].value = 1;
		document.forms["divided_payment_detail_form"].submit();
	}
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

<h3>生徒の登録 - 分割支払詳細</h3>
<a href="divided_payment_list.php?no=<?=$member['no']?>&mode=new">戻る</a><br><br>

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
<?php
	if ($message) {
?>
			<font color="blue" size="3"><?= $message ?></font><br>
<?php
	}
?>


<form method="post" name="divided_payment_detail_form" action="divided_payment_detail.php">
	<input type="hidden" name="no" value="<?=$payment["member_no"]?>">
	<input type="hidden" name="delete" value="">
	<input type="hidden" name="year" value="<?=$payment["year"]?>">
	<input type="hidden" name="month" value="<?=$payment["month"]?>">
<!--
	<input type="hidden" name="no" value="<?=$payment["member_no"]?>">
	<input type="hidden" name="month" value="<?=$month?>">
	<input type="hidden" name="time" value="<?=$payment["time"]?>">
-->
	<input type="hidden" name="payment_no" value="<?=$payment["payment_no"]?>">
	<input type="hidden" name="delete_time_no" value="">

	<h3><?=$member["name"]?>様</h3>
	<table border="1" id="payment_table">
	<tr>
		<th colspan="2">授業年月</th>
		<th>教室</th>
		<th>タイプ</th>
		<th>回数</th>
		<th>合計授業料金</th>
		<th>メモ</th>
	</tr>
	<tr>
		<td align="center">
      <?=$payment["year"]?>年
		</td>
		<td align="center">
      <?=$payment["month"]?>月
		</td>
		<td align="center">
      <?=$lesson_list[$payment["lesson_id"]]?>
		</td>
		<td align="center">
      <?=$type_list[$payment["type_id"]]?>
		</td>
		<td align="center">
      <?=$payment["time"]?>回
		</td>
		<td align="right">
      <?=$payment["payment_price"]?>円
		</td>
		<td align="center">
      <?=$payment["memo"]?>
		</td>
	</tr>
	</table>
<?php
//if (count($payment["devided_payment_list"]) == 0) {
//	if ($payment["payment_no"]) {
?>
<table>
	<tr>
  <td align="center">
<!--
		<input type="button" value="削除" onclick="delete_payment()">
-->
		<input type="submit" value="削除" name="delete_payment">
	</td>
	</tr>
</table>
<br><br>
<?php
//	}
//} else {
?>
	<table border="1" id="devided_payment_detail_table">
	<tr>
		<th>回</th>
		<th colspan="2">月謝対象月(*1)</th>
		<th><font color="red">*</font>&nbsp;支払金額</th>
<!--
		<th>&nbsp;</th>
-->
	</tr>
<?php
	$row_no = 0;
	foreach ($payment["devided_payment_list"] as $devided_payment) {
	?>
	<tr>
		<td>
			<?=$devided_payment["time_no"]?>
			<input type="hidden" name="time_no[]" value="<?=$devided_payment["time_no"]?>">
		</td>
		<td>
			<input type="text" name="payment_year[]" value="<?=$devided_payment["payment_year"]?>" size="4">年
		</td>
		<td>
			<input type="text" name="payment_month[]" value="<?=$devided_payment["payment_month"]?>" size="4">月
		</td>
		<td>
			<input type="text" name="price[]" value="<?=$devided_payment["price"]?>">
		</td>
<!--
		<td>
			<input type="button" value="削除" onclick="delete_payment_detail(<?=$devided_payment["time_no"]?>)">
		</td>
-->
	</tr>
 	<?php
		$row_no++;
	}
?>
</table>
*1 この月の月謝に請求が組み込まれます。支払いは翌月となります。
<?php
//}
?>

<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録">
		<input type="reset" value="リセット">
	</td>
	</tr>
</table>


</form>

</div>

</body>
</html>
