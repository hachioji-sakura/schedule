<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
require_once("./calculate_fees.php");

$result = check_user($db, "1");

$errArray = array();
$message = "";

$student_no = trim($_GET["no"]);

if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['delete'])) {
	$action = 'delete';
} else if (isset($_POST['selected_payment_no'])) {
	$action = 'selected_payment_no';
} else {
	$action = "";
}

if ($action == 'add' || $action == 'selected_payment_no') {
	$student_no = trim($_POST["no"]);
	$year = trim($_POST["year"]);
	$month = trim($_POST["month"]);
	$payment_array["year"] = $year;
	$payment_array["month"] = $month;
	$payment_array["member_no"] = $student_no;
	$payment_array["payment_no"] = trim($_POST["payment_no"]);
	$payment_array["lesson_id"] = trim($_POST["lesson_id"]);
	$payment_array["type_id"] = trim($_POST["type_id"]);
	$payment_array["time"] = trim($_POST["time"]);
	//$payment_array["payment_price"] = trim($_POST["payment_price"]);
	$payment_array["memo"] = trim($_POST["memo"]);
/*
} else if ($action == 'delete') {
	$student_no = trim($_POST["no"]);
	$year = trim($_POST["year"]);
	$month = trim($_POST["month"]);
	$payment_array["year"] = $year;
	$payment_array["month"] = $month;
	$payment_array["member_no"] = $student_no;
	$payment_array["payment_no"] = trim($_POST["payment_no"]);
	//$payment_array["lesson_id"] = trim($_POST["lesson_id"]);
	//$payment_array["type_id"] = trim($_POST["type_id"]);
	//$payment_array["time"] = trim($_POST["time"]);
	//$payment_array["payment_price"] = trim($_POST["payment_price"]);
	//$payment_array["memo"] = trim($_POST["memo"]);
*/
}

$result1 = check_number($errArray, "生徒No", $student_no, true);
if ($result1 === false) {
	header('location: menu.php');
}

// メンバー情報を取得
$member = null;
if ($student_no != "") {
	$member = get_member($db, array("tbl_member.no = ?"), array($student_no));
}
if ($member == null) {
	header('location: menu.php');
}

if ($action == 'add') {
// 更新処理

	// 入力チェック処理
	//if (!$payment_array["payment_no"]) $payment_array["payment_price"] = 0;
	$result = check_divided_payment($errArray, $payment_array);
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			if (!$payment_array["payment_no"]) {
			// 新規登録時
				// 分割対象全額の算出
				$payment_price = get_payment_price($db, $payment_array, $errArray);
				if ($payment_price !== false) {
					
					// 20170830 2017夏期講習の特別対応
					if ($payment_array['year']==2017 && $payment_array['month']==8 && $payment_array['type_id']==4) {$payment_price /= 1.08;}
					
					$payment_array["payment_price"] = $payment_price;
					// payment_noの取得
   				$payment_no = insert_divided_payment($db, $payment_array);
					if ($payment_no !== false) {
						$payment_array["payment_no"] = $payment_no;
						// 分割詳細情報の登録
						$result = add_divided_payment_detail($db, $payment_array, $errArray);
						if ($result === false) $errFlag = 1;
					} else {
						$errFlag = 1;
					}
				}
				//if ($payment_price === false || $payment_no === false || $result === false) $errFlag = 1;
				//if ($payment_no === false || $result === false) 
			} else {
      // 更新時
				//$result = edit_divided_payment($db, $payment_array, $errArray);
				$result = update_divided_payment($db, $payment_array);
				if (!$result) $errFlag = 1;
			}
		}catch (PDOException $e){
			$errFlag = 1;
		  //print('Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			// 登録時、フォームを初期化
			$payment_array = array("payment_no"=>"", "year"=>"", "month"=>"", "lesson_id"=>"", "type_id"=>"", "time"=>"", "payment_price"=>"", "memo"=>"");
			//$payment_array = array("payment_no"=>"", "year"=>"", "month"=>"", "lesson_id"=>"", "time"=>"", "payment_price"=>"", "memo"=>"");
			$message = "登録できました。";
		} else {
			$db->rollback();
			array_push($errArray, "登録中にエラーが発生しました。");
		}
	}
