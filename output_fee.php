<?php
ini_set( 'display_errors', 0 );
//ini_set('display_errors', 'on');
//error_reporting(E_ALL);

require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");

//ini_set('include_path', CLIENT_LIBRALY_PATH);
//require_once "Google/autoload.php";
//require_once('../vendor/autoload.php');
//use Google\Spreadsheet\DefaultServiceRequest;
//use Google\Spreadsheet\ServiceRequestFactory;
//set_time_limit(300);
require_once("./google_drive_util.php");
//require_once("./calculate_fees.php");

$result = check_user($db, "1");

$year = $_GET["y"];
$month = $_GET["m"];
//$p = $_GET["p"];

// 20150807追加
$err_flag = false;
if (isset($year) === false || empty($year) === true) {
	$err_flag = true;
} else {
	if (preg_match("/^[0-9]+$/", $year) !== 1 || $year < 2015) {
		$err_flag = true;
	}
}
if (isset($month) === false || empty($month) === true) {
	$err_flag = true;
} else {
	if (preg_match("/^[0-9]+$/", $month) !== 1 || $month < 1 || $month > 12) {
		$err_flag = true;
	}
}
if ($err_flag == true) {
	header('location: menu.php');
	exit();
}

$errArray = array();
$calculator = null;
$student_list = array();

