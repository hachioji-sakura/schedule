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

if (is_null($year) == true || $year == "") {
	$year = $_GET["y"];
}
if (is_null($month) == true || $month == "") {
	$month = $_GET["m"];
}
if ((is_null($year) == true || $year == "") || (is_null($month) == true || $month == "")) {
	throw new Exception('年月が不明です。');
}

if (($year<'2019') || ($year=='2019' && $month<'10')) {
	throw new Exception('2019年9月以前の請求更新は確定済みです。');
} else {
//	throw new Exception('現在2019年10月以降（消費税10％）を処理できません。');
}

if (($year<'2020') || ($year=='2020' && $month<='02')) {
	$entrance_fee_for_lesson = array( 1=>15000, 2=>15000, 3=>10000, 4=>10000 );
} else {
	$entrance_fee_for_lesson = array( 1=>20000, 2=>15000, 3=>10000, 4=>10000 );			// 2020/02以降
}

$cond_name = "";
if (isset($_POST["cond_name"])) {
	$cond_name = trim($_POST["cond_name"]);
}

$db->beginTransaction();

$param_array = array("tbl_entrance_fee.year=?","tbl_entrance_fee.month=?");
$value_array = array($year,$month);
$order_array = array();
$entrance_fee_list0 = get_entrance_fee_list($db, $param_array, $value_array, $order_array);
foreach ($entrance_fee_list0 as $item) {
	$entrance_fee_list1[] = $item['member_no'];
}

// 入会者
$sql = "SELECT tbl_member.no, tbl_event.lesson_id FROM tbl_member, tbl_event ".
		"WHERE tbl_member.kind = 3 ".
		"AND tbl_member.del_flag = 0 ".
		"AND tbl_member.name <> '体験生徒' ".
		"AND tbl_event.member_no = tbl_member.no ".
		"AND tbl_event.event_year=$year AND tbl_event.event_month=$month ".
		"AND tbl_event.event_start_timestamp = ".
		"( SELECT MIN(event_start_timestamp) FROM tbl_event ".
		"  WHERE member_no = tbl_member.no ".
		"  AND course_id NOT IN (0,4,5,6,9) ".
		"  AND tbl_event.trial_flag = 0 ) ".
		"ORDER BY tbl_member.furigana ";
$stmt = $db->prepare($sql);
$stmt->execute();
$newcomer_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
$newcomer_list = array_unique($newcomer_list,SORT_REGULAR);
foreach ($newcomer_list as $member0) {
	if (in_array($member0['no'],$entrance_fee_list1)) { continue; }
	$sql = "INSERT INTO tbl_entrance_fee (member_no, year, month, price, memo, insert_timestamp, update_timestamp".
				" ) VALUES (?, ?, ?, ?, ?, now(), now())";
	$stmt = $db->prepare($sql);
	$stmt->execute(array($member0['no'],$year,$month,$entrance_fee_for_lesson[$member0['lesson_id']],''));
}
$db->commit();


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

$member_list = get_simple_member_list($db, $param_array, $value_array, $order_array, 4);
if (count($member_list) == 0) {
	$errFlag = 1;
	throw new Exception('生徒が見つかりませんでした。');
}
//var_dump($student_list);

$student_list = get_calculated_list($member_list, $year, $month);
if ($student_list == false) {
	$errFlag = 2;
	throw new Exception('月謝計算中にエラーが発生しました。');
}

$stmt = $db->query("SELECT fee_no FROM tbl_fee WHERE temp_flag=1 AND teacher_id!=0");
$rslt = $stmt->fetch(PDO::FETCH_NUM);
if ($rslt) throw new Exception(
"仮登録の受講料があります。<br>".
"下記の「登録済み生徒一覧へ」リンクをクリックし「生徒の登録 - 生徒一覧」画面で".
"赤字表示の受講料を正しい金額で再登録してください。<br>".
"<a href=\"./student_fee_list.php\">登録済み生徒一覧へ</a>"
);

$db->beginTransaction();

