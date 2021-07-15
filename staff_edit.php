<?php
ini_set( 'display_errors', 0 );
require_once(dirname(__FILE__)."/const/const.inc");
require_once(dirname(__FILE__)."/func.inc");
require_once(dirname(__FILE__)."/const/login_func.inc");

if (!$staff_acount) 	$staff_acount = $_POST['staff_acount'];
if (!$staff_acount) 	$result = check_user($db, "1");

if (!$year)		$year =		$_POST['y'];
if (!$month)	$month =	$_POST['m'];
if (!$mode)		$mode =		$_POST['mode'];

$course_list = get_course_list($db);
$teacher_list = get_teacher_list($db,array(),array(),array(),1);
$staff_list = get_staff_list($db);

foreach ($teacher_list as $teacher) {
	foreach ($staff_list as $staff) {
		if ($staff['name'] == $teacher['name']) {
			$teacher_and_staff_list[$teacher['name']] = array('teacher'=>$teacher, 'staff'=>$staff);
		}
	}
}

$errArray = array();

$staff = array();
if ($staff_acount) {
	if ($month<10) $month = '0'.$month;
	$staff_id = $_SESSION['ulogin']['staff_id'];
} else {
	$staff_id = trim($_POST["no"]);
}
$staff["no"] = $staff_id;

if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['delete'])) {
	$action = 'delete';
} else if (isset($_POST['pass_reset'])) {
	$action = 'pass_reset';
} else {
	$action = "";
}

if ($action == 'add' || $action == 'delete') {
	$staff["name"]         = trim($_POST["name"]);
	$staff["furigana"]     = trim($_POST["furigana"]);
	$staff["del_flag"]     = trim($_POST["del_flag"]);
	$staff["mail_address"] = trim($_POST["mail_address"]);
	$staff["worker_code"]  = trim($_POST["worker_code"]);
	$wage_array			  = $_POST["wage-"];
	$crew_no_array = array(1);
	$staff["transport_cost"] = trim($_POST["transport_cost"]);
	$staff["transport_DOW"]  = implode(',',$_POST["transport_DOW"]);
	if (!$staff["transport_DOW"]) { $staff["transport_DOW"]=''; }
	$staff["transport_limit"] = $_POST["transport_limit"];
	$staff["transport_zero"] = $_POST["transport_zero"];
	$staff["gennsenn_choushuu_shubetu"] = $_POST["gennsenn_choushuu_shubetu"];
	$staff["huyou_ninnzuu"]   = $_POST["huyou_ninnzuu"];
	$staff["jyuuminnzei1"]    = $_POST["jyuuminnzei1"];
	$staff["jyuuminnzei2"]    = $_POST["jyuuminnzei2"];
	$staff["bank_no"]          = $_POST["bank_no"];
	$staff["bank_branch_no"]   = $_POST["bank_branch_no"];
	$staff["bank_acount_type"] = $_POST["bank_acount_type"];
	$staff["bank_acount_no"]   = $_POST["bank_acount_no"];
	$staff["bank_acount_name"] = $_POST["bank_acount_name"];
}

if ($action == 'add') {
// 更新処理

	// 入力チェック処理
	$result = check_staff($db, $errArray, $staff);

	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$staff['password'] = '';
			$result = update_staff($db, $staff);
			if (!$result) $errFlag = 1;
			
			$sql = "INSERT tbl_wage_staff (staff_id, wage_no, crew_no, hourly_wage, insert_timestamp, update_timestamp) ".
							"VALUES (?, ?, ?, ?, now(), now()) ".
							"ON DUPLICATE KEY UPDATE crew_no=?, hourly_wage=?, update_timestamp=now()";
			$stmt = $db->prepare($sql);
			$stmt->execute(array($staff_id, 0, $crew_no_array[0], $wage_array[0], $crew_no_array[0], $wage_array[0]));
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			if (!$staff_acount) {
				if ($lms_mode) {
					if ($staff_id>200048)	$staff_id--;
					header("Location: staff_list.php?staff_id={$staff_id}");
				} else {
					header('Location: staff_list.php?sort_type=1');
				}
				exit;
			}
		} else {
			$db->rollback();
			array_push($errArray, "登録中にエラーが発生しました。");
		}
	}
	// エラーが発生した場合、編集画面を再表示する

} else if ($action == 'delete') {
// 削除処理

	$result = check_number($errArray, "事務員No", $staff_id, true);
	
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_staff($db, $staff_id);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
			array_push($errArray, "削除中にエラーが発生しました。");
		}
		if ($errFlag == 0) {
			$db->commit();
			header('Location: staff_list.php?sort_type=1');
			exit();
		} else {
			$db->rollback();
			array_push($errArray, "削除中にエラーが発生しました。");
		}
	}
	// エラーが発生した場合、編集画面を再表示する

} else {
// 初期表示処理
	if ($staff_id > 0) {
		try{
			$cmd = "SELECT * FROM tbl_staff WHERE no={$staff_id}";
			$stmt = $db->query($cmd);
			$staff = $stmt->fetch(PDO::FETCH_ASSOC);
			$cmd = "SELECT * FROM tbl_wage_staff WHERE staff_id={$staff_id}";
			$stmt = $db->query($cmd);
			$wages = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
		}
	}
}

