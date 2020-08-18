<?php
// This routine load specified year month schedule ( both m2m and self study ) from 
// tbl_season_schedule(m2m) and tbl_season_class_entry_data(m2m and selfstudy) into tbl_schedule_onetime.
// syntax: php upload_season_calender_batch.php STARTYEAR STARTMONTH ENDMONTH [replace]
// replace is the option to load data even if there exist target month's data in tbl_schedule_onetime.

ini_set( 'display_errors', 0 );
error_reporting(0);
$errArray = array();

$request_startyear = $_POST['startyear'];
if (!$request_startyear){
	$err_flag = true;
	$message = 'Syntax error: correct syntax is php upload_season_calender_batch.php STARTYEAR STARTMONTH ENDMONTH';
	array_push($errArray,$message);
	goto error_label;
}

$request_startmonth = $_POST['startmonth'];
if (!$request_startmonth){
	$err_flag = true;
	$message = 'Syntax error: correct syntax is php upload_season_calender_batch.php STARTYEAR STARTMONTH ENDMONTH';
	array_push($errArray,$message);
	goto error_label;
}

if (strlen($request_startmonth) === 1) {	// filling leading zero.
	$request_startmonth_str = '0'.$request_startmonth;
} else {
	$request_startmonth_str = $request_startmonth;
}

if ($request_startmonth_str == '12') {		// if December then next year.
	$plusoneyear = (int)($request_startyear) + 1;
	$request_endyear = $plusoneyear;
} else {
	$request_endyear = $request_startyear;
}

$request_endmonth = $_POST['endmonth'];
if (!$request_endmonth){
	$err_flag = true;
	$message = 'Syntax error: correct syntax is php upload_season_calender_batch.php STARTYEAR STARTMONTH ENDMONTH';
	array_push($errArray,$message);
	goto error_label;
}

if (strlen($request_endmonth) === 1) {	// filling leading zero.
	$request_endmonth_str = '0'.$request_endmonth;
} else {
	$request_endmonth_str = $request_endmonth;
}

$request_mode = $_POST['mode'] ;
if ($request_mode ){
	$request_mode = 'replace';
}

require_once "./const/const.inc";
require_once "./func.inc";
require_once("./const/login_func.inc");
require_once("./const/token.php");
ini_set('include_path', CLIENT_LIBRALY_PATH);
set_time_limit(60);

// ****** メイン処理ここから ******

mb_regex_encoding("UTF-8");
			// 科目リストの取得
$subject_list = get_subject_list($db);
			// コースリストの取得
$course_list = get_course_list($db);

$now = date('Y-m-d H:i:s');

$work_list = get_work_list($dbh);  // making work list.

define('ATTEND','出席');
define('ABSENT1','休み１');
define('ABSENT2','休み２');
define('ABSENT2TODAY','休み２当日');
define('TODAY','当日');
define('ALTERNATE','振替');

define('TEACHERATTEND',5);
define('SEASON',10);
define('SEASONSS',11);

$const_attend = ATTEND;		
$const_absent1 = ABSENT1;		
$const_absent2 = ABSENT2;		
$const_today = TODAY;		
$const_alternate = ALTERNATE;		

$target_work_id = SEASON;		// for season and weekend seminar m2m only. shortname is 'season'.
$target_work_id2 = SEASONSS;		// for season and weekend seminar selfstudy only. shortname is 'seasonss'.
$target_work_id3 = TEACHERATTEND;	// for season and weekend seminar attend only. shortname is 'ss'.

			// check whether schedule for the month is set.