try{

//	$stmt = $db->query("SELECT fixed FROM tbl_fixed WHERE year=\"$year\" AND month=\"$month\"");
//	$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
//	if (!$rslt['fixed']) throw new Exception("{$year}年{$month}月分の出席簿登録が確定されていないため、スプレッドシート出力できません。（PDF出力のみ実行しました。）");

	// Google Driveを使うための準備（認証など）
	$drive = new google_drive_util();

	// スプレッドシートの取得
	//$id = "10LMdEPcgeeZ-6y3RCRhukkKZU-XaxpUtqjOk8Bwb7IE";
	$spreadsheet = $drive->spreadsheetService->getSpreadsheetById(SHEETID);
	if (is_null($spreadsheet)) {
		throw new Exception('シートの取得に失敗しました。');
	}

	// シートの取得
	$worksheet = null;
	$old_worksheet = null;
	$worksheetFeed = $spreadsheet->getWorksheets();	// $worksheetFeedは複数ある
	//$worksheetName = "月謝".$year."年".$month."月";
	$worksheetName = "月謝";
	$old_worksheet = $worksheetFeed->getByTitle($worksheetName);
	if ($old_worksheet != null) {
	  $old_worksheet->delete();
	}
	// 対象年月のワークシートを追加する
	$worksheet = $spreadsheet->addWorksheet($worksheetName, 150, 7); //デフォルト：$rowCount=100、$colCount=10

	$student_list = array();
	//$num_per_page = 60;
	//$member_list = get_member_list($db, array("kind = ?"), array("3"));
/*
	$member_cnt = get_member_cnt($db, array("kind = ?"), array("3"));
	if (!$p) { $p = 0; }
	if ($p > 0) {
	    $start_no = $p * $num_per_page;
	} else {
			$start_no = 0;
	}
*/
/*
	// no, name, sheet_id, cid 
	$cmd = "SELECT no, name, sheet_id, cid FROM tbl_member";
  $cmd .= " where kind = '3'";
	$cmd .= "	order by tbl_member.furigana";
	//$cmd .= "	limit ".$start_no.", ".$num_per_page;
//echo $cmd;
	$stmt = $db->prepare($cmd);
	$stmt->execute();
	$member_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$member_list = array();
	foreach ( $member_array as $row ) {
		//$param_array = array("tbl_fee.member_no = ?");
		//$value_array = array($row["no"]);
		//$row["fee_list"] = get_fee_list($db, $param_array, $value_array);
		$member_list[$row["no"]] = $row;
	}
*/
//	$member_list = get_simple_member_list($db, array("kind = ?","name <> ?","no='001239'"), array("3","体験生徒"));
	$member_list = get_simple_member_list($db, array("kind = ?","name <> ?"), array("3","体験生徒"));

//var_dump($member_list);


$student_list = array();
foreach ($member_list as $member_no => $member) {
	$param_array = array();
	$value_array = array();
	array_push($param_array, "tbl_statement.member_no = ?");
	array_push($param_array, "tbl_statement.seikyu_year = ?");
	array_push($param_array, "tbl_statement.seikyu_month = ?");
	array_push($value_array, $member_no);
	array_push($value_array, $year);
	array_push($value_array, $month);
	$statement_list = get_statement_list($db, $param_array, $value_array);
	$statement_array = array();
	if (count($statement_list) == 1) {
		$statement_array = $statement_list[0];
	}

	$param_array = array();
	$value_array = array();
	array_push($param_array, "tbl_statement_detail.statement_no = ?");
	array_push($value_array, $statement_array["statement_no"]);
	$detail_list = get_statement_detail_list($db, $param_array, $value_array);

		// 分割支払い
		$param_array = array("tbl_divided_payment.member_no=?", "tbl_divided_payment_detail.payment_year=?", "tbl_divided_payment_detail.payment_month=?");
		$value_array = array($member["no"], $year, $month);
		$order_array = array("tbl_divided_payment.payment_no","tbl_divided_payment_detail.time_no");
		$divided_payment_list = get_both_divided_payment_list($db, $param_array, $value_array, $order_array);

//var_dump($divided_payment_list);

		foreach ($divided_payment_list as $key => $item) {
/*
			if ($item["year"] == $year && $item["month"] == $month) {
				foreach ($detail_list as $event) {
//				foreach ($this->lesson_detail_list as $lesson_detail) {
//        	foreach ($lesson_detail["event_list"] as $event) {
          	//if ($event["lesson_id"] == $item["lesson_id"] && $event["type_id"] == $item["type_id"]) {
          	if ($event["lesson_id"] == $item["lesson_id"] && $event["course_id"] == $item["type_id"]) {
							$tmp_fees = str_replace(",", "", $event["fees"]);
							$tmp_total_fees = $tmp_total_fees - $tmp_fees;
						}
//					}
//				}
				}
			}
*/
			//$tmp_divided_price = $tmp_divided_price + $item["price"];
			$item["price"] = number_format($item["price"]);
			$divided_payment_list[$key] = $item;
		}

		// テキスト代
		$param_array = array("tbl_buying_textbook.member_no=?", "tbl_buying_textbook.year=?", "tbl_buying_textbook.month=?");
		$value_array = array($member["no"], $year, $month);
		$order_array = array("tbl_buying_textbook.input_year", "tbl_buying_textbook.input_month", "tbl_buying_textbook.input_day", "tbl_buying_textbook.buying_no");
		$textbook_list = get_buying_textbook_list($db, $param_array, $value_array, $order_array);
		//foreach ($textbook_list as $item) {
		//	$tmp_textbook_price = $tmp_textbook_price + $item["price"];
		//}

		// その他追加項目
		$param_array = array("tbl_others.member_no=?", "tbl_others.year=?", "tbl_others.month=?", "tbl_others.charge=?");
		$value_array = array($member["no"], $year, $month, 1);
		$order_array = array("tbl_others.year, tbl_others.month, tbl_others.others_no");
		$others_list = get_others_list($db, $param_array, $value_array, $order_array);
		foreach ($others_list as $key => $item) {
		//	$this->others_price = $this->others_price + $item["price"];
			$item["price"] = number_format($item["price"]);
			$others_list[$key] = $item;
		}

	$tmp_student["no"] = $member['no'];
	$tmp_student['name'] = $member['name'];
	$tmp_student['cid'] = $member['cid'];
	if (!$tmp_student['cid'])	$tmp_student['cid'] = $member['mail_address'];
	$tmp_student['furigana'] = $member['furigana'];
	$tmp_student["furikomisha_name"] = $member["furikomisha_name"];
	$tmp_student["tax_flag"] = $member["tax_flag"];
	$tmp_student["statement"] = $statement_array;
	//$tmp_student["total_hours"] = $total_hours;
	//$tmp_student["total_fees"] = $total_fees;
	//$tmp_student["divided_price"] = $divided_payment_total_price;
	//$tmp_student["entrance_fee"] = $entrance_fee_total_price;
 	//$tmp_student["membership_fee"] = $membership_fee;
	//$tmp_student["textbook_price"] = $textbook_total_price;
	//$tmp_student["others_price"] = $others_total_price;
	//$tmp_student["simple_total_price"] = $total_price;
	//$tmp_student["consumption_tax_price"] = $consumption_tax_price;
	//$tmp_student["last_total_price"] = $total_price + $consumption_tax_price;
	$tmp_student["statement"]["detail_list"] = $detail_list;
	$tmp_student["divided_payment_list"] = $divided_payment_list;
	$tmp_student["textbook_list"] = $textbook_list;
	$tmp_student["others_list"] = $others_list;

	$student_list[] = $tmp_student;
}

	// スプレッドシートに出力

	$row_num = 1;
/*
	$col_num = 1;
	$cellFeed = $worksheet->getCellFeed();
	write_in_cell($cellFeed, $row_num, $col_num, "no");
	$col_num++;
	write_in_cell($cellFeed, $row_num, $col_num, "name");
	$col_num++;
	write_in_cell($cellFeed, $row_num, $col_num, "total_price");
	$row_num++;
*/
  $cellFeed = $worksheet->getCellFeed();
  $cellFeed->editCell($row_num,1, "id");
  $cellFeed->editCell($row_num,2, "name");
  $cellFeed->editCell($row_num,3, "fees");
  //$cellFeed->editCell($row_num,4, "no");
  $cellFeed->editCell($row_num,4, "header");
  $cellFeed->editCell($row_num,5, "content");

	// 20151107 シート名を追加
  //$cellFeed->editCell($row_num,6, "sheet_name");
  $cellFeed->editCell($row_num,6, "yearmonth");
  //$cellFeed->editCell(2,6, "月謝".$year."年".$month."月");

  //$cellFeed->editCell(1,4, "Row1Col4Header");
  $cellFeed->editCell($row_num,7, "seikyuno");

  $listFeed = $worksheet->getListFeed();
	//$col_num = 1;

/*
 ["statement_no"]=>
  string(4) "1582"
  ["statement_id"]=>
  string(14) "20160605000004"
  ["statement_date"]=>
  string(10) "1465064248"
  ["member_no"]=>
  string(6) "002203"
  ["seikyu_year"]=>
  string(4) "2016"
  ["seikyu_month"]=>
  string(1) "4"
  ["lesson_hours"]=>
  string(1) "2"
  ["lesson_price"]=>
  string(4) "6000"
  ["membership_fee"]=>
  string(4) "1000"
  ["entrance_fee"]=>
  string(1) "0"
  ["textbook_price"]=>
  string(1) "0"
  ["others_price"]=>
  string(1) "0"
  ["devided_price"]=>
  string(1) "0"
  ["total_price"]=>
  string(4) "7000"
  ["consumption_tax_price"]=>
  string(3) "560"
  ["grand_total_price"]=>
  string(4) "7560"
*/
	$first_row_flag = ture;
	foreach ($student_list as $student) {


//var_dump($student["lesson_detail_list"]);


	  if ($student["statement"]["grand_total_price"] > 0) {

			// 授業明細
			$header_text = "";
			$content_text = "";
			$header_text2 = "";
			$others_text = "";
			$lesson_text = "";
			$divided_payment_text = "";
			$absent_flag1 = false;
			$absent_flag2 = false;
			$event_list = array();

			// 税抜きの生徒さんの場合
/*
			//if ($student["tax_flag"] == "1") {
			//	$header_text2 .= "合計金額 ".number_format($student["statement"]["total_price"])." 円\n";
				//$header_text2 .= "消費税 ".number_format($student["statement"]["consumption_tax_price"])." 円\n";
				//if ($student["statement"]["textbook_price"] > 0) {
				// 税抜でかつ税込のテキスト代がある場合
				//	$header_text2 .= "テキスト代 ".number_format($student["statement"]["textbook_price"])." 円\n";
				//}
			//}
*/
			if ($student["tax_flag"] == "1") {
				$header_text2 .= "授業金額 ".number_format(floor($student["statement"]["total_price"]))." 円\n";
				$header_text2 .= "消費税 ".number_format($student["statement"]["consumption_tax_price"])." 円\n";
				if ($student["statement"]["textbook_price"] > 0) {
				// 税抜でかつ税込のテキスト代がある場合
					$header_text2 .= "テキスト代 （税込）".number_format($student["statement"]["textbook_price"])." 円\n";
				}
				
				// 20170830 2017夏期講習の特別対応
				if ($year==2017 && $month==8) {
					foreach($student["others_list"] as $key=>$item) {
						if ($item["year"] == "2017" && $item["month"] == "8" && 
								$item["kind"] == 7 && $item["charge"] == 1 && 
								array_search($item["member_no"], $except20170830) === false) {
							$header_text2 .= "夏期講習 （税込）".$item["price"]." 円\n";
						}
					}
				}
				// 20180208 2018冬期講習の特別対応
				if ($year==2018 && $month==1) {
					foreach($student["others_list"] as $key=>$item) {
						if ($item["name"] == "冬期講習" && $item["charge"] == 1) {
							$header_text2 .= "冬期講習 （税込）".$item["price"]." 円\n";
						}
					}
				}
				// 20180509 2018春期講習の特別対応
				if ($year==2018 && $month==4) {
					foreach($student["others_list"] as $key=>$item) {
						if ($item["name"] == "春期講習" && $item["charge"] == 1) {
							$header_text2 .= "春期講習 （税込）".$item["price"]." 円\n";
						}
					}
				}
				
			}
			$header_text2 .= "合計金額 ".number_format(floor($student["statement"]["grand_total_price"]))." 円\n";

/*
			// 20151103
			foreach ($student["lesson_detail_list"] as $lesson) {
				$event_list = array_merge($event_list, $lesson["event_list"]);
			}
			ksort($event_list);
*/

			// 入会金
			if ($student["statement"]["entrance_fee"] > 0) {
				//if ($header_text != "") { $ohters_text .= "\n"; }
      	$others_text .= "\n";
			  $others_text .= "◆ 入会金 ".number_format($student["statement"]["entrance_fee"])." 円 ";
				// 20160706
				//if ($student["tax_flag"] == "1") {
				//	$others_text .= " （消費税 ".number_format($student["statement"]["entrance_fee"]-$student["statement"]["entrance_fee"]/1.08)." 円）";
				//}
				$others_text .= "\n";
			}

			// 20151204 月会費が見やすいように上に移動しました
			// 20151204 授業がある（授業料が発生する）生徒さんだけ月会費が発生することを確認しました
			//if ($student["membership_fee"] > 0 && $student["total_fees"] > 0) {
				
			// 20170830 2017夏期講習の特別対応
			$ret = false;
			if ($year==2017 && $month==8) {
				$sql = "SELECT * FROM tbl_others WHERE member_no=? AND year=2017 AND month=8 AND kind=7";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($student["no"]));
				$ret = $stmt->fetch(PDO::FETCH_BOTH);
			}
			// 20180208 2018冬期講習の特別対応
			if ($year==2018 && $month==1) {
				foreach($student["others_list"] as $key=>$item) {
					if ($item["name"] == "冬期講習" && $item["charge"] == 1) {
						$ret = true;
					}
				}
			}
			// 20180509 2018冬期講習の特別対応
			if ($year==2018 && $month==4) {
				foreach($student["others_list"] as $key=>$item) {
					if ($item["name"] == "春期講習" && $item["charge"] == 1) {
						$ret = true;
					}
				}
			}

			if ($student["statement"]["membership_fee"] > 0 && 
					($student["statement"]["lesson_price"] > 0 || $ret!==false)) {
				//if ($ohters_text != "") { $ohters_text .= "\n"; }
				$others_text .= "\n";
			  $others_text .= "◆ 月会費 ".number_format($student["statement"]["membership_fee"])." 円 ";
				// 20160706
				//if ($student["tax_flag"] == "1") {
				//	$others_text .= " （消費税 ".number_format($student["statement"]["membership_fee"]-$student["statement"]["membership_fee"]/1.08)." 円）";
				//}
				$others_text .= "\n";
			}

			// 20160103 入会金が見やすいように上に移動しました。
			// 20151114 調整
			//if (count($student["others_list"]) > 0) {
				foreach ($student["others_list"] as $key => $others_array) {

					// 20170830 2017夏期講習の特別対応
					if ($others_array["year"] == "2017" && $others_array["month"] == "8" && 
							$others_array["kind"] == 7 && $others_array["charge"] == 1) {
						continue;
					}
				
				//if ($ohters_text != "") { $ohters_text .= "\n"; }
					$others_text .= "\n";
					if (empty($others_array["memo"]) == true) {
				  	$others_text .= "◆ ".$others_array["name"]." ".$others_array["price"]." 円　（税込）\n";
					} else {
				  	$others_text .= "◆ ".$others_array["name"]." ".$others_array["price"]." 円 （税込）（".$others_array["memo"]."）\n";
					}
				}
				//if (count($student["others_list"]) > 0) {
				//	$content_text .= "\n";
				//}
			//}

			// 20160706
			// テキスト代
			//if ($student["statement"]["textbook_price"] > 0) {
			//	if ($others_text != "") { $others_text .= "\n"; }
			//	//$content_text .= "\n";
			//	$others_text .= "◆ テキスト代 ".number_format($student["statement"]["textbook_price"])."円\n";
			//	foreach ($student["textbook_list"] as $textbook) {
			//		// 20151106 調整
			//		//if ($i > 0) { $content_text .= "\n"; }
			//		$others_text .= "\n";
			//		$others_text .= "◇ ".$textbook["name"]." ".number_format($textbook["price"])." 円\n";
			//	}
			//}
			// テキスト代
			//if ($student["statement"]["textbook_price"] > 0) {
				//if ($header_text != "") { $ohters_text .= "\n"; }
      //	$others_text .= "\n";
			//  $others_text .= "◆ テキスト代 ".number_format($student["statement"]["textbook_price"])." 円\n";
			//}

			// 20160706
			//if ($student["tax_flag"] == "1") {
			//	if ($student["statement"]["total_price"] > 0) {
			//			//if ($header_text != "") { $ohters_text .= "\n"; }
		  //    	$lesson_text .= "\n";
			//		  $lesson_text .= "◆ 授業料金合計 ".number_format(($student["statement"]["total_price"]+$student["statement"]["consumption_tax_price"]))." 円 ";
			//			$lesson_text .= "(消費税 ".number_format($student["statement"]["consumption_tax_price"])."円)\n";
			//		  //$lesson_text .= "◆ 授業料金の消費税 ".number_format($student["statement"]["consumption_tax_price"])." 円\n";
			//	}
			//}

			// 20151103
			$lesson_array = array();
			$divided_payment_array = array();
			$before_lesson_id = "";
			$subtotal_fees = 0;
			$subtotal_hours = 0;
			$divided_payment_subtotal_fees = 0;
			$divided_payment_subtotal_hours = 0;
			$i = 0;
      foreach ($student["statement"]["detail_list"] as $event) {

				if ($before_lesson_id != $event["lesson_id"]) {
					$lesson_array[$event["lesson_id"]]["list_text"] = "";
					$lesson_array[$event["lesson_id"]]["subtotal_fees"] = 0;
					$lesson_array[$event["lesson_id"]]["subtotal_hours"] = 0;
					$subtotal_fees = 0;
					$subtotal_hours = 0;
					$divided_payment_subtotal_fees = 0;
					$divided_payment_subtotal_hours = 0;
				}

					$event_text = "";
					// 20151230 面談の表示変更
					if (mb_strpos($event["comment"],"面談") !== FALSE) {
						$event_text .= "\n";
						$event_text .= "◇ ".$event["date"]."（".$event["weekday"]."）\n";
						$event_text .= $event["time"]."\n";
						$event_text .= "面談\n";
            $event_text .= "（― 円／時間）\n";
						$event_text .= "― 円";
						$event_text .= "\n";
					} else {
						$event_text .= "\n";
						$event_text .= "◇ ".$event["date"]."（".$event["weekday"]."）\n";
						$event_text .= $event["time"]."\n";
						if ($event["subject_name"] != "　" || empty($event["subject_name"])==true) {
							$event_text .= $event["subject_name"]." ";
						} else {
							$event_text .= $lesson_list[$event["lesson_id"]]." ";
						}
						$event_text .= $event["course_name"]."\n";
						if ($event["monthly_fee_flag"]!=1) {
							if ($event["additional_fee"] === "0" && $event["subject_name"] != '演習') {
							// 1時間未満割増料金の場合は単価を非表示
							//	$event_text .= "（".number_format($event["fee_for_an_hour"])." 円（".$tax_kind."）／時間）\n";
								$event_text .= "（".str_replace('.00','',$event["fee_for_an_hour"])." 円／時間）\n";
							}
							//$event_text .= "（".number_format($event["fee_for_an_hour"])." 円／時間）\n";
							$event_text .= str_replace('.00','',$event["fees"])." 円";
						}
            if ($event["additional_fee"] === "0") {
							// 1時間未満割増料金の場合はコメントを非表示
							if ($event["comment"]) {
								$event_text .= " 【 ".$event["comment"]." 】";
							}
						}
						$event_text .= "\n";
					}

					if ($event["absent_flag"] == "1") {
						$absent_flag1 = true;
					}
					if ($event["absent_flag"] == "2") {
						$absent_flag2 = true;
					}


					$divided_payment_flag = false;
					foreach ($student["divided_payment_list"] as $divided_payment) {
						// 請求年月が指定年月で明細に授業内容が表示される場合
						// $divided_payment["year"]と$divided_payment["month"]は請求年月なので注意
						if ($divided_payment["year"] == $year && $divided_payment["month"] == $month) {
							if (($divided_payment["lesson_id"] == '' || $divided_payment["lesson_id"] == $event["lesson_id"]) && 
									($divided_payment["type_id"] == $event["course_id"] || ($divided_payment["type_id"]==9 && $event["course_id"]===null))) {
								$divided_payment_array[$divided_payment["payment_no"]]["list_text"] .= $event_text;
								$divided_payment_array[$divided_payment["payment_no"]]["subtotal_hours"] += $event["diff_hours"];
								$divided_payment_array[$divided_payment["payment_no"]]["subtotal_fees"] += $event["fees"];
								$lesson_array[$event["lesson_id"]]["subtotal_hours"] -= $event["diff_hours"];
								$lesson_array[$event["lesson_id"]]["subtotal_fees"] -= $event["fees"];
								$divided_payment_flag = true;
								break;
							}
						}
					}

					if ($divided_payment_flag == false) {
							$lesson_array[$event["lesson_id"]]["list_text"] .= $event_text;
					}

					$lesson_array[$event["lesson_id"]]["subtotal_fees"] += $event["fees"];
					$lesson_array[$event["lesson_id"]]["subtotal_hours"] += $event["diff_hours"];

					$before_lesson_id = $event["lesson_id"];
			}
			
			$sql = "SELECT * FROM tbl_monthly_fee WHERE member_no=? AND lesson_id=? AND subject_id=? AND course_id=?";
			$stmt = $db->prepare($sql);
			$checkflag = array();
			foreach ($student["statement"]["detail_list"] as $event) {
				if ($event["monthly_fee_flag"] && $event["absent_flag"]==0) {
					if ($checkflag[$event["lesson_id"]][$event["subject_id"]][$event["course_id"]]) { continue; }
					$checkflag[$event["lesson_id"]][$event["subject_id"]][$event["course_id"]] = 1;
					$stmt->execute(array($student["no"],$event["lesson_id"],$event["subject_id"],$event["course_id"]));
					$m_fee = $stmt->fetch(PDO::FETCH_ASSOC);
					$stmt = $db->query("SELECT fee_free FROM tbl_member WHERE no={$student["no"]}");
					$fee_free = ($stmt->fetch(PDO::FETCH_NUM))[0];
					if (!$m_fee['fee'] && !$fee_free) { 	array_push($errArray, $student["name"]."：月謝未登録エラー"); }
					$lesson_array[$event["lesson_id"]]["subtotal_fees"] += $m_fee['fee'];
				}
			}

			// 20160103 見やすいように移動しました
			// 20151114 調整
			foreach ($student["divided_payment_list"] as $divided_payment) {
				$divided_payment_text .= "\n";
				if (empty($divided_payment["memo"]) == true) {
					$divided_payment_text .= "◆ 授業料金分割払い\n".
							"{$divided_payment['year']}年{$divided_payment['month']}月{$type_list[$divided_payment['type_id']]}分\n".
							$divided_payment["price"]." 円\n";
				} else {
					$divided_payment_text .= "◆ 授業料金分割払い\n".
							"{$divided_payment['year']}年{$divided_payment['month']}月{$type_list[$divided_payment['type_id']]}分\n".
							$divided_payment["price"]." 円\n".
							$divided_payment["memo"]."\n";
				}

				// 分割払いの分を表示する
				if (isset($divided_payment_array[$divided_payment["payment_no"]]) !== false) {
					$divided_payment_text .=
						"\n{$divided_payment['year']}年{$divided_payment['month']}月{$type_list[$divided_payment['type_id']]}\n".
						"(合計：{$divided_payment_array[$divided_payment['payment_no']]['subtotal_hours']}時間　{$divided_payment_array[$divided_payment['payment_no']]['subtotal_fees']}円)\n".
						$divided_payment_array[$divided_payment["payment_no"]]["list_text"];
				}
			}

			$content_text = $header_text2.$others_text.$divided_payment_text.$lesson_text;

			// 20151103
			//$i = 0;
 			//foreach ($student["lesson_detail_list"] as $lesson) {
			foreach ($lesson_array as $lesson_id => $tmp_lesson) {
				//$tmp_subtotal_hours = $lesson["subtotal_hours"] - $tmp_lesson["minus_subtotal_hours"];
				//$tmp_subtotal_fees = $lesson["subtotal_fees"] - $tmp_lesson["minus_subtotal_fees"];
				// 分割払いがあるときは、教室ごとの小計を表示しない
				//if (count($student["divided_payment_list"]) > 0) {
				//	$content_text = "\n◆ ".$lesson["lesson_name"]." ".$lesson["subtotal_hours"]." 時間\n".$divided_payment_text;
				//} else {
					// 20160706
					//if ($student["tax_flag"] == "1") {
					//	$content_text .= "\n◇ ";
					//	$content_text .= $lesson_list[$lesson_id]." ".$tmp_lesson["subtotal_hours"]." 時間 ".number_format($tmp_lesson["subtotal_fees"])." 円（税抜）\n".$tmp_lesson["list_text"];
          //} else {
						$content_text .= "\n◆ ";
						$content_text .= $lesson_list[$lesson_id]." ".$tmp_lesson["subtotal_hours"]." 時間 ".number_format(floor($tmp_lesson["subtotal_fees"]))." 円\n".$tmp_lesson["list_text"];
					//}
				//}
			}

			//if ($absent_flag1 === true || $absent_flag2 === true) {
			//	$content_text .= "\n";
			//}
			//if ($absent_flag1 === true) {
			//	$content_text .= "※お休み１= 授業料が発生しないお休み\n";
			//}
			//if ($absent_flag2 === true) {
			//	$content_text .= "※お休み２= 授業料が発生するお休み\n";
			//}
/*
			// 20151205 （無料体験授業を含む）授業を受けてなければ、下のメッセージを表示しない
			if ($student["statement"]["lesson_hours"] > 0) {
				$content_text .= "\n24時間前までにご連絡をいただきませんでしたお休みは、\n";
				$content_text .= "お休み２となり、授業料をいただきます。\n";
				$content_text .= "\nお休みや振替をされました場合は、\n";
				$content_text .= "お休みや振替の日時が正しいかご確認をお願いいたします。\n\n";
				$content_text .= "月謝明細書には、万全を期しておりますが、\n";
				$content_text .= "万が一、誤りがございましたら、\n";
				$content_text .= "訂正いたしますので、\n";
				$content_text .= "ご連絡いただきますようお願い致します。\n";
			}
*/

			// 20160706
			// テキスト代
			if ($student["statement"]["textbook_price"] > 0) {
				if ($content_text != "") { $content_text .= "\n"; }
				//$content_text .= "\n";
				$content_text .= "◆ テキスト代 ".number_format($student["statement"]["textbook_price"])."円　（税込）\n";
				foreach ($student["textbook_list"] as $textbook) {
					// 20151106 調整
					//if ($i > 0) { $content_text .= "\n"; }
					$content_text .= "\n";
					$content_text .= "◇ ".$textbook["name"]." ".number_format($textbook["price"])." 円　（税込）\n";
				}
			}

			// 20170830 2017夏期講習の特別対応
			foreach($student["others_list"] as $key=>$item) {
				if ($item["year"] == "2017" && $item["month"] == "8" && 
						$item["kind"] == 7 && $item["charge"] == 1 && 
						array_search($item["member_no"], $except20170830) === false) {
					$content_text .= "\n";
					$content_text .= "◆ 夏期講習 ".$item["price"]." 円\n";
				}
			}
			
			// 20151205 本文とフッター（署名）の間に、改行を1つ追加
			$content_text .= "\n";


//print_r($content_text);

			$content_text = preg_replace('/[\x00-\x09\x0b-\x1f\x7f]/', '', $content_text); 
			if ($first_row_flag == true) {

//var_dump($student);
			//$row = array('id'=>$student["cid"], 'name'=>$student["name"], 'fees'=>$student["grand_total_price"]."円", 'header'=>$header_text, 'content'=>$content_text);
				$row = array('id'=>$student["cid"], 'name'=>$student["name"], 'fees'=>number_format($student["statement"]["grand_total_price"])." 円", 'header'=>$header_text, 'content'=>$content_text, 'yearmonth'=>$year."年".$month."月", 'seikyuno'=>$student["statement"]["statement_id"]);
			//$row = array('no'=>$student["no"], 'name'=>$student["name"], 'fees'=>$student["grand_total_price"]."円", 'sno'=>$student["sno"]);
			} else {
				$row = array('id'=>$student["cid"], 'name'=>$student["name"], 'fees'=>number_format($student["statement"]["grand_total_price"])." 円", 'header'=>$header_text, 'content'=>$content_text, 'yearmonth'=>"", 'seikyuno'=>$student["statement"]["statement_id"]);
			}
			$listFeed->insert($row);
			$first_row_flag = false;
		}
	}



