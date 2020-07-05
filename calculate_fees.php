<?php
// calculate_fees class define include file

get_season_fee_table($db);

class calculate_fees {

	var $total_hours;     // 授業時間
 	var $total_fees;      // 授業料
  var $membership_fee;  // 月会費
  var $entrance_fee;  // 入会金
	var $textbook_price;  // テキスト代金額（小計）
	var $others_price; 		// その他金額（小計）
	var $others_price_no_charge; 		// その他金額（小計）
	var $divided_price;		// 分割支払金額（小計）
  var $simple_total_price; // 単純合計金額（消費税金額を除く）
	var $consumption_tax_price; //消費税金額
  var $last_total_price; // 総合計金額（消費税金額を含む）

	var $member_array;				 // 生徒情報の配列
	var $lesson_detail_list;	 // 教室ごとの授業明細の配列
	var $entrance_fee_list; 	 // 入会金の配列
	var $buying_textbook_list; // テキスト購入明細の配列
	var $others_list; 				 // その他金額の配列
	var $divided_payment_list; // 分割払い明細の配列

	var $season_class_list;     // 2017 期間講習用
	var $season_class_list2;		// 2018/3以降　期間講習用

	var $db;
	
	var $event_list;

	public function __construct() {
		global $db;
		$this->total_hours = 0;
		$this->total_fees = 0;
		$this->membership_fee = 0;
		$this->entrance_fee = 0;
		$this->textbook_price = 0;
		$this->others_price = 0;
		$this->others_price_no_charge = 0;
		$this->divided_price = 0;
		$this->simple_total_price = 0;
		$this->consumption_tax_price = 0;
		$this->last_total_pirce = 0;
		$this->member_array = array();
		$this->lesson_detail_list = array();
		$this->entrance_fee_list = array();
		$this->buying_textbook_list = array();
		$this->others_list = array();
		$this->divided_payment_list = array();
		$this->db = $db;
//		connect_DB($this->db);	// func.inc
	}

	// 月謝計算のメイン
	public function calculate($member_no, $year, $month) {
	global $date_list_array,$sat_sun_class_date_list,$global_event_list;

	$year1 = $year; $month1 = $month-1; if ($month1<1) { $year1--; $month1=12; }
	
	if ($year>2020 || ($year==2020 && $month>2)) {
		$date_list = array();
		foreach ($date_list_array as $dlist)	$date_list = array_merge($date_list, $dlist);
		$date_list = array_unique(array_merge($date_list,$sat_sun_class_date_list));
		$date_list_string = "("; $flag=0;
		foreach ($date_list as $item) {
			if (str_replace('/0','/',substr($item,0,7)) != "$year/$month") { continue; }
			if ($flag==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; }
			$flag = 1;
		}
		$date_list_string = $date_list_string.")";
	} else {
		if ($month!=1 && $month!=4 && $month!=8) {
			$date_list = $sat_sun_class_date_list;
			$date_list_string = "("; $flag=0;
			foreach ($date_list as $item) {
				if (str_replace('/0','/',substr($item,0,7)) != "$year/$month") { continue; }
				if ($flag==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; }
				$flag = 1;
			}
			$date_list_string = $date_list_string.")";
		} else {
			$date_list = $date_list_array["$year1/$month1"];
			if (!$date_list)	$date_list = array();
			$date_list = array_unique(array_merge($date_list,$sat_sun_class_date_list));
			$date_list_string = "("; $flag=0;
			foreach ($date_list as $item) {
				if (in_array($item, $sat_sun_class_date_list) && (str_replace('/0','/',substr($item,0,7)) != "$year/$month")) { continue; }
				if ($flag==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; }
				$flag = 1;
			}
			$date_list_string = $date_list_string.")";
		}
	}

try{
	
		if ($global_event_list !== 'empty') {
			$this->event_list = $global_event_list;
		} else {
			$param_array = array();
			$value_array = array();
			$param_text = "(tbl_event.seikyu_year = ? and tbl_event.seikyu_month = ?)";
			array_push($value_array, $year);
			array_push($value_array, $month);
			array_push($param_array, $param_text);
			$order_array = array("tbl_event.lesson_id", "tbl_event.event_start_timestamp");
			$global_event_list = get_event_list($this->db, $param_array, $value_array, $order_array);
			
			// 一人グループチェック
			$tmp_event1 = array(); $tmp_event2 = array();
			foreach ($global_event_list as $key=>$tmp_event) {
				$key1 = $key2; $tmp_event1 = $tmp_event2;
				$key2 = $key3; $tmp_event2 = $tmp_event3;
				$key3 = $key;  $tmp_event3 = $tmp_event;			
				if ($tmp_event2['course_id'] == 2 && 
						($tmp_event2['start_timestamp'] != $tmp_event1['start_timestamp'] || $tmp_event2['cal_evt_summary'] != $tmp_event1['cal_evt_summary']) && 
						($tmp_event2['start_timestamp'] != $tmp_event3['start_timestamp'] || $tmp_event2['cal_evt_summary'] != $tmp_event3['cal_evt_summary'])) {
					$global_event_list[$key2]['one_man_group'] = 1;
				}
			}
			if ($tmp_event3['course_id'] == 2 && 
					$tmp_event3['cal_evt_summary'] != $tmp_event2['cal_evt_summary']) {
				$global_event_list[$key3]['one_man_group'] = 1;
			}
			$this->event_list = $global_event_list;
		}

		// メンバー情報を取得
		$this->member_array = get_member($this->db, array("tbl_member.no = ?"), array($member_no));
		if (is_null($this->member_array) == true) {
//var_dump($member_no);
			return false;
		}
//var_dump($this->member_array);

		// 期間講習
		$this->season_class_list = array();
		$this->season_class_list2 = array();
//		if ($month==1 || ($year==2017 && $month==3) || $month==4 || $month==8) {
			if ($year==2017) {
			switch ($month) {
				case 3:
					$tmp = array_filter( $date_list, function($var){return(preg_match('|/03/|', $var));} );
					$date_list = array_values( $tmp );
					$date_list_string = "(";
					foreach ($date_list as $i=>$item) { if ($i==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; } }
					$date_list_string .= ")";
					break;
				case 4:
					$tmp = array_filter( $date_list, function($var){return(preg_match('|/04/|', $var));} );
					$date_list = array_values( $tmp );
					$date_list_string = "(";
					foreach ($date_list as $i=>$item) { if ($i==0) { $date_list_string .= "'$item'"; } else { $date_list_string .= ",'$item'"; } }
					$date_list_string .= ")";
					break;
				}
			}
			if ($date_list_string != '()' && 
					!($year == 2018 && $month == 4)			// 20180509 2018年4月特別対応
				) {
				// tbl_season_class 読み込み
				$sql = "SELECT * FROM tbl_season_class WHERE member_id=? AND date IN $date_list_string ORDER BY date ASC, stime ASC";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($member_no));
				$this->season_class_list = $stmt->fetchAll(PDO::FETCH_BOTH);

				// tbl_season_schedule スケジュール読み込み
				$sql = "SELECT a.date, a.stime, a.etime, a.teacher_no, a.lesson_id, a.subject_id, ".
								"b.season_course_id, b.stime as student_stime, b.etime as student_etime, b.attend_status, b.furikae_status, b.furikae_flag ".
								"FROM tbl_season_schedule a, tbl_season_class_entry_date b ".
								"WHERE a.date IN $date_list_string AND a.member_no=? AND a.member_no=b.member_id AND a.date=b.date ORDER BY a.date ASC, a.stime ASC";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($member_no));
				$this->season_class_list2 = $stmt->fetchAll(PDO::FETCH_ASSOC);		
/*
				$sql = "SELECT a.date, a.stime, a.etime, a.teacher_no, a.lesson_id, a.subject_id, ".
								"b.season_course_id, b.stime as student_stime, b.etime as student_etime, c.presence as attend_status, b.furikae_status, b.furikae_flag ".
								"FROM tbl_season_schedule a JOIN tbl_season_class_entry_date b ".
								"ON a.date IN $date_list_string AND a.member_no=? AND a.member_no=b.member_id AND a.date=b.date ".
								"LEFT JOIN tbl_teacher_presence_report c ".
								"ON c.member_no=a.member_no AND a.stime=SUBSTR(c.time,1,5) ".
								"AND a.date=CONCAT(c.year,'/',lpad(c.month,2,'0'),'/',lpad(REPLACE(SUBSTR(c.date, POSITION('月' IN c.date)+1),'日',''),2,'0')) ".
								"ORDER BY a.date ASC, a.stime ASC";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($member_no));
				$this->season_class_list2 = $stmt->fetchAll(PDO::FETCH_ASSOC);		
*/
			}
//		}