$startofmonth = $request_startyear.'-'.$request_startmonth_str.'-01';
$endofmonth = $request_endyear.'-'.$request_endmonth_str.'-31';
try{
$sql = "SELECT COUNT(*) AS COUNT FROM tbl_schedule_onetime WHERE (work_id=? OR work_id=? OR work_id=?) AND delflag=0 AND ymd BETWEEN ? AND ?";
$stmt = $dbh->prepare($sql);
$stmt->bindValue(1, $target_work_id, PDO::PARAM_INT);
$stmt->bindValue(2, $target_work_id2, PDO::PARAM_INT);
$stmt->bindValue(3, $target_work_id3, PDO::PARAM_INT);
$stmt->bindValue(4, $startofmonth, PDO::PARAM_STR);
$stmt->bindValue(5, $endofmonth, PDO::PARAM_STR);
$stmt->execute();
$already_exist = (int)$stmt->fetchColumn();
if ($already_exist > 0) {			// Already exsit target year month data.
	if ($request_mode != 'replace') {	// replace option is not specified.
		$err_flag = true;
		$message = 'The schedule is already registerd. If you want to append the data, use mode=replace .';
		array_push($errArray,$message);
		goto error_label;
	}
				// check m2m data both tbl_schedule_onetime and tbl_season_schedule at first.

	// 1st cycle. Check season_class schedule on tbl_schedule_onetime that is not confired yet. 
	// if there is, logical delete the data.
	$sql = "SELECT * FROM tbl_schedule_onetime ";
	$sql .= " WHERE delflag=0 AND confirm='f' AND (work_id=? OR work_id=? OR work_id=?) AND ymd BETWEEN ? AND ? ORDER BY id";

	$stmt = $dbh->prepare($sql);
	$stmt->bindValue(1, $target_work_id, PDO::PARAM_INT);
	$stmt->bindValue(2, $target_work_id2, PDO::PARAM_INT);
	$stmt->bindValue(3, $target_work_id3, PDO::PARAM_INT);
	$stmt->bindValue(4, $startofmonth, PDO::PARAM_STR);
	$stmt->bindValue(5, $endofmonth, PDO::PARAM_STR);
	$stmt->execute();
	$confirmed_schedule_onetime_array = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$sql = "SELECT id FROM tbl_schedule_onetime ";
//	$sql .= " WHERE delflag=0 AND confirm!='f' AND (work_id=? OR work_id=? OR work_id=?) AND ymd BETWEEN ? AND ? ORDER BY id";
	$sql .= " WHERE delflag=0 AND (work_id=? OR work_id=? OR work_id=?) AND ymd BETWEEN ? AND ? ORDER BY id";

	$stmt = $dbh->prepare($sql);
	$stmt->bindValue(1, $target_work_id, PDO::PARAM_INT);
	$stmt->bindValue(2, $target_work_id2, PDO::PARAM_INT);
	$stmt->bindValue(3, $target_work_id3, PDO::PARAM_INT);
	$stmt->bindValue(4, $startofmonth, PDO::PARAM_STR);
	$stmt->bindValue(5, $endofmonth, PDO::PARAM_STR);
	$stmt->execute();
	$start_id = 0;
	$schedule_onetime_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ( $schedule_onetime_array as $schedule_onetime_row ) {
		$sql = "UPDATE tbl_schedule_onetime SET delflag=1,deletetime=?,updateuser=-1 ";
		$sql .= " WHERE id=?";
		$stmt = $dbh->prepare($sql);
		$stmt->bindValue(1, $now, PDO::PARAM_STR);
		$id = $schedule_onetime_row['id'];
		$stmt->bindValue(2, $id, PDO::PARAM_INT);
		$stmt->execute();

		if ($start_id === 0) { 		// initialization
			$start_id = $id; 
			$end_id = $id;
		} else if ($end_id < $id - 1 ){ // not sequential.
/*
			$result = lms_delete_notify($start_id,$end_id);
			if (!$result){		// if null then error.
				$err_flag = true;
				$message = 'lms_delete_notify error.';
				array_push($errArray,$message);
				goto error_label;
			}
*/
			$start_id = $id; 
			$end_id = $id;
		} else { 			// sequential.
			$end_id = $id;		// count up $end_id
		}
	}
	if ($start_id) {
/*
		$result = lms_delete_notify($start_id,$end_id);
		if (!$result){		// if null then error.
			$err_flag = true;
			$message = 'lms_delete_notify error.';
			array_push($errArray,$message);
			goto error_label;
		}
*/
	}

} else if ($already_exist == 0) {		// load new data.
/*
	if ($request_mode == 'replace') {	// replace option is specified but no data exist..
		$err_flag = true;
		$message = 'The schedule is not registerd yet. Remove replace option and try again.';
		array_push($errArray,$message);
		goto error_label;
	}
*/
}

	// 2nd cycle. If the date is already confirmed on tbl_schedule_onetime, skip the update. 
	// if the data is not confirm or not found , then insert the data .
	// delete previous schedule .

			// tbl_season_scheduleからman2manデータの取得
$startyearmonth_percent = $request_startyear.'/'.$request_startmonth_str.'%';
$endyearmonth_percent = $request_endyear.'/'.$request_endmonth_str.'%';

$start_id = 0;

			// retrieve tbl_season_class_entry_date ( this has no teacher data.)
$sql = "SELECT date,stime,etime,member_id FROM tbl_season_class_entry_date ";
$sql .= " WHERE date LIKE ? OR date LIKE ? ORDER BY date,member_id";
$stmt = $db->prepare($sql);
$stmt->bindValue(1, $startyearmonth_percent, PDO::PARAM_STR);
$stmt->bindValue(2, $endyearmonth_percent, PDO::PARAM_STR);

$stmt->execute();
$season_entry_date_array = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ( $season_entry_date_array as $season_entry_date_row ) {
	  			 // Initialization.
 	$temporary = 0; 
  	$trial_id = ""; 
  	$alternate = ""; 
  	$altsched_id = 0; 
  	$teacher_id = 0 ; 
  	$student_no = 0; 
  	$user_id = 0 ; 
	
  	$cancel = ""; 
  	$cancel_reason = ""; 
  	$lecture_id = 0 ; 
  	$lesson_id = 0 ; 
  	$course_id = 0 ; 
  	$subject_id = 0 ; 
  	$work_id = 0 ; 
  	$repetition_id = "" ; 
  	$absent1_num = 0; 
  	$absent2_num = 0; 
  	$trial_num = 0; 
	$start_timestamp = null;
	$end_timestamp = null;
  	$comment = ""; 

    $datewithslash = $season_entry_date_row['date'];
    $datewithhyphen = mb_ereg_replace('/','-',$datewithslash);
								// replace '/' with '-'

    $starttime = $season_entry_date_row['stime'];
	$timestamp_str = $datewithhyphen.' '.$starttime.':00';
	$dateObj = new DateTime($timestamp_str);
	$start_timestamp = $dateObj->getTimestamp();

    $endtime = $season_entry_date_row['etime'];
	$timestamp_str = $datewithhyphen.' '.$endtime.':00';
	$dateObj = new DateTime($timestamp_str);
	$end_timestamp = $dateObj->getTimestamp();

    $user_id = (int)$season_entry_date_row['member_id'] ;

/*
						// check the target schedule is registered on the tbl_schedule_onetime.
	$onetime_schedule_status = check_target_schedule($dbh,$datewithhyphen,$start_timestamp,$end_timestamp,$user_id);

	if ($onetime_schedule_status == 'new'){
		// insert.
	} else if ($onetime_schedule_status == 'confirmed'){
		$message = "Error:already confirmed:user_id=$user_id,date=$datewithhyphen";
		array_push($errArray,$message);
							// skip insert process.
		continue;
	}
*/

    $student_no = $user_id ;
    $student_id = (string)$student_no ;
	$student_id_len = strlen($student_id);
	if ($student_id_len == 1) {
                $student_id_complete = '00000'.$student_id;
        } else if ($student_id_len == 2) {
                $student_id_complete = '0000'.$student_id;
        } else if ($student_id_len == 3) {
                $student_id_complete = '000'.$student_id;
        } else if ($student_id_len == 4) {
                $student_id_complete = '00'.$student_id;
        } else if ($student_id_len == 5) {
                $student_id_complete = '0'.$student_id;
        }

	$sql = "SELECT place FROM tbl_season_class_entry_date ";
	$sql .= " WHERE date = ? AND member_id = ?";
	$stmt = $db->prepare($sql);
	$stmt->bindValue(1, $datewithslash, PDO::PARAM_STR);
	$stmt->bindValue(2, $student_id_complete, PDO::PARAM_STR);
	$stmt->execute();
	$season_class_entry_date_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ( $season_class_entry_date_array as $season_class_entry_date_row ) {
       		$place_char = $season_class_entry_date_row['place'] ;
		if (mb_strpos($place_char,'八王子北口校舎')!== FALSE) {
                      $place_id = 4;
		      break;
               } else if (mb_strpos($place_char,'国立校舎')!== FALSE) {
                      $place_id = 6;
		      break;
               } else if (mb_strpos($place_char,'豊田校舎')!== FALSE) {
                      $place_id = 1;
		      break;
               } 
	}

				// tbl_season_schedule has total schedule both m2m and selfstudy.
	$sql = "SELECT stime,etime,lnum,teacher_no,course_id,lesson_id,subject_id FROM tbl_season_schedule ";
	$sql .= " WHERE date = ? AND member_no = ?";
	$stmt = $db->prepare($sql);
	$stmt->bindValue(1, $datewithslash, PDO::PARAM_STR);
	$stmt->bindValue(2, $student_id_complete, PDO::PARAM_STR);
	$stmt->execute();
	$season_schedule_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
						//m2m schedule.
	foreach ( $season_schedule_array as $season_schedule_row ) {
				// initialization.
 		$temporary = 0; 
	  	$trial_id = ""; 
 	 	$alternate = ""; 
  		$altsched_id = 0; 
  		$cancel = ""; 
  		$cancel_reason = ""; 
  		$confirm = ""; 
  		$repetition_id = "" ; 
  		$absent1_num = 0; 
  		$absent2_num = 0; 
  		$trial_num = 0; 
  		$comment = ""; 

  		$work = 'season';
        $lesson_id = (int)$season_schedule_row['lesson_id'] ;
        $course_id = (int)$season_schedule_row['course_id'] ;
        $subject_id = (int)$season_schedule_row['subject_id'] ;
        $teacher_no = (int)$season_schedule_row['teacher_no'] ;
        $teacher_id = $teacher_no + 100000 ;

		$starttime = $season_schedule_row['stime'];
		$timestamp_str = $datewithslash.' '.$starttime.':00';
		$dateObj = new DateTime($timestamp_str);
		$start_timestamp = $dateObj->getTimestamp();

		$endtime = $season_schedule_row['etime'];
		$timestamp_str = $datewithslash.' '.$endtime.':00';
		$dateObj = new DateTime($timestamp_str);
		$end_timestamp = $dateObj->getTimestamp();

		$sql = "SELECT lecture_id FROM tbl_lecture WHERE lesson_id = ? AND course_id=? AND subject_id= ? ";
      		$stmt = $db->prepare($sql);
		$stmt->bindValue(1, $lesson_id, PDO::PARAM_INT);
		$stmt->bindValue(2, $course_id, PDO::PARAM_INT);
		$stmt->bindValue(3, $subject_id, PDO::PARAM_INT);
		$stmt->execute();
       	$result = $stmt->fetch(PDO::FETCH_ASSOC);
        $lecture_id = $result['lecture_id'];

		if ( is_null($lecture_id)){
			$lecture_id = 88;	// setting default value.
		}
				// 個別スケジュールへの挿入		
				// m2m のスケジュールをまず挿入する
		$result = insert_calender_event($dbh,
					$start_timestamp,
					$end_timestamp,
					$repetition_id,
					$user_id,
					$teacher_id,
					$student_no,
					$lecture_id,
					$work,
					$free,
					$cancel,
					$cancel_reason,
					$alternate,
					$altsched_id,
					$trial_id,
					$repeattimes,
					$place_id,
					$temporary,
					$comment,
					$recurrence_id,
					$absent1_num,
					$absent2_num,
					$trial_num,
					$subject_id);

		if ($start_id ===0){			// 挿入データの最初のid
			$sql = "SELECT MAX(id) FROM tbl_schedule_onetime where delflag=0";
			$stmt = $dbh->prepare($sql);
			$stmt->execute();
			$start_id = (int)$stmt->fetchColumn() ;
		}
						// m2mのデータ投入終了
	} 	
			// Initialization.
 	$temporary = 0; 
  	$trial_id = ""; 
  	$alternate = ""; 
  	$altsched_id = 0; 
  	$teacher_id = 0 ; 
  	$student_no = 0; 
  	$cancel = ""; 
  	$cancel_reason = ""; 
  	$confirm = ""; 
  	$work_id = 0 ; 
  	$repetition_id = "" ; 
  	$absent1_num = 0; 
  	$absent2_num = 0; 
  	$trial_num = 0; 
  	$comment = ""; 

        $starttime = $season_entry_date_row['stime'];
	$timestamp_str = $datewithslash.' '.$starttime.':00';
	$dateObj = new DateTime($timestamp_str);
	$startofday_ts = $dateObj->getTimestamp();

        $endtime = $season_entry_date_row['etime'];
	$timestamp_str = $datewithslash.' '.$endtime.':00';
	$dateObj = new DateTime($timestamp_str);
	$endofday_ts = $dateObj->getTimestamp();

	$attend_status = $season_entry_date_row['attend_status'];

//	$work = 'ss';
	$work = 'seasonss';
	$lesson_id = 1 ; 	// 塾
	$course_id = 9 ;	// weekend seminar (fixed value for temporary). 
	$subject_id = 0 ;
	
	$sql = "SELECT lecture_id FROM tbl_lecture WHERE lesson_id = ? AND course_id=? AND subject_id= ? ";
      	$stmt = $db->prepare($sql);
	$stmt->bindValue(1, $lesson_id, PDO::PARAM_INT);
	$stmt->bindValue(2, $course_id, PDO::PARAM_INT);
	$stmt->bindValue(3, $subject_id, PDO::PARAM_INT);
	$stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
       	$lecture_id = $result['lecture_id'];
					// 全体時間からm2mの時間を引いて生徒の演習時間を求める
	$status = insert_selfstudy_schedule($db,$dbh,$student_id_complete,$startofday_ts,$endofday_ts,$lecture_id,$place_id,$subject_id,$attend_status);

} 	// end of for each tbl_season_classentry_data.

			// 先生の演習時間入力
$sql = "SELECT no,date,times FROM tbl_season_class_teacher_entry1 ";
$sql .= " WHERE date LIKE ? OR date LIKE ? ORDER BY date,no";
$stmt = $db->prepare($sql);
$stmt->bindValue(1, $startyearmonth_percent, PDO::PARAM_STR);
$stmt->bindValue(2, $endyearmonth_percent, PDO::PARAM_STR);
$stmt->execute();
$season_teacherattend_array = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ( $season_teacherattend_array as $row ) {
	$times_string = $row['times'];
	$times_string_length = strlen($times_string) ;
        $datewithslash = $row['date'];
        $datewithhyphen = mb_ereg_replace('/','-',$datewithslash);
			// replace '/' with '-'
	$teacher_no = $row['no'];
	$attendstime = '';
	$offset = 0;

	while ($offset < $times_string_length) {
								// analyze times string.
		$crnt_time_string  = substr($times_string,$offset,5);	// format hh:mm
		if ($attendstime == '' ) {
			$attendstime = $crnt_time_string;			// format hh:mm 
		}
		$crnt_hour = (integer) substr($crnt_time_string,0,2);
		$crnt_minute = (integer)substr($crnt_time_string,3,2);
		if ($crnt_minute == 30 ) {
			$expect_next_hour = $crnt_hour + 1;
			$expect_next_minute = 0 ;
		} else if ($crnt_minute == 0) {
			$expect_next_hour = $crnt_hour ;
			$expect_next_minute = 30 ;
	 	}	
		$nextoffset = $offset + 6;		// increment 6 characters like 'hh:mm,'
		if ($nextoffset < $times_string_length ) {
			$next_time_string  = substr($times_string,$nextoffset,5);	// format hh:mm
			$next_hour = (integer)substr($next_time_string,0,2);
			$next_minute = (integer)substr($next_time_string,3,2);

			if ($expect_next_hour == $next_hour && $expect_next_minute == $next_minute ) {
				$offset = $offset + 6;		// increment the offset by six characters. 'hh:mm,'
				continue;	// time string is sequential.
			}
		} else {
				// reach the end of times string. do nothing.
		}

		$attendstime_str = $datewithslash.' '.$attendstime.':00';
		$dateObj = new DateTime($attendstime_str);
		$attendstime_ts = $dateObj->getTimestamp();

		$attendetime_str = $datewithslash.' '.$crnt_time_string.':00';
		$dateObj = new DateTime($attendetime_str);
		$attendetime_ts = $dateObj->getTimestamp();
		$attendetime_ts = strtotime('+30 minute',$attendetime_ts); // 30分単位の開始時間のため終了時間は+30分
	
															// 当該スケジュールが既に入力済かをチェックする
		$start_timestamp = $attendstime_ts;
		$end_timestamp = $attendetime_ts;
		$user_id = $teacher_no + 100000 ;
		$onetime_schedule_status = check_target_schedule($dbh,$datewithhyphen,$start_timestamp,$end_timestamp,$user_id);

		if ($onetime_schedule_status == 'new'){
			// insert.
		} else if ($onetime_schedule_status == 'confirmed'){
			$message = "Error: confirmed:user_id=$user_id,date=$datewithhyphen";
			array_push($errArray,$message);
			// skip insert process.
			continue;
		}

		$starttime_str = date("H:i:s",$start_timestamp);
		$endtime_str = date("H:i:s",$end_timestamp);

		$sql = "SELECT place_id FROM tbl_schedule_onetime WHERE ymd=? AND teacher_id=? AND starttime >=?  AND endtime <=? and delflag=0"; 
							// select from tbl_schedule_onetime.
		$stmt = $dbh->prepare($sql);
		$stmt->bindValue(1, $datewithhyphen, PDO::PARAM_STR);
		$stmt->bindValue(2, $user_id, PDO::PARAM_INT);
		$stmt->bindValue(3, $starttime_str, PDO::PARAM_STR);
		$stmt->bindValue(4, $endtime_str, PDO::PARAM_STR);
		$stmt->execute();
        	$place_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
        	foreach ( $place_array as $row ) {
               		 $place_id = $row["place_id"];
        	}
						// 先生の演習時間を求める
		$status = insert_teacherattend_schedule($db,$dbh,$teacher_no,$attendstime_ts,$attendetime_ts,$place_id);

		$attendstime = '' ;		// initialization.
		$offset = $offset + 6;		// increment the offset by six characters. 'hh:mm,'
	}	// end of for.

}

$sql = "SELECT MAX(id) FROM tbl_schedule_onetime where delflag=0 ";
$stmt = $dbh->prepare($sql);
$stmt->execute();
$end_id = (int)$stmt->fetchColumn();

$tmp_start_id = $start_id;
$tmp_end_id = $tmp_start_id;

					// notify insert to lms.
while ($tmp_end_id < $end_id){
	if ($end_id > $tmp_start_id + 100) {
		$tmp_end_id = $tmp_start_id + 100 ;
	} else {
		$tmp_end_id = $end_id;
	}
/*
	$status = lms_insert_notify($tmp_start_id,$tmp_end_id);
	if (!$status){		// if null then error.
		$err_flag = true;
		$message = 'lms_insert_notify error.';
		array_push($errArray,$message);
		goto error_label;
	}
*/
	$tmp_start_id = $tmp_end_id + 1;	// setting next start.
}


}catch (PDOException $e){
        print_r('exception: ' . $e->getMessage());
        return false;
}