/*
// 別ファイルにするしかない

	$worksheetName = "月謝";
	$old_worksheet = $worksheetFeed->getByTitle($worksheetName);
	if ($old_worksheet != null) {
	  $old_worksheet->delete();
	}
	// 対象年月のワークシートを追加する
	$worksheet = $spreadsheet->addWorksheet($worksheetName, 150, 6); //デフォルト：$rowCount=100、$colCount=10

  $cellFeed = $worksheet->getCellFeed();
  $cellFeed->editCell($row_num,1, "id");
  $cellFeed->editCell($row_num,2, "name");
  $cellFeed->editCell($row_num,3, "fees");
  //$cellFeed->editCell($row_num,4, "no");
  $cellFeed->editCell($row_num,4, "header");
  $cellFeed->editCell($row_num,5, "content");

  //$cellFeed->editCell(1,4, "Row1Col4Header");

// 前のワークシートから行ごとのデータを取得する

$max_row = ?;
for ($row=1; $<$max_row; $row++) {
  $listFeed = $worksheet->getListFeed();

	//$listFeedUrl = $worksheet.getListFeedUrl();
	$listFeed = $worksheet.getListFeed();
	//$entries = $listFeed.getEntries();
	
var_dump($entries);
	foreach ($entries as $listEntry) {
  	 echo $listEntry.getValues();
	}
	// 新しいワークシートに入れる
	$listFeed->insert($row);
} 
*/

