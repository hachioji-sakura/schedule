<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

define('LESSON60', 1);
define('LESSON90', 2);
define('LESSON120', 3);

$errFlag = 0;
$errArray = array();

$class_type = $_GET['class_type'];
if (!$class_type) $class_type = $_POST['class_type'];
if ($class_type=='sat_sun_class') {
	$date_list = $sat_sun_class_date_list;
	$date_list_string = $sat_sun_class_date_list_string;
}
if ($class_type == 'sat_sun_class') {
	$page_title = "土日講習スケジュール";
} else {
	$page_title = "$season_class_title　スケジュール";
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<body>

<div id="header">
	事務システム 
</div>


<div id="content" align="left">

<h2><?= $page_title ?></h2>
<h3>申込みフォームCSVファイル読み込み</h3>

<a href="menu.php">メニューへ戻る</a><br><br>

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

$year = date("Y");
$month = date("n");
$today = "$year/".date('m').'/'.date('d');

$class_type = $_GET['class_type'];
if (!$class_type) $class_type = $_POST['class_type'];
if ($class_type=='sat_sun_class') {
	$date_list = $sat_sun_class_date_list;
	$date_list_string = $sat_sun_class_date_list_string;
}

$season_class = $_GET['season_class'];
if (!$season_class) $season_class = $_POST['season_class'];

if (is_uploaded_file($_FILES["upfile"]["tmp_name"])) {
  if (move_uploaded_file($_FILES["upfile"]["tmp_name"], "tmp/" . $_FILES["upfile"]["name"])) {
    chmod("tmp/" . $_FILES["upfile"]["name"], 0644);
    echo $_FILES["upfile"]["name"] . "をアップロードしました。";
		echo '<br><br>';
		
	try{	
		
		$fp = fopen("tmp/" . $_FILES["upfile"]["name"], "r");
		$fp1 = fopen("tmp/current_season_class_entry_form.csv", "w");
		$line1 = '';
		while( ! feof($fp) ) {
			$errFlag = 0;
			$line = $line1.fgets( $fp, 4096 );
			fputs( $fp1, $line );
			if (mb_substr_count($line,'"')%2==1) {
				if (ord(substr($line,strlen($line)-1))==10) {
					if (strlen($line)>1) {
						if (ord(substr($line,strlen($line)-2))!=13) {
							$line1 = str_replace("\n",'',$line);
							continue;
						}
					} else {
						continue;
					}
				}
			}
			$line1 = '';
//var_dump($line);echo"<br><br>";
			$dqarray = explode( '"', $line );
			$i=0; $sqarray = array();
			while (isset($dqarray[$i])) {
				$sqarray1 = explode( ',', $dqarray[$i++]);
				if ($sqarray1[0]==='') {array_splice($sqarray1,0,1);}
				if ($sqarray1[count($sqarray1)-1]==='') {array_splice($sqarray1,count($sqarray1)-1,1);}
				$sqarray = array_merge($sqarray, $sqarray1);
				$sqarray[] = $dqarray[$i++];
			}
//var_dump($dqarray);echo'<br><br>';
//var_dump($sqarray);echo'<br><br>';

			if ($sqarray[0] == 'タイムスタンプ') {
				$header = $sqarray;
				$name_index = array_search('生徒氏名',$header);
				$course_index = array_search('コース',$header);
				$date_index = array_search('希望日時',$header);
				$subject_index = array_search('希望授業科目',$header);
				$place_index = array_search('希望校舎',$header);
/*				
//				if ($class_type == 'sat_sun_class') {
				if (1) {
					$date_index = array();
					foreach ($header as $key=>$title) {
						if (preg_match('|希望日時 \[(\d+)/(\d+)|u',$title,$matches)) {
							$year1 = $year;
							if ($matches[1] < $month-1) { 
								if ($month-$matches[1] > 6) {
									$year1++;
								} else {
									throw new Exception("過去の日付データ（{$matches[1]}月）は登録できません。");
								}
							}
							$date = ($matches[1]<10)? "$year1/0{$matches[1]}" : "$year1/{$matches[1]}";
							$date = ($matches[2]<10)? "$date/0{$matches[2]}" : "$date/{$matches[2]}";
							$index0 = array_search($date,$date_list);
							if ($index0!==false){
								$date_list1[] = $date;
							} else {
								echo "{$date}は講習日ではありません。<br>";
							}
						}
					}

					$date_list_string1 = "('".implode("','", $date_list1)."')";
					
					foreach ($header as $key=>$title) {
						if (preg_match('|希望日時 \[(\d+)/(\d+)|u',$title,$matches)) {
							$year1 = $year;
							if (abs($matches[1]-$month)>6) $year1++;
							$date = ($matches[1]<10)? "$year1/0{$matches[1]}" : "$year1/{$matches[1]}";
							$date = ($matches[2]<10)? "$date/0{$matches[2]}" : "$date/{$matches[2]}";
							$index0 = array_search($date,$date_list1);
							if ($index0!==false){
								$date_index[$index0] = $key;
							}
							if ($sat_sun_class) {
							}
							if ($season_class) {
							}
						}
					}
				} else {
					$date_list1 = $date_list;
					$date_list_string1 = $date_list_string;
				}
	*/			
				continue;
			}
			
		$month = date('m');
		$date_list1=array();
		foreach($date_list as $key=>$date) {
			if ($date<"$year/$month")	{
				unset($date_list[$key]);
				continue;
			}
			$date_list1[] = $date;
		}
		$date_list_string1 = ($date_list1)? "('".implode("','",$date_list1)."')" : "";
			
			if (!$header) {
				echo "CSVファイルの先頭行（見出し行）が不正です。<br>漢字コードがUTF8であることを確認してください。<br>";
				exit();
			}

			$name = $sqarray[$name_index];
			$place = $sqarray[$place_index];
			if (!$name) { continue; }
			echo "{$name}: ";
			$stmt = $db->prepare("SELECT no,name,del_flag,grade FROM tbl_member");
			$stmt->execute();
			$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$member_no = 0;
			foreach ($rslt as $member) {
				if (($member['del_flag']==0) && 
						str_replace(array(' ','　'),'',$member['name']) == str_replace(array(' ','　'),'',$name)) {
					$member_no = $member['no'];
					break;
				}
			}
			if (!$member_no) {
				echo "<font color=\"red\">生徒名未登録エラー</font><br>"; $errFlag = 1;
				echo "<font color=\"red\">登録できません。</font><br>"; $errFlag = 1;
				continue;
			}
			
			$sql = "SELECT * FROM tbl_season_class_entry_date WHERE date IN {$date_list_string1} AND member_id='{$member_no}'";
			$stmt = $db->query($sql);
			$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($rslt) {
				echo "<font color=\"red\">登録済みのため変更しません。（変更がある場合「登録済み生徒一覧」から変更してください。）</font><br>"; $errFlag = 1;
				continue;
			}
			$sql = "SELECT * FROM tbl_season_class_entry_subject WHERE date IN {$date_list_string1} AND member_id='{$member_no}'";
			$stmt = $db->query($sql);
			$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($rslt) {
				echo "<font color=\"red\">登録済みのため変更しません。（変更がある場合「登録済み生徒一覧」から変更してください。）</font><br>"; $errFlag = 1;
				continue;
			}

//			if ($class_type != 'sat_sun_class') {
//			if (0) {
				preg_match_all('|\d{1,2}/\d{1,2}|',$sqarray[$date_index],$dates);
				if ($class_type == 'sat_sun_class') {
					preg_match_all('|(\d{1,2}:\d{1,2})-|',$sqarray[$date_index],$stimes);
					preg_match_all('|-(\d{1,2}:\d{1,2})|',$sqarray[$date_index],$etimes);
				} else {
					foreach ($dates[0] as $key=>$date)	{ $stimes[1][]='11:00';$etimes[1][]='16:00'; }
				}
			$dates1 = array();$stimes1 = array();$etimes1 = array();
				foreach ($dates[0] as $key=>$date) {
					$date0 = explode('/',$date);
					$date0 = sprintf('%02d/%02d',$date0[0],$date0[1]);
					$flag=0;
					foreach ($date_list as $date1) { if (substr($date1,5)==$date0) { $flag=1; break; }}
					if ($flag) {
						$dates1[] = $date1;
						$stimes1[] = $stimes[1][$key];
						$etimes1[] = $etimes[1][$key];
					} else {
						echo "<font color=\"red\">{$date0}　日付エラー</font><br>"; $errFlag = 1;
					}
				}
//var_dump($dates1);echo"<br><br>";
//var_dump($stimes1);echo"<br><br>";
//var_dump($etimes1);echo"<br><br>";
/*
			} else {
				$dates1 = array(); $stimes1 = array(); $etimes1 = array();
				foreach ($date_list1 as $key=>$date) {
					if (!$sqarray[$date_index[$key]]) continue;
					if ($sqarray[$date_index[$key]]=='全時間') {
						$dates1[] = $date;
						if (strpos($header[$date_index[$key]],'土')!==false) {
							$stimes1[] = '13:00';
							$etimes1[] = '18:00';
						} else {
							$stimes1[] = '11:00';
							$etimes1[] = '16:00';
						}
						continue;
					}
					if (!preg_match('/^(\d\d:\d\d-\d\d:\d\d(, )?)*$/u',$sqarray[$date_index[$key]])) {
						echo "　　<font color=\"red\">時刻データエラー：　{$sqarray[$date_index[$key]]}</font><BR>";
						continue;
					}
					$times = explode('-',$sqarray[$date_index[$key]]);
					$dates1[] = $date;
					foreach ($times as $time0){
						if (preg_match_all('/(\d\d:\d\d), (\d\d:\d\d)/', $time0,$matches)) {
							$mdiff = array_diff_assoc($matches[1],$matches[2]);
						 if ($mdiff) 
							 foreach ($mdiff as $key=>$val)
								echo "<font color='red'>警告：{$date} {$val}-{$matches[2][$key]}が選択されていません。出席として処理します。</font><br>";
						}
					}
					$stimes1[] = $times[0];
					$etimes1[] = end($times);
				}
			}
*/			
			
			$lesson_length = 60; $season_course_id = LESSON60;
			if (preg_match('/60/u',$sqarray[$course_index])) { $season_course_id = LESSON60; $lesson_length = 60; }
			if (preg_match('/90/u',$sqarray[$course_index])) { $season_course_id = LESSON90; $lesson_length = 90; }
			if (preg_match('/120/u',$sqarray[$course_index])) { $season_course_id = LESSON120; $lesson_length = 120; }
			$subjects = explode( ';', $sqarray[$subject_index] );
//			$subjects = explode( ',', $sqarray[$subject_index] );
//			$subjects = explode( ';', $sqarray[10] );
			$subjects_id = array();
			foreach ($subjects as $subject) {
				$subject = str_replace(array(' ','　'),'',$subject);
				$subject = preg_replace('/[(（].*[）)]/', '', $subject);
				if (!$subject) { continue; }
				$subject_id = array_search($subject, $subject_list);
				if ($subject_id===FALSE){
					echo "<font color=\"red\">{$subject}　科目名エラー</font><br>";
				} else {
					// 数学・算数
					if ($subject_id ==47) $subject_id = ($member['grade']>=8)? 1:4;
					$subjects_id[] = $subject_id;
				}
			}
			if (count($subjects_id)) {
				$subject_time = substr(($lesson_length * count($dates1) / count($subjects_id) / 60), 0, 4);	
			} else {
				$subject_time =  0;
				echo "<font color=\"red\">科目が指定されていません。</font><br>"; $errFlag = 1;
			}
			
			if ($errFlag) { echo "<font color=\"red\">登録できません。</font><br>"; continue; }
			
			$db->beginTransaction();
			
			//$sql = "DELETE FROM tbl_season_class_entry_date WHERE date IN {$date_list_string1} AND member_id=? AND date>'$today'";
			//$stmt = $db->prepare($sql);
			//$stmt->execute(array($member_no));

			//$sql = "DELETE FROM tbl_season_class_entry_subject WHERE member_id=? AND date IN {$date_list_string1} AND date>'$today'";
			//$stmt = $db->prepare($sql);
			//$stmt->execute(array($member_no));
			
			foreach ($subjects_id as $subject_id) {
				$lesson_id = 1;
				$sql = "INSERT INTO tbl_season_class_entry_subject VALUES (?, ?, ?, ?, ?, now(), now())";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($member_no, $lesson_id, $subject_id, $subject_time, $date_list1[0]));
			}
			foreach ($dates1 as $key=>$date) {
				if (array_search($date, $date_list1) === false) {
					echo "{$date}は講習日ではないので登録できません。<br>";
					continue;
				}
				if ($date <= $today) {
					echo "{$date}は過去の日付なので登録できません。<br>";
					continue;
				}
				$sql = "INSERT INTO tbl_season_class_entry_date VALUES (?, ?, ?, ?, ?, now(), now(), ?, ?, ?, ?, ?, ?)";
				$stmt = $db->prepare($sql);
				$stmt->execute(array($member_no, $season_course_id, $date, '11:00', '', $stimes1[$key], $etimes1[$key], '', '', 0, $place));
			}
			
			// 受講日以外のスケジュール削除
			//$stmt = $db->query("SELECT date FROM tbl_season_class_entry_date WHERE member_id='{$member_no}' AND date IN {$date_list_string1}");
			//$rslt = $stmt->fetchAll(PDO::FETCH_ASSOC);
			//$in_dates = implode("','", array_column($rslt, 'date'));
			//if ($in_dates) {
			//	$sql = "DELETE FROM tbl_season_schedule WHERE member_no='{$member_no}' AND date IN {$date_list_string1} AND date NOT IN ('{$in_dates}') AND date>'$today'";
			//	$db->query($sql);
			//}
			
			$db->commit();
			echo "登録しました。<br>";
		}
		fclose ($fp);
		fclose ($fp1);

	} catch (Exception $e){
		$errFlag = 1;
		echo "<font color='red'>エラーが発生しました。<br>".$e->getMessage(); echo'</font><br>';
		$db->rollback();
	}		

		
  } else {
    echo "ファイルをアップロードできません。";
  }
} else {
  echo "ファイルが選択されていません。";
}

	
?>