error_label:
	if ($err_flag === true){
//		var_dump($message);
	}
//} // the end of main program. 

/************* Single Insert ****************/

function insert_calender_event(&$dbh,$start_timestamp,$end_timestamp,$repetition_id,$user_id,$teacher_id,$student_no,$lecture_id,$work,$free,$cancel,$cancel_reason,$alternate,$altsched_id,$trial_id,$repeattimes,$place_id,$temporary,$comment,$recurrence_id,$absent1_num,$absent2_num,$trial_num,$subject_id ) {

global $work_list;
global $subject_list;
global $now;
global $confirmed_schedule_onetime_array;

	$confirm = null;
	foreach ($confirmed_schedule_onetime_array as $key=>$record) {
		if (
			"{$record['ymd']} {$record['starttime']}" == date('Y-m-d H:i:s', $start_timestamp) &&
			"{$record['ymd']} {$record['endtime']}" == date('Y-m-d H:i:s', $end_timestamp) &&
			$record['teacher_id'] == $teacher_id &&
			$record['student_no'] == $student_no
			) {
				$confirm = 'f';
				unset($confirmed_schedule_onetime_array[$key]);
				break;
			}
	}

	$startymd = date('Y-m-d',$start_timestamp);
	$starttime = date('H:i:s',$start_timestamp);
	$endymd = date('Y-m-d',$end_timestamp);
	$endtime = date('H:i:s',$end_timestamp);
	if ($startymd != $endymd) {
					// 開始日と終了日が異なる 
		goto exit_label;
	}
	$ymd = $startymd;	
					// tbl_schedule_onetimeに挿入する項目の設定
	if ($recurrence_id !== "") {
		$repetition_id = -1; // 定期的スケジュールの識別子。暫定で-1とする
	}
					// converting work shortname into work_id
	foreach ($work_list as $workitem) {
		if (mb_strpos($workitem["shortname"], $work)!==false) {
                        $work_id = $workitem["id"];
                        break;  // for each
                }
        }  // end of for each.

	if ($subject_id) {
             $subject_expr = $subject_list[$subject_id];
        }

	if ($user_id==0) { goto exit_label;}
	$updateuser = -1;
try{
						// not Repeting
	$sql = "INSERT INTO tbl_schedule_onetime (".
	" repetition_id,".
	" user_id,".
	" teacher_id,".
	" student_no,".
	" ymd,".
	" starttime,".
	" endtime,".
	" lecture_id,".
	" subject_expr,".
	" work_id,".
	" free,".
	" cancel,".
	" cancel_reason, ".
	" alternate,".
	" altsched_id,".
	" trial_id,".
	" absent1_num,".
	" absent2_num,".
	" trial_num,".
	" repeattimes,".
	" place_id,".
	" temporary,".
	" entrytime,".
	" updateuser, ".
	" comment,".
	" recurrence_id, ".
	" confirm ".
	" ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
	$stmt = $dbh->prepare($sql);
	$stmt->bindValue(1, $repetition_id, PDO::PARAM_INT);
	$stmt->bindValue(2, $user_id, PDO::PARAM_INT);
	$stmt->bindValue(3, $teacher_id, PDO::PARAM_INT);
	$stmt->bindValue(4, $student_no, PDO::PARAM_INT);
	$stmt->bindValue(5, $ymd, PDO::PARAM_STR);
	$stmt->bindValue(6, $starttime, PDO::PARAM_STR);
	$stmt->bindValue(7, $endtime, PDO::PARAM_STR);
	$stmt->bindValue(8, $lecture_id, PDO::PARAM_INT);
	$stmt->bindValue(9, $subject_expr, PDO::PARAM_STR);
	$stmt->bindValue(10, $work_id, PDO::PARAM_INT);
	$stmt->bindValue(11, $free, PDO::PARAM_STR);
	$stmt->bindValue(12, $cancel, PDO::PARAM_STR);
	$stmt->bindValue(13, $cancel_reason, PDO::PARAM_STR);
	$stmt->bindValue(14, $alternate, PDO::PARAM_STR);
	$stmt->bindValue(15, $altsched_id, PDO::PARAM_STR);
	$stmt->bindValue(16, $trial_id, PDO::PARAM_STR);
	$stmt->bindValue(17, $absent1_num, PDO::PARAM_INT);
	$stmt->bindValue(18, $absent2_num, PDO::PARAM_INT);
	$stmt->bindValue(19, $trial_num, PDO::PARAM_INT);
	$stmt->bindValue(20, $repeattimes, PDO::PARAM_INT);
	$stmt->bindValue(21, $place_id, PDO::PARAM_INT);
	$stmt->bindValue(22, $temporary, PDO::PARAM_INT);
	$stmt->bindValue(23, $now, PDO::PARAM_STR);
	$stmt->bindValue(24, $updateuser, PDO::PARAM_INT);
	$stmt->bindValue(25, $comment, PDO::PARAM_STR);
	$stmt->bindValue(26, $recurrence_id, PDO::PARAM_STR);
	$stmt->bindValue(27, $confirm, PDO::PARAM_STR);
	$stmt->execute();

	return $status;
exit_label:
}catch (PDOException $e){
	$err_flag = true;
	print_r('insert_calender_event:failed: ' . $e->getMessage());
	return false;
}
return $event_no;
} // End:event_insert

