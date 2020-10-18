<?php
ini_set( 'display_errors', 0 );

require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
require_once("./calculate_fees.php");
$result = check_user($db, "1");

$errArray = array();

$year = trim($_GET["y"]);
$month = trim($_GET["m"]);
if (is_null($year) === true || empty($year) === true)   { $year = $_POST['y']; }
if (is_null($month) === true || empty($month) === true) { $month = $_POST['m']; }
$result = check_input_year_month($year, $month);
if ($result == false) {
	throw new Exception('年月が不明です。');
}

$cons_tax_rate = get_cons_tax_rate($year, $month);

try {
	
//$log_member_no = '000444';

	// 生徒情報（受講している教室と科目情報を含む）を取得
	$student_list = array();
	$param_array = array();
	$value_array = array();
	$order_array = array();
	array_push($param_array, "tbl_member.kind = ?");
	array_push($value_array, "3");
	if ($log_member_no)	array_push($param_array, "tbl_member.no = '$log_member_no'");
	$all_student_flag = "1";	// 前生徒と現生徒を抽出
	$member_list = get_simple_member_list($db, $param_array, $value_array, $order_array, $all_student_flag);
//var_dump($member_list);

	$course_list = get_course_list($db);

	if ($_POST['place0_def'] == '校舎登録') {
		foreach ($_POST as $key=>$value) {
			if (substr($key, 0, 6) != 'place-')	continue;
			$place_data = explode('-', $key); $place_id = null;
			foreach ($place_list as $place)	if ($place['name']==$value)	{ $place_id = $place['no']; break; }
			if (!$place_id)	continue;
			$member_no = $place_data[1];
			$lesson_id = $place_data[2];
			$course_id = $place_data[3];
//		echo "$member_no, $lesson_id, $course_id, $value, $place_id<br>";
			$db->query(
			"INSERT INTO tbl_place0 VALUES ('$year','$month','$member_no','$lesson_id','$course_id','{$place_id}',now(),now()) ".
			"ON DUPLICATE KEY UPDATE place_id='$place_id',update_timestamp=now() "
			);
		}
	}

	$place0_list = $db->query(
		"SELECT st.member_no,std.lesson_id,std.course_id,pl.place_id ".
		"FROM tbl_statement_detail AS std JOIN tbl_statement AS st ON (std.statement_no=st.statement_no) ".
		"LEFT JOIN tbl_place0 AS pl ON (st.seikyu_year=pl.year AND st.seikyu_month=pl.month AND st.member_no=pl.member_no AND std.lesson_id=pl.lesson_id ".
		"AND (std.course_id=pl.course_id or (std.course_id is null and pl.course_id=''))) ".
		"WHERE std.statement_no in ".
		"(SELECT statement_no FROM tbl_statement where seikyu_year=$year and seikyu_month=$month) ".
		"and std.place_id=0 ".
		"group by member_no,lesson_id,course_id "
		)->fetchAll(PDO::FETCH_ASSOC);
		
	foreach ($place0_list as $key=>$row) {
		if (!$member_list[$row['member_no']])	continue;
		if ($row['place_id'])	continue;
	switch ($row['course_id']) {
	case 4:
	case 5:
	case 6:
		$place_ids = $db->query(
			"SELECT place_id FROM tbl_statement_detail,tbl_statement WHERE ".
			"seikyu_year=$year and seikyu_month=$month ".
			"and tbl_statement_detail.statement_no=tbl_statement.statement_no ".
			"and member_no='{$row['member_no']}' ".
			"and lesson_id='{$row['lesson_id']}' and course_id='{$row['course_id']}' and place_id!=0 ".
			"order by start_timestamp desc "
			)->fetchAll(PDO::FETCH_COLUMN);
			break;
	case null:
		$place_ids = $db->query(
			"SELECT place_id FROM tbl_statement_detail,tbl_statement WHERE ".
			"seikyu_year=$year and seikyu_month=$month ".
			"and tbl_statement_detail.statement_no=tbl_statement.statement_no ".
			"and member_no='{$row['member_no']}' ".
			"and lesson_id='{$row['lesson_id']}' and course_id is null and place_id!=0 ".
			"order by start_timestamp desc "
			)->fetchAll(PDO::FETCH_COLUMN);
			break;
	default:
		$place_ids = $db->query(
			"SELECT place_id FROM tbl_statement_detail,tbl_statement WHERE ".
			"tbl_statement_detail.statement_no=tbl_statement.statement_no ".
			"and member_no='{$row['member_no']}' ".
			"and start_timestamp<UNIX_TIMESTAMP(ADDDATE('$year-$month-01',INTERVAL 1 MONTH)) ".
			"and lesson_id='{$row['lesson_id']}' and course_id='{$row['course_id']}' and place_id!=0 ".
			"order by start_timestamp desc "
			)->fetchAll(PDO::FETCH_COLUMN);
	}
		if ($place_ids) {
			$place_id = $place_ids[0];
			$place0_list[$key]['place_id'] = $place_id;
			$db->query(
			"INSERT INTO tbl_place0 VALUES ('$year','$month','{$row['member_no']}','{$row['lesson_id']}','{$row['course_id']}',{$place_id},now(),now()) ".
			"ON DUPLICATE KEY UPDATE place_id=$place_id,update_timestamp=now() "
			);
		} else {
	//		var_dump($row);echo"<br>";
		}
	}

	$place0_input='';
	foreach ($place_list as $key=>$place)		if ($place['name']!='北口校') $places[]=$place['name'];
	sort($places);
	foreach ($place0_list as $key=>$row) {
		if (!$member_list[$row['member_no']])	continue;
		if ($row['place_id'])	continue;
	$place0_input .= "<tr><td>{$member_list[$row['member_no']]['name']}</td><td>{$lesson_list[$row['lesson_id']]}</td><td>{$course_list[$row['course_id']]['course_name']}</td><td>";
		disp_pulldown_menu($places, "place-{$row['member_no']}-{$row['lesson_id']}-{$row['course_id']}", '', '', $place_select);
		$place0_input .= $place_select."</td></tr>\n";
	}
	if ($place0_input) {
		print <<<PLACE0_INPUT_PAGE
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<link rel="stylesheet" type="text/css" href="./script/print.css" media="print" />
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
</script>
</head>
<body>
<div id="header">
	事務システム
</div>

<div id="content">

<center>
		<h3>年月別一覧 - 部門別受講料</h3>
		<div>
			<a href="menu.php">メニューへ戻る</a>&nbsp;&nbsp;
		</div>
<br>
以下の生徒はオンライン授業です。売上を計上する校舎を指定してください。
<br>
<form method="post" action="./total_list.php">
<input type="hidden" name="y" value="$year">
<input type="hidden" name="m" value="$month">
<table border="1\"><tr><th>生徒名</th><th>教室</th><th>コース</th><th>校舎</th></tr>
$place0_input
</table>
<input type="submit" name="place0_def" value="校舎登録">
</form>
</center>
</div>
</body></html>
PLACE0_INPUT_PAGE;
		exit;
	}

	$last_total_price = 0;
	$total_price = 0;
	$lesson_total_price = 0;
	$monthly_total_price = 0;
	$textbook_total_price = 0;
	$entrance_fee_total_price = 0;
	$consumption_tax_price = 0;
	$total_consumption_tax_price = 0;
	$season_class_total = 0;

	$price_list = array();
	$price_list['membership_fee'] = 0;
	//$price_list['textbook_price'] = 0;
	$price_list['entrance_fee'] = 0;
	$price_list['consumption_tax_price'] = 0;
	$price_list['test_price'] = 0;
	$price_list['tokusoku'] = 0;
	$lesson_array = array();
	foreach ($lesson_list as $lesson_id => $lesson) {
  	$price_list['lesson'][$lesson_id] = 0;
  	$lesson_array[$lesson_id] = array("fees"=>0, "hours"=>0);
  	$price_list['textbook_price'][$lesson_id] = 0;
		//$price_list['entrance_fee'][$lesson_id] = 0;
	}

	$sql = "SELECT * FROM tbl_statement where seikyu_year=? and seikyu_month=?";
	$stmt = $db->prepare($sql);
	$stmt->bindParam(1, $tmp_year);
	$stmt->bindParam(2, $tmp_month);
	$tmp_year = $year;
	$tmp_month = $month;
	$stmt->execute();
	$statement_array = $stmt->fetchAll(PDO::FETCH_BOTH);
	if (count($statement_array) < 1) {
		$message = '<br>対象年月の明細書を保存してから部門別受講料を算出してください。<br>';
		$message .= '<a href="./save_statement.php?y='.$year.'&m='.$month.'">'.$year.'年'.$month.'月の明細書を保存する</a>';
    throw new Exception($message);
	}

	foreach ($member_list as $member_no => $member) {
//echo "{$member_list[$member_no]['name']}<br>";
		$tax_season_class_total = 0;

		$lesson_array = array();
		$consumption_tax_price = 0;
		
    $statement_list = array();
		$sql = "SELECT * FROM tbl_statement where member_no=? and  seikyu_year=? and seikyu_month=?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $tmp_member_no);
		$stmt->bindParam(2, $tmp_year);
		$stmt->bindParam(3, $tmp_month);
		$tmp_member_no = $member_no;
		$tmp_year = $year;
		$tmp_month = $month;
		$stmt->execute();
		$statement_array = $stmt->fetchAll(PDO::FETCH_BOTH);

		if (count($statement_array) > 0) {
			$statement_no = $statement_array[0]["statement_no"];
			$statement_list[$statement_no] = $statement_array[0];

			$sql = "SELECT * FROM tbl_statement_detail where statement_no=?";
			$stmt = $db->prepare($sql);
			$stmt->bindParam(1, $tmp_statement_no);
			$tmp_statement_no = $statement_no;
			$stmt->execute();
			$statement_event_array = $stmt->fetchAll(PDO::FETCH_BOTH);
			$statement_list[$statement_no]['event_list'] = $statement_event_array;
//if ($member_no == $log_member_no)	{var_dump($statement_event_array);echo "<BR>";}
		}

		foreach ($statement_list as $statement_no => $statement) {

			// 20160127 消費税金額（生徒ごとに異なる）
			$consumption_tax_price = (int)str_replace(",","",$statement["consumption_tax_price"]);
			//$price_list['consumption_tax_price'] = $price_list['consumption_tax_price'] + $consumption_tax_price;
			$total_consumption_tax_price = $total_consumption_tax_price + $consumption_tax_price;

			// 月会費
			$membership_fee = (int)str_replace(",","",$statement["membership_fee"]);
			// 20160705 消費税を月会費に振り分ける
			if ($consumption_tax_price > 0) {
				$tax_membership_fee = $membership_fee * $cons_tax_rate;
				$membership_fee = $membership_fee + $tax_membership_fee;
			}
			$price_list['membership_fee'] = $price_list['membership_fee'] + $membership_fee;
			$total_price = $total_price + $membership_fee;

			// 入会金
			//$total_price = $total_price + $entrance_fee_price;
			$entrance_fee = (int)str_replace(",","",$statement["entrance_fee"]);
			// 20160705 消費税を入会金に振り分ける
			if ($consumption_tax_price > 0) {
				$tax_entrance_fee = $entrance_fee * $cons_tax_rate;
				$entrance_fee += $tax_entrance_fee;
			}
			$price_list['entrance_fee'] = $price_list['entrance_fee'] + $entrance_fee;
			//$entrance_fee_total_price += $entrance_fee;
			$total_price = $total_price + $entrance_fee;

			// 科目別授業料
			$hours = 0;
			$fees = 0;
			$lesson_array = array();
			foreach ($lesson_list as $lesson_id => $lesson) {
		  	$lesson_array[$lesson_id] = array("fees"=>0, "hours"=>0);
			}

			$tax_season_class_total = 0;
			$tax_satsun_class_total = 0;
			
			foreach ($statement["event_list"] as $event) {
				
				if (!$event["lesson_id"])	continue;
				if (!$event['place_id']) {
					foreach ($place0_list as $row) {
						if ($row['member_no'] == $member_no && $row['lesson_id'] == $event['lesson_id'] && $row['course_id'] == $event['course_id']) {
							$event['place_id'] = $row['place_id'];
						break;
						}
					}
				}
				if (!$event['place_id']) {
//				echo "$member_no, {$event['lesson_id']}, {$event['course_id']}.<br>";
					array_push($errArray, "{$member_list[$member_no]['name']}-{$lesson_list[$event['lesson_id']]}-{$course_list[$event['course_id']]['course_name']} 校舎不明エラー");
				}
				
				$lesson_id = $event["lesson_id"];
				$fees = str_replace(",","",$event["fees"]);
				$hours = str_replace(",","",$event["diff_hours"]);
				
				if (($event['course_id'] == 4) || 	// 夏期講習
						($event['course_id'] == 5) || 	// 冬期講習
						($event['course_id'] == 6)) { 	// 春期講習
						if ($member["tax_flag"] == "1") {
							$tax_season_class = $fees * $cons_tax_rate;
							$fees += $tax_season_class;
							$tax_season_class_total += $tax_season_class;
						}
						$season_class_total += $fees ;
						$lesson_array[$lesson_id]['hours'] += $hours;
if ($member_no == $log_member_no)	echo "{$event['date']} fees2 $fees<BR>";
						$total_price += $fees ;
						
						if ($event['place_id'])	$price_list['season_place'][$event['place_id']] += $fees;
						continue;
				}
				if ($event['course_id'] === NULL ) { 	// 土日講習
						if ($member["tax_flag"] == "1") {
							$tax_satsun_class = $fees * $cons_tax_rate;
							$fees += $tax_satsun_class;
							$tax_satsun_class_total += $tax_satsun_class;
						}
						$satsun_class_total += $fees ;
						$lesson_array[$lesson_id]['hours'] += $hours;
if ($member_no == $log_member_no)	echo "{$event['date']} fees2 $fees<BR>";
						$total_price += $fees ;
						
						if ($event['place_id'])	$price_list['satsun_place'][$event['place_id']] += $fees;
						continue;
				}
				//if (array_key_exists($lesson_id, $price_list['lesson']) === true) {
				// すでに科目がprice_listにあるとき
					//$price_list['lesson'][$lesson_id]['fees'] += $fees;
					//$price_list['lesson'][$lesson_id]['hours'] += $hours;
if ($member_no == $log_member_no)	echo "{$event['date']} fees1 $fees<BR>";
//if ($lesson_id==4) echo "{$lesson_array[$lesson_id]['fees']} += {$fees}<br>";
					$lesson_array[$lesson_id]['fees'] += $fees;
					$lesson_array[$lesson_id]['hours'] += $hours;
					$lesson_array[$lesson_id]['fees_place'][$event['place_id']] += $fees;
					//$total_price = $total_price + $fees;
					//$lesson_total_price = $lesson_total_price + $fees;
				//} else {
				// まだ科目がprice_listにないとき
				//	$price_list['lesson'][$lesson_id] = $fees;
				//}
				//$fees = 0;
			}
		}

		// 月謝
		$calculator = new calculate_fees();
		$result = $calculator->calculate($member_no, $year, $month);
		if ($result == false) {
				array_push($errArray, "{$member['name']}: 月謝エラーが発生しました。");
		} else {
			$lesson_detail_list = $calculator->get_lesson_detail_list();
			foreach ($lesson_array as $lesson_id => $item) {
				$tmp_index= array_search($lesson_id,array_column($lesson_detail_list,'lesson_id'));
//if ($lesson_id==4 && $tmp_index !== false) echo "{$lesson_array[$lesson_id]['monthly_fees']} += {$lesson_detail_list[$tmp_index]['monthly_fees_total']}<br>";
				if ($tmp_index !== false) $lesson_array[$lesson_id]['monthly_fees'] += $lesson_detail_list[$tmp_index]['monthly_fees_total'];
			}
if ($member_no == $log_member_no)	{var_dump($lesson_array);echo "<BR>";}
		}
		unset($calculator);


		// その他項目（これまでの模試代がこちらに含まれている）
		$tax_others = 0;
		$param_array = array("tbl_others.member_no=?", "tbl_others.year=?", "tbl_others.month=?");
		$value_array = array($member_no, $year, $month);
		$order_array = array("tbl_others.year, tbl_others.month, tbl_others.others_no");
		$others_list = get_others_list($db, $param_array, $value_array, $order_array);
		foreach ($others_list as $key => $others) {
			$others_price = (int)str_replace(",","",$others["price"]);
			if ($member["tax_flag"] == "1" && $others["tax_flag"] == null) {
if ($member_no == $log_member_no)	{var_dump($others);echo "<BR>";}	
				$tax_others += floor($others_price * $cons_tax_rate);
				$others_price = $others_price + floor($others_price * $cons_tax_rate);
			}

			if ($others["kind"] == "1") {
				//20180208, 20180509 2018冬期講習春期講習特別対応
				if ($others["name"] == "冬期講習" || $others["name"] == "春期講習") {
					$season_class_total += $others_price;
				} else {
			// 種類が授業の場合
					$lesson_id = $others["lesson_id"];
					$price_list['lesson'][$lesson_id] = $price_list['lesson'][$lesson_id] + $others_price;
					$lesson_total_price = $lesson_total_price + $others_price;
					$price_list['lesson_place'][$lesson_id][$others['place_id']] += $others_price;
				}
			} else if ($others["kind"] == "2") {
			// 種類がテキストの場合
				//$price_list['textbook_price'] = $price_list['textbook_price'] + $others_price;
				$lesson_id = $others["lesson_id"];
				$price_list['textbook_price'][$lesson_id] = $price_list['textbook_price'][$lesson_id] + $others["price"];
				$textbook_total_price = $textbook_total_price + $others_price;
			} else if ($others["kind"] == "3") {
			// 種類が模試の場合
				$price_list['test_price'] = $price_list['test_price'] + $others_price;
			// 20160103追加
			} else if ($others["kind"] == "4") {
			// 種類が月会費の場合
				$price_list['membership_fee'] = $price_list['membership_fee'] + $others_price;
			} else if ($others["kind"] == "5") {
			// 種類が入会金の場合
				$price_list['entrance_fee'] = $price_list['entrance_fee'] + $others_price;
				//$lesson_id = $others["lesson_id"];
				//$price_list['entrance_fee'][$lesson_id] = $price_list['entrance_fee'][$lesson_id] + $others_price;
				//$entrance_fee_total_price = $entrance_fee_total_price + $others_price;
			} else if ($others["kind"] == "6") {
			// 種類が督促金の場合
				$price_list['tokusoku'] = $price_list['tokusoku'] + $others_price;
			} else if ($others["kind"] == "7") {
			// 20170830 2017夏期講習の特別対応
				$season_class_total += $others_price;
			} else if ($others["kind"] == "8") {
			// 過払い調整
				continue;
			}

			$total_price = $total_price + $others_price;
if ($member_no == $log_member_no)	echo "others $others_price<BR>";
		}

		// 授業料分割払い（対象年月に支払った分だけでよい：確認済み）
		$param_array = array("tbl_divided_payment.member_no=?", "tbl_divided_payment_detail.payment_year=?", "tbl_divided_payment_detail.payment_month=?");
		$value_array = array($member_no, $year, $month);
		$order_array = array("tbl_divided_payment.payment_no","tbl_divided_payment_detail.time_no");
		$sql = 
			"SELECT
					tbl_divided_payment.payment_no as payment_no,
					tbl_divided_payment.member_no as member_no,
					tbl_divided_payment.year as year,
					tbl_divided_payment.month as month,
					tbl_divided_payment.lesson_id as lesson_id,
					tbl_divided_payment.type_id as type_id,
					tbl_divided_payment.time as time,
					tbl_divided_payment.payment_price as payment_price,
					tbl_divided_payment.memo as memo,
					tbl_divided_payment_detail.payment_no as payment_no,
					tbl_divided_payment_detail.time_no as time_no,
					tbl_divided_payment_detail.payment_year as payment_year,
					tbl_divided_payment_detail.payment_month as payment_month,
					tbl_divided_payment_detail.price as price
			 FROM tbl_divided_payment, tbl_divided_payment_detail";
		$sql .= " where tbl_divided_payment_detail.payment_no = tbl_divided_payment.payment_no";
		if(count($param_array) > 0){
			$sql .= " and " . join(" and ",$param_array);
		}
		if(count($order_array) > 0){
			$sql .= "	order by " . join(" , ",$order_array);
		}
		else {
			$sql .= "	order by tbl_divided_payment.year, tbl_divided_payment.month";
		}
		$stmt = $db->prepare($sql);
		$stmt->execute($value_array);
		$divided_payment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$tax_divided = 0;
		if ($year<2019 || ($year==2019 && $month<4)) {
			foreach ($divided_payment_list as $divided_payment) {
				$lesson_id = $divided_payment["lesson_id"];
				$divided_payment_price = (int)str_replace(",","",$divided_payment["price"]);
				if ($member["tax_flag"] == "1") {
					$tax_divided += $divided_payment_price*$cons_tax_rate;
					$divided_payment_price *= (1.0+$cons_tax_rate);
				}
				$price_list['lesson'][$lesson_id]		+= $divided_payment_price;
				$total_price												+= $divided_payment_price;
	if ($member_no == $log_member_no)	echo "divide $divided_payment_price<BR>";
				$lesson_total_price									+= $divided_payment_price;
				if ($divided_payment["year"] == $year && $divided_payment["month"] == $month) {
					if ($member["tax_flag"] == "1") {
						$tax_season_class_total -= $divided_payment["payment_price"]*$cons_tax_rate;
						$divided_payment["payment_price"] *=(1.0+$cons_tax_rate);
					}
					$price_list['lesson'][$lesson_id]	-= $divided_payment["payment_price"];
					$total_price 											-= $divided_payment["payment_price"];
	if ($member_no == $log_member_no)	echo "divide {$divided_payment["payment_price"]}<BR>";
					$lesson_total_price								-= $divided_payment["payment_price"];
				}
			}
		} else if ($member["tax_flag"] == "1") {
			foreach ($divided_payment_list as $divided_payment) {
				$lesson_id = $divided_payment["lesson_id"];
				$divided_payment_price = (int)str_replace(",","",$divided_payment["price"]);
				$tax_divided += $divided_payment_price*$cons_tax_rate;
				$divided_payment_price *= (1.0+$cons_tax_rate);
	if ($member_no == $log_member_no)	echo "divide $divided_payment_price<BR>";
				if ($divided_payment["year"] == $year && $divided_payment["month"] == $month) {
						$tax_season_class_total -= $divided_payment["payment_price"]*$cons_tax_rate;
						$divided_payment["payment_price"] *=(1.0+$cons_tax_rate);
				}
			}
		}

if ($member_no == $log_member_no)	echo "consumption_tax_price $consumption_tax_price <BR>";
if ($member_no == $log_member_no)	echo "tax_entrance_fee $tax_entrance_fee <BR>";
if ($member_no == $log_member_no)	echo "tax_membership_fee $tax_membership_fee <BR>";
if ($member_no == $log_member_no)	echo "tax_others $tax_others <BR>";
if ($member_no == $log_member_no)	echo "tax_divided $tax_divided <BR>";
		// 20160705 消費税の振り分け
		if ($consumption_tax_price > 0) {
			// 授業時間が短い順に昇順にソート
			$lesson_array0 = $lesson_array;
			$result = uasort($lesson_array0, "cmp_hours");
			$tmp_tax_total_lesson_fee = 0;
			$tmp_tax_total_monthly_fee = 0;
			foreach ($lesson_array0 as $lesson_id => $item) {
				$lesson_array[$lesson_id]['fees'] = floor($item['fees'] * (1.0+$cons_tax_rate));
				// 20160810堀内不具合修正　+を追加
				//$tmp_tax_total_lesson_fee = floor($item['fees'] * $cons_tax_rate);
				$tmp_tax_total_lesson_fee += floor($item['fees'] * $cons_tax_rate);
				$lesson_array[$lesson_id]['monthly_fees'] = floor($item['monthly_fees'] * (1.0+$cons_tax_rate));
 				$tmp_tax_total_monthly_fee += floor($item['monthly_fees'] * $cons_tax_rate);
     }
			// 部門ごとに消費税率をかけ切り捨てる
			// 切り捨てにより消費税合計金額より少ない場合は、授業時間が一番多い部門に加える
      $tax_total_lesson_fee_old = $consumption_tax_price - $tax_entrance_fee - $tax_membership_fee - $tax_others - $tax_season_class_total - $tax_satsun_class_total;
      $tax_total_lesson_fee = $consumption_tax_price - $tax_entrance_fee - $tax_membership_fee - $tax_others - $tax_divided - $tax_season_class_total - $tax_satsun_class_total;
/*
if ($tax_total_lesson_fee_old!=$tax_total_lesson_fee) {
	echo "old: $tax_total_lesson_fee_old = $consumption_tax_price - $tax_entrance_fee - $tax_membership_fee - $tax_others - $tax_season_class_total;<br>";
	echo "new: $tax_total_lesson_fee = $consumption_tax_price - $tax_entrance_fee - $tax_membership_fee - $tax_others - $tax_divided;<br>";
	echo "$tmp_tax_total_lesson_fee+$tmp_tax_total_monthly_fee < $tax_total_lesson_fee<br>";
}
*/
if ($member_no == $log_member_no)	echo "tax_total_lesson_fee $tax_total_lesson_fee <BR>";
if ($member_no == $log_member_no)	echo "tmp_tax_total_lesson_fee $tmp_tax_total_lesson_fee <BR>";
if ($member_no == $log_member_no)	echo "tmp_tax_total_monthly_fee $tmp_tax_total_monthly_fee <BR>";
			if ($tmp_tax_total_lesson_fee+$tmp_tax_total_monthly_fee < $tax_total_lesson_fee) {
//echo "lesson_id $lesson_id, member_no $member_no, ".($tax_total_lesson_fee - $tmp_tax_total_lesson_fee - $tmp_tax_total_monthly_fee)."<BR>";
				$lesson_array[$lesson_id]['fees'] += ($tax_total_lesson_fee - $tmp_tax_total_lesson_fee - $tmp_tax_total_monthly_fee);
if ($member_no == $log_member_no)	echo "lesson_id $lesson_id lesson-fees {$lesson_array[$lesson_id]['fees']}<BR>";
			}
 		}

		foreach ($lesson_array as $lesson_id => $item) {
			$price_list['lesson'][$lesson_id] += $item['fees'];
			if ($consumption_tax_price > 0)
				foreach ($place_list as $key=>$item0)	$price_list['lesson_place'][$lesson_id][$key] += floor($item['fees_place'][$key] * (1.0+$cons_tax_rate));
			else
				foreach ($place_list as $key=>$item0)	$price_list['lesson_place'][$lesson_id][$key] += $item['fees_place'][$key];
			$total_price = $total_price + $item['fees'];
if ($member_no == $log_member_no)	echo "lesson_id $lesson_id lesson-fees {$item['fees']}<BR>";
			$lesson_total_price += $item['fees'];
		}

		foreach ($lesson_array as $lesson_id => $item) {
//if ($lesson_id==4) echo "{$price_list['monthly'][$lesson_id]} += {$item['monthly_fees']}<br>";
			$price_list['monthly'][$lesson_id] += $item['monthly_fees'];
			$total_price = $total_price + $item['monthly_fees'];
if ($member_no == $log_member_no)	echo "lesson_id $lesson_id monthly-fees {$item['monthly_fees']}<BR>";
			$monthly_total_price += $item['monthly_fees'];
		}

		// 月会費
		//$tmp_membership_fee = $calculator->get_membership_fee();
		//$membership_fee = (int)str_replace(",","",$tmp_membership_fee);
		//$price_list['membership_fee'] = $price_list['membership_fee'] + $membership_fee;
		//$total_price = $total_price + $membership_fee;

		// テキスト代（模試代含む）
		// 20151009 テキスト購入テーブルに科目も登録してあるが集計には使わない
		$param_array = array("tbl_buying_textbook.member_no=?", "tbl_buying_textbook.year=?", "tbl_buying_textbook.month=?");
		$value_array = array($member_no, $year, $month);
		$order_array = array("tbl_buying_textbook.input_year", "tbl_buying_textbook.input_month", "tbl_buying_textbook.input_day", "tbl_buying_textbook.buying_no");
		$buying_textbook_list = get_buying_textbook_list($db, $param_array, $value_array, $order_array);
		//$textbook_price = 0;
		foreach ($buying_textbook_list as $buying) {
			if ($buying["kind"] == "2") {
			// 種類がテキストの場合
				$lesson_id = $buying["lesson_id"];
				$price_list['textbook_price'][$lesson_id] = $price_list['textbook_price'][$lesson_id] + $buying["price"];
				$textbook_total_price = $textbook_total_price + $buying["price"];
			} else if ($buying["kind"] == "3") {
			// 種類が模擬テストの場合
				$price_list['test_price'] = $price_list['test_price'] + $buying["price"];
			}
			$total_price = $total_price + $buying["price"];
if ($member_no == $log_member_no)	echo "text {$buying["price"]}<BR>";
		}

		// 入会費
/*
		$param_array = array("tbl_entrance_fee.member_no=?", "tbl_entrance_fee.year=?", "tbl_entrance_fee.month=?");
		$value_array = array($member_no, $year, $month);
		$order_array = array("tbl_entrance_fee.year, tbl_entrance_fee.month, tbl_entrance_fee.entrance_fee_no");
		$entrance_fee_list = get_entrance_fee_list($db, $param_array, $value_array, $order_array);
		foreach ($entrance_fee_list as $key => $entrance_fee) {
			$entrance_fee_price = (int)str_replace(",","",$entrance_fee["price"]);
			//$price_list['entrance_fee'] = $price_list['entrance_fee'] + $entrance_fee_price;
			$entrance_fee_total_price = $entrance_fee_total_price + $entrance_fee_price;
			$total_price = $total_price + $entrance_fee_price;
		}
*/

//var_dump($price_list); echo"<br>";
//$statementCount=count($statement_array);
//echo "$statementCount, $member_no, $total_price<br>";

$member_total=$total_price-$last_total_price;
$last_total_price = $total_price;
//if ($member_total)	echo "{$member_list[$member_no]['name']}, $member_total<BR>";

/*
	echo "{$member_list[$member_no]['name']}, {$price_list['lesson'][1]}, ";
	$price = $price_list['lesson_place'][1];
	$rowtotal = 0;
	foreach ($place_list as $key1=>$item1)	{
		if ($key1==11)	continue;
		if ($key1==3)	{
			if (11 != $key0)	$rowtotal += $price[11];
		}
		if ($key1 != $key0)	$rowtotal += $price[$key1];
	}
	echo "$rowtotal.<BR>";
*/
	}

//var_dump($price_list['lesson']);

	//$last_total_price = $total_price + $total_consumption_tax_price;
	$last_total_price = $total_price;

} catch (Exception $e) {
	// 処理を中断するほどの致命的なエラー
//var_dump($e->getMessage());
	array_push($errArray, $e->getMessage());
}

