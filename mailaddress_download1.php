<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

$errArray = array();
$errFlag = 0;

$year = date("Y");
$month = date("n");
$month = $month-1;
if ($month<1) { $year--; $month+=12; }
	
$year   = $_GET['y'];
$month  = $_GET['m'];
$type   = $_GET['type'];
$lesson = $_GET['lesson'];

$tmpfname = "./tmp/"."mailaddress_tmp_".session_id().".txt";

if (isset($_GET['down'])) {
	$fp = @fopen($tmpfname, "r");
	if ($fp) {
		// ヘッダ
		header("Content-Type: application/octet-stream");
		// ダイアログボックスに表示するファイル名
		header("Content-Disposition: attachment; filename=mail-address.txt");
		while( $str = fgets( $fp, 1000 ) ){
			echo $str;
		}
		fclose($fp);
		exit;
	}
}

try {
	if ($lesson) $lesson_cond = "AND lesson_id=$lesson ";
	
	if ($type==1 || $type==3) {
		$sql = "SELECT name, mail_address FROM tbl_statement_detail, tbl_member ".
					"WHERE FROM_UNIXTIME(start_timestamp, '%Y') = $year ".
					"AND  FROM_UNIXTIME(start_timestamp, '%m')+0 = $month ".
					"AND tbl_member.del_flag in (0,2) ".
					"AND tbl_member.name <> '体験生徒' ".
					"AND absent_flag = 0 ".
					"AND tbl_statement_detail.student_id = tbl_member.no ".
					$lesson_cond.
					"GROUP BY student_id ".
					"ORDER BY furigana ";
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$student_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$student_list1 = $student_list;
	}

	if ($type==2 || $type==3) {
		$sql = "SELECT name, mail_address FROM tbl_member, tbl_statement_detail ".
					"WHERE tbl_member.kind = 3 ".
					"AND tbl_member.del_flag = 0 ".
					"AND tbl_member.name <> '体験生徒' ".
					"AND tbl_statement_detail.student_id = tbl_member.no ".
					"AND tbl_statement_detail.start_timestamp = ( SELECT MIN(start_timestamp) FROM tbl_statement_detail WHERE student_id = tbl_member.no ) ".
					"AND FROM_UNIXTIME(tbl_statement_detail.start_timestamp,'%Y/%m') >= '$year/".sprintf('%02d',$month)."' ".
					$lesson_cond.
					"GROUP BY student_id ".
					"ORDER BY furigana ";
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$student_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$student_list2 = $student_list;
	}
	if ($type==3) {
		$student_list = array_unique( array_merge( $student_list1, $student_list2 ),SORT_REGULAR );
	}
//var_dump($student_list);echo'<br>';	

	$fp = fopen ($tmpfname, "w");
	if (!$fp){ throw new Exception('一時ファイルオープンエラー'); }
	if (!flock($fp, LOCK_EX)){ throw new Exception('一時ファイルオープンエラー'); }
	$flag=0;$mail_list=array();
	foreach ($student_list as $item) {
		if ($item['mail_address']) {
			if (array_search($item['mail_address'], $mail_list)!==false) continue;
			$mail_list[] = $item['mail_address'];
			$mcount++;
			if ($flag) fwrite ($fp, ',');
			fwrite ($fp, $item['mail_address']); $flag=1;
		} else {
			$errcount++;
			$errArray[] = "メールアドレス未登録：　{$item['name']}";
		}
	}
	fclose ($fp);

} catch (Exception $e) {
	// 処理を中断するほどの致命的なエラー
	array_push($errArray, $e->getMessage());
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
function download()
{
	var url;
	url = "./mailaddress_download1.php?down=1";
	document.location = url;
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

<h3>メールアドレスダウンロード</h3>
<a href="menu.php">メニューへ戻る</a><br><br>

<form id="form" method="get" action="mailaddress_download1.php">
<table>
<tr><th align="left">年月指定</th><td><?= $year ?>年<?= $month ?>月</td></tr>
<tr><th align="left">対象生徒指定</th><td>
<?php
	if ($type==1) echo '指定月受講者';
	if ($type==2) echo '指定月以降入会者';
	if ($type==3) echo '指定月受講者及び指定月以降入会者';
?>
</td></tr>
<tr><th align="left">部門指定</th><td>
<?php
	if ($lesson==0) echo '全部門';
	if ($lesson==1) echo '塾';
	if ($lesson==2) echo '英会話';
	if ($lesson==3) echo 'ピアノ';
	if ($lesson==4) echo '習い事';
?>
</td></tr>
<tr><th align="left">対象生徒数</th><td><?= count($student_list) ?></td></tr>
<tr><th align="left">メールアドレス数</th><td><?= $mcount ?></td></tr>
<tr><th align="left">メールアドレス未登録数</th><td><?= $errcount ?></td></tr>
</table>
<br>
<table><tr><td>
<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br>
<?php
		}
	}
?>
</td></tr></table>

<br>
<input type="button" value="ファイルダウンロード" onclick="download()">
</form>

</div>
</body>
</html>