// 作業名の一覧を取得
function get_work_list(&$dbh) {
        $sql = "SELECT * FROM tbl_work ";
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        $work_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $work_list = array();
        foreach ( $work_array as $row ) {
                $work_list[$row["id"]] = $row;
        }
        return $work_list;
}

/************* Selfstudy schedule for student Insert ****************/

function insert_selfstudy_schedule(&$db,&$dbh,$student_id_complete,$startofday_ts,$endofday_ts,$lecture_id,$place_id,$subject_id,$attend_status ) {
				// for a given season schedule(member_id,startofdayts,endofdayts), make up selfstudy schedule. 
global $work_list;
global $subject_list;
global $now;

$result = true;
$startymd = date('Y/m/d',$startofday_ts);
$endymd = date('Y/m/d',$endofday_ts); 
if ($startymd != $endymd) {
					// 開始日と終了日が異なる 
	goto exit_label;
}
$work = "seasonss" ; 		// 自習
			// converting work shortname into work_id
foreach ($work_list as $workitem) {
	if (mb_strpos($workitem["shortname"], $work)!==false) {
                $work_id = $workitem["id"];
                break;  // for each
        }
}  // end of for each.

if ($subject_id) {
        $subject_expr = $subject_list[$subject_id];
}

try{
					// 生徒のスケジュール作成
	$sql = "SELECT date,stime,etime,member_no,teacher_no,lesson_id,subject_id,course_id ";
	$sql.= " FROM tbl_season_schedule ";
	$sql.= " WHERE member_no=? AND date=? ";
	$sql.= " ORDER BY stime";
	$stmt = $db->prepare($sql);
	$stmt->bindValue(1, $student_id_complete, PDO::PARAM_STR);	// 6桁の数値を表す文字列。先頭０．
	$stmt->bindValue(2, $startymd, PDO::PARAM_STR);
	$stmt->execute();
	$season_schedule_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$reccnt = 0;
	$crnt_ts = $startofday_ts;
	$m2metime_ts = null;

	foreach ( $season_schedule_array as $row ) {

		$reccnt = $reccnt + 1;

        	$m2mstime = $row['stime'];
		$m2mstime_str = $row['date'].' '.$m2mstime.':00';
		$dateObj = new DateTime($m2mstime_str);
		$m2mstime_ts = $dateObj->getTimestamp();

        	$m2metime = $row['etime'] ;
		$m2metime_str = $row['date'].' '.$m2metime.':00';
		$dateObj = new DateTime($m2metime_str);
		$m2metime_ts = $dateObj->getTimestamp();

		if ($m2metime_ts === $endofday_ts && $m2mstime_ts === $crnt_ts ) {
				// 当日のスケジュールの終端に到達した。挿入するデータなし。
			break;
		} else if ($m2mstime_ts === $crnt_ts) {
				// m2mから始まる。演習は後。
			$crnt_ts = $m2metime_ts;
			continue;
		} else  if ($m2mstime_ts > $crnt_ts) { 		//次のman2manが始まるまでに自習時間がある
			$start_timestamp = $crnt_ts ;
			$end_timestamp = $m2mstime_ts ;
			$crnt_ts = $m2metime_ts;

	  			 // Initialization.
		 	$temporary = 0; 
  			$trial_id = ""; 
 		 	$alternate = ""; 
  			$altsched_id = 0; 
  			$teacher_id = 0 ; 
  			$student_no = (int)$student_id_complete; 
  			$user_id = (int)$student_id_complete; 
  			$cancel = ""; 
  			$cancel_reason = ""; 
  			$repetition_id = "" ; 
  			$absent1_num = 0; 
  			$absent2_num = 0; 
  			$trial_num = 0; 
  			$comment = ""; 
  			$confirm = ""; 

			switch ($attend_status) {
			case ABSENT1:
				$cancel = 'a1';
				break;
			case ABSENT2:
				$cancel = 'a2';
				break;
			case ABSENT2TODAY:
				$cancel = 'a2';
  				$cancel_reason = TODAY; 
				break;
			case ATTEND:
				$confirm = 'f';
				break;
			case ALTERNATE:
				$alternate = 'a';
				$altsched_id = -1;
				break;
			}
			$result = insert_calender_event($dbh,
					$start_timestamp,
					$end_timestamp,
					$repetition_id,
					$user_id,
					$teacher_id,
					$student_no,
					$lecture_id,
					$work,
					$free,
					$cancel,
					$cancel_reason,
					$alternate,
					$altsched_id,
					$trial_id,
					$repeattimes,
					$place_id,
					$temporary,
					$comment,
					$recurrence_id,
					$absent1_num,
					$absent2_num,
					$trial_num,
					$subject_id);
		}		// end of if
	}		// end of foreach.
			// no more man2man recod but not reach the end of day. Then insert selfstudy record.

	if ($m2metime_ts && $m2metime_ts < $endofday_ts) { 		//その日の終了時間まで自習時間がある
		$start_timestamp = $crnt_ts ;
		$end_timestamp = $endofday_ts ;
	  			 	// Initialization.
	 	$temporary = 0; 
  		$trial_id = ""; 
 	 	$alternate = ""; 
  		$altsched_id = 0; 
  		$teacher_id = 0 ; 
  		$student_no = (int)$student_id_complete; 
  		$user_id = (int)$student_id_complete; 
  		$cancel = ""; 
  		$cancel_reason = ""; 
  		$repetition_id = "" ; 
  		$absent1_num = 0; 
  		$absent2_num = 0; 
  		$trial_num = 0; 
  		$comment = ""; 
		switch ($attend_status) {
		case ABSENT1:
			$cancel = 'a1';
			break;
		case ABSENT2:
			$cancel = 'a2';
			break;
		case ABSENT2TODAY:
			$cancel = 'a2';
  			$cancel_reason = TODAY; 
			break;
		case ATTEND:
			$confirm = 'f';
			break;
		case ALTERNATE:
			$alternate = 'a';
			$altsched_id = -1;
			break;
		}

		$result = insert_calender_event($dbh,
				$start_timestamp,
				$end_timestamp,
				$repetition_id,
				$user_id,
				$teacher_id,
				$student_no,
				$lecture_id,
				$work,
				$free,
				$cancel,
				$cancel_reason,
				$alternate,
				$altsched_id,
				$trial_id,
				$repeattimes,
				$place_id,
				$temporary,
				$comment,
				$recurrence_id,
				$absent1_num,
				$absent2_num,
				$trial_num,
				$subject_id);
	}		// end of if

        return $result;
//var_dump($sql);
exit_label:
}catch (PDOException $e){
	print_r('insert_selfstudy_schedule:failed: ' . $e->getMessage());
	return false;
}
} // End:event_insert