//		$student["no"] = $member["no"];
//		$student["name"] = $member["name"];
//		$student["sheet_id"] = $member["sheet_id"];
//		$student["cid"] = $member["cid"];
//		$student["total_hours"] = $calculator->get_total_hours();;
//		$student["total_fees"] = $calculator->get_total_fees();
//		$student["membership_fee"] = $calculator->get_membership_fee();
//		$student["textbook_price"] = $calculator->get_textbook_price();
//		$student["others_price"] = $calculator->get_others_price();
//		$student["divided_price"] = $calculator->get_divided_price();
//		$student["last_total_fees"] = $calculator->get_last_total_fees();
//		$student["lesson_detail_list"] = $calculator->get_lesson_detail_list();
//		$student["buying_textbook_list"] = $calculator->get_buying_textbook_list();
//		$student["others_list"] = $calculator->get_others_list();
//		$student["divided_payment_list"] = $calculator->get_divided_payment_list();

	//	try{
				$sql = "SELECT statement_no FROM tbl_statement where seikyu_year=? and seikyu_month=?";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($year, $month));
				$array = $stmt->fetchAll(PDO::FETCH_BOTH);
				$sql = "DELETE FROM tbl_statement_detail where statement_no=?";
				$stmt = $db->prepare($sql);
				$stmt->bindParam(1, $statement_no);
				foreach ($array as $no) { $statement_no=$no['statement_no']; $stmt->execute(); }
				$sql = "DELETE FROM tbl_statement where seikyu_year=? and seikyu_month=?";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($year, $month));
$index = 1;
foreach ($student_list as $member_no => $student) {
			//foreach ($student["lesson_detail_list"] as $lesson_id => $lesson) {
//var_dump($lesson);
				$result = insert_statement($db, $year, $month, $student, $index);
				$index++;
				if (!$result) { $errFlag = 1; continue; }
			//}
}

//		}catch (PDOException $e){
	//		$errFlag = 1;
