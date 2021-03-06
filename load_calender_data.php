<?php
//echo "Program Start.\n";

ini_set( 'display_errors', 0 );
$err_flag = false ;
$errArray = array();

$request_year = $_POST['year'];
$request_year = str_replace("'","",$request_year);
$request_year = str_replace('"',"",$request_year);

$request_month = $_POST['month'];
$request_month = str_replace("'","",$request_month);
$request_month = str_replace('"',"",$request_month);

$request_user_id = $_POST['user_id'];
$request_user_id = str_replace("'","",$request_user_id);
$request_user_id = str_replace('"',"",$request_user_id);

$request_replace = $_POST['replace'];
$request_replace = str_replace("'","",$request_replace);
$request_replace = str_replace('"',"",$request_replace);

require_once "./const/const.inc";
require_once "./func.inc";
require_once("./const/login_func.inc");
require_once("./const/token.php");
ini_set('include_path', CLIENT_LIBRALY_PATH);
//require_once "Google/autoload.php";
set_time_limit(60);
//define(API_TOKEN, '7511a32c7b6fd3d085f7c6cbe66049e7');

define('CONST_ALTERNATE','振替:');
define('CONST_ABSENT','休み:');
define('CONST_ABSENT1','休み1:');
define('CONST_ABSENT2','休み2:');
define('CONST_ABSENTOFF','休講');
define('CONST_ABSENTLATE','当日');
define('CONST_COLON',':');
define('CONST_INTERVIEW1',':三者面談1');
define('CONST_INTERVIEW2',':三者面談2');
define('CONST_INTERVIEW3',':面談');
define('CONST_TRIAL',':無料体験');
define('CONST_SENSEI',' 先生');
define('CONST_SAMA',' 様');
define('CONST_SAN',' さん');
define('CONST_FAMILY','ファミリー(');
define('CONST_GROUP','グループ(');
define('CONST_CLOSING',')');
define('CONST_TRYSTUDENT','体験生徒');
define('CONST_SS','：演習');
define('CONST_SEASONSS','：季節講習演習');
define('CONST_SEASON','：季節講習');
define('CONST_NOTDEFINED','不定科目');

	// for english lessons.

define('CONST_ABSENT_ENG','Absent:');
define('CONST_ABSENT1_ENG','Absent1:');
define('CONST_ABSENT2_ENG','Absent2:');
define('CONST_ABSENTOFF_ENG','No Class');
define('CONST_ABSENTLATE_ENG','Today');
define('CONST_ALTERNATE_ENG','make-up');

$teacher_list = get_teacher_list($db);

$staff_list = get_staff_list($db);

$member_list = get_member_list($db,null,null,null,4);

$lesson_list = get_lesson_list($db);

$subject_list = get_subject_list($db);

if (!$request_year){
	$err_flag = true;
	$message = 'Syntax error: request_year is missing.';
	array_push($errArray,$message);
	goto exit_label;
}

if ($request_year < 2020 ){
	$err_flag = true;
	$message = 'Error: request_year is not correct.';
	array_push($errArray,$message);
	goto exit_label;
}
$request_year = (int)$request_year;

if (!$request_month){
	$err_flag = true;
	$message = 'Syntax error: request_month is missing.';
	array_push($errArray,$message);
	goto exit_label;
}
if ($request_month < 1 || $request_month > 12){
	$err_flag = true;
	$message = 'Error: request_month is not correct.';
	array_push($errArray,$message);
	goto exit_label;
}
$request_month = (int)$request_month;

$request_startdate = $request_year.'-'.$request_month.'-'.'01'; 

$endtimestamp = mktime(0,0,0,$request_month + 1,0,$request_year);
$enddate = getdate($endtimestamp);
$request_enddate = $request_year.'-'.$request_month.'-'.$enddate['mday'];

if (!$request_user_id){
	$request_user_id = 0;
} else {
	$request_user_id = (int) $request_user_id;
}

mb_regex_encoding("UTF-8");
$now = date('Y-m-d H:i:s');
$request_year_str = (string)$request_year;
$request_month_str = (string)$request_month;