function cmp_hours($a, $b) {
      if ($a["hours"] == $b["hours"]) {
		    return 0;
      }
      return ($a["hours"] > $b["hours"]) ? +1 : -1;
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<link rel="stylesheet" type="text/css" href="./script/print.css" media="print" />
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
</script>
</head>
<body>
<div id="header">
	事務システム
</div>

<div id="content">

<center>
		<h3>年月別一覧 - 部門別受講料</h3>
		<div>
			<a href="menu.php">メニューへ戻る</a>&nbsp;&nbsp;
		</div>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red"><?= $error ?></font><br><br>
<?php
		}
	}
?>


<h3><?= $year ?>年<?= $month ?>月</h3>
作成日付：<?= date('Y/m/d H:i:s') ?><br>
<?php
if (count($price_list) > 0) {
?>
<table class="meisai" cellpadding="2">
	<tr>
	<th class="meisai" colspan="2">項目</th><th class="meisai">金額</th>
	</tr>
<?php
		$lesson_num = count($price_list['lesson']);
		$i = 0;
		foreach ($price_list['lesson'] as $lesson_id => $price) {
?>
		<tr>
<?php
			if ($i == 0) {
?>
				<td width="60" align="left" class="meisai" rowspan="<?=$lesson_num+1?>">授業料</td>
<?php
			}
?>
			<td width="120" align="left" class="meisai"><?= $lesson_list[$lesson_id] ?></td>
			<td width="140" align="right" class="meisai"><?= number_format($price) ?></td>
		</tr>
<?php
			$i++;
		}
?>
		<tr>
			<td width="120" align="right" class="meisai"><b><font color="red">合計</font></b></td>
			<td width="140" align="right" class="meisai"><font color="red"><?= number_format($lesson_total_price) ?></font></td>
		</tr>
<?php
		$lesson_num = count($price_list['lesson']);
		$i = 0;
		if ($monthly_total_price) {
		foreach ($price_list['monthly'] as $lesson_id => $price) {
?>
		<tr>
<?php
			if ($i == 0) {
?>
				<td width="60" align="left" class="meisai" rowspan="<?=$lesson_num+1?>">月謝</td>
<?php
			}
?>
			<td width="120" align="left" class="meisai"><?= $lesson_list[$lesson_id] ?></td>
			<td width="140" align="right" class="meisai"><?= number_format($price) ?></td>
		</tr>
<?php
			$i++;
		}
?>
		<tr>
			<td width="120" align="right" class="meisai"><b><font color="red">合計</font></b></td>
			<td width="140" align="right" class="meisai"><font color="red"><?= number_format($monthly_total_price) ?></font></td>
		</tr>

<?php
		}
		$lesson_num = count($price_list['textbook_price']);
		$i = 0;
		foreach ($price_list['textbook_price'] as $lesson_id => $price) {
?>
		<tr>
<?php
			if ($i == 0) {
?>
				<td width="60" align="left" class="meisai" rowspan="<?=$lesson_num+1?>">テキスト代</td>
<?php
			}
?>
			<td width="120" align="left" class="meisai"><?= $lesson_list[$lesson_id] ?></td>
			<td width="140" align="right" class="meisai"><?= number_format($price) ?></td>
		</tr>
<?php
			$i++;
		}
?>
		<tr>
			<td width="120" align="right" class="meisai"><b><font color="red">合計</font></b></td>
			<td width="140" align="right" class="meisai"><font color="red"><?= number_format($textbook_total_price) ?></font></td>
		</tr>
		<tr>
			<td width="120" align="left" class="meisai">模擬試験代</td>
			<td width="120" align="left" class="meisai">塾</td>
			<td width="140" align="right" class="meisai"><?= number_format($price_list['test_price']) ?></td>
		</tr>
<?php if ($season_class_total>0) { ?>
		<tr>
			<td width="120" align="left" class="meisai" colspan="2">季節講習</td>
			<td width="140" align="right" class="meisai"><?= number_format($season_class_total) ?></td>
		</tr>
<?php } ?>
<?php if ($satsun_class_total>0) { ?>
		<tr>
			<td width="120" align="left" class="meisai" colspan="2">土日講習</td>
			<td width="140" align="right" class="meisai"><?= number_format($satsun_class_total) ?></td>
		</tr>
<?php } ?>
		<tr>
			<td width="120" align="left" class="meisai" colspan="2">入会金</td>
			<td width="140" align="right" class="meisai"><?= number_format($price_list['entrance_fee']) ?></td>
		</tr>
<!--
<?php
		$lesson_num = count($price_list['entrance_fee']);
		$i = 0;
		foreach ($price_list['entrance_fee'] as $lesson_id => $price) {
?>
		<tr>
<?php
			if ($i == 0) {
?>
				<td width="60" align="left" class="meisai" rowspan="<?=$lesson_num+1?>">入会金</td>
<?php
			}
?>
			<td width="120" align="left" class="meisai"><?= $lesson_list[$lesson_id] ?></td>
			<td width="140" align="right" class="meisai"><?= number_format($price) ?></td>
		</tr>
<?php
			$i++;
		}
?>
		<tr>
			<td width="120" align="right" class="meisai"><b><font color="red">合計</font></b></td>
			<td width="140" align="right" class="meisai"><font color="red"><?= number_format($entrance_fee_total_price) ?></font></td>
		</tr>
-->
		<tr>
			<td width="120" align="left" class="meisai" colspan="2">月会費</td>
			<td width="140" align="right" class="meisai"><?= number_format($price_list['membership_fee']) ?></td>
		</tr>
		<tr>
			<td width="120" align="left" class="meisai" colspan="2">督促金</td>
			<td width="140" align="right" class="meisai"><?= number_format($price_list['tokusoku']) ?></td>
		</tr>
<?php
	//}
?>
<!--
		<tr>
			<td width="120" align="left" class="meisai" colspan="2">合計金額</td>
			<td width="140" align="right" class="meisai"><?= number_format($total_price) ?></td>
		</tr>
		<tr>
			<td width="120" align="left" class="meisai" colspan="2">消費税</td>
			<td width="140" align="right" class="meisai"><?= number_format($total_consumption_tax_price) ?></td>
		</tr>
-->
		<tr>
			<td width="120" align="right" class="meisai" colspan="2"><b>総合計金額</b></td>
			<td width="140" align="right" class="meisai"><b><?= number_format($last_total_price) ?></b></td>
		</tr>
</table>
<?php
}
?>
<br><br>