if ($errFlag == "1") {
			array_push($errArray, "登録中にエラーが発生しました。");
		  //print('Error:'.$e->getMessage());
			$db->rollback();
		} else {
    $db->commit();
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
		array_push($param_array, "tbl_event.type_id = ?");
		array_push($value_array, $fee_array["type_id"]);

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



function insert_statement(&$db,$tmp_year,$tmp_month,$member_array,$index) {
	$errFlag = 0;
	try {

			$sql = "SELECT * FROM tbl_statement where member_no=? and  seikyu_year=? and seikyu_month=?";
			$stmt = $db->prepare($sql);
			$stmt->bindParam(1, $member_no);
			$stmt->bindParam(2, $year);
			$stmt->bindParam(3, $month);
			$member_no = $member_array["no"];
//		$student["textbook_price"] = $calculator->get_textbook_price();
//		$student["others_price"] = $calculator->get_others_price();
//		$student["divided_price"] = $calculator->get_divided_price();
			$year = $tmp_year;
			$month = $tmp_month;
			$stmt->execute();
			$statement_array = $stmt->fetchAll(PDO::FETCH_BOTH);
			$before_statement_no = null;
			if (count($statement_array) > 0) {
				$before_statement_no = $statement_array[0]["statement_no"];
			}
//echo __FILE__.__LINE__;
//var_dump($statement_array);
			if (is_null($before_statement_no) === false) {
/*
				if ($month == "8" || $month == "1" || $month == "4") {
					$lastYear = date("Y", mktime(0, 0, 0, $month-1, 1, $year));
					$lastMonth = date("n", mktime(0, 0, 0, $month-1, 1, $year));
					if ($month == "8") {
						$sql = "DELETE FROM tbl_event where event_year = ? and event_month = ? and course_id = '4'";
					} else if ($month == "1") {
						$sql = "DELETE FROM tbl_event where event_year = ? and event_month = ? and course_id = '5'";
					} else if ($month == "4") {
						$sql = "DELETE FROM tbl_event where event_year = ? and event_month = ? and course_id = '6'";
					}
					$stmt = $db->prepare($sql);
					$stmt->execute(array($lastYear, $lastMonth));
				}
*/
				$sql = "DELETE FROM tbl_statement where member_no=? and  seikyu_year=? and seikyu_month=?";
				$stmt = $db->prepare($sql);
				$stmt->bindParam(1, $member_no);
				$stmt->bindParam(2, $year);
				$stmt->bindParam(3, $month);
				$member_no = $member_array["no"];
				$year = $tmp_year;
				$month = $tmp_month;
				$stmt->execute();
				$sql = "DELETE FROM tbl_statement_detail where statement_no=?";
				$stmt = $db->prepare($sql);
				$stmt->bindParam(1, $before_statement_no);
				$stmt->execute();
			}

		// 請求金額が0円より多い人のみ登録
		$toroku_flag = false;
		foreach ($member_array["lesson_detail_list"] as $lesson_id => $lesson) {
			if (count($lesson["event_list"]) > 0) {
				$toroku_flag = true;
			}
		}
		
		// 20170830 2017夏期講習の特別対応
		if ($year==2017 && $month==8 ) {
			$sql = "SELECT * FROM tbl_others WHERE member_no=? AND year=2017 AND month=8 AND kind=7";
			$stmt = $db->prepare($sql);
			$stmt->execute(array($member_no));
			$ret = $stmt->fetch(PDO::FETCH_BOTH);
			if ($ret!==false) { $toroku_flag = true; }
		}
		// 20180208 2018 冬期講習の特別対応
		if ($year==2018 && $month==1 ) {
			$sql = "SELECT * FROM tbl_others WHERE member_no=? AND year=2018 AND month=1 AND name='冬期講習'";
			$stmt = $db->prepare($sql);
			$stmt->execute(array($member_no));
			$ret = $stmt->fetch(PDO::FETCH_BOTH);
			if ($ret!==false) { $toroku_flag = true; }
		}

		// 20180509 2018 冬期講習の特別対応
		if ($year==2018 && $month==4 ) {
			$sql = "SELECT * FROM tbl_others WHERE member_no=? AND year=2018 AND month=4 AND name='春期講習'";
			$stmt = $db->prepare($sql);
			$stmt->execute(array($member_no));
			$ret = $stmt->fetch(PDO::FETCH_BOTH);
			if ($ret!==false) { $toroku_flag = true; }
		}

		// 分割支払い
		$param_array = array("tbl_divided_payment.member_no=?", "tbl_divided_payment_detail.payment_year=?", "tbl_divided_payment_detail.payment_month=?");
		$value_array = array($member_no, $year, $month);
		$order_array = array("tbl_divided_payment.payment_no","tbl_divided_payment_detail.time_no");
		$divided_payment_list = get_both_divided_payment_list($db, $param_array, $value_array, $order_array);
		if (count($divided_payment_list)) {$toroku_flag = true;}

		if ($toroku_flag == true || $member_array["last_total_price"] != 0) {

			$stmt = $db->prepare("INSERT INTO tbl_statement_no (insert_timestamp, update_timestamp) VALUES (now(), now())");
			$stmt->execute();
			$last_statement_no = $db->lastInsertId();


//var_dump($last_statement_no);

			// 20160527 列を追加
			$sql = "INSERT INTO tbl_statement (statement_id, statement_date, member_no, seikyu_year, seikyu_month, ".
						"lesson_hours, lesson_price, membership_fee, entrance_fee, ".
						"textbook_price, others_price, devided_price, total_price, ".
						"consumption_tax_price, grand_total_price, no_charge, insert_timestamp, update_timestamp".
						" ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), now())";
			$stmt = $db->prepare($sql);
			$stmt->bindParam(1, $statement_id);
			$stmt->bindParam(2, $statement_date);
			$stmt->bindParam(3, $member_no);
			$stmt->bindParam(4, $year);
			$stmt->bindParam(5, $month);
			$stmt->bindParam(6, $lesson_hours);
			$stmt->bindParam(7, $lesson_price);
			$stmt->bindParam(8, $membership_fee);
			$stmt->bindParam(9, $entrance_fee);
			$stmt->bindParam(10, $textbook_price);
			$stmt->bindParam(11, $others_price);
			$stmt->bindParam(12, $devided_price);
			$stmt->bindParam(13, $total_price);
			$stmt->bindParam(14, $consumption_tax_price);
			$stmt->bindParam(15, $grand_total_price);
			$stmt->bindParam(16, $others_price_no_charge);
			$statement_id = date("Ymd")."_".sprintf("%06d", (string)$last_statement_no);
//var_dump($statement_id);
			$statement_date = time();
			$member_no = $member_array["no"];
			$year = $tmp_year;
			$month = $tmp_month;
			$lesson_hours = (int)str_replace(",", "", $member_array["total_hours"]);
			$lesson_price = (int)str_replace(",", "", $member_array["total_fees"]);
			$membership_fee = (int)str_replace(",", "", $member_array["membership_fee"]);
			$entrance_fee = (int)str_replace(",", "", $member_array["entrance_fee"]);
			$textbook_price = (int)str_replace(",", "", $member_array["textbook_price"]);
			$others_price = (int)str_replace(",", "", $member_array["others_price"]);
			$devided_price = (int)str_replace(",", "", $member_array["divided_price"]);
			$total_price = (int)str_replace(",", "", $member_array["simple_total_price"]);
			$consumption_tax_price = (int)str_replace(",", "", $member_array["consumption_tax_price"]);
			$grand_total_price = (int)str_replace(",", "", $member_array["last_total_price"]);
			$others_price_no_charge = (int)str_replace(",", "", $member_array["others_price_no_charge"]);
			$stmt->execute();
			$last_no = $db->lastInsertId();

		}
//var_dump($member_array["consumption_tax_price"]);
//echo __FILE__.__LINE__;

			// 20160521 start_timestampとend_timestampとstudent_idとteacher_idとplace_idを追加
			$i = 1;
			foreach ($member_array["lesson_detail_list"] as $lesson_id => $lesson) {
				foreach ($lesson["event_list"] as $event_array) {
					$sql = "INSERT INTO tbl_statement_detail (no, statement_no, date, weekday, time, start_timestamp, end_timestamp, absent_flag, absent1_num, absent2_num,".
								 "		diff_hours, fee_for_an_hour, additional_fee, fees, lesson_id, lesson_name, subject_id, subject_name, course_id, course_name, student_id, teacher_id, place_id, ".
								 "    monthly_fee_flag, comment, insert_timestamp, update_timestamp".
								 " ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,now(), now())";
					$stmt = $db->prepare($sql);
					$stmt->bindParam(1, $no);
					$stmt->bindParam(2, $statement_no);
					$stmt->bindParam(3, $date);
					$stmt->bindParam(4, $weekday);
					$stmt->bindParam(5, $time);
					$stmt->bindParam(6, $start_timestamp);
					$stmt->bindParam(7, $end_timestamp);
					$stmt->bindParam(8, $absent_flag);
					$stmt->bindParam(9, $absent1_num);
					$stmt->bindParam(10, $absent2_num);
					$stmt->bindParam(11, $diff_hours);
					$stmt->bindParam(12, $fee_for_an_hour);
					$stmt->bindParam(13, $additional_fee);
					$stmt->bindParam(14, $fees);
					$stmt->bindParam(15, $lesson_id);
					$stmt->bindParam(16, $lesson_name);
					$stmt->bindParam(17, $subject_id);
					$stmt->bindParam(18, $subject_name);
					$stmt->bindParam(19, $course_id);
					$stmt->bindParam(20, $course_name);
					$stmt->bindParam(21, $student_id);
					$stmt->bindParam(22, $teacher_id);
					$stmt->bindParam(23, $place_id);
					$stmt->bindParam(24, $monthly_fee_flag);
					$stmt->bindParam(25, $comment);
					$no = $i;

					$statement_no = $last_no;
					$date = $event_array["date"];
					$weekday = $event_array["weekday"];
					$time = $event_array["time"];
					$start_timestamp = $event_array["start_timestamp"];
					$end_timestamp = $event_array["end_timestamp"];
					$absent_flag = $event_array["absent_flag"];
					$absent1_num = $event_array["absent1_num"];
					$absent2_num = $event_array["absent2_num"];
					$diff_hours = $event_array["diff_hours"];
					$fee_for_an_hour = str_replace(",", "", $event_array["fee_for_an_hour"]);
					$additional_fee = (int)str_replace(",", "", $event_array["additional_fee"]);
					$fees = str_replace(",", "", $event_array["fees"]);
					$lesson_id = $event_array["lesson_id"];
					$lesson_name = $event_array["lesson_name"];
					$subject_id = $event_array["subject_id"];
					$subject_name = $event_array["subject_name"];
					$course_id = $event_array["course_id"];
					$course_name = $event_array["course_name"];
					$student_id = $member_array["no"];
					$teacher_id = $event_array["teacher_id"];
					$place_id = $event_array["place_id"];
					$monthly_fee_flag = $event_array["monthly_fee_flag"];
					$comment = $event_array["comment"];
					$stmt->execute();
					$i++;

//	var_dump($event_array);

				}
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
												"lesson_name" => $lesson_name,
												"lesson_id" => $tmp_event["lesson_id"],
												"subject_name" => $subject_name,
												"type_name" => $type_name,
												"type_id" => $tmp_event["type_id"],
												"comment" => $comment);
*/
		}catch (PDOException $e){
			$errFlag = 1;
			//throw $e;
			var_export($event_array);echo"<BR>";
		  print('Error:'.$e->getMessage()."<BR>");
		}
		if ($errFlag == 0) {
			return true;
		} else {
			return false;
		}
}

errMsgFileCheck( $errArray );

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="index,follow">
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
<h3>生徒の月謝計算 - 請求データ保存</h3>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br><br>
<?php
		}