function insert_teacherattend_schedule(&$db,&$dbh,$teacher_no,$attendstime_ts,$attendetime_ts,$place_id ) {
				// for a given season schedule(teacher_no,date), make up teacherattend schedule. 
global $work_list;
global $subject_list;
global $now;

$result = true;
$teacher_id = 100000 + $teacher_no;

			// converting work shortname into work_id
//$work = "ss" ; 		// 自習
$work = "seasonss" ; 		// 自習
foreach ($work_list as $workitem) {
	if (mb_strpos($workitem["shortname"], $work)!==false) {
                $work_id = $workitem["id"];
                break;  // for each
        }
}  // end of for each.

try{
							// 先生の立ち合いスケジュール作成
$startymd = date('Y/m/d',$attendstime_ts);
							// 講習予定を検索する。なければ立ち合いなし
$sql = "SELECT * FROM tbl_schedule_onetime WHERE teacher_id=? AND ymd=? AND delflag=0 AND work_id=10 ORDER BY starttime ";
$stmt = $dbh->prepare($sql);
$stmt->bindValue(1, $teacher_id, PDO::PARAM_INT);	 
$stmt->bindValue(2, $startymd, PDO::PARAM_STR);
$stmt->execute();
$teacherattend_schedule_array = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$teacherattend_schedule_array){ 	// not found.
		// no need to insert attend schedule.
		return true;
}
							// 当該先生のattend時間が既に作成済かどうかを調べる
$crnt_ts = $attendstime_ts;				// setting start point.

foreach ( $teacherattend_schedule_array as $row ) {

	$workstime = $row['starttime'];
	$worketime = $row['endtime'];

	$workstime_str = $row['ymd'].' '.$workstime;
	$dateObj = new DateTime($workstime_str);
	$workstime_ts = $dateObj->getTimestamp();

	$worketime_str = $row['ymd'].' '.$worketime;
	$dateObj = new DateTime($worketime_str);
	$worketime_ts = $dateObj->getTimestamp();

	if ($worketime_ts === $attendetime_ts && $workstime_ts === $crnt_ts ) {
				// 当日のスケジュールの終端に到達した。挿入するデータなし。
			break;
	} else if ($workstime_ts === $crnt_ts) {
				// m2mから始まる。演習は後。
			$crnt_ts = $worketime_ts;
			continue;
	} else  if ($workstime_ts > $crnt_ts) { 		//次のman2manが始まるまでに自習時間がある
		$start_timestamp = $crnt_ts ;
		if ($attendetime_ts > $workstime_ts) {
			$end_timestamp = $workstime_ts ;
		} else {
			$end_timestamp = $attendetime_ts ;	// attend time finish before man2man seminar start.
		}
		$crnt_ts = $worketime_ts;

	  			 // Initialization.
	 	$temporary = 0; 
  		$trial_id = ""; 
 	 	$alternate = ""; 
  		$altsched_id = 0; 
  		$student_no = 0; 
  		$user_id = (int)$teacher_id; 
  		$cancel = ""; 
  		$cancel_reason = ""; 
  		$repetition_id = "" ; 
  		$absent1_num = 0; 
  		$absent2_num = 0; 
  		$trial_num = 0; 
  		$comment = ""; 
  		$confirm = ""; 
  		$cancel = ""; 
  		$cancel_reason = ""; 

		$result = insert_calender_event($dbh,
				$start_timestamp,
				$end_timestamp,
				$repetition_id,
				$user_id,
				$teacher_id,
				$student_no,
				$lecture_id,
				$work,
				$free,
				$cancel,
				$cancel_reason,
				$alternate,
				$altsched_id,
				$trial_id,
				$repeattimes,
				$place_id,
				$temporary,
				$comment,
				$recurrence_id,
				$absent1_num,
				$absent2_num,
				$trial_num,
				$subject_id);
		}		// end of if
	}		// end of foreach.
			// no more man2man recod but not reach the end of day. Then insert selfstudy record.

	if ($worketime_ts < $attendetime_ts) { 		//その日の終了時間まで演習がある
		$start_timestamp = $crnt_ts ;
		$end_timestamp = $attendetime_ts ;

	  			 // Initialization.
	 	$temporary = 0; 
  		$trial_id = ""; 
 	 	$alternate = ""; 
  		$altsched_id = 0; 
  		$student_no = 0; 
  		$user_id = $teacher_id; 
  		$cancel = ""; 
  		$cancel_reason = ""; 
  		$repetition_id = "" ; 
  		$absent1_num = 0; 
  		$absent2_num = 0; 
  		$trial_num = 0; 
  		$comment = ""; 

		$result = insert_calender_event($dbh,
				$start_timestamp,
				$end_timestamp,
				$repetition_id,
				$user_id,
				$teacher_id,
				$student_no,
				$lecture_id,
				$work,
				$free,
				$cancel,
				$cancel_reason,
				$alternate,
				$altsched_id,
				$trial_id,
				$repeattimes,
				$place_id,
				$temporary,
				$comment,
				$recurrence_id,
				$absent1_num,
				$absent2_num,
				$trial_num,
				$subject_id);
	}		// end of if

        return $result;
teacherattend_exit:
}catch (PDOException $e){
	print_r('insert_teacherattendy_schedule:failed: ' . $e->getMessage());
	return false;
}
} // End:event_insert