foreach ($teacher_list as $teacher) {
	foreach ($staff_list as $staff) {
		if ($staff['name'] == $teacher['name']) {
			$teacher_and_staff_list[$teacher['name']] = array('teacher'=>$teacher, 'staff'=>$staff);
		}
	}
}
	
try{
	if ($request_user_id > 0) {
				// 当該月の当該user_idのデータをtbl_eventから削除する

		$request_member_no_str = (string)$request_user_id;
				// adjusting member_no to 6 digits.
		if (strlen($request_member_no_str) === 1){
			$request_member_no_str = '00000'.$request_member_no_str;
		} else if (strlen($request_member_no_str) === 2){
			$request_member_no_str = '0000'.$request_member_no_str;
		} else if (strlen($request_member_no_str) === 3){
			$request_member_no_str = '000'.$request_member_no_str;
		} else if (strlen($request_member_no_str) === 4){
			$request_member_no_str = '00'.$request_member_no_str;
		} else if (strlen($request_member_no_str) === 5){
			$request_member_no_str = '0'.$request_member_no_str;
		}
		if ($request_user_id < 100000 ){
			$sql = "DELETE FROM tbl_event where event_year = ? AND event_month = ? AND member_no = ? ";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(1, $request_year_str, PDO::PARAM_STR);
			$stmt->bindValue(2, $request_month_str, PDO::PARAM_STR);
			$stmt->bindValue(3, $request_member_no_str, PDO::PARAM_STR);
			$stmt->execute();
		} else if ($request_user_id == 200000 ){
			$sql = "DELETE FROM tbl_event_staff where event_year = ? AND event_month = ? ";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(1, $request_year_str, PDO::PARAM_STR);
			$stmt->bindValue(2, $request_month_str, PDO::PARAM_STR);
			$stmt->execute();
		} else if ($request_user_id > 200000 && $request_user_id < 300000 && !$teacher_and_staff_list[$staff_list[$request_user_id-200000]['name']]){
			$sql = "DELETE FROM tbl_event_staff where event_year = ? AND event_month = ? AND staff_no = ? ";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(1, $request_year_str, PDO::PARAM_STR);
			$stmt->bindValue(2, $request_month_str, PDO::PARAM_STR);
			$stmt->bindValue(3, $request_user_id-200000, PDO::PARAM_STR);
			$stmt->execute();
		} else {
			$err_flag = true;
			$message = 'Error: 生徒番号、事務員番号エラー';
			array_push($errArray,$message);
			goto exit_label;
		}
	} else if (!$request_replace) { 		// the parameter replace is not specified. Then if the data exist, notify an error.
		$sql = "SELECT COUNT(*) AS COUNT FROM tbl_event where event_year = ? AND event_month = ? ";
		$stmt = $db->prepare($sql);
		$stmt->bindValue(1, $request_year_str, PDO::PARAM_STR);
		$stmt->bindValue(2, $request_month_str, PDO::PARAM_STR);
		$stmt->execute();
		$already_exist = (int)$stmt->fetchColumn();
		if ($already_exist > 0) {
			$err_flag = true;
			$message = 'Error: target month has been already set up. use replace mode to replace them.';
			array_push($errArray,$message);
			goto exit_label;
		} 
	} else { 			// the parameter replace is specified. Then delete existing data.
			$sql = "DELETE FROM tbl_event where event_year = ? AND event_month = ? ";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(1, $request_year_str, PDO::PARAM_STR);
			$stmt->bindValue(2, $request_month_str, PDO::PARAM_STR);
			$stmt->execute();

					// the parameter replace is specified. Then delete existing data.
			$sql = "DELETE FROM tbl_event_staff where event_year = ? AND event_month = ? ";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(1, $request_year_str, PDO::PARAM_STR);
			$stmt->bindValue(2, $request_month_str, PDO::PARAM_STR);
			$stmt->execute();
	}


	$sql = "SELECT id, ".
	"repetition_id, ".
	"user_id, ".
	"teacher_id, ".
	"student_no, ".
	"ymd, ".
	"starttime, ".
	"endtime, ".
	"lecture_id, ".
	"work_id, ".
	"free, ".
	"cancel, ".
	"cancel_reason, ".
	"alternate, ".
	"altsched_id, ".
	"trial_id,".
	"absent1_num,".
	"absent2_num,".
	"trial_num,".
	"repeattimes,".
	"place_id,".
	"temporary,".
	"confirm,".
	"entrytime,".
	"updatetime,".
	"updateuser,".
	"comment,".
	"googlecal_id,".
	"googleevent_id,".
	"subject_expr,".
	"recurrence_id".
	" FROM tbl_schedule_onetime WHERE delflag!=1 AND (cancel IS NULL OR cancel!='c') AND ymd BETWEEN ? AND ? ";
	if ($request_user_id == 200000) {
		$sql .= " AND user_id> $request_user_id";
	} else if ($request_user_id > 200000) {
		$sql .= " AND user_id= $request_user_id";
	} else if ($request_user_id > 0) {
		$sql .= " AND user_id= $request_user_id";
	}
	$stmt = $dbh->prepare($sql);
	$stmt->bindValue(1, $request_startdate, PDO::PARAM_STR);
	$stmt->bindValue(2, $request_enddate, PDO::PARAM_STR);
	$stmt->execute();
        $schedule_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ( $schedule_array as $row ) {
if ($row[id]==29344) continue;	//2020/11/20 安田さん削除エラー対応
		$schedule_id	=	$row[id];
		$repetition_id	=	$row[repetition_id];
		$user_id 	=	(int)$row[user_id];
		$teacher_id	=	$row[teacher_id];
		$member_no	=	sprintf("%06d",$row[student_no]);
		$ymd		=	$row[ymd];
		$starttime	=	$row[starttime];
		$endtime	=	$row[endtime];
		$lecture_id	=	(int)$row[lecture_id];
		$work_id	=	$row[work_id];
		$free		=	$row[free];
		$cancel		=	$row[cancel];
		$cancel_reason	=	$row[cancel_reason];
		$alternate	=	$row[alternate];
		$altsched_id	=	(int)$row[altsched_id];
		$trial_id	=	$row[trial_id];
		$absent1_num	=	$row[absent1_num];
		$absent2_num	=	$row[absent2_num];
		$trial_num	=	$row[trial_num];
		$repeattimes	=	$row[repeattimes];
		$place_id	=	$row[place_id];
		$temporary	=	$row[temporary];
		$confirm	= $row[confirm];
		$comment	=	$row[comment];
		$entrytime	=	$row[entrytime];
		$updated_timestamp	=	$row[updatetime];
		$googlecal_id	=	$row[googlecal_id];
		$googleevent_id =	$row[googleevent_id];
		$subject_expr 	=	$row[subject_expr];
		$recurrence_id	=	$row[recurrence_id];
						// DB データの変換処理
		if ( !($trial_id > 0 && $confirm == 'f') )
		if ( $temporary > 0 && $temporary < 110 ) {
				// temporary. target data should be omitted.
			continue;
		}
		
		// 事務兼任講師の事務カレンダーは無視（講師カレンダーの事務作業を使用）
//		if ($user_id>200000 && $teacher_and_staff_list[$staff_list[$user_id-200000]['name']])
//			continue;
		if ($work_id==9 && $confirm != 'f')	continue;
		
		// 事務兼任講師の事務作業時に講師IDを事務員IDに変換
		if ($user_id>100000 && $user_id<200000) {
			if ($work_id==9) {
				$tid = $user_id-100000;
				$mid = $teacher_and_staff_list[$teacher_list[$tid]['name']]['staff']['no'];
				if (!$mid) {
					$err_flag = true;
					$message = "{$teacher_list[$tid]['name']}先生の事務作業がありますが、事務員登録されていません。";
					array_push($errArray,$message);
					continue;
				}
				$user_id = $mid+200000;
			} else if ($request_user_id > 200000)	{
				continue;
			}
		}

		sscanf($ymd,'%d-%d-%d',$event_year,$event_month,$event_day);	

		$event_start_timestring = $ymd.' '.$starttime;
		$event_start_timestamp = strtotime($event_start_timestring);
		sscanf($starttime,'%d:%d:%d',$event_start_hour,$event_start_minute,$event_start_second);	

		$event_end_timestring = $ymd.' '.$endtime;
		$event_end_timestamp = strtotime($event_end_timestring);
		sscanf($endtime,'%d:%d:%d',$event_end_hour,$event_end_minute,$event_end_second);	

		$event_diff_hours = ($event_end_timestamp - $event_start_timestamp) / (60*60);

		$evt_summary = '';			// Initialization.
		$lesson_id = 0;
		$course_id = 0;
		$subject_id = 0;
		$recurringEvent = '1';

		$lecture_list = get_lecture_vector($db,$lecture_id);
		$row_cnt = count($lecture_list) ;
		if ($row_cnt  > 0) {
			$lesson_id = (int)$lecture_list[lesson_id];
			$course_id = (int)$lecture_list[course_id];
			$subject_id = (int)$lecture_list[subject_id];
			if ($course_id == '2' ) {		// Group
				$evt_summary = CONST_GROUP ;
			} else if ($course_id == '3' ) {  // family
				$evt_summary = CONST_FAMILY ;
			}
		}
		
		if (!$lesson_id || !$course_id || !$subject_id) {
			if ($lms_subject_conv_tbl[$subject_expr]) {
				$lesson_id = 1; $course_id = 1; $subject_id = $lms_subject_conv_tbl[$subject_expr];
			}
		}
		
			// making $evt_summary from tbl_schedule_onetime.

							// 休み処理
		if ($cancel == 'a1') { 
			$absent_flag = '1'; 
			if ($lesson_id == 2 ) {		// English lesson.
				$evt_summary = $evt_summary.CONST_ABSENT1_ENG;
			} else {			// not English.
				$evt_summary = $evt_summary.CONST_ABSENT1;
			}
			$event_diff_hours = 0;
		} else if ($cancel == 'a2') { 
			$absent_flag = '2';
			if ($lesson_id == 2 ) {		// English lesson.
				$evt_summary = $evt_summary.CONST_ABSENT2_ENG;
			} else {			// not English.
				$evt_summary = $evt_summary.CONST_ABSENT2;
			}
		} else if ($cancel == 'a') { 
			$absent_flag = '1'; 
			if ($lesson_id == 2 ) {		// English lesson.
				$evt_summary = $evt_summary.CONST_ABSENT_ENG;
			} else {			// not English.
				$evt_summary = $evt_summary.CONST_ABSENT;
			}
			$event_diff_hours = 0;
		} else { $absent_flag = '0'; }
							// 振替処理
		if ($alternate ===' ' || $alternate ===NULL ){
			$alternate = ''; 
		}
		if ($alternate !=='' || $altsched_id > 0 ) { 
			$alternative_flag = '1' ;  
			$recurringEvent = '0';
			$event_diff_hours = 0;
			if ($lesson_id == 2 ) {		// English lesson.
				$evt_summary = $evt_summary.CONST_ALTERNATE_ENG;
			} else {			// not English.
				$evt_summary = $evt_summary.CONST_ALTERNATE;
			}
		} else {
			$alternative_flag = '';
		} 

		if ($user_id > 200000 ) { // staff
			
			$sql = "SELECT name FROM tbl_staff where no = ?";
			$stmt = $db->prepare($sql);
			$staff_no = $user_id - 200000;
			$stmt->bindValue(1,$staff_no, PDO::PARAM_INT);
			$stmt->execute();
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
			$staff_cal_name = $result['name'];
			$evt_summary = $evt_summary.$staff_cal_name;
			$evt_summary = $evt_summary.CONST_SAN;

		} else if ($user_id > 100000 ) { // teacher
			$member_cal_name = ' ';
			foreach ($teacher_list as $teacher) {
				if ($teacher['no'] == $user_id - 100000){
					$evt_summary = $evt_summary.$teacher['name'];
					$evt_summary = $evt_summary.CONST_SENSEI;
				}
			}
		} else if ($user_id > 1 ) { // student
			$member_cal_name = ' ';
			foreach ($member_list as $member) {
				if ($member['no'] == $user_id ){
					$member_cal_name = $member['name'];
					$grade = $member['grade'];
					$evt_summary = $evt_summary.$member_cal_name;
					$evt_summary = $evt_summary.CONST_SAMA;
					break;
				}
			}
			if ($member_cal_name == ' ') {
				$err_flag = true;
				$message = "user_id=$user_id 未登録エラー";
				array_push($errArray,$message);
				continue;
			}			
		} else if ($user_id == 1 ) { // try student
			if ($lesson_id == 1) {
				$err_flag = true;
				$message = "体験生徒 未登録エラー";
				array_push($errArray,$message);
				continue;
			} else {
				$trial_id = '1'; 
//			$grade = $trial_num;
				$grade = 0;
				$member_cal_name = CONST_TRYSTUDENT;
				$evt_summary = $evt_summary.$member_cal_name;
				$evt_summary = $evt_summary.CONST_SAMA;
			}
		} else if ($user_id < 0 ) { // student not defined.
			if ($comment !== ' '){
				$member_cal_name = $comment;
			} else {
				$member_cal_name = CONST_TRYSTUDENT;
			}
			$evt_summary = $evt_summary.$member_cal_name;
			$evt_summary = $evt_summary.CONST_SAMA;
			if ($trial_num > 0 ) {
				$trial_id = '1'; 
				$grade = $trial_num;
			}
		}
							// 面談を文字列にする処理
		$interview_flag = '';   
		if ($work_id == 1){
			$interview_flag = '1';   
			$evt_summary = $evt_summary.CONST_INTERVIEW1;
		} else if ($work_id == 2){
			$interview_flag = '2';   
			$evt_summary = $evt_summary.CONST_INTERVIEW2;
		} else if ($work_id == 3){
			$interview_flag = '3';   
			$evt_summary = $evt_summary.CONST_INTERVIEW3;
		}

		if ($course_id == 2 || $course_id == 3 ) {		// Group or Family
							// 体験を文字列にする処理
			if ($trial_id === '0' || $trial_id === ' ' || $trial_id =='' || $trial_id == NULL ){
				$trial_flag = '0'; 
			} else {
				$trial_flag = '1'; 
				$evt_summary = $evt_summary.CONST_COLON;
				$evt_summary = $evt_summary.CONST_TRIAL;
			}  
			if ($cancel_reason == CONST_ABSENTLATE ) { 
				$evt_summary = $evt_summary.CONST_COLON;
				if ($lesson_id == 2 ) {		// English lesson.
					$evt_summary = $evt_summary.CONST_ABSENTLATE_ENG;
				} else {			// not English.
					$evt_summary = $evt_summary.CONST_ABSENTLATE;
				}
			} else if ($cancel_reason == CONST_ABSENTOFF ) { 
				$evt_summary = $evt_summary.CONST_COLON;
				if ($lesson_id == 2 ) {		// English lesson.
					$evt_summary = $evt_summary.CONST_ABSENTOFF_ENG;
				} else {			// not English.
					$evt_summary = $evt_summary.CONST_ABSENTOFF;
				}
			} 
			$evt_summary = $evt_summary.CONST_CLOSING ;
		}

		if ($subject_id > 0 ) {		// setting subject name 
			$evt_summary = $evt_summary.CONST_COLON ;
//			$evt_summary = $evt_summary.$subject_list[$subject_id];
			$evt_summary = $evt_summary.$subject_expr;
		}

		if ( $user_id < 100000 ) {			// setting teacher name 
			$evt_summary = $evt_summary.CONST_COLON ;
			foreach ($teacher_list as $teacher) {
				if ($teacher['no'] == $teacher_id - 100000){
					$evt_summary = $evt_summary.$teacher['name'];
					$evt_summary = $evt_summary.CONST_SENSEI;
				}
			}
		}
		if ($course_id !== 2 && $course_id !== 3 ) {		// Neither Group nor Family
							// 体験を文字列にする処理
			if ($trial_id === '0' || $trial_id === ' ' || $trial_id ==='' || $trial_id===NULL){
				$trial_flag = '0'; 
			} else {
				$trial_flag = '1'; 
				$evt_summary = $evt_summary.CONST_COLON;
				$evt_summary = $evt_summary.CONST_TRIAL;
			}  
			if ($cancel_reason == CONST_ABSENTLATE ) { 
				$evt_summary = $evt_summary.CONST_COLON;
				if ($lesson_id == 2 ) {		// English lesson.
					$evt_summary = $evt_summary.CONST_ABSENTLATE_ENG;
				} else {			// not English.
					$evt_summary = $evt_summary.CONST_ABSENTLATE;
				}
			} else if ($cancel_reason == CONST_ABSENTOFF ) { 
				$evt_summary = $evt_summary.CONST_COLON;
				if ($lesson_id == 2 ) {		// English lesson.
					$evt_summary = $evt_summary.CONST_ABSENTOFF_ENG;
				} else {			// not English.
					$evt_summary = $evt_summary.CONST_ABSENTOFF;
				}
			} 
		} 

		if ($recurrence_id !== 0 ) { 
							// 繰り返しスケジュールを表す
			//$recurringEvent = "1";
		} 
		if ($work_id==5) {			// 演習の文字列をセット 
			$evt_summary = $evt_summary.CONST_SS ;
		}
		if ($work_id==10) {			// 季節講習の文字列をセット 
			$evt_summary = $evt_summary.CONST_SEASON ;
		}
		if ($work_id==11) {			// 季節講習演習の文字列をセット 
			$evt_summary = $evt_summary.CONST_SEASONSS ;
		}
		switch ($place_id) {			// tbl_schedule_onetime のplace_idからtbl_eventのplace_idをセット 
		case '1':		// Toyoda
			$new_place_id = 8 ;
			break;
		case '2':		// north exit 2nd floor.
			$new_place_id = 11 ;
			break;
		case '3':		// north exit 3rd floor.
			$new_place_id = 3 ;
			break;
		case '4':		// north exit 4th floor.
			$new_place_id = 4 ;
			break;
		case '5':		// south exit 
			$new_place_id = 2 ;
			break;
		case '6':		// kunitachi
			$new_place_id = 10 ;
			break;
		case '7':		// koyasu
			$new_place_id = 9 ;
			break;
		case '8':		// Dattocchi
			$new_place_id = 7 ;
			break;
		case '9':		// arrowore
			$new_place_id = 6 ;
			break;
		case '10':		// home
			$new_place_id = 0 ;
			break;
		case '11':		// Mitaka
			$new_place_id = 12 ;
			break;
		case '12':		// 吉祥寺
			$new_place_id = 13 ;
			break;
		case '13':		// 立川
			$new_place_id = 14 ;
			break;
		case '14':		// 荻窪校
			$new_place_id = 15 ;
			break;
		}

		if ($user_id > 200000 ) {	// スタッフの場合
			$staff_no = $user_id - 200000 ;
                        $sql = "INSERT INTO tbl_event_staff (".
                        "event_id, ".
                        "staff_no, ".
                        "staff_cal_name, ".
                        "event_year, ".
                        "event_month, ".
                        "event_day, ".
                        "event_start_timestamp, ".
                        "event_end_timestamp, ".
                        "event_diff_hours, ".
                        "absent_flag,".
                        "cal_id, ".
                        "cal_summary, ".
                        "cal_evt_summary, ".
                        "cal_evt_location, ".
                        "cal_evt_description, ".
                        "insert_datetime, ".
                        "update_datetime, ".
                        "place_floors".
                        " ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(1, $schedule_id, PDO::PARAM_INT);
			$stmt->bindValue(2, $staff_no, PDO::PARAM_INT);
			$stmt->bindValue(3, $staff_cal_name, PDO::PARAM_STR);  
			$stmt->bindValue(4, $event_year, PDO::PARAM_STR);  
			$stmt->bindValue(5, $event_month, PDO::PARAM_STR);  
			$stmt->bindValue(6, $event_day, PDO::PARAM_STR);  
			$stmt->bindValue(7, $event_start_timestamp, PDO::PARAM_STR);  
			$stmt->bindValue(8, $event_end_timestamp, PDO::PARAM_STR);  
			$stmt->bindValue(9, $event_diff_hours, PDO::PARAM_STR);  
			$stmt->bindValue(10, $absent_flag, PDO::PARAM_STR);  
			$stmt->bindValue(11, $googlecal_id, PDO::PARAM_STR);   
			$stmt->bindValue(12, $googlecal_summary, PDO::PARAM_STR);  // NULL値をセット 
			$stmt->bindValue(13, $evt_summary, PDO::PARAM_STR);   
			$stmt->bindValue(14, $googlecal_evt_location, PDO::PARAM_STR);  // NULL値をセット 
			$stmt->bindValue(15, $googlecal_evt_description, PDO::PARAM_STR);  // NULL値をセット 
			$stmt->bindValue(16, $now, PDO::PARAM_STR);   
			$stmt->bindValue(17, $now, PDO::PARAM_STR);   
			$stmt->bindValue(18, $new_place_id, PDO::PARAM_INT);  // setting place_floors. 
			$stmt->execute();
               
		 }  else {			// スタッフでない場合
			if ($user_id > 100000){ // 先生の場合
				$member_no = 0; // 生徒がいない先生のスケジュール
			} 
			if ($teacher_id > 100000){ // 先生の場合
				$teacher_id = $teacher_id - 100000 ;
			} 
			$member_kind = 'student';
			
			$evt_summary .= ":Subject?$subject_expr?";

			$repeat_flag = $repeattimes;  

                	$sql = "INSERT INTO tbl_event (".
                        " event_id, ".
                        " member_no, ".
                        " member_id, ".
                        " member_cal_name, ".
                        " member_kind, ".
                        " event_year, ".
                        " event_month, ".
                        " event_day, ".
                        " event_start_timestamp, ".
                        " event_start_hour, ".
                        " event_start_minute, ".
                        " event_end_timestamp, ".
                        " event_end_hour, ".
                        " event_end_minute, ".
                        " event_diff_hours, ".
                        " lesson_id, ".
                        " subject_id, ".
                        " course_id, ".
                        " teacher_id, ".
                        " place_floors, ".
                        " place_id, ".
                        " absent_flag, ".
                        " trial_flag, ".
                        " interview_flag, ".
                        " alternative_flag,".
                        " absent1_num, ".
                        " absent2_num, ".
                        " trial_num, ".
                        " repeat_flag, ".
                        " cal_id, ".
                        " cal_summary, ".
                        " cal_evt_summary, ".
                        " cal_attendance_data, ".
                        " cal_evt_location, ".
                        " cal_evt_description, ".
                        " insert_datetime, ".
                        " update_datetime, ".
                        " seikyu_year, ".
                        " seikyu_month,".
                        " recurringEvent, ".
                        " grade, ".
                        " monthly_fee_flag ".
                        " ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                        $stmt = $db->prepare($sql);
			$stmt->bindValue(1, $schedule_id, PDO::PARAM_STR);
			$stmt->bindValue(2, $member_no, PDO::PARAM_STR);
			$stmt->bindValue(3, $member_id, PDO::PARAM_STR); 	// NULL値をセット
			$stmt->bindValue(4, $member_cal_name, PDO::PARAM_STR);  
			$stmt->bindValue(5, $member_kind, PDO::PARAM_STR);
			$stmt->bindValue(6, $event_year, PDO::PARAM_STR);  
			$stmt->bindValue(7, $event_month, PDO::PARAM_STR);  
			$stmt->bindValue(8, $event_day, PDO::PARAM_STR);  
			$stmt->bindValue(9, $event_start_timestamp, PDO::PARAM_STR);  
			$stmt->bindValue(10, $event_start_hour, PDO::PARAM_STR);  
			$stmt->bindValue(11, $event_start_minute, PDO::PARAM_STR);  
			$stmt->bindValue(12, $event_end_timestamp, PDO::PARAM_STR);  
			$stmt->bindValue(13, $event_end_hour, PDO::PARAM_STR);  
			$stmt->bindValue(14, $event_end_minute, PDO::PARAM_STR);  
			$stmt->bindValue(15, $event_diff_hours, PDO::PARAM_STR);  
			$stmt->bindValue(16, $lesson_id, PDO::PARAM_STR);  
			$stmt->bindValue(17, $subject_id, PDO::PARAM_STR);  
			$stmt->bindValue(18, $course_id, PDO::PARAM_STR);  
			$stmt->bindValue(19, $teacher_id, PDO::PARAM_STR);
			$stmt->bindValue(20, $new_place_id, PDO::PARAM_INT); 	// setting place_floors column . 
			$stmt->bindValue(21, $new_place_id, PDO::PARAM_INT); 	// setting place_floors column . 
			$stmt->bindValue(22, $absent_flag, PDO::PARAM_STR);
			$stmt->bindValue(23, $trial_flag, PDO::PARAM_STR);  
			$stmt->bindValue(24, $interview_flag, PDO::PARAM_STR);  
			$stmt->bindValue(25, $alternative_flag, PDO::PARAM_STR);  
			$stmt->bindValue(26, $absent1_num, PDO::PARAM_INT);  
			$stmt->bindValue(27, $absent2_num, PDO::PARAM_INT);  
			$stmt->bindValue(28, $trial_num, PDO::PARAM_INT);  
			$stmt->bindValue(29, $repeat_flag, PDO::PARAM_INT);  
			$stmt->bindValue(30, $googlecal_id, PDO::PARAM_STR);   
			$stmt->bindValue(31, $googlecal_summary, PDO::PARAM_STR);  // NULL値をセット 
			$stmt->bindValue(32, $evt_summary, PDO::PARAM_STR);  
			$cal_attendance_data = $evt_summary ; 
			$stmt->bindValue(33, $cal_attendance_data, PDO::PARAM_STR);  // $evt_summary と同じ値をセット 
			$stmt->bindValue(34, $googlecal_evt_location, PDO::PARAM_STR);  // NULL値をセット 
			$stmt->bindValue(35, $googlecal_evt_description, PDO::PARAM_STR);  // NULL値をセット 
			$stmt->bindValue(36, $now, PDO::PARAM_STR);   
			$stmt->bindValue(37, $now, PDO::PARAM_STR);   
			$stmt->bindValue(38, $request_year, PDO::PARAM_STR);   
			$stmt->bindValue(39, $request_month, PDO::PARAM_STR);   
			$stmt->bindValue(40, $recurringEvent, PDO::PARAM_STR);   
			$stmt->bindValue(41, $grade, PDO::PARAM_STR);   
			$stmt->bindValue(42, $monthly_fee_flag, PDO::PARAM_STR);  // NULL値をセット 
			$stmt->execute();
		}	// スタッフでない場合
        }		// end of foreach

exit_label:
}catch (PDOException $e){
	print_r('insert_calender_event:failed:' . $e->getMessage());
	return false;
}

			// レクチャIDからレッスンID,コースID、科目IDを取得する
function get_lecture_vector(&$db,$lecture_id) {
        $sql = "SELECT lesson_id,course_id,subject_id FROM tbl_lecture WHERE lecture_id = ?";
        $stmt = $db->prepare($sql);
	$stmt->bindValue(1, $lecture_id, PDO::PARAM_INT);   
        $stmt->execute();
        $lecture_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $lecture_list = array();
        foreach ( $lecture_array as $row ) {
                $lecture_list = $row;
        }
        return $lecture_list;
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<style type="text/css">
<!--
 -->
</style>
<script type = "text/javascript">
<!--
-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
</head>
<body>
<div align="center">
<?php
if ($err_flag == true) {
?>

        <a href='menu.php'><h4><font color="red">カレンダーデータべースに取り込むことができませんでした。メニュー画面に戻る</font></h4></a>

<?php
        if (count($errArray) > 0) {
                foreach( $errArray as $error) {
?>
                        <font color="red"><?= $error ?></font><br><br>
<?php
                }
        }
} else {
?>
        <a href='menu.php'><h4>正常終了しました。メニュー画面に戻る</h4></a>
<?php
}
?>

</div>
</body>
</html>