?>
	<a href="./menu.php">メニューへ戻る</a>
	<br>
<?php
	exit();
	}
?>
<!--
<div class="menu_box">
-->
<h3><font color="blue">請求データを保存しました。</font></h3>
<?php if ($_GET['go']) { ?>
<h3><font color="blue">PDFファイル・スプレッドシート出力実行中です。</font></h3>
<script type = "text/javascript">
<!--
location.href = "all_output_pdf.php?y=<?= $year ?>&m=<?= $month ?>";
//-->
</script>
<?php } ?>
<div class="menu_box">
	<br>
１．先生の授業時間をチェックする場合は、<a href="./check_list.php?y=<?=$year?>&m=<?=$month?>">こちら</a>です。<br><br>
２．休み回数をチェックする場合は、<a href="./absent_list.php?y=<?=$year?>&m=<?=$month?>">こちら</a>です。<br><br>
３．生徒一覧を表示する場合は、<a href="./student_list.php?y=<?=$year?>&m=<?=$month?>">こちら</a>です。<br><br>
４．明細書をpdfファイルに出力し、月謝金額お知らせメール送信の準備（明細書をスプレッドシートに出力）をする場合は、<a href="./all_output_pdf.php?y=<?= $year ?>&m=<?= $month ?>" target="_blank">こちら</a>です。<br>
　　&nbsp;<font color="red">※月謝金額お知らせメール送信の準備には、１分ぐらい時間がかかります。<br>
　　　　処理が終了しますまでしばらくお待ちください。</font><br><br>
５．部門別受講料を出力する場合は、<a href="./total_list.php?y=<?=$year?>&m=<?=$month?>">こちら</a>です。<br><br>
</div>
<!--
</div>
-->
<a href="./menu.php">メニューへ戻る</a>
<br>

<!--
<h3><?= $year ?>年<?= $month ?>月</h3>

<form method="post" name="search_form" action="./student_list.php">
<input type="hidden" name="y" value="<?=$year?>">
<input type="hidden" name="m" value="<?=$month?>">
生徒氏名：<input type="text" name="cond_name" value="">
<input type="submit" value="&nbsp;&nbsp;検&nbsp;索&nbsp;&nbsp;">
<input type="button" value="&nbsp;検索解除&nbsp;" onclick="search_reset()">
</form>
-->


</div>

</body></html>