if ($action == 'pass_reset') {

	try{
		$db->beginTransaction();
		$sql = "UPDATE tbl_staff SET ".
					" password=? , update_timestamp=now()".
					" WHERE no=?";
		$stmt = $db->prepare($sql);
		$stmt->bindParam(1, $password);
		$stmt->bindParam(2, $no);
		$no = $staff_id;
		$password = openssl_encrypt($staff["initial_password"], 'AES-128-ECB', PASSWORD_KEY);
		$stmt->execute();
		$db->commit();
	}catch (PDOException $e){
		$db->rollback();
		array_push($errArray, 'Error:'.$e->getMessage());
	}
}

if ($lms_mode) $lms_display = 'style="display:none;"';
if ($staff_acount) $staff_display = 'style="display:none;"';

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
function delete_staff() {
	result = window.confirm("事務員情報を削除します。\nよろしいですか？");
	if (result) {
		document.forms["staff_form"].submit();
	}
}
function passResetCheck() {
	return window.confirm('現在のパスワードを初期パスワードに変更してよろしいですか？');
}
function input_check() {
	var str, err_char;
//	str = document.forms['staff_form'].elements['bank_no'].value;
//	err_char = str.replace(/[0-9]/g,'');
//	if (str && (err_char || str.length!=4)) { alert("銀行番号は半角数字4ケタで入力してください。"); return false; }
//	str = document.forms['staff_form'].elements['bank_branch_no'].value;
//	err_char = str.replace(/[0-9]/g,'');
//	if (str && (err_char || str.length!=3)) { alert("支店番号は半角数字3ケタで入力してください。"); return false; }
//	str = document.forms['staff_form'].elements['bank_acount_no'].value;
//	err_char = str.replace(/[0-9]/g,'');
//	if (str && (err_char || str.length!=7)) { alert("口座番号は半角数字7ケタで入力してください。"); return false; }
//	str = document.forms['staff_form'].elements['bank_acount_name'].value;
//	err_char = str.replace(/[　１２３４５６７８９０ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺアイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワンガギグゲゴザジズゼゾダヂヅデドバビブベボパピプペポヴ（）．－]/g,'');
//	if (err_char) { alert("以下の文字は口座名に使用できません。\n“"+err_char+"”"); return false; }
	return true;
}
function transport_zero_set(onoff) {
	var form = document.forms['staff_form'];
	form.elements['transport_cost'].value = 0;
	form.elements['transport_cost'].disabled = onoff;
	elem = document.getElementsByName("transport_DOW[]");
	for (i=0;i<elem.length;i++) {
		elem[i].disabled = onoff;
	}
	form.elements['transport_limit'].value = 0;
	form.elements['transport_limit'].disabled = onoff;
}
//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
</head>
<body>

<?php if (!$staff_acount) { ?>
<div id="header">
	事務システム 
</div>
<?php } ?>

<div id="content" align="center">

<?php if (!$staff_acount) { ?>
<h3>交通費登録</h3>
<?php if ($lms_mode) { ?>
<h3>事務員の時給・交通費・税金登録</h3>
<?php } else { ?>
<h3>事務員の登録 - 更新・削除</h3>

<a href="staff_list.php">事務員一覧へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br><br>

<?php }} else if ($staff_acount == 1) { ?>

<div id="content" align="center">
<h2>交通費申請</h2>
<h3><?= $year ?>年<?= $month ?>月分</h3>
<a href="menu.php">メニューへ戻る</a><br>

<?php } else { ?>

<div id="content" align="center">
<h2>交通費確定</h2>
<h3><?= $year ?>年<?= $month ?>月分</h3>
<form method="post" name="backform" action="">
    <input type="hidden" name="y" value="<?=$year?>">
    <input type="hidden" name="m" value="<?=$month?>">
    <a href="javascript:backform.submit()">戻る</a><br>
</form>
<?php } ?>

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
	} else if ($action == 'add') {
		echo "<h4><font color=\"blue\">登録しました。</font></h4><br>";
	}
?>