function check_target_schedule(&$dbh,$datewithhyphen,$start_timestamp,$end_timestamp,$user_id){
			// This function check every season record on tbl_season_schedule.
			// If the schedule is already confirmed then skip the insert.
			// If the schedule is not confirmed, then delete previous schedule and insert the schedule on tbl_schedule_onetime.
			// if the schedule is not found , just insert the schedule to tbl_schedule_onetime.
			// return value: $onetimestatus 'new','confirmed','notconfirmed' 
	$starttime_str = date("H:i:s",$start_timestamp);
	$endtime_str = date("H:i:s",$end_timestamp);
try{
			// select from tbl_schedule_onetime.
	$sql = "SELECT COUNT(*) AS COUNT FROM tbl_schedule_onetime WHERE delflag=0 AND ymd=? AND starttime>=? AND endtime<=? AND user_id=? ";
	$stmt = $dbh->prepare($sql);
	$stmt->bindValue(1, $datewithhyphen, PDO::PARAM_STR);
	$stmt->bindValue(2, $starttime_str, PDO::PARAM_STR);
	$stmt->bindValue(3, $endtime_str, PDO::PARAM_STR);
	$stmt->bindValue(4, $user_id, PDO::PARAM_INT);
	$stmt->execute();
	$already_exist = (int)$stmt->fetchColumn();
	if ($already_exist == 0) {

			// if target data is notfound.
			$onetimestatus = 'new';
	} else {
			// if target data is found.
			// check if target data is confirmed.
		$sql = "SELECT COUNT(*) AS COUNT FROM tbl_schedule_onetime WHERE delflag=0 AND ymd=? AND starttime>=? AND endtime<=? AND user_id=? ";
		$sql .= "AND confirm='f'";
		$stmt = $dbh->prepare($sql);
		$stmt->bindValue(1, $datewithhyphen, PDO::PARAM_STR);
		$stmt->bindValue(2, $starttime_str, PDO::PARAM_STR);
		$stmt->bindValue(3, $endtime_str, PDO::PARAM_STR);
		$stmt->bindValue(4, $user_id, PDO::PARAM_INT);
		$stmt->execute();
		$confirmed_exist = (int)$stmt->fetchColumn();
		if ($confirmed_exist == 0 ){
			// if target data is notconfirmed.
			$onetimestatus = 'notconfirmed';
		} else {
			// if target data is confirmed.
			$onetimestatus = 'confirmed';
		}
			// if target data is notconfirmed.
	}

check_target_schedule_exit_label:
	return($onetimestatus);

}catch (PDOException $e){
	print_r('check_target_schedule:failed: ' . $e->getMessage());
	return false;
}
}

