<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
require_once("./calculate_fees.php");
$result = check_user($db, "1");

$errArray = array();
$errFlag = 0;

try {

$year = $_POST["y"];
$month = $_POST["m"];
$taikaisha_flag = $_POST['t'];

if (is_null($year) == true || $year == "") {
	$year = $_GET["y"];
}
if (is_null($month) == true || $month == "") {
	$month = $_GET["m"];
}
if (is_null($taikaisha_flag) == true || $taikaisha_flag == "") {
	$taikaisha_flag = $_GET["t"];
}
if ((is_null($year) == true || $year == "") || (is_null($month) == true || $month == "")) {
	throw new Exception('年月が不明です。');
}

$cond_name = "";
if (isset($_POST["cond_name"])) {
	$cond_name = trim($_POST["cond_name"]);
}

// 生徒情報（受講している教室と科目情報を含む）を取得
$student_list = array();
$param_array = array();
$value_array = array();
array_push($param_array, "tbl_member.kind = ?");
array_push($value_array, "3");
if ($cond_name != "") {
// 検索時
	array_push($param_array," tbl_member.name like concat('%',?,'%') ");
	// 20150817 修正
	//array_push($value_array,str_replace(" " , "　" , $cond_name));
	array_push($value_array,$cond_name);
}
// 20150816 ふりがなの50音順にソートする
$order_array = array("tbl_member.furigana asc");


// 20151230 授業料表示のため変更
//$member_list = get_simple_member_list($db, $param_array, $value_array, $order_array);
if ($taikaisha_flag)
	$member_list = get_member_list($db, $param_array, $value_array, $order_array, 1);
else
	$member_list = get_member_list($db, $param_array, $value_array, $order_array);
//var_dump($member_list);
if (count($member_list) == 0) {
	$errFlag = 1;
	throw new Exception('生徒が見つかりませんでした。');
}
$student_list = get_calculated_list($member_list, $year, $month);
if ($student_list == false) {
	$errFlag = 2;
	throw new Exception('月謝計算中にエラーが発生しました。');
}

foreach ($student_list as $key => $student) {
	// 1時間当たりの授業料を表示する
	$fees_text = "";
	$fee_list = array();
	$fee_array = array();
	if (isset($member_list[$student["no"]]) == true) {
   	$fee_list = $member_list[$student["no"]]["fee_list"];
		$result = usort($fee_list, "cmp_fee");
		foreach ($fee_list as $fee) {
			if (mb_strpos($fee["subject_name"],"科目なし") !== false) {
				$fee_array[] = $fee["lesson_name"]."_".$fee["course_name"]."：".$fee["fee"]."円";
			} else {
				$fee_array[] = $fee["lesson_name"]."_".$fee["course_name"]."_".$fee["subject_name"]."(".$fee["teacher_name"].")：".str_replace('.00','',$fee["fee"])."円";
			}
		}
		$fees_text = implode("<br>", $fee_array);
	}
	$student_list[$key]["fee_for_an_hour"] = $fees_text;
}

/*
	foreach ($member_list as $member_no => $member) {
		$calculator = new calculate_fees();
		$result = $calculator->calculate($member_no, $year, $month);
		if ($result == false) {
			array_push($errArray, "月謝計算中にエラーが発生しました。");
		}
		$student = array();
		$student["no"] = $member["no"];
		$student["name"] = $member["name"];
		$student["total_hours"] = $calculator->get_total_hours();;
		$student["total_fees"] = $calculator->get_total_fees();
		$student["membership_fee"] = $calculator->get_membership_fee();
		$student["textbook_price"] = $calculator->get_textbook_price();
		$student["others_price"] = $calculator->get_others_price();
		$student["last_total_fees"] = $calculator->get_last_total_fees();
		$student["lesson_detail_list"] = $calculator->get_lesson_detail_list();
		$student["buying_textbook_list"] = $calculator->get_buying_textbook_list();
		$student["others_list"] = $calculator->get_others_list();
		$student_list[$member["no"]] = $student;
		$calculator = null;
	}
}
*/
/*
	$total_hours = $calculator->get_total_hours();
	// 2015/07/17 追加
	$total_fees = $calculator->get_total_fees();	 //授業料のみ
	$membership_fee = $calculator->get_membership_fee();
	$textbook_price = $calculator->get_textbook_price();
	$others_price = $calculator->get_others_price();
	$last_total_fees = $calculator->get_last_total_fees(); // テキスト代と月会費を含む合計
*/
	// このページは生徒一覧なので、検索結果の生徒を一人ずつ処理する
	//$lesson_detail_list = get_lesson_detail_list($db, $student, $year, $month);

/*
	// 受けているレッスンをもとに対象年月の予定を取得する
	$total_hours = 0;
	$total_fees = 0;

//var_dump($student["fee_list"]);

	foreach ($student["fee_list"] as $fee_array) {
		$param_array = array();
		$value_array = array();
		array_push($param_array, "tbl_event.member_no = ?");
		array_push($value_array, $student["no"]);
		array_push($param_array, "tbl_event.event_year = ?");
		array_push($value_array, $year);
		array_push($param_array, "tbl_event.event_month = ?");
		array_push($value_array, $month);
		array_push($param_array, "tbl_event.lesson_id = ?");
		array_push($value_array, $fee_array["lesson_id"]);
		array_push($param_array, "tbl_event.subject_id = ?");
		array_push($value_array, $fee_array["subject_id"]);
		array_push($param_array, "tbl_event.course_id = ?");
		array_push($value_array, $fee_array["course_id"]);

		$subtotal_hours = 0;
		$event_list = get_event_list($db, $param_array, $value_array);

		foreach ($event_list as $key => $event) {
//var_dump($event);

			// 2015/05/28
			// absent_flag=1は授業料が発生しない休み、absent_flag=2は授業料が発生する休み
			// 授業料が発生しないのは、授業料が発生しない休み(absent_flag=1)と無料体験と面談（科目面談を無料に）
			//if ($event["absent_flag"] != 1 && $event["trial_flag"] != 1) {
			if ($event["absent_flag"] != 1 && $event["trial_flag"] != 1 && $event["trial_flag"] != 2) {
			// 2015/05/22 授業料が発生しない休みでない場合と無料体験でない場合、時間に足しこむ
				$subtotal_hours = $subtotal_hours + $event["diff_hours"];
			}

		}
		$total_hours = $total_hours + $subtotal_hours;
		$total_fees = $total_fees + ($fee_array["fee"] * $subtotal_hours);
	}
*/
/*
	$student_list[$member["no"]]["name"] = $member["name"];
	$student_list[$member["no"]]["total_hours"] = $total_hours;
	$student_list[$member["no"]]["last_total_fees"] = $last_total_fees;
  // 2015/07/17 追加
	$student_list[$member["no"]]["total_fees"] = $total_fees;
	$student_list[$member["no"]]["membership_fee"] = $membership_fee;
	$student_list[$member["no"]]["textbook_price"] = $textbook_price;
	$student_list[$member["no"]]["others_price"] = $others_price;
*/
//}// End:foreach ($student_list as $student)

//uasort($student_list, 'cmp');

} catch (Exception $e) {
	// 処理を中断するほどの致命的なエラー
	array_push($errArray, $e->getMessage());
}