/*
javaの参考
 URL listFeedUrl = worksheetEntry.getListFeedUrl();
        ListFeed listFeed = service.getFeed(listFeedUrl, ListFeed.class);
        List<ListEntry> entries = listFeed.getEntries();
*/

} catch (Exception $e) {
	// 処理を中断するほどの致命的なエラー
	array_push($errArray, $e->getMessage());
}


/*

function write_in_cell(&$cellFeed, $row_num, $col_num, $value) {
	$cellEntry = $cellFeed->createInsertionCell($row_num, $col_num, $value); // $row, $col, $content。重要！！セルを追加してcellEntryを追加してからでないと
	$cellEntry->update($value);	// 値を変える。updateメソッドが推奨と書いてあるサイトがあった
}

function update_sheet_id(&$db, $student_no, $id) {
	$errFlag = 0;
	$db->beginTransaction();
	try {
			$sql = "UPDATE tbl_member SET sheet_id=?, update_timestamp=now() WHERE no=?";
			$stmt = $db->prepare($sql);
			$stmt->bindParam(1, $sheet_id);
			$stmt->bindParam(2, $member_no);
			$sheet_id = $id;
			$member_no = $student_no;
			$stmt->execute();
	}catch (PDOException $e){
		$errFlag = 1;
	  print('Error:'.$e->getMessage());
	}
	if ($errFlag == 0) {
		$db->commit();
		return true;
	} else {
		$db->rollback();
		return false;
	}
}
*/