<h3>校舎別</h3>
<table class="meisai" cellpadding="2">
	<tr>
	<th class="meisai" colspan="2" rowspan="2">項目</th>
<?php
	foreach ($price_list['lesson_place'] as $lesson_id => $price) {
		$sum0 = 0;
		foreach ($place_list as $key=>$item0) {
			if (strpos($item0['name'], '北口校') !== false) $sum0 += $price[$key];
			if ($item0['name'] == '北口校') $key0 = $key;
		}
		$price_list['lesson_place'][$lesson_id][$key0] = $sum0;
	}
	$place_list[$key0]['name'] = '北口校合計';
	foreach ($place_list as $key=>$item0)	{
		if ($key==11)	continue;
		if (strpos($item0['name'], '北口校') !== false && $key!=11) { $kitaguchi_num++; continue; }
		if ($kitaguchi_num) { $kitaguchi_num++; echo "<th colspan=\"$kitaguchi_num\">北口校</th>"; $kitaguchi_num = 0;}
		echo "<th class=\"meisai\" rowspan=\"2\">{$item0['name']}</th>";
	}
	echo "<th class=\"meisai\" rowspan=\"2\">全校舎合計</th></tr><tr>";
	$lesson_num = count($lesson_list);
	foreach ($place_list as $key=>$item0)	{
		if ($key==11)	continue;
		if (strpos($item0['name'], '北口校') === false)	continue;
		if ($key==3)	echo "<th class=\"meisai\">{$place_list[11]['name']}</th>";
		echo "<th class=\"meisai\">{$item0['name']}</th>";
	}