function cmp_fee($a, $b) {
      if ($a["lesson_id"] == $b["lesson_id"]) {
	      if ($a["course_id"] == $b["course_id"]) {
		      if ($a["subject_id"] == $b["subject_id"]) {
		            return 0;
		      }
		      return ($a["subject_id"] > $b["subject_id"]) ? +1 : -1;
	      }
	      return ($a["course_id"] > $b["course_id"]) ? +1 : -1;
      }
      return ($a["lesson_id"] > $b["lesson_id"]) ? +1 : -1;
}

function insert_meisai(&$db,$tmp_year,$tmp_month,$member_array,$event_list) {
	$errFlag = 0;
	try {

			$sql = "SELECT * FROM tbl_statement where member_no=? and  year=? and month=?";
			$stmt = $db->prepare($sql);
			$stmt->bindParam(1, $member_no);
			$stmt->bindParam(2, $year);
			$stmt->bindParam(3, $month);
			$member_no = $member_array["no"];
			$year = $tmp_year;
			$month = $tmp_month;
			$stmt->execute();
			$statement_array = $stmt->fetchAll(PDO::FETCH_BOTH);
			$before_statement_no = null;
			if ($statement_array > 0) {
				$before_statement_no = $statement_array[0]["statement_no"];
			}
	
			if (is_null($before_statement_no) !== false) {
				$sql = "DELETE FROM tbl_statement where member_no=? and  year=? and month=?";
				$stmt = $db->prepare($sql);
				$stmt->bindParam(1, $member_no);
				$stmt->bindParam(2, $year);
				$stmt->bindParam(3, $month);
				$member_no = $member_array["no"];
				$year = $tmp_year;
				$month = $tmp_month;
				$stmt->execute();
				$sql = "DELETE FROM tbl_detail_statement where statement_no=?";
				$stmt = $db->prepare($sql);
				$stmt->bindParam(1, $before_statement_no);
				$stmt->execute();
			}

			$sql = "INSERT INTO tbl_statement (member_no, year, month, membership_fee, insert_timestamp, update_timestamp".
						" ) VALUES (?, ?, ?, ?, now(), now())";
			$stmt = $db->prepare($sql);
			$stmt->bindParam(1, $member_no);
			$stmt->bindParam(2, $year);
			$stmt->bindParam(3, $month);
			$stmt->bindParam(4, $membership_fee);
			$member_no = $member_array["no"];
			$year = $tmp_year;
			$month = $tmp_month;
			$membership_fee = $member_array["membership_fee"];
			$stmt->execute();
			$last_no = $db->lastInsertId();

			$i = 1;
			foreach ($event_list as $event_array) {
						$sql = "INSERT INTO tbl_detail_statement (no, statement_no, date, weekday, time, absent_flag, absent1_num, absent2_num,".
									 "		diff_hours, fee_for_an_hour, fees, lesson_id, lesson_name, subject_id, subject_name, course_id, course_name, comment, insert_timestamp, update_timestamp".
									 " ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now())";
						$stmt = $db->prepare($sql);
						$stmt->bindParam(1, $no);
						$stmt->bindParam(2, $statement_no);
						$stmt->bindParam(3, $date);
						$stmt->bindParam(4, $weekday);
						$stmt->bindParam(5, $time);
						$stmt->bindParam(6, $absent_flag);
						$stmt->bindParam(7, $absent1_num);
						$stmt->bindParam(8, $absent2_num);
						$stmt->bindParam(9, $diff_hours);
						$stmt->bindParam(10, $fee_for_an_hour);
						$stmt->bindParam(11, $fees);
						$stmt->bindParam(12, $lesson_id);
						$stmt->bindParam(13, $lesson_name);
						$stmt->bindParam(14, $subject_id);
						$stmt->bindParam(15, $subject_name);
						$stmt->bindParam(16, $course_id);
						$stmt->bindParam(17, $course_name);
						$stmt->bindParam(18, $comment);
						$no = $i;
						$statement_no = $last_no;
						$date = $event_array["date"];
						$weekday = $event_array["weekday"];
						$time = $event_array["time"];
						$absent_flag = $event_array["absent_flag"];
						$absent1_num = $event_array["absent1_num"];
						$absent2_num = $event_array["absent2_num"];
						$diff_hours = $event_array["diff_hours"];
						$fee_for_an_hour = $event_array["fee_for_an_hour"];
						$fees = $event_array["fees"];
						$lesson_id = $event_array["lesson_id"];
						$lesson_name = $event_array["lesson_name"];
						$subject_id = $event_array["subject_id"];
						$subject_name = $event_array["subject_name"];
						$course_id = $event_array["course_id"];
						$course_name = $event_array["course_name"];
						$comment = $event_array["comment"];
						$stmt->execute();
						$i++;
			}
/*
$event = array("date" => date("n月j日", $start_datetime),
												"weekday" => $weekday_array[$weekday_id],
												"time" => date("H:i", $start_datetime) ." ～ ". date("H:i", $end_datetime),
												"absent_flag" => $tmp_event["absent_flag"] ,
												"absent1_num" => $tmp_event["absent1_num"] ,
												"absent2_num" => $tmp_event["absent2_num"] ,
												"diff_hours" => $diff_hours ,
												"fee_for_an_hour" => number_format($fee_for_an_hour),
												"fees" => number_format($fees),
												"lesson_id" => $tmp_event["lesson_id"],
												"lesson_name" => $lesson_name,
												"subject_id" => $subject_name,
												"subject_name" => $subject_name,
												"course_id" => $tmp_event["course_id"],
												"course_name" => $course_name,
												"comment" => $comment);
*/
		}catch (PDOException $e){
			$errFlag = 1;
			throw $e;
		  //print('Error:'.$e->getMessage());
		}
	if ($errFlag == 0) {
		return $no;
	} else {
		return false;
	}
}