		$tmp_total_hours = 0;
		$tmp_total_fees = 0;
		$tmp_membership_fee = 0;
		$tmp_entrance_fee = 0;
		$tmp_divided_price = 0;
		$tmp_textbook_price = 0;

		// 教室ごとの授業明細を取得
		$this->lesson_detail_list = $this->calculate_lesson_detail($this->db, $this->member_array, $year, $month);
		if (is_null($this->lesson_detail_list) == true) {
//20160503
//var_dump($this->member_array);
			return false;
		}

		// 授業時間と授業料
		// 【消費税】20160701 税抜表示の生徒の場合、税抜金額が登録されているので消費税を加える
		$membership_flag = 0;
		foreach ($this->lesson_detail_list as $lesson_detail) {
			$tmp_total_hours = $tmp_total_hours + $lesson_detail["subtotal_hours"];
			$tmp_total_fees = $tmp_total_fees + $lesson_detail["subtotal_fees"];
			if ($tmp_total_hours > 0) {
				// いずれかの教室で小計が0円より大きい場合、通学したとして月会費を請求
				$membership_flag = 1;
			}
			//$this->total_hours = $this->total_hours + $lesson_detail["subtotal_hours"];
			//$this->total_fees = $this->total_fees + $lesson_detail["subtotal_fees"];
		}
		$this->total_hours = $tmp_total_hours;
		$this->total_fees = $tmp_total_fees;
		$this->simple_total_price = $this->total_fees;