?>
	</tr>
	<tr>
		<td width="60" align="left" class="meisai" rowspan="<?=$lesson_num+1?>">授業料</td>
<?php
		foreach ($price_list['lesson_place'] as $lesson_id => $price) {
			$rowtotal = 0;
?>
		<td width="100" align="left" class="meisai"><?= $lesson_list[$lesson_id] ?></td>
<?php
			foreach ($place_list as $key1=>$item1)	{
				if ($key1==11)	continue;
				if ($key1==3)	{
					echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($price[11])."</td>";
					$total_fees_place[11] += $price[11];
					if (11 != $key0)	$rowtotal += $price[11];
				}
				echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($price[$key1])."</td>";
				$total_fees_place[$key1] += $price[$key1];
				if ($key1 != $key0)	$rowtotal += $price[$key1];
			}
			echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($rowtotal)."</td>";
			echo "</tr><tr>";
		}
?>
		<td width="100" align="right" class="meisai"><b><font color="red">合計</font></b></td>
<?php
		$rowtotal = 0;
		foreach ($place_list as $key1=>$item1) {
			if ($key1==11)	continue;
			if ($key1==3)	{
				echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($total_fees_place[11])."</td>";
				if (11 != $key0)	$rowtotal += $total_fees_place[11];
			}
			echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($total_fees_place[$key1])."</td>";
			if ($key1 != $key0)	$rowtotal += $total_fees_place[$key1];
		}
		echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($rowtotal)."</td>";