/*
} else if ($action == 'delete') {
// 削除処理

	$result = check_number($errArray, "支払No", $payment_array["payment_no"], true);
	if ($result === false) {
		header('location: menu.php');
		exit();
	}

	$param_array = array("tbl_divided_payment.payment_no = ?");
	$value_array = array($payment_array["payment_no"]);
	$payment_list = get_divided_payment_list($db, $param_array, $value_array, array());
	if (count($payment_list) == 1) {
		$payment_array = $payment_list[0];
	} else {
		header('location: menu.php');
		exit();
	}

	$param_array = array("tbl_divided_payment_detail.payment_no = ?");
	$value_array = array($payment_array["payment_no"]);
	$detail_list = get_divided_payment_detail_list($db, $param_array, $value_array, array());
	foreach ($detail_list as $payment) {
		$last_month_date = mktime(0,0,0,date("n"),0,date("Y"));
//echo date("Y-m-d", $last_month_date);
		if ($payment["payment_year"] < date("Y",$last_month_date)
		 || $payment["payment_month"] < date("m",$last_month_date)) {
			array_push($errArray, "支払済みの分割払いがあり削除できません。");
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
			$result = delete_payment($db, $payment_array["payment_no"]);
			if (!$result) $errFlag = 1;
			$result = delete_payment_detail($db, $payment_array["payment_no"]);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, "支払情報を削除中にエラーが発生しました。");
		  //print('Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			header("Location: divided_payment_list.php?no=".$payment_array["member_no"]);
			exit();
		} else {
			$db->rollback();
			header("Location: divided_payment_detail.php?pno=".$payment_array["payment_no"]);
			exit();
		}
	}
*/
} else if ($action == 'selected_payment_no') {
// 選択時
		$payment_array = array("payment_no"=>"", "year"=>"", "month"=>"", "lesson_id"=>"", "type_id"=>"", "time"=>"", "payment_price"=>"", "memo"=>"");
		//$payment_array = array("payment_no"=>"", "year"=>"", "month"=>"", "lesson_id"=>"", "time"=>"", "payment_price"=>"", "memo"=>"");
		$payment_list = get_divided_payment_list($db, array("tbl_divided_payment.payment_no = ?"), array($_POST["selected_payment_no"]));
		if (count($payment_list) == 1) {
			$payment_array = $payment_list[0];
		} else {
			array_push($errArray, "選択された分割情報を取得できませんでした");
		}
} else {
// 初期表示処理
		$payment_array = array("payment_no"=>"", "year"=>"", "month"=>"", "lesson_id"=>"", "type_id"=>"", "time"=>"", "payment_price"=>"", "memo"=>"");
		//$payment_array = array("payment_no"=>"", "year"=>"", "month"=>"", "lesson_id"=>"", "time"=>"", "payment_price"=>"", "memo"=>"");
}

// 一覧表示
$param_array = array("tbl_divided_payment.member_no = ?");
$value_array = array($student_no);
$order_array = array("tbl_divided_payment.year", "tbl_divided_payment.month");
$payment_list = get_divided_payment_list($db, $param_array, $value_array, $order_array);

function add_divided_payment_detail(&$db, $payment_array, &$errArray) {
					// 分割金額を算出し、tbl_divided_payment_detailに登録する
					$year  = $payment_array['year'];
					$month = $payment_array['month'];
					$divided_payment_detail_array = array();
					for ($i=1; $i<$payment_array["time"]+1; $i++) {
						
						if ($payment_array["payment_price"] == 0) {
						// 分割対象金額が0円のとき割ることができないので0円とする
							$tmp_price = 0;
						} else {
							$tmp_price = floor($payment_array["payment_price"] / $payment_array["time"]);
							if ($i == 1) {
							// 割り切れなかった場合、1回目に余りを足す
	            	$tmp_price = $tmp_price + ($payment_array["payment_price"] % $payment_array["time"]);
							}
						}
						$divided_payment_detail_array["payment_no"] = $payment_array["payment_no"];
						$divided_payment_detail_array["time_no"] = $i;
						$divided_payment_detail_array["payment_year"] = $year;
						$divided_payment_detail_array["payment_month"] = $month;
						$divided_payment_detail_array["price"] = $tmp_price;
						$result = insert_divided_payment_detail($db, $divided_payment_detail_array);
						if (!$result) {
							array_push($errArray, "分割詳細情報をを登録中にエラーが発生しました。");
	          	return false;
						}
						$month++; if ($month>12) { $year++; $month=1; }
					}
			//}
return true;
}

