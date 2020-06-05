<?php

define( "NOT_CONFIRMED_ERR_MSG1", "「(仮)」入力エラー" );
define( "NOT_CONFIRMED_ERR_MSG2", "「(Not Defined)」入力エラー" );
define( "NOT_CONFIRMED_ERR_MSG3", "「(Not Confirmed)」入力エラー" );
define( "NOT_CONFIRMED_ERR_MSG4", "「(temporary)」入力エラー" );

if (!$date_list_string) {
	$sql = "SELECT * FROM tbl_season_schedule s LEFT OUTER JOIN tbl_member m ON s.member_no=m.no WHERE s.date IN {$date_list_string}";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	$season_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($nocheck) $result=true;

if ($result == false) {
	$err_flag = true;
	$errArray[] = "セッションエラーが発生しました";
}

$member_list0 = get_simple_member_list($db);

if ($result) {

	// カレンダーデータをチェックする
	// 20160810堀内修正　請求月ごとにチェック
	//$param_array = array("tbl_event.event_year = ?","tbl_event.event_month = ?");
	$param_array = array("tbl_event.seikyu_year = ?","tbl_event.seikyu_month = ?");
	$value_array = array($year, $month);
	// 20160506 順を追加
	//$order_array = array();
	$order_array = array("member_no", "event_start_timestamp", "event_end_timestamp");
	$event_list = get_event_list($db, $param_array, $value_array, $order_array);

	$errArray = array();	// すべてのイベントのエラーを格納する
	$before_event = array("member_no"=>"","start_timestamp"=>"","end_timestamp"=>"");

			foreach ($event_list as $event) {

//var_dump($event);
				if ($tid && $event["teacher_id"]!=$tid) {continue;}

					// イベントにエラーがあったとき表示する内容を、あらかじめ用意しておく
					$error_event = array("date"=>date("Y-m-d", $event["start_timestamp"]),
															 "start_time"=>date("H:i", $event["start_timestamp"]),
															 "end_time"=>date("H:i", $event["end_timestamp"]),
															 "summary"=>$event["cal_evt_summary"],
															 "calender_summary"=>$event["cal_summary"],
															 "message"=>"");

					$event["cal_evt_summary"] = str_replace(array("　", "（", "）", "：", "︰"), array(" ", "(", ")", ":", ":"), trim($event["cal_evt_summary"]));
					$event["cal_evt_summary"] = mb_convert_kana($event["cal_evt_summary"], "n", "utf-8");

					//if ($event["member_no"] == "") {
					// 201508/26 テストのため暫定対応
					//if ($event["member_no"] == "" && $event["type_id"] != "4") {
					//if ($event["member_no"] == "") {
					//	$error_event["message"] = "氏名の取得エラー";
					//	$errArray[] = $error_event;
					//} else 
					if (is_null($event["start_timestamp"]) === true) {
						$error_event["message"] = "開始時刻の取得エラー";
						$errArray[] = $error_event;
					} else if (is_null($event["end_timestamp"]) === true) {
						$error_event["message"] = "終了時刻の取得エラー";
						$errArray[] = $error_event;
					} else if ($event["lesson_id"] == "0") {
						$error_event["message"] = "教室の取得エラー";
						$errArray[] = $error_event;
// 20160423 先生を追加
// 20160601 面談の場合は先生の取得エラーを出さない
					} else if ($event["teacher_id"] == "0" && $event["interview_flag"] != "1" && $event["interview_flag"] != "3") {
						$error_event["message"] = "先生の取得エラー";
						$errArray[] = $error_event;
// 20150603 科目データが未入力の場合は、ここをコメントアウトする
					} else if ($event["lesson_id"] == "4") {
					// 習い事の場合は、科目として「そろばん」などが入力される
						if ($event["subject_id"] === "0") {
							$error_event["message"] = "科目の取得エラー";
							$errArray[] = $error_event;
						}
					} else if ($event["lesson_id"] == "1") {

					// 塾の場合
						// （入塾前の）面談と三者面談１の場合は科目が入力されないため、チェックしない
						// 20151023 無料体験の場合も、科目が入力されないため、チェックしない（先生から必須チェックを外してほしいと依頼がありました）
						//if ($event["interview_flag"] != "1" && $event["interview_flag"] != "3") {
						if ($event["interview_flag"] != "1" && $event["interview_flag"] != "3" && $event["trial_flag"] != "1") {
							if ($event["subject_id"] == "0") {
								$error_event["message"] = "科目の取得エラー";
								$errArray[] = $error_event;
							}
						}
					// タイプはデフォルト値が「1:マンツーマン」
					/*
					} else if ($event["type_id"] == "") {
						$errArray["message"] = "タイプ取得エラー";
						$errArray[] = $error_event;
					*/
					// 2015/05/14 生徒の月謝登録のみ使う間は、場所の入力がなくても登録できるようにする
					/*
					} else if ($event["place_id"] == null) {
						$errArray["message"] = "場所取得エラー";
						$errArray[] = $error_event;
					*/
					}

					// 20160525追加 場所追加に伴いチェックも追加
					if ($event["place_id"] == 0) {
						$error_event["message"] = "場所取得エラー";
						$errArray[] = $error_event;
 					}
/*
					// グループが入っていて、「()」で生徒氏名がくくられていないときのエラー
					if ((preg_match("/グループ/", $event["cal_evt_summary"]) === 1)
						|| (preg_match("/Group/", $event["cal_evt_summary"]) === 1)
						|| (preg_match("/group/", $event["cal_evt_summary"]) === 1)) {
						//if ($event["type_id"] !== "2" ) {
						$match_num = preg_match_all("/\((.*?)\)/", $event["cal_evt_summary"], $tmp_matches, PREG_PATTERN_ORDER);
						if ($match_num === false || $match_num === 0) {
							$error_event["message"] = "グループの設定エラー";
							$errArray[] = $error_event;
						}
					}
					// ファミリーが入っていて、「()」で生徒氏名がくくられていないときのエラー
					if ((preg_match("/ファミリー/", $event["cal_evt_summary"]) === 1)
						|| (preg_match("/Family/", $event["cal_evt_summary"]) === 1)
						|| (preg_match("/family/", $event["cal_evt_summary"]) === 1)) {
						//if ($event["type_id"] !== "3" ) {
						$match_num = preg_match_all("/\((.*?)\)/", $event["cal_evt_summary"], $tmp_matches, PREG_PATTERN_ORDER);
						if ($match_num === false || $match_num === 0) {
							$error_event["message"] = "ファミリーの設定エラー";
							$errArray[] = $error_event;
						}
					}
					// 夏期講習が入っていて、「()」で生徒氏名がくくられていないときのエラー
					if (preg_match("/夏期講習/", $event["cal_evt_summary"]) === 1) {
						//if ($event["type_id"] !== "4" ) {
						$match_num = preg_match_all("/\((.*?)\)/", $event["cal_evt_summary"], $tmp_matches, PREG_PATTERN_ORDER);
						if ($match_num === false || $match_num === 0) {
							$error_event["message"] = "夏期講習の設定エラー";
							$errArray[] = $error_event;
						}
					}
*/
					$error_absent_flag = false;
					// 休みに数字がついていないときのエラー
					// 20160331 講師都合の休み「休み3」を追加
					if (preg_match("/休み/", $event["cal_attendance_data"]) === 1) {
						if (preg_match("/休み[1-3]{1}/", $event["cal_attendance_data"]) !== 1) {
							$error_event["message"] = "休みの数字なしエラー";
							$errArray[] = $error_event;
							$error_absent_flag = true;
						}
					}
					if (preg_match("/Absent/", $event["cal_attendance_data"]) === 1) {
						if (preg_match("/Absent[1-3]{1}/", $event["cal_attendance_data"]) !== 1) {
							$error_event["message"] = "休みの数字なしエラー";
							$errArray[] = $error_event;
							$error_absent_flag = true;
						}
					}
					if (preg_match("/absent/", $event["cal_attendance_data"]) === 1) {
						if (preg_match("/absent[1-3]{1}/", $event["cal_attendance_data"]) !== 1) {
							$error_event["message"] = "休みの数字なしエラー";
							$errArray[] = $error_event;
							$error_absent_flag = true;
						}
					}

					// 休みが入っていて、absent_flagとabsent_numがないときのエラー
 					if ($error_absent_flag == false) {
							if ((preg_match("/休み/", $event["cal_attendance_data"]) === 1)
								|| (preg_match("/Absent/", $event["cal_attendance_data"]) === 1)
								|| (preg_match("/absent/", $event["cal_attendance_data"]) === 1)) {
								if ($event["absent_flag"] === "0" && $event["absent1_num"] === 0 && $event["absent2_num"] === 0) {
									$error_event["message"] = "休みの設定エラー";
									$errArray[] = $error_event;
								}
							}
					}

					if (preg_match("/三者面談/", $event["cal_attendance_data"]) === 1) {
					// 三者面談には必ず数字がついている
					// 三者面談に数字がついていないときのエラー
						if (preg_match("/三者面談[1-2]{1}/", $event["cal_attendance_data"]) !== 1) {
							$error_event["message"] = "三者面談の数字なしエラー";
							$errArray[] = $error_event;
						}
					} else if (preg_match("/面談/", $event["cal_attendance_data"]) === 1) {
					// 面談には必ず数字がついていない
					// 面談に数字がついているときのエラー
						if (preg_match("/面談[1-2]{1}/", $event["cal_attendance_data"]) === 1) {
							$error_event["message"] = "面談の数字ありエラー";
							$errArray[] = $error_event;
						}
					}

					// 体験が入っていて、trial_flagがないときのエラー（trial_num：ファミリーの無料体験は未対応）
					if ((preg_match("/体験/", $event["cal_attendance_data"]) === 1)
							|| (preg_match("/Trial/", $event["cal_attendance_data"]) === 1)
							|| (preg_match("/trial/", $event["cal_attendance_data"]) === 1)) {
						if ($event["trial_flag"] == "0") {
							$error_event["message"] = "無料体験の設定エラー";
							$errArray[] = $error_event;
						}
					}

					// 20150827追加 ファミリー、グループ、Bussiness Group、夏期講習がタイトルの先頭にあるかチェック
					// 20160331変更 ファミリー、グループ、Bussiness Group、夏期講習より前に（仮）がある可能性があるため、チェックを変更
					if (strpos($event["cal_evt_summary"], "ファミリー") !== FALSE && preg_match("/^(\(仮\))?ファミリー/", $event["cal_evt_summary"]) !== 1) {
 						$error_event["message"] = "ファミリーの入力エラー";
						$errArray[] = $error_event;
					}
					if (strpos($event["cal_evt_summary"], "グループ") !== FALSE && preg_match("/^(\(仮\))?グループ/", $event["cal_evt_summary"]) !== 1) {
 						$error_event["message"] = "グループの入力エラー";
						$errArray[] = $error_event;
					}
					if (strpos($event["cal_evt_summary"], "Business Group") !== FALSE) {
						if (preg_match("/^(\(仮\))?Business Group/", $event["cal_evt_summary"]) !== 1) {
	 						$error_event["message"] = "グループの入力エラー";
							$errArray[] = $error_event;
						}
					} else if (strpos($event["cal_evt_summary"], "Group") !== FALSE) {
						if (preg_match("/^(\(仮\))?Group/", $event["cal_evt_summary"]) !== 1) {
	 						$error_event["message"] = "グループの入力エラー";
							$errArray[] = $error_event;
						}
					} else if (strpos($event["cal_evt_summary"], "group") !== FALSE) {
						if (preg_match("/^(\(仮\))?group/", $event["cal_evt_summary"]) !== 1) {
	 						$error_event["message"] = "グループの入力エラー";
							$errArray[] = $error_event;
						}
					}
					// 夏期講習,冬期講習,春期講習のエラーチェックを外す  (2016/9/1 T.Katsu)
//					if (strpos($event["cal_evt_summary"], "夏期講習") !== FALSE && preg_match("/^(\(仮\))?夏期講習/", $event["cal_evt_summary"]) !== 1) {
// 						$error_event["message"] = "夏期講習の入力エラー";
//						$errArray[] = $error_event;
//					}
//					if (strpos($event["cal_evt_summary"], "冬期講習") !== FALSE && preg_match("/^(\(仮\))?冬期講習/", $event["cal_evt_summary"]) !== 1) {
// 						$error_event["message"] = "冬期講習の入力エラー";
//						$errArray[] = $error_event;
//					}
//					if (strpos($event["cal_evt_summary"], "春期講習") !== FALSE && preg_match("/^(\(仮\))?春期講習/", $event["cal_evt_summary"]) !== 1) {
// 						$error_event["message"] = "春期講習の入力エラー";
//						$errArray[] = $error_event;
//					}

					// 20160119 グループコース
					$course_list = get_course_list($db);
/*  エラー判定削除	2019/12/17 T.Katsu
				if (count($errArray) < 1) {
						foreach ($course_list as $course) {
							if ($course["course_id"] == "1" || $course["course_id"] == "2" || $course["course_id"] == "3") {
								continue;
							}
							// 夏期講習,冬期講習,春期講習のエラーチェックを外す  (2016/9/26 T.Katsu)
							if ($course["course_id"] == "4" || $course["course_id"] == "5" || $course["course_id"] == "6") {
								continue;
							}
							if (strpos($event["cal_evt_summary"], $course["course_name"]) !== FALSE && preg_match("/^(\(仮\))?".$course["course_name"]."/", $event["cal_evt_summary"]) !== 1) {
	 							$error_event["message"] = $course["course_name"]."の入力エラー";
								$errArray[] = $error_event;
							}
						}
					}
*/
					// 20150827追加 「キャンセル」と入力されている場合、エラーとする。（キャンセルでなくて休み１と休み２を入力してもらう）
					if (strpos($event["cal_evt_summary"], "キャンセル") !== FALSE) {
 						$error_event["message"] = "「キャンセル」入力エラー";
						$errArray[] = $error_event;
					}

					// 20150827追加 「、」と入力されている場合、エラーとする。（生徒氏名を「、」で区切られていることが多いためチェックする）
					if (strpos($event["cal_evt_summary"], "、") !== FALSE) {
 						$error_event["message"] = "「、」入力エラー";
						$errArray[] = $error_event;
					}

					// 20151229追加 「(仮)」と入力されている場合、エラーとする。
					// 20160331 「（仮）」が入っていても授業料は登録するようにする
					if (strpos($event["cal_evt_summary"], "(仮)") !== FALSE) {
 						$error_event["message"] = NOT_CONFIRMED_ERR_MSG1;
						$errArray[] = $error_event;
					}

					// 20160506追加 「ed」のつけ忘れもチェックするため、あえてdefineまでを条件とする
					if (preg_match("/\(not\s*define/i", $event["cal_evt_summary"]) == 1) {
 						$error_event["message"] = NOT_CONFIRMED_ERR_MSG2;
						$errArray[] = $error_event;
					}

					if (preg_match("/\(not\s*confirm/i", $event["cal_evt_summary"]) == 1) {
 						$error_event["message"] = NOT_CONFIRMED_ERR_MSG3;
						$errArray[] = $error_event;
					}

					if (preg_match("/\(temporary\)/i", $event["cal_evt_summary"]) == 1) {
 						$error_event["message"] = NOT_CONFIRMED_ERR_MSG4;
						$errArray[] = $error_event;
					}

					// 20160524追加 古い北口校カレンダー（3Fと4Fに分かれていないカレンダー）に入力されている場合、エラーとする。
					// 20160916追加 2016年4月までは古い北口校カレンダーを使っていたため、2016年4月より後をチェックする
					if ($year > 2016 && $month > 4) {
						if (strpos($event["cal_summary"], "北口") !== FALSE || strpos($event["cal_summary"], "north") !== FALSE) {
							if (strpos($event["cal_summary"], "3F") === FALSE && strpos($event["cal_summary"], "4F") === FALSE) {
	 							$error_event["message"] = "古い北口校カレンダーへの入力エラー";
								$errArray[] = $error_event;
							}
						}
					}

					// 20151229追加 「?」「？」と入力されている場合、エラーとする。
					if (strpos($event["cal_evt_summary"], "？") !== FALSE || strpos($event["cal_evt_summary"], "?") !== FALSE) {
 						$error_event["message"] = "「?」入力エラー";
						$errArray[] = $error_event;
					}

					if ($event["member_no"] == $before_event["member_no"] && $event["start_timestamp"] == $before_event["start_timestamp"] && $event["end_timestamp"] == $before_event["end_timestamp"]) {
//var_dump($before_event);
            // 面談以外で予定の重複がある場合
            			
						if (($event["interview_flag"] < 1) && (get_member($db, array("tbl_member.no = ?"), array($event["member_no"]))[0]["name"] == "体験生徒" )) {
	 							$error_event["message"] = "予定の重複エラー";
								$errArray[] = $error_event;
						}
					}
					
					if (!$event["absent_flag"])
					foreach ($season_schedules as $season_schedule) {
						if ($season_schedule['teacher_no']!=$event['teacher_id'])	continue;
						$stimestamp = DateTime::createFromFormat('Y/m/d H:i', "{$season_schedule['date']} {$season_schedule['stime']}")->getTimestamp();
						$etimestamp = DateTime::createFromFormat('Y/m/d H:i', "{$season_schedule['date']} {$season_schedule['etime']}")->getTimestamp();
						if (($event['start_timestamp']<=$stimestamp && $stimestamp<$event['end_timestamp']) ||
								($event['start_timestamp']> $stimestamp && $etimestamp>$event['start_timestamp'])) {
	 							$error_event["message"] = "季節講習・土日講習と重複しています";
								$warning_Array[] = $error_event;
								}
					}
					
					$before_event = $event;

/*
					// 20160525コメントアウト 4月分の月謝計算時に一通り登録できたため
					// 20160423 先生を追加に伴う暫定対応
					$param_array = array();
					$value_array = array();
					array_push($param_array, "tbl_fee.member_no = ?");
					array_push($value_array, $event["member_no"]);
					array_push($param_array, "tbl_fee.lesson_id = ?");
					array_push($value_array, $event["lesson_id"]);
					array_push($param_array, "tbl_fee.subject_id = ?");
					array_push($value_array, $event["subject_id"]);
					array_push($param_array, "tbl_fee.course_id = ?");
					array_push($value_array, $event["course_id"]);
					$tmp_fee_cnt = get_fee_cnt($db, $param_array, $value_array);
					if ($tmp_fee_cnt == 1) {
						$fee_list = get_fee_list($db, $param_array, $value_array);
						if (empty($fee_list[0]["teacher_id"]) == true) {
							$result = update_teacher_id($db, $event["member_no"], $event["lesson_id"], $event["subject_id"], $event["course_id"], $event["teacher_id"]);
						}
          // 生徒NO、教室、科目、コースで2件以上ある場合
					}
					else {
//echo $tmp_fee_cnt."件";
//var_dump($value_array);
						// そのままであとから手動で入力する
						// 面談はtbl_feeに登録しない
						//var_dump($event);
					}
*/

					// 20160525追加 税込or税抜と月会費が登録されているかチェックする
					// 20160601 無料体験と面談の場合は、エラーを表示しない
					if ($event["interview_flag"] != "1" && $event["interview_flag"] != "3" && $event["trial_flag"] != "1"
							&& (strpos($event["cal_evt_summary"], "演習") === FALSE)
							&& (strpos($event["cal_evt_summary"], "試験監督") === FALSE)) {
						$param_array = array();
						$value_array = array();
						array_push($param_array, "tbl_member.no = ?");
						array_push($value_array, $event["member_no"]);
						$member_list = get_simple_member_list($db, $param_array, $value_array);
						if (count($member_list) == 0) {
							$error_event["message"] = "生徒の未登録エラー";
							$errArray[] = $error_event;
						} else if (count($member_list) > 1) {
							$error_event["message"] = "生徒の登録エラー";
							$errArray[] = $error_event;
						} else {
							$member_array = array_pop($member_list);
	//var_dump($member_array);
							if ($member_array["tax_flag"] == null && $member_array["tax_flag"] !== 0) {
							// tax_flagのデフォルト値はnull。tax_flagは0でもOK
								$error_event["message"] = "税種別の未登録エラー";
								$errArray[] = $error_event;
							}
							if ($member_array["membership_fee"] == null && $member_array["membership_fee"] !== 0) {
							// membership_feeのデフォルト値はnull。membership_feeは0でもOK
								$error_event["message"] = "月会費の未登録エラー";
								$errArray[] = $error_event;
							}
						}
					}

					if (!$tid
							&& (strpos($event["cal_evt_summary"], "演習") === FALSE)
							&& (strpos($event["cal_evt_summary"], "試験監督") === FALSE)) {
					// 20160423 先生を追加
					// 生徒NO、教室、科目、コース、先生を指定して授業料を取得する
					// 無料体験、面接は、フラグで処理している
					$param_array = array();
					$value_array = array();
					array_push($param_array, "tbl_fee.member_no = ?");
					array_push($value_array, $event["member_no"]);
					array_push($param_array, "tbl_fee.lesson_id = ?");
					array_push($value_array, $event["lesson_id"]);
					// type_idからcourse_idへ変更
					//array_push($param_array, "tbl_fee.type_id = ?");
					//array_push($value_array, $event["type_id"]);
					array_push($param_array, "tbl_fee.subject_id = ?");
					array_push($value_array, $event["subject_id"]);
					array_push($param_array, "tbl_fee.course_id = ?");
					array_push($value_array, $event["course_id"]);
					array_push($param_array, "tbl_fee.teacher_id = ?");
					array_push($value_array, $event["teacher_id"]);
					// 20160123 変更
					//array_push($param_array, "tbl_fee.course_id = ?");
					//if ($event["course_id"] == "") {
          // 	array_push($value_array, '0');
					//} else {
					//	array_push($value_array, $event["course_id"]);
					//}
					$fee_list = get_fee_list($db, $param_array, $value_array);
					if ($event["monthly_fee_flag"]) {
						$param_array = array();
						$value_array = array();
						array_push($param_array, "tbl_monthly_fee.member_no = ?");
						array_push($value_array, $event["member_no"]);
						array_push($param_array, "tbl_monthly_fee.lesson_id = ?");
						array_push($value_array, $event["lesson_id"]);
						array_push($param_array, "tbl_monthly_fee.subject_id = ?");
						array_push($value_array, $event["subject_id"]);
						array_push($param_array, "tbl_monthly_fee.course_id = ?");
						array_push($value_array, $event["course_id"]);
						$fee_list = get_m_fee_list($db, $param_array, $value_array);
					}
					if (count($fee_list) == 1) {
					// 1件の場合
					//if (count($fee_list) > 0) {
					// 20160423変更
					// 生徒NO、教室、科目、コース、先生が主キーのため1件だけ取得できる
					// 授業料の設定がある場合
						//foreach ($fee_list as $fee) {
						//	if ($fee["fee"] == 0) {
						{
						$param_array = array("tbl_others.member_no=?", "tbl_others.year=?", "tbl_others.month=?", "tbl_others.charge=?");
						$value_array = array($event["member_no"], $year, $month, 1);
						$order_array = array("tbl_others.year, tbl_others.month, tbl_others.others_no");
						$others_list = get_others_list($db, $param_array, $value_array, $order_array);
						}
						if ($fee_list[0]["fee"] == 0 && count($others_list) == 0) {
							// 20150926変更
							// 旧面談の場合、寛香さんの場合、無料体験の場合は、0円でもOK
							// 無料体験だけして入塾しない人もいるので、無料体験の場合もOK
							$tmp_cal_evt_summary = str_replace(array(" "), array(""), $event["cal_evt_summary"]);
							if ($event["member_no"]) {
								$stmt = $db->query("SELECT fee_free FROM tbl_member WHERE no={$event["member_no"]}");
								$fee_free = ($stmt->fetch(PDO::FETCH_NUM))[0];
							} else {
								$fee_free = false;
							}
							if ((strpos($tmp_cal_evt_summary, "旧面談") === FALSE)
								&& (strpos($tmp_cal_evt_summary, "Hiroka") === FALSE)
								&& (strpos($tmp_cal_evt_summary, "弓削寛香") === FALSE)
								&& (!$fee_free)
								&& ($event["trial_flag"] == "0")) {
								$error_event["message"] = "授業料の未登録エラー";
								$errArray[] = $error_event;
				    	}
						}
						if ($error_event["message"] == "") {
							if (!$event["monthly_fee_flag"] && $event["course_id"] == "3" && $fee_list[0]["family_minus_price"] == 0) {
	               	$error_event["message"] = "ファミリー１人欠席料金の未登録エラー";
									$errArray[] = $error_event;
							}
						}
          } else if (count($fee_list) > 1) {
					// 2件以上の場合
						$error_event["message"] = "授業料の重複エラー";
						$errArray[] = $error_event;
						//}
					} else {
				// 授業料の設定がない場合

/*
						// 料金が未登録の場合は、tbl_feeに登録する
						$add_fee_flag = true;
						if ($event_param[1] == "" || $event_param[15] == "" || $event_param[17] == "") {
						// member_no、lesson_id、type_idがない場合、登録しない
	          	$add_fee_flag = false;
						} else {
							if ($event_param[15] == "1" && $event_param[16] == "0") {
							// 塾で科目なしの場合、料金は登録しない（入塾前の無料面談の場合も含めて登録しない）
	             	$add_fee_flag = false;
							} else if ($event_param[15] == "4" && $event_param[16] == "0") {
							// 習い事で科目なしの場合、料金は登録しない
	             	$add_fee_flag = false;
							}
						}
	          if ($add_fee_flag == true) {
*/
						// 20160331 「（仮）」が入っていても授業料は登録するようにする
						//if ($error_event["message"] == "") {

						if ($error_event["message"] == "" || 
								$error_event["message"] == NOT_CONFIRMED_ERR_MSG1 ||
								$error_event["message"] == NOT_CONFIRMED_ERR_MSG2 ||
								$error_event["message"] == NOT_CONFIRMED_ERR_MSG3
								) {
						// ここまでに入力エラーがなければ、授業料を新規登録する

							// 20151023 面談と三者面談１と無料体験の場合科目がないため、授業料を登録しない
							//if ($event["interview_flag"] != "1" && $event["interview_flag"] != "3") {
							if ($event["interview_flag"] != "1" && $event["interview_flag"] != "3" && $event["trial_flag"] != "1") {
								if ($event["monthly_fee_flag"]) {
									$result = add_m_fee($db, $event["member_no"], $event["lesson_id"], $event["subject_id"], $event["course_id"]);
								} else {
									if ($event["teacher_id"])
										$result = add_fee($db, $member_list0[$event["member_no"]], $event["lesson_id"], $event["subject_id"], $event["course_id"], $event["teacher_id"], $year, $month);
								}
								if ($result) {
/* 授業料0円チェックは清算時に行う
								// 20150827 授業料を新規登録する場合、
								// 夏期講習、春期講習、冬期講習は、自動で授業料を登録するので、授業料未登録エラーを表示しない
								// 夏期講習、春期講習、冬期講習以外は、初期値0円を登録するので、授業料未登録エラーを表示する
									if ($event["course_id"] != "4" && $event["course_id"] != "5" && $event["course_id"] != "6") {
										// 20150926変更
										// 旧面談の場合、寛香さんの場合、無料体験の場合は、0円でもOK
										// 無料体験だけして入塾しない人もいるので、無料体験の場合もOK
										$tmp_cal_evt_summary = str_replace(array(" "), array(""), $event["cal_evt_summary"]);
										$stmt = $db->query("SELECT fee_free FROM tbl_member WHERE no={$event["member_no"]}");
										$fee_free = ($stmt->fetch(PDO::FETCH_NUM))[0];
										if ((strpos($tmp_cal_evt_summary, "旧面談") === FALSE)
											&& (strpos($tmp_cal_evt_summary, "Hiroka") === FALSE)
											&& (strpos($tmp_cal_evt_summary, "弓削寛香") === FALSE)
											&& (!$fee_free)
											&& ($event["trial_flag"] == "0")) {
											$error_event["message"] = "授業料の未登録エラー";
											$errArray[] = $error_event;
										}
									}
*/
								} else {
									$errArray[] = $error_event;
								}
							}

						}
					}
					}

					$error_event = null;
		}

	if (!$tid) {
		
		$sql = "SELECT * FROM tbl_event_staff WHERE event_year = ? AND event_month = ?";
		$stmt = $db->prepare($sql);
		$stmt->execute(array($year, $month));
		$event_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$before_event = array("staff_no"=>"","start_timestamp"=>"","end_timestamp"=>"");
		foreach ($event_list as $event) {

			// イベントにエラーがあったとき表示する内容を、あらかじめ用意しておく
			$error_event = array("date"				=>date("Y-m-d", $event["event_start_timestamp"]),
													 "start_time"	=>date("H:i", $event["event_start_timestamp"]),
													 "end_time"		=>date("H:i", $event["event_end_timestamp"]),
													 "summary"		=>$event["cal_evt_summary"],
													 "calender_summary"=>$event["cal_summary"],
													 "message"		=>"");

			$event["cal_evt_summary"] = str_replace(array("　", "（", "）", "：", "︰"), array(" ", "(", ")", ":", ":"), trim($event["cal_evt_summary"]));
			$event["cal_evt_summary"] = mb_convert_kana($event["cal_evt_summary"], "n", "utf-8");

			if (is_null($event["event_start_timestamp"]) === true) {
				$error_event["message"] = "開始時刻の取得エラー";
				$errArray[] = $error_event;
			} else if (is_null($event["event_end_timestamp"]) === true) {
				$error_event["message"] = "終了時刻の取得エラー";
				$errArray[] = $error_event;
			}

			// 「(仮)」と入力されている場合、エラーとする。
			if (strpos($event["cal_evt_summary"], "(仮)") !== FALSE) {
				$error_event["message"] = NOT_CONFIRMED_ERR_MSG1;
				$errArray[] = $error_event;
			}

			// 「?」「？」と入力されている場合、エラーとする。
			if (strpos($event["cal_evt_summary"], "？") !== FALSE || strpos($event["cal_evt_summary"], "?") !== FALSE) {
				$error_event["message"] = "「?」入力エラー";
				$errArray[] = $error_event;
			}

			// 予定の重複がある場合
			if ($event["staff_no"] && $event["staff_no"] == $before_event["staff_no"] && $event["event_start_timestamp"] == $before_event["event_start_timestamp"] && $event["event_end_timestamp"] == $before_event["event_end_timestamp"]) {
						$error_event["message"] = "予定の重複エラー";
						$errArray[] = $error_event;
			}
			
			if (!$event["staff_no"]) {
				$error_event["message"] = "氏名未登録エラー";
				$errArray[] = $error_event;
			}
			
			$before_event = $event;
		}
	}

} // result終わり

// ****** メイン処理ここまで ******

// 20160423引数に先生を追加
function add_fee(&$db, $member, $lesson_id, $subject_id, $course_id, $teacher_id, $year, $month) {
	global $error_event;

	$member_no = $member['no'];
	
	$fee_array = array();
	$fee_array["lesson_id"] = $lesson_id;
	$fee_array["subject_id"] = $subject_id;
	// type_idの代わりにcourse_idを使用するように変更
	//$fee_array["type_id"] = $type_id;
	$fee_array["course_id"] = $course_id;
	$fee_array["teacher_id"] = $teacher_id;

	// 2015/07/15 6月月謝計算でマスタテーブルに金額が登録できた
	// 新規登録の生徒と区別できるように、料金0円とファミリー一人欠席時引く金額に0円を登録する。
	$fee_array["fee"] = 0;
	$fee_array["family_minus_price"] = 0;

	// 20150825 夏期講習
	if ($lesson_id == "1" && $course_id == "4") {
	// 塾でグループ
		$fee_array["fee"] = "2000";
	}

	// 20151229 冬期講習
	if ($lesson_id == "1" && $course_id == "5") {
	// 塾でグループ
		$fee_array["fee"] = "2000";
	}

	// 20151229 春期講習
	if ($lesson_id == "1" && $course_id == "6") {
	// 塾でグループ
		$fee_array["fee"] = "2000";
	}


/*
	// 2015/05/19 HPを参考に料金を仮登録する
	$fee_array["fee"] = "0";
	if ($subject_id == "7") {
	// 面談
		$fee_array["fee"] = "0";
	}	else if ($lesson_id == "1" && $type_id == "1") {
	// 塾でマンツーマン（学年により受験コースにより料金が変わってくるので、修正が必要）
		$fee_array["fee"] = "4000";
	} else if ($lesson_id == "1" && $type_id == "2") {
	// 塾でグループ
		$fee_array["fee"] = "2500";
	} else if ($lesson_id == "1" && $type_id == "3") {
	// 塾でファミリー
		$fee_array["fee"] = "6000";
	} else if ($lesson_id == "2" && $type_id == "1") {
	// 英会話でマンツーマン
		$fee_array["fee"] = "4000";
	} else 	if ($lesson_id == "2" && $type_id == "2") {
	// 英会話でグループ
		$fee_array["fee"] = "2500";
	} else if ($lesson_id == "2" && $type_id == "3") {
	// 英会話でファミリー
		$fee_array["fee"] = "6000";
	} else if ($lesson_id == "3" && $type_id == "1") {
	// ピアノでマンツーマン（プロの場合があるので修正が必要）
		$fee_array["fee"] = "3500";
	} else if ($lesson_id == "4" && $type_id == "1") {
	// 習い事でマンツーマン
		$fee_array["fee"] = "4000";
	}

	//2015/06/18 タイプがファミリーで、一人が休みの時ひく金額を設定
	$fee_array["family_minus_price"] = 0;
	if ($type_id == "3") {
   	$fee_array["family_minus_price"] = 1000;
	}
*/

	$ret = get_default_fee($db, $member, $year, $month, $lesson_id, $course_id);
	if (!is_numeric($ret)) {
		$error_event["message"] = "{$member['name']}: $ret";
		$ret = 0;
	}
	
	$fee_array["fee"] = $ret;
	$fee_array["temp_flag"] = 1;

	$result = insert_fee($db, $member_no, $fee_array);
	if ($result == true) {
		return true;
	} else {
		$error_event["message"] = "授業料の新規登録エラー";
		return false;
	}
}

function add_m_fee(&$db, $member_no, $lesson_id, $subject_id, $course_id) {
	global $error_event;

	$fee_array = array();
	$fee_array["lesson_id"] = $lesson_id;
	$fee_array["subject_id"] = $subject_id;
	$fee_array["course_id"] = $course_id;

	// 新規登録の生徒と区別できるように、料金0円とファミリー一人欠席時引く金額に0円を登録する。
	$fee_array["fee"] = 0;
	$fee_array["minus_price"] = 0;

	$result = insert_m_fee($db, $member_no, $fee_array);
	if ($result == true) {
		return true;
	} else {
		$error_event["message"] = "授業料の新規登録エラー";
		return false;
	}
}

// 20160423 先生を追加に伴う暫定対応
function update_teacher_id(&$db, $tmp_member_no, $tmp_lesson_id, $tmp_subject_id, $tmp_course_id, $tmp_teacher_id) {
try{
		$sql = "UPDATE tbl_fee SET teacher_id = ?, update_timestamp = now()".
					" WHERE member_no = ? AND lesson_id = ? AND subject_id = ? AND course_id = ?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $teacher_id);
		$stmt->bindParam(2, $member_no);
		$stmt->bindParam(3, $lesson_id);
		$stmt->bindParam(4, $subject_id);
		$stmt->bindParam(5, $course_id);
		$teacher_id = $tmp_teacher_id;
		$member_no = $tmp_member_no;
		$lesson_id = $tmp_lesson_id;
		$subject_id = $tmp_subject_id;
		$course_id = $tmp_course_id;
		$stmt->execute();
	}catch (PDOException $e){
		return false;
	}
	return true;
}

?>