?>
		</tr>
<?php if ($season_class_total>0) { ?>
	<tr>
		<td align="left" class="meisai" colspan="2">季節講習</td>
<?php
		$price = $price_list['season_place'];
		$sum0 = 0;
		foreach ($place_list as $key=>$item0) {
			if (strpos($item0['name'], '北口校') !== false) $sum0 += $price[$key];
			if ($item0['name'] == '北口校') $key0 = $key;
		}
		$price[$key0] = $sum0;
		$rowtotal = 0;
		foreach ($place_list as $key1=>$item1)	{
			if ($key1==11)	continue;
			if ($key1==3)	{
				echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($price[11])."</td>";
				$total_fees_place[11] += $price[11];
				if (11 != $key0)	$rowtotal += $price[11];
			}
			echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($price[$key1])."</td>";
			$total_fees_place[$key1] += $price[$key1];
			if ($key1 != $key0)	$rowtotal += $price[$key1];
		}
		echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($rowtotal)."</td>";
?>
	</tr>
<?php } ?>
<?php if ($satsun_class_total>0) { ?>
	<tr>
		<td align="left" class="meisai" colspan="2">土日講習</td>
<?php
		$price = $price_list['satsun_place'];
		$sum0 = 0;
		foreach ($place_list as $key=>$item0) {
			if (strpos($item0['name'], '北口校') !== false) $sum0 += $price[$key];
			if ($item0['name'] == '北口校') $key0 = $key;
		}
		$price[$key0] = $sum0;
		$rowtotal = 0;
		foreach ($place_list as $key1=>$item1)	{
			if ($key1==11)	continue;
			if ($key1==3)	{
				echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($price[11])."</td>";
				$total_fees_place[11] += $price[11];
				if (11 != $key0)	$rowtotal += $price[11];
			}
			echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($price[$key1])."</td>";
			$total_fees_place[$key1] += $price[$key1];
			if ($key1 != $key0)	$rowtotal += $price[$key1];
		}
		echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($rowtotal)."</td>";
?>
	</tr>
<?php } ?>
	<tr>
		<td width="100" align="right" class="meisai" colspan="2"><b><font color="red">合計</font></b></td>
<?php
		$rowtotal = 0;
		foreach ($place_list as $key1=>$item1) {
			if ($key1==11)	continue;
			if ($key1==3)	{
				echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($total_fees_place[11])."</td>";
				if (11 != $key0)	$rowtotal += $total_fees_place[11];
			}
			echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($total_fees_place[$key1])."</td>";
			if ($key1 != $key0)	$rowtotal += $total_fees_place[$key1];
		}
		echo "<td width=\"100\" align=\"right\" class=\"meisai\">".number_format($rowtotal)."</td>";
?>
		</tr>
</table>

<p>
<!--
注意事項<br>
※ 分割払いの場合、対象月にお支払いいただいた金額のみ含めています。<br>
※ 2015年7月と2015年8月の模擬試験データは、その他項目テーブルに入っています。<br>
-->
</p>

</center>
</div>

</body></html>