// 分割情報の登録
// 正常終了時にはpayment_noを返す。エラーがある場合はfalseを返す
function insert_divided_payment(&$db, $payment_array) {
	//$errFlag = 0;
	try{
		$sql = "INSERT INTO tbl_divided_payment (member_no, year, month, lesson_id, type_id, time, payment_price, memo, insert_timestamp, update_timestamp".
					" ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, now(), now())";
		// type_idなし
		//$sql = "INSERT INTO tbl_divided_payment (member_no, year, month, lesson_id, time, payment_price, memo, insert_timestamp, update_timestamp".
		//			" ) VALUES (?, ?, ?, ?, ?, ?, ?, now(), now())";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $member_no);
		$stmt->bindParam(2, $year);
		$stmt->bindParam(3, $month);
		$stmt->bindParam(4, $lesson_id);
		$stmt->bindParam(5, $type_id);
		$stmt->bindParam(6, $time);
		$stmt->bindParam(7, $payment_price);
		$stmt->bindParam(8, $memo);
		$member_no = $payment_array["member_no"];
		$year = $payment_array["year"];
		$month = $payment_array["month"];
		$lesson_id = $payment_array["lesson_id"];
		$type_id = $payment_array["type_id"];
		$time = $payment_array["time"];
		$payment_price = $payment_array["payment_price"];
		$memo = $payment_array["memo"];
		$stmt->execute();
		$payment_no = $db->lastInsertId();
	}catch (PDOException $e){
		//$errFlag = 1;
	  print('Error:'.$e->getMessage());
		//throw $e;
		return false;
	}
	//if ($errFlag == 0) {
	//	return true;
	//} else {
	//	return false;
	//}
	return $payment_no;
}

// 分割詳細情報の登録
function insert_divided_payment_detail(&$db, $divided_payment_detail_array) {
	//$errFlag = 0;
	try{
		$sql = "INSERT INTO tbl_divided_payment_detail (payment_no, time_no, payment_year, payment_month, price, insert_timestamp, update_timestamp".
					" ) VALUES (?, ?, ?, ?, ?, now(), now())";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $payment_no);
		$stmt->bindParam(2, $time_no);
		$stmt->bindParam(3, $payment_year);
		$stmt->bindParam(4, $payment_month);
		$stmt->bindParam(5, $price);
		$payment_no = $divided_payment_detail_array["payment_no"];
		$time_no = $divided_payment_detail_array["time_no"];
		$payment_year = $divided_payment_detail_array["payment_year"];
		$payment_month = $divided_payment_detail_array["payment_month"];
		$price = $divided_payment_detail_array["price"];
		$stmt->execute();
	}catch (PDOException $e){
		$errFlag = 1;
	  //print('Error:'.$e->getMessage());
		//throw $e;
		return false;
	}
	//if ($errFlag == 0) {
		return true;
	//} else {
	//	return false;
	//}
}