		// 20170830 2017夏期講習の特別対応
		if ($year==2017 && $month==8 ) {
			$sql = "SELECT * FROM tbl_others WHERE member_no=? AND year=2017 AND month=8 AND kind=7";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($member_no));
			$ret = $stmt->fetch(PDO::FETCH_BOTH);
			if ($ret!==false) { $membership_flag = 1; }
		}
		// 20180208 2018 冬期講習の特別対応
		if ($year==2018 && $month==1 ) {
			$sql = "SELECT * FROM tbl_others WHERE member_no=? AND year=2018 AND month=1 AND name='冬期講習'";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($member_no));
			$ret = $stmt->fetch(PDO::FETCH_BOTH);
			if ($ret!==false) { $membership_flag = 1; }
		}
		// 20180509 2018 春期講習の特別対応
		if ($year==2018 && $month==4 ) {
			$sql = "SELECT * FROM tbl_others WHERE member_no=? AND year=2018 AND month=4 AND name='春期講習'";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($member_no));
			$ret = $stmt->fetch(PDO::FETCH_BOTH);
			if ($ret!==false) { $membership_flag = 1; }
		}

		// 月会費
		// 【消費税】20160701 税抜表示の生徒の場合、税抜金額が登録されているので消費税を加える
		// 20150904 授業料金が発生しない場合、月会費を表示しないように修正した。
		// 20151010 カレンダーからのデータで授業料が0円より大きい場合、通学しているものとして月会費を請求するようにした。
		// 授業料金は発生せず分割払い料金のみの場合も、月会費を表示しないようにしている（2015年9月に要確認）
		$tmp_membership_fee = $this->member_array["membership_fee"];
		//if ($tmp_membership_fee > 0 && $this->total_fees > 0) {
		if ($tmp_membership_fee > 0 && $membership_flag == 1) {
		// 20160706 税抜の場合そのまま登録されている。そのまま明細に表示する
			$this->membership_fee = $tmp_membership_fee;
			$this->simple_total_price = $this->simple_total_price + $this->membership_fee;
			//if ($this->member_array["tax_flag"] == "1") {
			// 消費税
			//	$this->membership_fee += floor($this->membership_fee * 0.08);
			//}
		}

		// 入会金
		// 【消費税】20160701 税抜表示の生徒の場合、税抜金額が登録されているので消費税を加える
		$param_array = array("tbl_entrance_fee.member_no=?", "tbl_entrance_fee.year=?", "tbl_entrance_fee.month=?");
		$value_array = array($this->member_array["no"], $year, $month);
		$order_array = array("tbl_entrance_fee.year, tbl_entrance_fee.month, tbl_entrance_fee.entrance_fee_no");
		$this->entrance_fee_list = $this->calculate_entrance_fee($this->db, $param_array, $value_array, $order_array);
		foreach ($this->entrance_fee_list as $key => $item) {
			$tmp_entrance_fee = $tmp_entrance_fee + $item["price"];
		}
		if ($tmp_entrance_fee > 0) {
			// 20160706 税抜の場合そのまま登録されている。そのまま明細に表示する
			$this->entrance_fee = $tmp_entrance_fee;
			$this->simple_total_price = $this->simple_total_price + $this->entrance_fee;
			//if ($this->member_array["tax_flag"] == "1") {
			// 消費税
				//	$this->entrance_fee += floor($this->entrance_fee * 0.08);
			//}
		}

		// 分割払い
		// 【消費税】20160701 税込金額で登録されている
		$sql = "SELECT * FROM tbl_divided_payment WHERE tbl_divided_payment.member_no=".$this->member_array["no"].
						" and tbl_divided_payment.year=".$year." and tbl_divided_payment.month=".$month;
		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		$this->divided_payment = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($this->divided_payment as $key => $item) {
			// 20170830 2017夏期講習の特別対応
			if ($item['year']==2017 && $item['month']==8 && $item['type_id']==4) { continue; }
			$this->total_fees -= $item["payment_price"];
			$this->simple_total_price -= $item["payment_price"];
		}
		$param_array = array("tbl_divided_payment.member_no=?", "tbl_divided_payment_detail.payment_year=?", "tbl_divided_payment_detail.payment_month=?");
		$value_array = array($this->member_array["no"], $year, $month);
		$order_array = array("tbl_divided_payment.payment_no","tbl_divided_payment_detail.time_no");
		$this->divided_payment = $this->calculate_divided_payment($this->db, $param_array, $value_array, $order_array);
		foreach ($this->divided_payment as $key => $item) {
			$tmp_divided_price = $tmp_divided_price + $item["price"];
			$item["price"] = number_format($item["price"]);
			$this->divided_payment_list[$key] = $item;
		}
		$this->divided_price = $tmp_divided_price;
		$this->simple_total_price = $this->simple_total_price + $tmp_divided_price;

		// テキスト代
		// 【消費税】20160701 税込金額で登録されている
		// テキスト代
		$param_array = array("tbl_buying_textbook.member_no=?", "tbl_buying_textbook.year=?", "tbl_buying_textbook.month=?");
		$value_array = array($this->member_array["no"], $year, $month);
		//$order_array = array("tbl_buying_textbook.input_day, tbl_buying_textbook.buying_no");
		$order_array = array("tbl_buying_textbook.input_year", "tbl_buying_textbook.input_month", "tbl_buying_textbook.input_day", "tbl_buying_textbook.buying_no");
		$this->buying_textbook_list = $this->calculate_buying_textbook($this->db, $param_array, $value_array, $order_array);
		foreach ($this->buying_textbook_list as $item) {
			$tmp_textbook_price = $tmp_textbook_price + $item["price"];
		}
 		// 月謝計算の対象月に授業を受けていなくても、対象月にテキスト代が入力されていれば、表示する
		//if ($this->textbook_price > 0 && $this->total_fees > 0) {
		if ($tmp_textbook_price > 0) {
			$this->textbook_price = $tmp_textbook_price;
			// 20160608 テキスト代は税込金額を入力するため、単純合計には加えない
			//$this->simple_total_price = $this->simple_total_price + $this->textbook_price;
		}

		// その他項目
		//  【消費税】20160701 税抜表示の生徒の場合、税抜金額が登録されているので消費税を加える
		// その他項目には、税抜対象の授業料と月会費のみ登録する
		$param_array = array("tbl_others.member_no=?", "tbl_others.year=?", "tbl_others.month=?", "tbl_others.charge=?");
		$value_array = array($this->member_array["no"], $year, $month, 1);
		$order_array = array("tbl_others.year, tbl_others.month, tbl_others.others_no");
		$this->others_list = $this->calculate_others($this->db, $param_array, $value_array, $order_array);
		//$tmp_duplicate_others_price = 0;
		foreach ($this->others_list as $key => $item) {
			//$this->others_price = $this->others_price + $item["price"];
			if ($item["tax_flag"] == null) {
			// 20160701 税種別の追加前 201605まで 消費税計算
				if ($this->member_array["tax_flag"] == "1") {
					$this->simple_total_price = $this->simple_total_price + $item["price"];
					//$tmp_duplicate_others_price += $item["price"];
					//$this->consumption_tax_price += floor($item["price"] * 0.08);
				}
			} else {
			// 20160701 税種別の追加後 201606以降はすべて税込
				//if ($item["tax_flag"] === "1") {
				//	$this->simple_total_price = $this->simple_total_price + $item["price"];
					//$this->consumption_tax_price += floor($item["price"] * 0.08);
				//}
			}
			$this->others_price = $this->others_price + $item["price"];

			// 20160103 入会金対応
			//if ($item["kind"] == "5") {
			//	$this->entrance_fee = $this->entrance_fee + $item["price"];
			//}
			$item["price"] = number_format($item["price"]);
			$this->others_list[$key] = $item;
			// 20150904 2015夏期講習の特別対応
			//if ($item["member_no"] == "000356" && $item["year"] == "2015" && $item["month"] == "8") {
			//	if ($item["lesson_id"] == "1" && $item["type_id"] == "4") {
			//		$this->others_price = $this->others_price - 5000;
			//	}
			//}
		// 20150915 明細書のその他項目に項目を表示するため授業料金は集計しない
		//if ($item["others_kind"] == "1") {
		// 授業代の場合
		//	if ($item["lesson_id"] == $payment_array["lesson_id"] && $item["type_id"] == $payment_array["type_id"] ) {
		//		$tmp_others_price = str_replace(",", "", $others["price"]);
		//		$payment_price = $payment_price + $tmp_others_price;
		//	}
		//}
		}
		// 月謝計算の対象月に授業を受けていなくても、対象月にその他追加項目が入力されていれば、表示する
		//if ($this->others_price > 0 && $this->total_fees > 0) {
		// 20160307 マイナス金額も入力可能にする
		//if ($this->others_price != 0) {
		//	$this->simple_total_price = $this->simple_total_price + $this->others_price;
		//}
		
		$param_array = array("tbl_others.member_no=?", "tbl_others.year=?", "tbl_others.month=?", "tbl_others.charge=?");
		$value_array = array($this->member_array["no"], $year, $month, 2);
		$order_array = array("tbl_others.year, tbl_others.month, tbl_others.others_no");
		foreach ($this->calculate_others($this->db, $param_array, $value_array, $order_array) as $key => $item) {
			$this->others_price_no_charge = $this->others_price_no_charge + $item["price"];
		}

		// 消費税金額
		// 20160701
		if ($this->member_array["tax_flag"] == "1") {
			$this->consumption_tax_price = floor($this->simple_total_price * get_cons_tax_rate($year, $month));
		}

		// 総合計金額
		// 20160608 授業料合計（税抜の場合あり）と授業料消費税とテキスト代（税込）を足す
		//$this->last_total_price = ($this->simple_total_price + $this->consumption_tax_price) + $this->textbook_price;
		$this->last_total_price = ($this->total_fees + $this->consumption_tax_price) + $this->textbook_price;
		// 20160702 授業料分割（税込）と月会費（税込）と入会金（税込）とその他（税込）を足す
		$this->last_total_price = $this->last_total_price + $this->divided_price + $this->entrance_fee + $this->membership_fee + $this->others_price;

} catch (Exception $e) {
	// 処理を中断するほどの致命的なエラー
	var_dump($e);
	return false;
}
		return true;
	}

	// 総合計金額（消費税金額を含む）
	public function get_last_total_price() {
		return number_format(floor($this->last_total_price));
	}

	// 単純合計金額（消費税金額を除く）
	public function get_simple_total_price() {
		return number_format(floor($this->simple_total_price));
	}

	// 消費税金額
	public function get_consumption_tax_price() {
		return number_format(floor($this->consumption_tax_price));
	}

	// メンバー配列
	public function get_member_array() {
		return $this->member_array;
	}

	// 月会費
	public function get_membership_fee() {
		return number_format($this->membership_fee);
	}

	// 教室ごとの授業明細
	public function get_lesson_detail_list() {
		return $this->lesson_detail_list;
	}

	// 授業時間
	public function get_total_hours() {
		return $this->total_hours;
	}

	// 授業料金
	public function get_total_fees() {
		return number_format(floor($this->total_fees));
	}

	// テキスト購入配列
	public function get_buying_textbook_list() {
		return $this->buying_textbook_list;
	}

	// テキスト代
	public function get_textbook_price() {
		return number_format($this->textbook_price);
	}

	// その他追加項目配列
	public function get_others_list() {
		return $this->others_list;
	}

	// その他追加項目の金額
	public function get_others_price() {
		return number_format($this->others_price);
	}

	public function get_others_price_no_charge() {
		return number_format($this->others_price_no_charge);
	}

	// 入会金の配列
	public function get_entrance_fee_list() {
		return $this->others_list;
	}

	// 入会金の金額
	public function get_entrance_fee() {
		return number_format($this->entrance_fee);
	}

	// 分割
	public function get_divided_payment_list() {
		return $this->divided_payment_list;
	}

	// 分割
	public function get_divided_price() {
		return number_format($this->divided_price);
	}

	private function eventCmp($a, $b) {
		if ($a["lesson_id"] == $b["lesson_id"]) {
			if ($a["start_timestamp"] == $b["start_timestamp"]) {
				if ($a["end_timestamp"] == $b["end_timestamp"]) {
					return 0;
				} else {
					return ($a["end_timestamp"] > $b["end_timestamp"]) ? -1 : 1;
				}
			} else {
				return ($a["start_timestamp"] < $b["start_timestamp"]) ? -1 : 1;
			}
		} else {
			return ($a["lesson_id"] < $b["lesson_id"]) ? -1 : 1;
		}
	}

	// 教室ごとに明細情報を取得するメソッド
	private function calculate_lesson_detail(&$db, $member, $year, $month) {
		global	$season_course_id,$season_course_list,$date_list,$sat_sun_class_date_list,$date_list_string;
		global $time_list,$default_stime,$default_etime,$lesson_fee_table,$exercise_fee_table;
		$lesson_list 	= get_lesson_list($db);
		$subject_list = get_subject_list($db);
		$course_list 	= get_course_list($db);
		$teacher_list = get_teacher_list($db);
		//$type_list 	= get_type_list($db);

		$season_fee_type = get_season_fee_type($db, $member['no']);

		$tmp_event_list = array();
		foreach ($this->event_list as $item)
			if ($item['member_no'] == $member['no']) $tmp_event_list[] = $item;
		
/*
			$param_array = array();
			$value_array = array();
			array_push($param_array, "tbl_event.member_no = ?");
			array_push($value_array, $member["no"]);
			array_push($param_array, "tbl_event.event_year = ?");
			array_push($value_array, $year);
			array_push($param_array, "tbl_event.event_month = ?");
			array_push($value_array, $month);
			//array_push($param_array, "tbl_event.lesson_id = ?");
			//array_push($value_array, $fee["lesson_id"]);
			//array_push($param_array, "tbl_event.subject_id = ?");
			//array_push($value_array, $fee["subject_id"]);
			//array_push($param_array, "tbl_event.type_id = ?");
			//array_push($value_array, $fee["type_id"]);
//var_dump(get_event_list($db, $param_array, $value_array));
		//	$tmp_event_list = array_merge($tmp_event_list, get_event_list($db, $param_array, $value_array));
		//}
		$order_array = array("tbl_event.lesson_id", "tbl_event.event_day");
		$tmp_event_list = get_event_list($db, $param_array, $value_array, $order_array);
*/
//		if ($month==1 || ($year==2017 && $month==3) || $month==4 || $month==8) {
			$event_item0 = array(
				'event_id'				=>'0',
				'member_no'				=>$member['no'],
				'year'						=>'',
				'month'						=>'',
				'day'							=>'',
				'start_timestamp'	=>'',
				'start_hour'			=>'',
				'start_minute'		=>'',
				'end_timestamp'		=>'',
				'end_hour'				=>'',
				'end_minute'			=>'',
				'diff_hours'			=>'',
				'lesson_id'				=>'',
				'subject_id'			=>'',
				'course_id'				=>'',
				'teacher_id'			=>'',
				'place_id'				=>'0',
				'alternative_flag'=>'0',
				'trial_flag'			=>'0',
				'interview_flag'	=>'0',
				'absent1_num'			=>'0',
				'absent2_num'			=>'0',
				'trial_num'				=>'0',
				'cal_evt_summary'	=>'',
				'cal_attendance_data'=>'',
				'cal_summary'			=>''
				);
				
/*
			// 2017 期間講習
			foreach ($this->season_class_list as $item) {
				$event_item['season_course_id'] = $item['season_course_id'];
				switch ($item['season_course_id']) {
				case 1:		//スタンダード
				case 2:		//スタンダードプラス
					$var_array1 = explode( '/',$item['date'] );
					$var_array2 = explode( ':',$item['stime'] );
					$var_array3 = explode( ':',$item['etime'] );
					$event_item['year']						=	$var_array1[0];
					$event_item['month']					=	$var_array1[1]+0;
					$event_item['day']						=	$var_array1[2]+0;
					$event_item['start_timestamp']=	mktime( $var_array2[0],$var_array2[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
					$event_item['start_hour']			=	$var_array2[0];
					$event_item['start_minute']		=	$var_array2[1];
					$event_item['end_timestamp']	=	mktime( $var_array3[0],$var_array3[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
					$event_item['end_hour']				=	$var_array3[0];
					$event_item['end_minute']			=	$var_array3[1];
					$event_item['diff_hours']			=	($event_item['end_timestamp']-$event_item['start_timestamp']) / (60.0*60.0);
					$event_item['lesson_id']			=	1;
					$event_item['subject_id']			=	"";
					$event_item['teacher_id']			=	"";
					if ($item['teacher1_id'] != null) $event_item['diff_hours'] = $event_item['diff_hours']-1.0;
					if ($item['teacher2_id'] != null) $event_item['diff_hours'] = $event_item['diff_hours']-1.0;
					array_push( $tmp_event_list, $event_item );
				case 3:		//マンツーマン
					$var_array1 = explode( '/',$item['date'] );
					$var_array2 = explode( ':',$item['stime1'] );
					$event_item['year']						=	$var_array1[0];
					$event_item['month']					=	$var_array1[1]+0;
					$event_item['day']						=	$var_array1[2]+0;
					$event_item['start_timestamp']=	mktime( $var_array2[0],$var_array2[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
					$event_item['start_hour']			=	$var_array2[0];
					$event_item['start_minute']		=	$var_array2[1];
					if (!$item['ltime1']) { $item['ltime1']=1.0; }
					$event_item['end_timestamp']	=	$event_item['start_timestamp'] + $item['ltime1']*3600;
					$event_item['end_hour']				=	date('H',$event_item['end_timestamp']);
					$event_item['end_minute']			=	date('i',$event_item['end_timestamp']);
					$event_item['diff_hours']			=	($event_item['end_timestamp']-$event_item['start_timestamp']) / (60.0*60.0);
					$event_item['lesson_id']			=	$item['lesson1_id'];
					$event_item['subject_id']			=	$item['subject1_id'];
					$event_item['teacher_id']			=	$item['teacher1_id'];
					array_push( $tmp_event_list, $event_item );
					if ($item['teacher2_id'] != null) {
						$var_array1 = explode( '/',$item['date'] );
						$var_array2 = explode( ':',$item['stime2'] );
						$event_item['year']						=	$var_array1[0];
						$event_item['month']					=	$var_array1[1]+0;
						$event_item['day']						=	$var_array1[2]+0;
						$event_item['start_timestamp']=	mktime( $var_array2[0],$var_array2[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
						$event_item['start_hour']			=	$var_array2[0];
						$event_item['start_minute']		=	$var_array2[1];
						if (!$item['ltime2']) { $item['ltime2']=1.0; }
						$event_item['end_timestamp']	=	$event_item['start_timestamp'] + $item['ltime2']*3600;
						$event_item['end_hour']				=	date('H',$event_item['end_timestamp']);
						$event_item['end_minute']			=	date('i',$event_item['end_timestamp']);
						$event_item['diff_hours']			=	($event_item['end_timestamp']-$event_item['start_timestamp']) / (60.0*60.0);
						$event_item['lesson_id']			=	$item['lesson2_id'];
						$event_item['subject_id']			=	$item['subject2_id'];
						$event_item['teacher_id']			=	$item['teacher2_id'];
						array_push( $tmp_event_list, $event_item );
					}
					break;
				}
			}
*/
			// 2018/3以降　期間講習
			if ($this->season_class_list2) {
				$stmt = $this->db->prepare("SELECT no, grade FROM tbl_member WHERE no=?");
				$stmt->execute(array($member['no']));
				$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
				$grade = $rslt['grade'];
				if ($season_fee_type && date('n')>=4 && $month==4) $grade--; 
				
				$stmt = $this->db->prepare("SELECT min(fee) FROM tbl_fee WHERE member_no=? AND lesson_id=1 AND course_id=1");
				$stmt->execute(array($member['no']));
				$rslt = $stmt->fetch(PDO::FETCH_NUM);
				$lesson_fee1 = $rslt[0];
			
				$date_count = count(array_unique(array_diff(array_column($this->season_class_list2, 'date'),$sat_sun_class_date_list)));
				if			($date_count >= LESSON_DATE_COUNT_2) { $date_count_index0=2; }
				else if	($date_count >= LESSON_DATE_COUNT_1) { $date_count_index0=1; }
				else { $date_count_index0=0; }

//foreach ($tmp_event_list as $temp_item) { var_dump($temp_item); echo "<BR><BR>"; }				
//foreach ($this->season_class_list2 as $temp_item) { var_dump($temp_item); echo "<BR><br>"; }				
				$last_date = '';
//				foreach ($this->season_class_list2 as $item) {
				$tmp_event_list1 = array();
				foreach ($tmp_event_list as $event_item) {
					if (strpos($event_item['cal_evt_summary'],'季節講習')===false)  {
						array_push( $tmp_event_list1, $event_item );
						continue;
					}
					if (strpos($event_item['cal_evt_summary'],'季節講習演習')!==false) continue;
					$item = null;
					foreach ($this->season_class_list2 as $item0) {
						$date0 = date('Y/m/d',$event_item['start_timestamp']);
						$time0 = date('H:i',$event_item['start_timestamp']);
						if ($item0['date'] == $date0)	{
							$item1 = $item0;
							if ($item0['stime'] == $time0) {
								$item = $item0; break;
							}
						}
					}
					if (!$item) {
						if (strpos($event_item['cal_evt_summary'],'季節講習演習')===false) {
							errMsgFileLog ("<table>".
								"<tr><td>警告：季節講習データ不一致</td></tr>".
								"<tr><td>事務DB：{$item1['date']} {$item1['stime']},{$teacher_list[$item1['teacher_no']]['name']},{$member['name']}</td></tr>".
								"<tr><td>SON-DB：$date0 $time0 {$event_item['cal_evt_summary']}</td></tr>".
								"</table>");
//							continue;
						}
						$item = $item1;
					}
					$event_item['season_course_id'] = $item['season_course_id'];
					$lesson_length = 1;
					if ($item['season_course_id']==LESSON90)  { $lesson_length = 1.5; }
					if ($item['season_course_id']==LESSON120) { $lesson_length = 2; }
					if (in_array($item['date'], $sat_sun_class_date_list)) {
						if (!($year==2020 && $month==5)) {
							$date_count_index=3;
						} else {
							$date_count = count(array_unique(array_column($this->season_class_list2, 'date')));
							if			($date_count >= 15)	{ $date_count_index=2; }
							else if	($date_count >= 10)	{ $date_count_index=1; }
							else												{ $date_count_index=0; }
						}
						$sat_sun_flag = 1;
					} else {
						$date_count_index = $date_count_index0;
						$sat_sun_flag = 0;
					}
					if ($member['jyukensei'])
						$lesson_fee0 = $lesson_fee_table[$season_fee_type][$grade][$item['season_course_id']][1];
					else
						$lesson_fee0 = $lesson_fee_table[$season_fee_type][$grade][$item['season_course_id']][0];
					$exercise_fee = $exercise_fee_table[$season_fee_type][$item['season_course_id']][$date_count_index];		
					if ($lesson_fee1 && $lesson_fee1!=0 && $lesson_fee1 < $lesson_fee0) { $lesson_fee0 = $lesson_fee1; }
					if ($member['fee_free']) { $lesson_fee0 = 0; $exercise_fee = 0; }
					
					if ($item['date'] != $last_date) {
//					if (strpos($event_item['cal_evt_summary'],'季節講習演習')!==false) {
						// 演習計算
						$event_item1 = $event_item0;
						$last_date = $item['date'];
						$var_array1 = explode( '/',$item['date'] );
						$var_array2 = explode( ':',$item['student_stime'] );
						$var_array3 = explode( ':',$item['student_etime'] );
						$event_item1['year']					=	$var_array1[0];
						$event_item1['month']					=	$var_array1[1]+0;
						$event_item1['day']						=	$var_array1[2]+0;
						$event_item1['start_timestamp']=	mktime( $var_array2[0],$var_array2[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
						$event_item1['start_hour']		=	$var_array2[0];
						$event_item1['start_minute']	=	$var_array2[1];
						$event_item1['end_timestamp']	=	mktime( $var_array3[0],$var_array3[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
						$event_item1['end_hour']			=	$var_array3[0];
						$event_item1['end_minute']		=	$var_array3[1];
						$event_item1['diff_hours']		=	($event_item1['end_timestamp']-$event_item1['start_timestamp']) / (60.0*60.0);
						$event_item1['lesson_id']			=	1;
						$event_item1['subject_id']		=	"";
						$event_item1['teacher_id']		=	"";
						$event_item1['fee'] = $exercise_fee;
						$event_item1["absent_flag"] = 0;
						if (strpos($event_item['cal_evt_summary'],'休み1:')!==false) {
							$event_item1["absent_flag"] = 1;
							$event_item1['diff_hours'] = 0;
						} else 
						if (strpos($event_item['cal_evt_summary'],'休み2:')!==false) {
							$event_item1["absent_flag"] = 2;
						}
						if (strpos($event_item['cal_evt_summary'],'振替:')!==false) {
							$event_item1["alternative_flag"] = 1;
							$event_item1['diff_hours'] = 0;
						} else {
							$event_item1["alternative_flag"] = 0;
						}
						$event_item1['sat_sun_flag'] = $sat_sun_flag;
						if ($sat_sun_flag) {
							$event_item1['course_id']=null;
						} else {
							switch ($season_course_id) {
							case 5: $event_item1['course_id']=5; break;
							case 6: $event_item1['course_id']=6; break;
							case 4: $event_item1['course_id']=4; break;
							default:$event_item1['course_id']=''; break;
							}
						}
						$event_item1['season_course_id'] = $item['season_course_id'];
						$exercise_index = array_push( $tmp_event_list1, $event_item1 )-1;
					}
//					} else {
//						$var_array1 = explode( '/',$item['date'] );
//						$var_array2 = explode( ':',$item['stime'] );
//						$var_array3 = explode( ':',$item['etime'] );
//						$event_item['year']						=	$var_array1[0];
//						$event_item['month']					=	$var_array1[1]+0;
//						$event_item['day']						=	$var_array1[2]+0;
//						$event_item['start_timestamp']=	mktime( $var_array2[0],$var_array2[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
//						$event_item['start_hour']			=	$var_array2[0];
//						$event_item['start_minute']		=	$var_array2[1];
//						$event_item['end_timestamp']	=	mktime( $var_array3[0],$var_array3[1],0,$var_array1[1],$var_array1[2],$var_array1[0] );
//						$event_item['end_hour']				=	$var_array3[0];
//						$event_item['end_minute']			=	$var_array3[1];
//						$event_item['diff_hours']			=	($event_item['end_timestamp']-$event_item['start_timestamp']) / (60.0*60.0);
					$event_item['lesson_id']			=	1;
					$event_item['subject_id']			=	$item['subject_id'];
					$event_item['teacher_id']			=	$item['teacher_no'];
					$lesson_fee = $lesson_fee0;
					// 代表の授業は+1,000円
					if ($event_item['teacher_id']==1 && $member['yuge_price']) { $lesson_fee += 1000; }
					$event_item['fee'] = $lesson_fee;
					
					$event_item["absent_flag"] = 0;
					if (strpos($event_item['cal_evt_summary'],'休み1:')!==false) {
						$event_item['diff_hours'] = 0;
						$event_item["absent_flag"] = 1;
					} else 
					if (strpos($event_item['cal_evt_summary'],'休み2:')!==false) {
						$event_item["absent_flag"] = 2;
					}
					if (strpos($event_item['cal_evt_summary'],'振替:')!==false) {
						$event_item["alternative_flag"] = 1;
						$event_item['diff_hours'] = 0;
					} else {
						$event_item["alternative_flag"] = 0;
					}
					
					$event_item['sat_sun_flag'] = $sat_sun_flag;
					if ($sat_sun_flag) {
						$event_item['course_id']=null;
					} else {
						switch ($season_course_id) {
						case 5: $event_item['course_id']=5; break;
						case 6: $event_item['course_id']=6; break;
						case 4: $event_item['course_id']=4; break;
						default:$event_item['course_id']=''; break;
						}
					}
//					}
						
					$tmp_event_list1[$exercise_index]['diff_hours'] -= $event_item['diff_hours']	;
										
					array_push( $tmp_event_list1, $event_item );
				}
				$tmp_event_list = $tmp_event_list1;
			}
			
			usort( $tmp_event_list,array("calculate_fees","eventCmp") );
//		}

		// 教室ごとに集計する
		//foreach ($member["fee_list"] as $fee) {
//if (count($this->season_class_list)>0) {
// var_dump($tmp_event_list);
//}
		$prev_lesson_id = "";
		$subtotal_hours = 0;
		$subtotal_fees = 0;
		$membership_flag = 0;

		$event_list = array();
		$lesson_detail_list = array();
		$event = array();
		foreach ($tmp_event_list as $tmp_event) {

				if ($prev_lesson_id != "" && $prev_lesson_id != $tmp_event["lesson_id"]) {
				// 最初から最後の1つ前のまでの$feeを$fee_arrayに格納する
					//$result = ksort($event_list);
					
					$subtotal_fees0 = $subtotal_fees;
					
					$sql = "SELECT a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price  ".
									"FROM tbl_event a, tbl_monthly_fee b ".
									"WHERE a.member_no=? AND a.member_no=b.member_no AND a.lesson_id=? ".
									"AND a.lesson_id=b.lesson_id AND a.subject_id=b.subject_id AND a.course_id=b.course_id ".
									"AND a.event_year=? AND a.event_month=? AND a.monthly_fee_flag=1 ".
									"GROUP BY a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array($member["no"],$tmp_event["lesson_id"],$year,$month));
					$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
					foreach ($ret as $item) {
						$subtotal_fees += $item['fee'];
					}
					$sql = "SELECT a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price, a.event_start_timestamp, a.cal_evt_summary ".
									"FROM tbl_event a, tbl_monthly_fee b ".
									"WHERE a.member_no=? AND a.member_no=b.member_no AND a.lesson_id=? ".
									"AND a.lesson_id=b.lesson_id AND a.subject_id=b.subject_id AND a.course_id=b.course_id ".
									"AND a.event_year=? AND a.event_month=? AND a.monthly_fee_flag=1 ".
									"GROUP BY a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price, a.event_start_timestamp, a.cal_evt_summary";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array($member["no"],$tmp_event["lesson_id"],$year,$month));
					$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
					$lesson_count = array();
					$lesson_minus_fee = array();
					foreach ($ret as $item) {
						if (!preg_match("/[:：︰]\s*(休講|no[^:：︰]*class)/ui",$item['cal_evt_summary'])) {
							$key = "{$item['lesson_id']}:{$item['subject_id']}:{$item['course_id']}";
							$lesson_count[$key]++;
							$lesson_minus_fee[$key] = $item['minus_price'];
						}
					}
					foreach ($lesson_count as $key=>$val) {
						$c = 4-$val;
						if ($c>0) {
							$subtotal_fees -= $lesson_minus_fee[$key]*$c;
						}
					}
					
					$monthly_fees_total = $subtotal_fees - $subtotal_fees0;

					$result = usort($event_list, array("calculate_fees","cmp_event"));
					$lesson_detail_list[] = array(
														"lesson_id"=>$prev_lesson_id,
														"lesson_name"=>$lesson_list[$prev_lesson_id],
														"subtotal_hours"=>$subtotal_hours,
														"subtotal_fees"=>$subtotal_fees,
														"monthly_fees_total"=>$monthly_fees_total,
														"event_list"=>	$event_list
													);
					//$total_hours = $total_hours + $subtotal_hours;
					//$total_fees = $total_fees + $subtotal_fees;
					$subtotal_hours = 0;
					$subtotal_fees = 0;
					$event_list = array();
				}

				$diff_hours = null;
				$fee_for_an_hour = null;
				$fee = array("fee"=>0, "family_minus_price"=>0, "additional_fee"=>0);
				$fees = "";
				$lesson_name = "";
				$subject_name = "";
				$course_name = "";
				$comment = "";
				
			// 授業料金とコメントをセットする
				// 無料体験や面談で、お休みの場合がある
			  if ($tmp_event["trial_flag"] == "1") {
				// 無料体験（時間ありの単価なし）
					$fee_for_an_hour = 0;
					$comment .= "無料体験";
				// 5月の暫定対応
				//} else if ($event["trial_flag"] == "2") {
				// 6月の対応
				//} else if ($tmp_event["subject_id"] == "7") {
			  } else if ($tmp_event["interview_flag"] == "1") {
				// 20150726 入塾後の無料面談（時間ありの単価なし）
					$fee_for_an_hour = 0;
					$comment .= "三者面談";
			  } else if ($tmp_event["interview_flag"] == "2") {
				// 20150726 入塾後の有料面談（時間ありの単価あり）
					// 授業と同じく科目が必ず入力されているため、その料金をあとからセットする
					$comment .= "三者面談";
				} else if ($tmp_event["interview_flag"] == "3") {
				// 20150806 入塾前の無料面談（時間ありの単価なし）
					$fee_for_an_hour = 0;
					$comment .= "面談";
			  }

				// &nbsp;はブラウザに出力する場合のみ。
				// このクラスは、スプレッドシートやPDFに出力する可能性があるので半角スペースにする
				// &nbsp;を含むと、スプレッドシートに出力できない、エラーがでる。
				//if ($comment != "") $comment .= "&nbsp;";
				if ($comment != "") $comment .= " ";

				// 料金表に登録してある料金を取得する
				if ($tmp_event["trial_flag"] == "1" || $tmp_event["interview_flag"] == "1" || $tmp_event["interview_flag"] == "3") {
					$fee["fee"] = 0;
					$fee["family_minus_price"] = 0;
					$fee["additional_fee"] = 0;
				} else if (!$tmp_event["monthly_fee_flag"]) {
					$fee = $this->get_fee($member["fee_list"], $tmp_event);
//var_dump($member["fee_list"]);
//var_dump($tmp_event);
//					if ($fee == null) { 
//20160503
//var_dump($member["fee_list"]);
//var_dump($tmp_event);
//						return null;
//					}
// 20150727 確認のための暫定対応(上のreturn nullをコメントアウト)
					if (is_null($fee) === true && !$member['fee_free']) {
						
						$fee_array = array();
						$fee_array["lesson_id"] = $tmp_event["lesson_id"];
						$fee_array["subject_id"] = $tmp_event["subject_id"];
						$fee_array["course_id"] = $tmp_event["course_id"];
						$fee_array["teacher_id"] = $tmp_event["teacher_id"];
						$fee_array["fee"] = 0;
						$fee_array["family_minus_price"] = 0;
						$ret = get_default_fee($this->db, $member, $year, $month, $tmp_event["lesson_id"], $tmp_event["course_id"]);
						if (!is_numeric($ret))	$ret = 0;
						$fee_array["fee"] = $ret;
						$fee_array["temp_flag"] = 1;
						$result = insert_fee($this->db, $member["no"], $fee_array);
						$member["fee_list"][] = $fee_array;
						if (!$result) errMsgFileLog (
								$member["name"].'：　'.
								"{$tmp_event['year']}/{$tmp_event['month']}/{$tmp_event['day']} ".
								"{$tmp_event['start_hour']}:{$tmp_event['start_minute']}-{$tmp_event['end_hour']}:{$tmp_event['end_minute']} ".
								$lesson_list[$tmp_event["lesson_id"]].'-'.
								$course_list[$tmp_event["course_id"]]["course_name"].'-'.
								$subject_list[$tmp_event["subject_id"]].'-'.
								$teacher_list[$tmp_event["teacher_id"]]["name"].
								"  授業料未登録エラー ({$tmp_event['cal_evt_summary']})\n"
								);
						continue; }
					if ($fee["fee"] == 0 && !$member['fee_free']) {
						errMsgFileLog (
							$member["name"].'：　'.
							"{$tmp_event['year']}/{$tmp_event['month']}/{$tmp_event['day']} ".
							"{$tmp_event['start_hour']}:{$tmp_event['start_minute']}-{$tmp_event['end_hour']}:{$tmp_event['end_minute']} ".
							$lesson_list[$tmp_event["lesson_id"]].'-'.
							$course_list[$tmp_event["course_id"]]["course_name"].'-'.
							$subject_list[$tmp_event["subject_id"]].'-'.
							$teacher_list[$tmp_event["teacher_id"]]["name"].
							"  授業料０円エラー ({$tmp_event['cal_evt_summary']})\n"
							);
						continue; }
				} else {
					$fee_for_an_hour = 0;
					$comment = "月謝授業";
				}

				if($comment != "") { $comment .= " "; }
				if ($tmp_event["absent_flag"] == "1") {
				// 料金なしの休み（時間なし「0」の単価あり）
					if (is_null($fee_for_an_hour) === true) $fee_for_an_hour = $fee["fee"];	// 単価が未設定の場合のみ単価をセットする
					$comment .= "お休み1";
				} else if ($tmp_event["absent_flag"] == "2") {
				// 料金ありの休み（時間ありの単価あり）
					if (is_null($fee_for_an_hour) === true) $fee_for_an_hour = $fee["fee"];	// 単価が未設定の場合のみ単価をセットする
					$comment .= "お休み2";
					if (preg_match('/[:：]当日/u',$tmp_event['cal_attendance_data']))	$comment .= '当日';
				} else if ($tmp_event["absent_flag"] == "3") {
				// 20160331 講師都合の休みを追加
				// 料金なしの休み（時間なし「0」の単価あり）
					if (is_null($fee_for_an_hour) === true) $fee_for_an_hour = $fee["fee"];	// 単価が未設定の場合のみ単価をセットする
					$comment .= "お休み3";
				// 2015/06/17 ファミリー対応
				//} else if ($tmp_event["absent1_num"] > 0 || $tmp_event["absent2_num"] > 0 || $tmp_event["trial_num"] > 0) {
				} else if ($tmp_event["alternative_flag"] == "1") {
				// 振替（時間なし「0」の単価あり）
					if (is_null($fee_for_an_hour) === true) $fee_for_an_hour = $fee["fee"];	// 単価が未設定の場合のみ単価をセットする
					$comment .= "振替";
				} else if ($tmp_event["absent1_num"] > 0 || $tmp_event["absent2_num"] > 0) {
					if (is_null($fee_for_an_hour) === true) {	// 単価が未設定の場合のみ単価をセットする
						$minus_price = 0;
						//if ($tmp_event["absent1_num"] > 0 || $tmp_event["trial_num"] > 0) {
						if ($tmp_event["absent1_num"] > 0) {
							//$minus_price = $fee["family_minus_price"] * ($tmp_event["absent1_num"]+$tmp_event["trial_num"]);	// 家族割引金額に人数をかける
							$minus_price = $fee["family_minus_price"] * $tmp_event["absent1_num"];	// 家族割引金額に人数をかける
						}
						$fee_for_an_hour = $fee["fee"] - $minus_price;
					}
					//if ($tmp_event["trial_num"] > 0) {
					// ファミリーで全員無料体験でないが誰か無料体験の場合
					//	$comment .= "無料体験（".$tmp_event["trial_num"]."人）";
					//}
					if ($tmp_event["absent1_num"] > 0) {
					// ファミリーで全員休みでないが誰か休み１の場合
						$comment .= "お休み1（".$tmp_event["absent1_num"]."人）";
					}
					if ($comment != "") $comment .= " ";
					if ($tmp_event["absent2_num"] > 0) {
					// ファミリーで全員休みでないが誰か休み２の場合
						$comment .= "お休み2（".$tmp_event["absent2_num"]."人）";
					}
				} else {
				// 出席（時間ありの単価あり）
					if (is_null($fee_for_an_hour) === true) $fee_for_an_hour = $fee["fee"];	// 単価が未設定の場合のみ単価をセットする
				}

/*
//2015/06/26 変更
		// （差分の）時間は、イベントデータをDBに登録するとき、セットする
		// お休み1の時間は0時間でDBに登録したいため、get_calender.data.phpで処理を行う。ここではしない。
				if ($tmp_event["absent_flag"] == "1") {
				// お休み1の場合は、無料体験でも面談でも普通の授業でも、ファミリーでも、時間を0にする
        	$diff_hours = 0;
				} else {
        	$diff_hours = $tmp_event["diff_hours"];
				}
*/
        $diff_hours = $tmp_event["diff_hours"];

// 2018/08/01 change exercise fee to fee for an hour  
//        if (isSeasonClassExercise($tmp_event)) {
//					$fees = $fee_for_an_hour;
//				} else {
			// 一人グループ対応
			if ($tmp_event["diff_hours"] == 0.33)
				$fees = $fee_for_an_hour*1/3;
			else if ($tmp_event["diff_hours"] == 0.67)
				$fees = $fee_for_an_hour*2/3;
			else
				$fees = $fee_for_an_hour*$diff_hours;
//				}

				$additional_fee = 0;
				if ($fee["additional_fee"] > 0 && $tmp_event["diff_hours"] > 0 &&  $tmp_event["diff_hours"] < 1) {
        // 20160625 1時間未満割増料金が設定されている場合でかつ授業時間が1時間未満の場合、
				// 割増料金を加える
					$fees = $fees + $fee["additional_fee"];
					$additional_fee = $fee["additional_fee"];
					//if($comment != "") { $comment .= " "; }
					//$comment .= "1時間未満割増料金：1授業につき＋".$fee["additional_fee"]."円";
				}

				// 時間と曜日
				$start_datetime = mktime($tmp_event["start_hour"], $tmp_event["start_minute"], 0, $tmp_event["month"], $tmp_event["day"], $tmp_event["year"]);
				$end_datetime = mktime($tmp_event["end_hour"], $tmp_event["end_minute"], 0, $tmp_event["month"], $tmp_event["day"], $tmp_event["year"]);
				$weekday_id = date("w", $start_datetime);
				$weekday_array = array("0"=>"日", "1"=>"月", "2"=>"火", "3"=>"水", "4"=>"木", "5"=>"金", "6"=>"土");

				// 教室
				$lesson_name = $lesson_list[$tmp_event["lesson_id"]];
				// 科目
				if ($tmp_event["subject_id"] == "0") {
				// ピアノ、英会話など、「科目なし」から変更
				// 20160128 教室がピアノや英会話の場合でも科目がtbl_eventに登録されるように変更した
				// そのため、20160128以降は、この条件には該当しなくなった
					$subject_name = "　";
/*
				} else if ($fee["plural_flag"] == 1) {
				// 複数科目
					if (strpos($subject_list[$tmp_event["subject_id"]], "複数科目") !== FALSE) {
						$subject_name = $subject_list[$tmp_event["subject_id"]];
					} else {
						$subject_name = "複数科目（".$subject_list[$tmp_event["subject_id"]]."）";
					}
*/
				} else {
					// 本番の未定科目のsubject_idは8
					if ($tmp_event["subject_id"] == 8) {
						$subject_name = "　";
					} else {
						$subject_name = $subject_list[$tmp_event["subject_id"]];
						if (preg_match("/:Subject\?(.*)\?/ui",$tmp_event['cal_evt_summary'],$matches)) {
							$subject_expr = preg_replace("/\([^)]*\)/","",$matches[1]);
							if ($subject_expr == '0') $subject_expr = '　';
							if ($subject_name != $subject_expr) $subject_name = $subject_expr;
						}
					}
				}
				// タイプ
	 			if ($tmp_event["course_id"] == "0") {
					$course_name = "";
				} else {
					$course_name = $course_list[$tmp_event["course_id"]]["course_name"];
				}
				if ($tmp_event["sat_sun_flag"]) $course_name = '土日講習';

// 20150903 lesson_idとtype_idを追加
// 20151230 start_timestampとend_timestampとinterview_flagとtrial_flagを追加
// 20151230 面談はinterview_flag=3が残っている
				$event = array("date" => date("n月j日", $start_datetime),
												"weekday" => $weekday_array[$weekday_id],
												"start_timestamp" => $start_datetime,
												"end_timestamp" => $end_datetime,
												"time" => date("H:i", $start_datetime) ." ～ ". date("H:i", $end_datetime),
												"absent_flag" => $tmp_event["absent_flag"] ,
												"absent1_num" => $tmp_event["absent1_num"] ,
												"absent2_num" => $tmp_event["absent2_num"] ,
												"interview_flag" => $tmp_event["interview_flag"] ,
												"trial_flag" => $tmp_event["trial_flag"] ,
												"diff_hours" => $diff_hours ,
												"fee_for_an_hour" => ($fee_for_an_hour),
												"additional_fee" => number_format($additional_fee),
												"fees" => ($fees),
												"lesson_id" => $tmp_event["lesson_id"],
												"lesson_name" => $lesson_name,
												"subject_id" => $tmp_event["subject_id"],
												"subject_name" => $subject_name,
												//"type_name" => $type_name,
												//"type_id" => $tmp_event["type_id"],
												"course_id" => $tmp_event["course_id"],
												"course_name" => $course_name,
												"teacher_id" => $tmp_event["teacher_id"],
												"place_id" => $tmp_event["place_id"],
												"monthly_fee_flag" => $tmp_event["monthly_fee_flag"],
												"comment" => $comment);
												
				if ($tmp_event["season_course_id"] !=null) {
					if (isSeasonClassExercise($tmp_event)) {
						$event["course_name"] = $event["course_name"].$season_course_list[$tmp_event["season_course_id"]]["course_name"];
						$event["subject_name"] = "演習";
					} else {
/* Old season class course
						if ($tmp_event["season_course_id"] != 3) {
							$event["course_name"] = $event["course_name"]."個別授業";
						} else {
							$event["course_name"] = $event["course_name"].$season_course_list[$tmp_event["season_course_id"]]["course_name"];
						}
*/
							$event["course_name"] = $event["course_name"]."個別授業";
					}
				}
//echo __FILE__.__LINE__;
//var_dump($event);
				//$event_list[$tmp_event["start_timestamp"]] = $event;
				$event_list[] = $event;

				// 20151230 面談の表示変更
				// 面談は授業と時間が重なっていることがあるので表示したり合計したりしない
				if (!$event["interview_flag"]) {
					$subtotal_hours = $subtotal_hours + $tmp_event["diff_hours"];
					//$subtotal_fees = $subtotal_fees + ($tmp_event["diff_hours"] * $fee_for_an_hour);
					$subtotal_fees = $subtotal_fees + $fees;
				}

				$prev_lesson_id = $tmp_event["lesson_id"];

		}	// End: foreach ($tmp_event_list as $tmp_event)

		if (count($tmp_event_list) > 0) {
		// 最後の$lessonを格納する
			//$result = 	ksort($event_list);
			
					$subtotal_fees0 = $subtotal_fees;

					$sql = "SELECT a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price  ".
									"FROM tbl_event a, tbl_monthly_fee b ".
									"WHERE a.member_no=? AND a.member_no=b.member_no AND a.lesson_id=? ".
									"AND a.lesson_id=b.lesson_id AND a.subject_id=b.subject_id AND a.course_id=b.course_id ".
									"AND a.event_year=? AND a.event_month=? AND a.monthly_fee_flag=1 ".
									"GROUP BY a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array($member["no"],$tmp_event["lesson_id"],$year,$month));
					$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
					foreach ($ret as $item) {
						$subtotal_fees += $item['fee'];
					}
					$sql = "SELECT a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price, a.event_start_timestamp, a.cal_evt_summary ".
									"FROM tbl_event a, tbl_monthly_fee b ".
									"WHERE a.member_no=? AND a.member_no=b.member_no AND a.lesson_id=? ".
									"AND a.lesson_id=b.lesson_id AND a.subject_id=b.subject_id AND a.course_id=b.course_id ".
									"AND a.event_year=? AND a.event_month=? AND a.monthly_fee_flag=1 ".
									"GROUP BY a.lesson_id, a.subject_id, a.course_id, b.fee, b.minus_price, a.event_start_timestamp, a.cal_evt_summary";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array($member["no"],$tmp_event["lesson_id"],$year,$month));
					$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
					$lesson_count = array();
					$lesson_minus_fee = array();
					foreach ($ret as $item) {
						if (!preg_match("/[:：︰]\s*(休講|no[^:：︰]*class)/ui",$item['cal_evt_summary'])) {
							$key = "{$item['lesson_id']}:{$item['subject_id']}:{$item['course_id']}";
							$lesson_count[$key]++;
							$lesson_minus_fee[$key] = $item['minus_price'];
						}
					}
					foreach ($lesson_count as $key=>$val) {
						$c = 4-$val;
						if ($c>0) {
							$subtotal_fees -= $lesson_minus_fee[$key]*$c;
						}
					}

					$monthly_fees_total = $subtotal_fees - $subtotal_fees0;

			$result = usort($event_list, array("calculate_fees","cmp_event"));
			$lesson_detail_list[] = array(
												"lesson_id"=>$prev_lesson_id,
												"lesson_name"=>$lesson_list[$prev_lesson_id],
												"subtotal_hours"=>$subtotal_hours,
												"subtotal_fees"=>$subtotal_fees,
												"monthly_fees_total"=>$monthly_fees_total,
												"event_list"=>$event_list
											);
		}

		return $lesson_detail_list;
	}

	private function cmp_event($a, $b) {
    if ($a["start_timestamp"] == $b["start_timestamp"]) {
	    if ($a["end_timestamp"] == $b["end_timestamp"]) {
	      return 0;
	    }
	    return ($a["end_timestamp"] < $b["end_timestamp"]) ? +1 : -1;
    }
    return ($a["start_timestamp"] > $b["start_timestamp"]) ? +1 : -1;
	}

	private function get_fee($fee_list, $tmp_event) {
		if (isset($tmp_event['fee'])) {
			// 一人グループ対応
			if ($tmp_event["one_man_group"] && ($tmp_event["lesson_id"] == 1 || $tmp_event["lesson_id"] == 2) && $tmp_event["diff_hours"] == 0.67) {
				return array(
								"fee_no"			=> "",
								"lesson_id"		=> $tmp_event["lesson_id"],
								"subject_id"	=> $tmp_event["subject_id"],
								"course_id"		=> $tmp_event["course_id"],
								"teacher_id"	=> $tmp_event["teacher_id"],
								"fee"					=> $tmp_event["fee"]*1.5,
								"family_minus_price"=> 0,
								"additional_fee"		=> 0
							);
				}
			return array(
							"fee_no"			=> "",
							"lesson_id"		=> $tmp_event["lesson_id"],
							"subject_id"	=> $tmp_event["subject_id"],
							"course_id"		=> $tmp_event["course_id"],
							"teacher_id"	=> $tmp_event["teacher_id"],
							"fee"					=> $tmp_event["fee"],
							"family_minus_price"=> 0,
							"additional_fee"		=> 0
						);
		}
		if (isSeasonClassExercise($tmp_event)) {
			// 期間講習演習
			foreach ($this->season_class_list as $season_class) {
				if (str_replace('/0','/',$season_class['date']) == "{$tmp_event['year']}/{$tmp_event['month']}/{$tmp_event['day']}") {
					$var1 = $season_class['fee']; 
					break;
				}
			}
			if($tmp_event["season_course_id"]==1 && !$season_class['fee']) { $var1 = 2000; }
			return array(
							"fee_no"			=> "",
							"lesson_id"		=> "",
							"subject_id"	=> "",
							"course_id"		=> $tmp_event["course_id"],
							"teacher_id"	=> "",
							"fee"					=> $var1,
							"family_minus_price"=> 0,
							"additional_fee"		=> 0
						);
		}
		foreach ($fee_list as $fee) {
    	if ($fee["lesson_id"] 	== $tmp_event["lesson_id"] && 
//    			$fee["subject_id"] 	== $tmp_event["subject_id"] && 
    			$fee["course_id"] 	== $tmp_event["course_id"] && 
    			$fee["teacher_id"] 	== $tmp_event["teacher_id"]) {
				// 一人グループ対応
				if ($tmp_event["one_man_group"] && ($tmp_event["lesson_id"] == 1 || $tmp_event["lesson_id"] == 2) && $tmp_event["diff_hours"] == 0.67)
					$fee["fee"] *= 1.5;
				return $fee;
			}
			if ($tmp_event["season_course_id"] != null) {
				// 期間講習の授業料は原則マンツーマン
				if ($fee["lesson_id"] 	== $tmp_event["lesson_id"] && 
						$fee["subject_id"] 	== $tmp_event["subject_id"] && 
						$fee["course_id"] 	== "1" && 
						$fee["teacher_id"] 	== $tmp_event["teacher_id"]) {
					return $fee;
				}
			}
		}
    return null;
	}

	// func.incのget_buying_textbook_list関数と同じ内容
	// テキストブック購入一覧を取得
	// 改ページ対応なし
	// $param_array : year = ?
	// $value_array : 2015
	// $order_array : name
	private function calculate_buying_textbook($db, $param_array=array(), $value_array=array(), $order_array=array()) {
		$sql = 
			"SELECT
					tbl_buying_textbook.buying_no as buying_no,
					tbl_buying_textbook.member_no as member_no,
					tbl_buying_textbook.year as year,
					tbl_buying_textbook.month as month,
					tbl_buying_textbook.day as day,
					tbl_buying_textbook.name as name,
					tbl_buying_textbook.price as price,
					tbl_buying_textbook.lesson_id as lesson_id,
					tbl_buying_textbook.kind as kind
			 FROM tbl_buying_textbook";
	  if(count($param_array) > 0){
	    $sql .= " where " . join(" and ",$param_array);
	  }
	  if(count($order_array) > 0){
	    $sql .= "	order by " . join(" , ",$order_array);
		} else {
			$sql .= "	order by tbl_buying_textbook.buying_no";
		}
		$stmt = $db->prepare($sql);
		$stmt->execute($value_array);
		$buying_textbook_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $buying_textbook_list;
	}

	// func.incのget_others_list関数と同じ内容
	// その他購入一覧を取得
	// 改ページ対応なし
	// $param_array : year = ?
	// $value_array : 2015
	// $order_array : name
	private function calculate_others($db, $param_array=array(), $value_array=array(), $order_array=array()) {
		global $except20170830;
		$sql = 
			"SELECT
					tbl_others.others_no as others_no,
					tbl_others.member_no as member_no,
					tbl_others.year as year,
					tbl_others.month as month,
					tbl_others.name as name,
					tbl_others.price as price,
					tbl_others.tax_flag as tax_flag,
					tbl_others.lesson_id as lesson_id,
					tbl_others.type_id as type_id,
					tbl_others.kind as kind,
					tbl_others.memo as memo,
					tbl_others.charge as charge
			 FROM tbl_others";
	  if(count($param_array) > 0){
	    $sql .= " where " . join(" and ",$param_array);
	  }
	  if(count($order_array) > 0){
	    $sql .= "	order by " . join(" , ",$order_array);
	  }
		else {
			//$sql .= "	order by tbl_others.year, tbl_others.month";
			$sql .= "	order by tbl_others.others_no";
		}
		$stmt = $db->prepare($sql);
		$stmt->execute($value_array);
		$others_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		// 20170830 2017夏期講習の特別対応
		foreach($others_list as $key=>$item) {
			if ($item["year"] == "2017" && $item["month"] == "8" && 
					$item["kind"] == 7 && $item["charge"] == 1 && 
					array_search($item["member_no"], $except20170830) !== false) {
					unset($others_list[$key]);
			}
		}

		return $others_list;
	}

	private function calculate_divided_payment($db, $param_array=array(), $value_array=array(), $order_array=array()) {
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
 		return $divided_payment_list;
	}

	// calculate_fee.phpのcalculate_entrance_fee関数と同じ内容
	// その他購入一覧を取得
	// 改ページ対応なし
	// $param_array : year = ?
	// $value_array : 2015
	// $order_array : name
	function calculate_entrance_fee($db, $param_array=array(), $value_array=array(), $order_array=array()) {
		$sql = 
			"SELECT
					tbl_entrance_fee.entrance_fee_no as entrance_fee_no,
					tbl_entrance_fee.member_no as member_no,
					tbl_entrance_fee.year as year,
					tbl_entrance_fee.month as month,
					tbl_entrance_fee.price as price,
					tbl_entrance_fee.memo as memo
			 FROM tbl_entrance_fee";
	  if(count($param_array) > 0){
	    $sql .= " where " . join(" and ",$param_array);
	  }
	  if(count($order_array) > 0){
	    $sql .= "	order by " . join(" , ",$order_array);
	  }
		else {
			//$sql .= "	order by tbl_entrance_fee.year, tbl_entrance_fee.month";
			$sql .= "	order by tbl_entrance_fee.entrance_fee_no";
		}
		$stmt = $db->prepare($sql);
		$stmt->execute($value_array);
		$entrance_fee_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $entrance_fee_list;
	}


}


?>