<form method="post" name="staff_form" action="staff_edit.php">
	<input type="hidden" name="y" value="<?=$year?>">
	<input type="hidden" name="m" value="<?=$month?>">
	<input type="hidden" name="no" value="<?=$staff_id?>">
	<input type="hidden" name="del_flag" value="<?=$staff["del_flag"]?>">
	<input type="hidden" name="mode" value="<?=$mode?>">
	<input type="hidden" name="staff_acount" value="<?=$staff_acount?>">

	<div class="menu_box">
		<font color="black" size="-1">
		※&nbsp;編集する場合は、事務員情報を入力して、登録ボタンを押してください。<br>
		※&nbsp;退塾者は、<font color="red">削除をせずに、</font>ステータスで「前事務員」を選択して登録してください。<br>
		&nbsp;&nbsp;&nbsp;&nbsp;データ取り込み時に誤って登録された不要データのみ、削除してください。
		</font>
	</div>


	<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録" onclick="return input_check()">
		<input type="submit" name="delete" value="削除" onclick="delete_staff()" <?=$lms_display?><?=$staff_display?>><?php /* buttonだとname=deleteが送信できないので、submitに*/ ?>
		<input type="reset" value="リセット">
	</td>
	</tr>
	</table>

	<br>

	<table id="form">
	<tr>
		<th>名前</th>
		<td>
			<input type="hidden" name="name" value="<?=$staff["name"]?>">
			<?=$staff['name']?>
		</td>
	</tr>
	<tr <?=$lms_display?><?=$staff_display?>>
		<th>ふりがな</th>
		<td>
			<input type="hidden" name="furigana" value="<?=$staff["furigana"]?>"><?=$staff["furigana"]?>
		</td>
	</tr>
	<tr <?=$lms_display?><?=$staff_display?>>
	<th>ステータス</th><td>
		<input type="hidden" name="del_flag" value="<?=$staff["del_flag"]?>">
		<?= ($staff["del_flag"] == 0)?'現事務員':'' ?><?= ($staff["del_flag"] == 2)?'前事務員':'' ?>
	</td>
	</tr>
<!--
	<tr>
	<th>メールアドレス</th><td>
		<input type="text" name="mail_address" size="80" maxlength="100" value="<?=$staff["mail_address"]?>">
	</td>
	</tr>
-->
	<tr valign="top" <?=$staff_display?>>
	<th>口座情報</th>
<?php if ($teacher_and_staff_list[$staff['name']]) { ?>
<td>講師登録画面で登録してください。</td>
<?php } else { ?>
	<td>
		銀行番号:<input type="hidden" name="bank_no" value="<?=$staff["bank_no"]?>"><?=$staff["bank_no"]?>　
		支店番号:<input type="hidden" name="bank_branch_no" value="<?=$staff["bank_branch_no"]?>"><?=$staff["bank_branch_no"]?>　
		預金種目:<?= ($staff["bank_acount_type"] == 1)?'普通':'' ?><?= ($staff["bank_acount_type"] == 2)?'当座':'' ?><?= ($staff["bank_acount_type"] == 4)?'貯蓄':'' ?>
		<input type="hidden" name="bank_acount_type" value="<?=$staff["bank_acount_type"]?>">
		<br>
		口座番号:<input type="hidden" name="bank_acount_no" value="<?=$staff["bank_acount_no"]?>"><?=$staff["bank_acount_no"]?>　
		口座名:<input type="hidden" name="bank_acount_name" value="<?=$staff["bank_acount_name"]?>"><?=$staff["bank_acount_name"]?>
	</td>
<?php } ?>
	</tr>
<!--
	<tr>
	<th>Crew従業員コード</th><td>
		<input type="text" name="worker_code" size="20" maxlength="40" value="<?=$staff["worker_code"]?>">
	</td>
	</tr>
	<tr>
	<th>初期パスワード</th><td>
		<?=$staff["initial_password"]?>&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" name="pass_reset" value="パスワード初期化" onclick="return passResetCheck()">
	</td>
	</tr>
-->
	</table>
	<br>

<table border="1" <?=$staff_display?>>
<tr><th>番号</th><th>時給</th></tr>
<?php
	$wage_type_list = array("-");
	$table_elename = 'table-';
	$crew_type_elename = 'crew_type-[]';
	$wage_elename = 'wage-[]';
	foreach($wage_type_list as $key=>$wage_type) {
		$wage=$wages[$key];
?>
<tr name="<?= $table_elename ?>">
<td><?= $key+1 ?></td>
<td><input type="text" name="<?= $wage_elename ?>" size="10" value="<?=$wage["hourly_wage"]?>">円</td>
</tr>
<?php
	}
?>
</table>