errMsgFileCheck($errArray);

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
function search_reset()
{
	document.search_form.cond_name = '';
	document.search_form.submit();
}
//-->
</script>
</head>
<body>
<div id="header">
	事務システム
</div>
<div id="content" align="center">
<!--
<div class="title_box">生徒一覧</div>
-->
<h3>生徒の月謝計算 - 生徒一覧</h3>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br><br>
<?php
		}
	}
?>

<div class="menu_box">
すべての生徒の明細書を１つのPDFファイルに出力する場合は、<a href="./all_output_pdf.php?y=<?=$year?>&m=<?=$month?>">こちら</a>です。<br>
<!-- すべての生徒の明細書を生徒ごとのスプレッドシートに出力する場合は、<a href="./all_output.php?y=<?=$year?>&m=<?=$month?>">こちら</a>です。<br> -->
</div>
<a href="./menu.php">メニューへ戻る</a>
<br>

<h3><?= $year ?>年<?= $month ?>月</h3>

<form method="post" name="search_form" action="./student_list.php">
<input type="hidden" name="y" value="<?=$year?>">
<input type="hidden" name="m" value="<?=$month?>">
<input type="hidden" name="t" id= "taikaisha_flag" value="<?=$taikaisha_flag?>">
生徒氏名：<input type="text" name="cond_name" value="">
<input type="submit" value="&nbsp;&nbsp;検&nbsp;索&nbsp;&nbsp;">
<input type="button" value="&nbsp;検索解除&nbsp;" onclick="search_reset()">
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<input type="button" value="&nbsp;退会者表示&nbsp;" onclick="document.getElementById('taikaisha_flag').value='1';document.search_form.submit();">
</form>