function get_statement_list(&$db, $param_array, $value_array, $order_array=array()) {
  $statement_list = array();
	$cmd = "SELECT * FROM tbl_statement ";
  if(count($param_array) > 0){
    $cmd .= " where " . join(" and ",$param_array);
  }
  if(count($order_array) > 0){
    $cmd .= "	order by " . join(" , ",$order_array);
  }
	else {
		$cmd .= "	order by tbl_statement.statement_id";
	}
	$stmt = $db->prepare($cmd);
	$stmt->execute($value_array);
	$statement_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $statement_list;
}

function get_statement_detail_list(&$db, $param_array, $value_array, $order_array=array()) {
  $statement_detail_list = array();
	$cmd = "SELECT * FROM tbl_statement_detail ";
  if(count($param_array) > 0){
    $cmd .= " where " . join(" and ",$param_array);
  }
  if(count($order_array) > 0){
    $cmd .= "	order by " . join(" , ",$order_array);
  }
	else {
		$cmd .= "	order by tbl_statement_detail.statement_no";
	}
	$stmt = $db->prepare($cmd);
	$stmt->execute($value_array);
	$statement_detail_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $statement_detail_list;
}



?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
function back() {
	document.back_form.submit();
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
<div class="title_box">明細</div>
-->
<h3>月謝金額一覧の出力</h3>


<?php
if ($_SESSION['login']['kind'] == "1") {
	//if ($_SESSION['login']['id'] != $_SESSION['member_id']) {
?> 
	<div>
<!--
		<a href="student_list.php?y=<?=$year?>&m=<?=$month?>">生徒一覧へ戻る</a>&nbsp;&nbsp;
-->
		<a href="menu.php">メニューへ戻る</a>
	</div>
<?php
	//}
}
?>
<br>
<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br>
<?php
		}
?>
	<br>
<?php
	} else {
?>
<!--
<?php
		if ($member_cnt > $start_no + $num_per_page) {
?>
月謝金額一覧出力処理に続きがあります。続きの処理を行ってください。<br>
<a href="output_fee.php?y=<?=$year?>&m=<?=$month?>&p=<?=$p+1?>">続きの処理を行う</a>
<?php
		} else {
?>
-->
月謝金額一覧をスプレッドシートに出力しました。<br>
<a href="https://docs.google.com/spreadsheets/d/<?=SHEETID?>/edit">こちらです</a>
<!--
<?php
  	}
?>
-->
<?php
  }
?>

</div>

</body></html>