function lms_insert_notify($tmp_start_id,$tmp_end_id){
                // this function notify update of the schedule to lms.
        $result = NULL;         // initialization.
        $senddata = array(
                'start_id' => $tmp_start_id,
                'end_id' => $tmp_end_id
        );
        $query = http_build_query($senddata,'',"&");
        $platform = PLATFORM;
                                // http-post:
        if ($platform === 'staging' ){
                $url = 'https://staging.sakuraone.jp/import/schedules?'.$query;
        } else if ($platform === 'production' ){
                $url = 'https://sakuraone.jp/import/schedules?'.$query;
        }
        $header = array(
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: '.strlen($url),
                'Api-Token: 7511a32c7b6fd3d085f7c6cbe66049e7'
        );
        $options = array('http' => array(
                'method' => 'POST',
                'timeout' => 9600 ,
                'header' => implode("\r\n",$header)
                )
        );
        $ctx = stream_context_create($options);
        $result = file_get_contents($url,false,$ctx);
        if(!empty($result)) $result = json_decode($result);
        return($result);
}

function lms_delete_notify($start_id,$end_id){
                // this function notify update of the schedule to lms.
        $result = NULL;         // initialization.
        $senddata = array(
                'is_delete_data' => '1',
                'start_id' => $start_id,
                'end_id' => $end_id
        );
        $query = http_build_query($senddata,'',"&");
        $platform = PLATFORM;
                                // http-post:
        if ($platform == 'staging' ){
                $url = 'https://staging.sakuraone.jp/import/schedules?'.$query;
        } else if ($platform == 'production' ){
                $url = 'https://sakuraone.jp/import/schedules?'.$query;
        }
        $header = array(
                'Content-Type:application/x-www-form-urlencoded',
                'Content-Length: '.strlen($url),
                'Api-Token: 7511a32c7b6fd3d085f7c6cbe66049e7'
        );

        $options = array('http' => array(
                   'method' => 'POST',
                   'timeout' => 9600 ,
                   'header' => implode("\r\n",$header)
                         )
                   );
        $ctx = stream_context_create($options);
        $result = file_get_contents($url,false,$ctx);
        if(!empty($result)) $result = json_decode($result);
        return($result);

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
	<a href='menu.php'><h4>季節講習スケジュールを取り込むことができませんでした。メニュー画面に戻る</h4></a>
<?php
        if (count($errArray) > 0) {
                foreach( $errArray as $error) {
?>
                        <font color="red"><?= $error ?></font><br><br>
<?php
                }
	}
} else {
	if (count($errArray) > 0) {
?>
	<a href='menu.php'><h4>季節講習スケジュールを取り込みましたがエラーがありました。メニュー画面に戻る</h4></a>
<?php
       		foreach( $errArray as $error) {
?>
               	        <font color="red"><?= $error ?></font><br><br>
<?php
       		}
       	} else {
					if ($confirmed_schedule_onetime_array) {
						echo "以下のSONカレンダー予定が変更・削除されました。<br><table>\n";
						foreach ($confirmed_schedule_onetime_array as $record) {
							$stmt = $db->query("SELECT name FROM tbl_member WHERE no=".str_pad($record['student_no'],6,'0',STR_PAD_LEFT));
							$student_name = ($stmt->fetch(PDO::FETCH_NUM))[0];
							$stmt = $db->query("SELECT name FROM tbl_teacher WHERE no=".($record['teacher_id']-100000));
							$teacher_name = ($stmt->fetch(PDO::FETCH_NUM))[0];
							echo "<tr><td>{$record['ymd']} ".substr($record['starttime'],0,5).'-'.substr($record['endtime'],0,5)." {$student_name}様 {$teacher_name}先生</td></tr>\n";
						}
						echo "</table><br>";
					}
?>
	<a href='menu.php'><h4>正常終了しました。メニュー画面に戻る</h4></a>
<?php
	}
}
?>
</div>
</body>
</html>