<br <?=$staff_display?>>
<table border="1">
<tr><th colspan="2"align="center">交通費</th></tr>
<?php if ($teacher_and_staff_list[$staff['name']]) { ?>
<tr><td>講師登録画面で登録してください。</td></tr>
<?php } else { ?>
<tr>
<td>交通費支給</td>
<td colspan="8"><input type="checkbox" name="transport_zero" value="1" <?= $staff['transport_zero']? 'checked':'' ?> onclick="transport_zero_set(this.checked)">交通費なし</td>
</tr>
<tr>
<td>交通費（1日分）</td>
<td><input type="text" name="transport_cost" size="10" value="<?= $staff['transport_cost'] ?>">円 （半角数字入力）</td>
</tr>
<tr>
<td>支給対象曜日</td>
<td>
<input type="checkbox" name="transport_DOW[]" value="0" <?= (strpos($staff['transport_DOW'],'0')!==false)?'checked':'' ?>>日　
<input type="checkbox" name="transport_DOW[]" value="1" <?= (strpos($staff['transport_DOW'],'1')!==false)?'checked':'' ?>>月　
<input type="checkbox" name="transport_DOW[]" value="2" <?= (strpos($staff['transport_DOW'],'2')!==false)?'checked':'' ?>>火　
<input type="checkbox" name="transport_DOW[]" value="3" <?= (strpos($staff['transport_DOW'],'3')!==false)?'checked':'' ?>>水　
<input type="checkbox" name="transport_DOW[]" value="4" <?= (strpos($staff['transport_DOW'],'4')!==false)?'checked':'' ?>>木　
<input type="checkbox" name="transport_DOW[]" value="5" <?= (strpos($staff['transport_DOW'],'5')!==false)?'checked':'' ?>>金　
<input type="checkbox" name="transport_DOW[]" value="6" <?= (strpos($staff['transport_DOW'],'6')!==false)?'checked':'' ?>>土　
</td>
</tr>
<tr>
<td>支給額制限</td>
<td><input type="checkbox" name="transport_limit" value="1" <?= $staff['transport_limit']? 'checked':'' ?>>給与の1割まで</td>
</tr>
<?php } ?>
</table>

<br>
<table border="1" <?=$staff_display?>>
<tr><th colspan="3"align="center">源泉徴収税・住民税</th></tr>
<?php if ($teacher_and_staff_list[$staff['name']]) { ?>
<tr><td>講師登録画面で登録してください。</td></tr>
<?php } else { ?>
<tr>
<td>　<input type="radio" name="gennsenn_choushuu_shubetu" value="甲" <?= ($staff['gennsenn_choushuu_shubetu']=='甲')?'checked':'' ?>>甲　</td>
<td>
<table>
<tr>
<td>扶養人数</td>
<td>
<select name="huyou_ninnzuu">
<option value="0" <?= ($staff['huyou_ninnzuu']==0)?'selected':'' ?>>0人</option>
<option value="1" <?= ($staff['huyou_ninnzuu']==1)?'selected':'' ?>>1人</option>
<option value="2" <?= ($staff['huyou_ninnzuu']==2)?'selected':'' ?>>2人</option>
<option value="3" <?= ($staff['huyou_ninnzuu']==3)?'selected':'' ?>>3人</option>
<option value="4" <?= ($staff['huyou_ninnzuu']==4)?'selected':'' ?>>4人</option>
<option value="5" <?= ($staff['huyou_ninnzuu']==5)?'selected':'' ?>>5人</option>
<option value="6" <?= ($staff['huyou_ninnzuu']==6)?'selected':'' ?>>6人</option>
<option value="7" <?= ($staff['huyou_ninnzuu']==7)?'selected':'' ?>>7人</option>
</select>
</td>
</tr>
<tr>
<td>住民税　6月</td>
<td><input type="text" name="jyuuminnzei1" size="10" value="<?= $staff['jyuuminnzei1'] ?>">円 （半角数字入力）</td>
</tr>
<tr>
<td>住民税　7月～翌年5月</td>
<td><input type="text" name="jyuuminnzei2" size="10" value="<?= $staff['jyuuminnzei2'] ?>">円 （半角数字入力）</td>
</tr>
</table>
</td>
</tr>
<tr>
<td>　<input type="radio" name="gennsenn_choushuu_shubetu" value="乙" <?= ($staff['gennsenn_choushuu_shubetu']=='乙')?'checked':'' ?>>乙　</td><td></td>
</tr>
<?php } ?>
</table>
<br <?=$staff_display?>>

<?php if ($lms_mode) { ?>
<br><input type="button" onclick="document.location='staff_list.php?staff_id=<?='2'.str_pad(($staff_id>48?$staff_id--:$staff_id), 5, 0, STR_PAD_LEFT)?>'" value="戻る">
		<input type="button" onclick="window.close()" value="閉じる">
<?php 
}
if ($teacher_acount == 1) {
} else if ($teacher_acount == 2) {
}
?>
</form>
</div>
</body>
</html>