<table border="1" cellpadding="5">
<tr>
<!--
	<th>氏名</th><th>授業時間（時間）</th><th>合計金額（円）</th>
	<th>明細書</th>
-->
</tr>
<tr>
	<th>氏名</th><th>授業時間（時間）</th><th>授業金額-小計（円）</th><th>分割払い-小計（円）</th>
	<th>入会金</th><th>月会費</th><th>その他項目-小計（円）</th><th>合計金額</th>
	<th>消費税</th><th>テキスト代（税込）-小計（円）</th><th>総合計金額</th><th>授業料</th>
<!--
	<th colspan="2">明細書</th>
-->
	<th colspan="2"></th>
</tr>
<?php
foreach ($student_list as $key => $item) {

?>
	<tr>
		<td><?php echo $item["name"]; ?></td>
		<td align="right"><?php echo $item["total_hours"]; ?></td>
		<td align="right"><?php echo $item["total_fees"]; ?></td>
		<td align="right"><?php echo $item["divided_price"]; ?></td>
		<td align="right"><?php echo $item["entrance_fee"]; ?></td>
		<td align="right"><?php echo $item["membership_fee"]; ?></td>
		<td align="right"><?php echo $item["others_price"]; ?></td>
		<td align="right"><?php echo $item["simple_total_price"] ?></td>
		<td align="right"><?php echo $item["consumption_tax_price"] ?></td>
		<td align="right"><?php echo $item["textbook_price"]; ?></td>
		<td align="right"><?php echo $item["last_total_price"] ?></td>
		<td align="left"><?php echo $item["fee_for_an_hour"] ?></td>
<!--
		<td align="center"><a href="buying_textbook_edit.php?no=<?= $item["no"]?>&y=<?= $year ?>&m=<?= $month ?>">登録・変更</a></td>
		<td align="center"><a href="others_edit.php?no=<?= $item["no"]?>&y=<?= $year ?>&m=<?= $month ?>">登録・変更</a></td>
-->
		<td align="center"><a href="detail.php?no=<?=$item["no"]?>&y=<?= $year ?>&m=<?= $month ?>" target="_blank">明細書表示</a></td>
		<td align="center"><a href="divided_payment_list.php?no=<?=$item["no"]?>&y=<?= $year ?>&m=<?= $month ?>&mode=new" target="_blank">分割払い設定</a></td>
<!--
		<td align="center"><a href="output.php?no=<?=$item["no"]?>&y=<?= $year ?>&m=<?= $month ?>">スプレッドシート出力</a></td>
		<td align="center"><a href="output_pdf.php?no=<?=$item["no"]?>&y=<?= $year ?>&m=<?= $month ?>">pdf出力</a></td>
		<td><a href="calender.php?id=<?= $item["id"] ?>&y=<?= $year ?>&m=<?= $month ?>">カレンダー</a></td>
-->
	</tr>
<?php
}
?>
</table>


</div>

</body></html>