// 分割情報の編集
// メモだけ編集可能にする。他の項目は変更不可とする
function update_divided_payment(&$db, $payment_array) {
	//$errFlag = 0;
	try{
		//$sql = "UPDATE tbl_divided_payment SET member_no=?, year=?, month=?, input_year=?, input_month=?, input_day=?, name=?, price=?, updatestamp=now() ".
		//			 "WHERE tbl_divided_payment.payment_no = ?";
		$sql = "UPDATE tbl_divided_payment SET memo=?, update_timestamp=now() ".
					 "WHERE tbl_divided_payment.payment_no = ?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $memo);
		$stmt->bindParam(2, $payment_no);
		$memo = $payment_array["memo"];
		$payment_no = $payment_array["payment_no"];
		$stmt->execute();
	}catch (PDOException $e){
		return false;
		//$errFlag = 1;
		//throw $e;
	  //print('Error:'.$e->getMessage());
	}
	//if ($errFlag == 0) {
	//	return true;
	//} else {
	//	return false;
	//}
	return true;
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




function get_payment_price(&$db, $payment_array, &$errArray) {
	// 分割対象金額を取得する
	// 塾の夏期講習の授業料だけが分割対象になるため、lesson_idとtype_idを指定して、分割対象金額を取得する
	$calculator = new calculate_fees();
	$result = $calculator->calculate($payment_array["member_no"], $payment_array["year"], $payment_array["month"]);


	if ($result == false) {
		array_push($errArray, "分割対象金額をを取得中にエラーが発生しました。");
   	return false;
	}
	$payment_price = 0;
	$tmp_lesson_detail_list = $calculator->get_lesson_detail_list();

//var_dump($tmp_lesson_detail_list);


  foreach ($tmp_lesson_detail_list as $lesson_detail) {
		foreach ($lesson_detail["event_list"] as $event) {
			if (!$event["course_id"]) $event["course_id"] = 9;
			$tmp_fees = str_replace(",", "", $event["fees"]);
			if (($payment_array["lesson_id"]=='' || $event["lesson_id"] == $payment_array["lesson_id"]) && 
					$event["course_id"] == $payment_array["type_id"]) {
				$payment_price = $payment_price + $tmp_fees;
			}
		}
	}
	


// 20150904 2015夏期講習の000356さんのための特別対応（計算が変わるので8月が過ぎても絶対に消さない）
	$tmp_others_list = $calculator->get_others_list();
  foreach ($tmp_others_list as $others) {
		//if ($others["member_no"] == "000356") {
		//if ($others["member_no"] == "000356" && $others["lesson_id"] == "1" && $others["type_id"] == "4") {
		//if ($others["member_no"] == "000356" && $others["year"] == "2015" && $others["month"] == "8") {
		//if ($others["year"] == "2015" && $others["month"] == "8" && $others["lesson_id"] == "1" && $others["type_id"] == "4") {
		if ($others["member_no"] == "000356" && $others["year"] == "2015" && $others["month"] == "8") {
			if ($others["lesson_id"] == "1" && $others["type_id"] == "4") {
				$payment_price = $payment_price + 5000;
			}
		}
/*
		// 20150915 明細書のその他項目に項目を表示したいから授業料金に足さないことにする
		if ($others["others_kind"] == "1") {
		// 授業代の場合
			if ($others["lesson_id"] == $payment_array["lesson_id"] && $others["type_id"] == $payment_array["type_id"] ) {
				$tmp_others_price = str_replace(",", "", $others["price"]);
				$payment_price = $payment_price + $tmp_others_price;
			}
		}
*/



	}
// 20170830 2017夏期講習の特別対応
	$param_array = array("tbl_others.member_no=?", "tbl_others.year=?", "tbl_others.month=?");
	$value_array = array($payment_array['member_no'], $payment_array['year'], $payment_array['month']);
	$order_array = array("tbl_others.year, tbl_others.month, tbl_others.others_no");
	$tmp_others_list = get_others_list($db, $param_array, $value_array, $order_array);
  foreach ($tmp_others_list as $others) {
		if ($others["year"] == "2017" && $others["month"] == "8") {
			if ($payment_array["type_id"] == 4 && $others["kind"] == 7 && $others["charge"] == 1) {
				$payment_price = $payment_price + (int)str_replace(",","",$others["price"]);
			}
		}
	}
           return $payment_price;
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="index,follow">
<title>事務システム</title>
<script type = "text/javascript">
<!--

function selected_payment(payment_no) {
		document.forms["payment_form"].selected_payment_no.value=payment_no;
		document.forms["payment_form"].submit();
}

function reset_form() {
		document.forms["payment_form"].payment_no.value="";
		document.forms["payment_form"].year.value="";
		document.forms["payment_form"].month.value="";
		document.forms["payment_form"].lesson_id.value="";
		document.forms["payment_form"].type_id.value="";
		document.forms["payment_form"].time.value="";
		document.forms["payment_form"].payment_price.value="";
		document.forms["payment_form"].memo.value="";
}


function jump_to_new_form($student_no) {
		document.forms["jump_to_new_form"].no.value=$student_no;
		document.forms["jump_to_new_form"].submit();
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


<h3>生徒の登録 - 分割支払い</h3>
<?php if (!$lms_mode) { ?>
<!--
<a href="student_fee_list.php">生徒の登録 - 生徒一覧へ</a>&nbsp;&nbsp;
-->
<a href="menu.php">メニューへ戻る</a><br><br>
<a href="#" onclick="javascript:window.open('about:blank', '_self').close();">閉じる</a><br><br>

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

<form method="post" name="payment_form" action="divided_payment_list.php">
	<input type="hidden" name="no" value="<?=$student_no?>">
	<input type="hidden" name="selected_payment_no" value="">
	<input type="hidden" name="payment_no" value="<?=$payment_array["payment_no"]?>">
	<h3><?=$member["name"]?>様</h3>

<?php if ($payment_array["payment_no"]) { ?>
	<table border="1" id="payment_table">
	<tr>
		<th colspan="2">授業年月</th>
		<th>教室</th>
		<th>タイプ</th>
		<th>回数</th>
<!--
		<th>分割対象金額</th>
-->
		<th>メモ</th>
	</tr>
	<tr>
		<td align="center">
        <?=$payment_array["year"]?>年
				<input type="hidden" name="year" value="<?=$payment_array["year"]?>">
		</td>
		<td align="center">
        <?=$payment_array["month"]?>月
				<input type="hidden" name="month" value="<?=$payment_array["month"]?>">
		</td>
		<td align="center">
        <?=$lesson_list[$payment_array["lesson_id"]]?>
				<input type="hidden" name="lesson_id" value="<?=$payment_array["lesson_id"]?>">
		</td>
		<td align="center">
        <?=$type_list[$payment_array["type_id"]]?>
				<input type="hidden" name="type_id" value="<?=$payment_array["type_id"]?>">
		</td>
		<td align="center">
        <?=$payment_array["time"]?>回
				<input type="hidden" name="time" value="<?=$payment_array["time"]?>">
		</td>
<!--
		<td align="right">
        <?=$payment_array["payment_price"]?>円
				<input type="hidden" name="payment_price" value="<?=$payment_array["payment_price"]?>">
		</td>
-->
		<td align="center">
			<input type="text" name="memo" value="<?=$payment_array["memo"]?>" size="50">
		</td>
	</tr>
	</table>
<?php } else { ?>
	<?php if (trim($_GET["mode"]) == "new" || $action == "add") { ?>
		<table border="1" id="payment_table">
		<tr>
			<th colspan="2"><font color="red">*</font>授業年月</th>
			<th><font color="red">*</font>教室</th>
			<th><font color="red">*</font>タイプ</th>
			<th><font color="red">*</font>回数</th>
	<!--
			<th>全額</th>
	-->
			<th>メモ</th>
		</tr>
		<tr>
			<td align="center">
					<input type="text" name="year" size="4" value="<?=$payment_array["year"]?>">年
			</td>
			<td align="center">
					<input type="text" name="month" size="4" value="<?=$payment_array["month"]?>">月
			</td>
			<td align="center">
					<?php disp_lesson_menu($lesson_list, "lesson_id", $payment_array["lesson_id"]); ?>
			</td>
			<td align="center">
				<?php disp_type_menu($type_list, "type_id", $payment_array["type_id"]); ?>
			</td>
			<td align="center">
					<input type="text" name="time" size="20" value="<?=$payment_array["time"]?>">回
			</td>
	<!--
			<td align="right"><?php if ($payment_array["payment_price"] == "") { echo "0円"; } else { echo $payment_array["payment_price"]."円"; } ?>
					<input type="text" name="payment_price" value="<?=$payment_array["payment_price"]?>">円
			</td>
	-->
			<td align="center">
				<input type="text" name="memo" value="<?=$payment_array["memo"]?>" size="50">
			</td>
		</tr>
		</table>
	<?php } ?> 
<?php } ?> 


<?php
 if (trim($_GET["mode"]) != "new" && $payment_array["payment_no"] == "") { } else {?>
<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録">
		<input type="reset" value="リセット">
<?php if ($payment_array["payment_no"]) { ?>
<!--
		<input type="submit" name="delete" value="削除" onclick="delete_payment()">
		<a href="javascript:jump_to_new_form('<?=$student_no?>')">新規登録</a>
		<a href="divided_payment_list.php?no=<?=$student_no?>">新規登録</a>
-->
<?php } ?>
	</td>
	</tr>
</table>
<?php } ?>

</form>
<form name="jump_to_new_form" method="post" action="divided_payment_list.php">
<input type="hidden" name="no" value="">
</form>

<?php
if (count($payment_list) > 0) {
?>
<br>
<table border="1">
	<tr>
		<th>授業年月</th>
		<th>教室</th>
		<th>タイプ</th>
		<th>回数</th>
		<th>合計授業料金</th>
		<th>メモ</th>
		<th>&nbsp;</th>
	</tr>
 	<?php
	$time_no = 1;
	foreach ($payment_list as $item) {
	?>
	<tr>
		<td align="center" width="110"><input type="hidden" name="payment_no[]" value="<?=$item["payment_no"]?>"><?=$item["year"]?>年<?=$item["month"]?>月</td>
		<td align="left" width="80"><?=$lesson_list[$item["lesson_id"]]?></td>
		<td align="left" width="120"><?=$type_list[$item["type_id"]]?></td>
		<td align="center" width="60"><?=$item["time"]?>回</td>
		<td align="right" width="120"><?=$item["payment_price"]?>円</td>
		<td align="left" width="250"><?=$item["memo"]?></td>
		<td align="center" width="120">
			<?php if ($item["payment_no"]) { ?>
				<input type="button" value="選択" onclick="selected_payment(<?=$item["payment_no"]?>)">
			<?php } ?>
			<a href="divided_payment_detail.php?pno=<?=$item["payment_no"]?>&y=<?= $item["year"] ?>&m=<?= $item["month"] ?>">分割詳細</a>
		</td>
	</tr>
 	<?php
	$time_no++;
	}
	?>
</table>
<?php
}
?>

<?php if ($lms_mode) { ?>
<br><input type="button" onclick="document.location='student_fee_list.php?student_id=<?=$student_no?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php } ?>

</div>

</body>
</html>
