<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

$errArray = array();

$furikomisha = array();
$furikomisha["no"] = trim($_POST["no"]);
$taikaisha_flag = $_POST['t'];

if (is_null($taikaisha_flag) == true || $taikaisha_flag == "") {
	$taikaisha_flag = $_GET["t"];
}
if (isset($_POST['add'])) {
	$action = 'add';
} else if (isset($_POST['delete'])) {
	$action = 'delete';
} else {
	$action = "";
}

$select_list = $_POST['selectMember'];

if ($taikaisha_flag)
	$member_list = get_simple_member_list( $db, array("name <> ? and name <> ?"), array("体験生徒","職員"), array(), 1);
else
	$member_list = get_simple_member_list( $db, array("name <> ? and name <> ?"), array("体験生徒","職員"));

try{
	$stmt = $db->prepare("SELECT member_no,member_name,furikomisha_name,del_flag FROM tbl_furikomisha WHERE no=? ORDER BY tbl_furikomisha.furikomisha_name asc");
	$stmt->execute(array($furikomisha["no"]));
	$furikomisha_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
}catch (PDOException $e){
	$errArray[] =  $e->getMessage();
}

if ($action == 'add' || $action == 'delete') {
	$furikomisha["furikomisha_name"] = trim($_POST["name"]);
	$furikomisha["del_flag"] = trim($_POST["del_flag"]);
	
	$sep = '';
	foreach ( $select_list as $item ) {
		$furikomisha["member_no"] .= $sep.$item;
		foreach ( $member_list as $item1 ) {
			if ($item1['no'] == $item) { $furikomisha["member_name"] .= $sep.$item1['name']; break; }
		}
		$sep = ':';
	}
} else {
	$furikomisha["member_no"] = $furikomisha_array[0]['member_no'];
	$furikomisha["furikomisha_name"] = $furikomisha_array[0]['furikomisha_name'];
	$furikomisha["del_flag"] = $furikomisha_array[0]['del_flag'];
	$sep = '';
	foreach ( $member_list as $item1 ) {
		if ($item1['no'] == $item) { $furikomisha["member_name"] .= $sep.$item1['name']; break; }
	}
	$sep = ':';
}

if ($action == 'add') {
// 更新処理

	if ($_POST['exclude_flag']) {
		$furikomisha["member_no"] = '';
		$furikomisha["member_name"] = '';
	} else {
		// 入力チェック処理
		try{
			$result = check_furikomisha($db, $errArray, $furikomisha);
		}catch (PDOException $e){
			array_push($errArray, $e->getMessage());
		}
	}

	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = update_furikomisha($db, $furikomisha);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
			array_push($errArray, "登録中にエラーが発生しました。");
		}
		if ($errFlag == 0) {
			$db->commit();
			header('Location: furikomisha_list.php?sort_type=1');
			exit;
		} else {
			$db->rollback();
			array_push($errArray, "登録中にエラーが発生しました。");
		}
	}
	// エラーが発生した場合、編集画面を再表示する

} else if ($action == 'delete') {
// 削除処理
	
	if (count($errArray) == 0) {
	// 入力エラーがない場合
		$errFlag = 0;
		try{
			$db->beginTransaction();
			$result = delete_furikomisha($db, $furikomisha["no"]);
			if (!$result) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
			array_push($errArray, "削除中にエラーが発生しました。");
		}
		if ($errFlag == 0) {
			$db->commit();
			header('Location: furikomisha_list.php?sort_type=1');
			exit();
		} else {
			$db->rollback();
			array_push($errArray, "削除中にエラーが発生しました。");
		}
	}
	// エラーが発生した場合、編集画面を再表示する

} else {
// 初期表示処理
	if ($furikomisha["no"] > 0) {
		try{
			$cmd = "SELECT * FROM tbl_furikomisha WHERE no=".$furikomisha["no"];
			$stmt = $db->prepare($cmd);
			$stmt->execute();
			$furikomishas = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$furikomisha = $furikomishas[0];
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
		}
	}
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
function delete_furikomisha() {
	result = window.confirm("振込者情報を削除します。\nよろしいですか？");
	if (result) {
		document.forms["furikomisha_form"].submit();
	}
}
function member_click( member_no, checked ) {
	var elem1 = document.getElementById(member_no);
	var exflag = document.getElementsByName('exclude_flag');
	if (checked){
		elem1.style.display = "";
		exflag[0].checked = false;
	} else {
		elem1.style.display = "none";
	}
}
function exflag_click(checked) {
	var elem1 = document.getElementsByName('selectMember[]');
	if (checked) {
		for (i=0;i<elem1.length;i++) {
			elem1[i].checked = false;
			document.getElementById('m'+(elem1[i].value)).style.display = "none";
		}
	}
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

<h3>振込者の登録 - 更新・削除</h3>

<a href="furikomisha_list.php">振込者一覧へ</a>&nbsp;&nbsp;
<a href="furikomisha_add.php">新規登録へ</a>&nbsp;&nbsp;
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
?>


<form method="post" name="furikomisha_form" action="furikomisha_edit.php">
	<input type="hidden" name="no" value="<?=$furikomisha["no"]?>">
	<input type="hidden" name="del_flag" value="<?=$furikomisha["del_flag"]?>">
<input type="hidden" name="t" id="taikaisha_flag" value="<?=$taikaisha_flag?>">

	<div class="menu_box">
		<font color="black" size="-1">
		※&nbsp;編集する場合は、振込者名または振込生徒を修正し、登録ボタンを押してください。<br>
		</font>
	</div>


	<table>
	<tr>
  <td align="center">
		<input type="submit" name="add" value="登録">
		<input type="submit" name="delete" value="削除" onclick="delete_furikomisha()"><?php /* buttonだとname=deleteが送信できないので、submitに*/ ?>
		<input type="reset" value="リセット">
		&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="button" value="&nbsp;退会者表示&nbsp;" onclick="document.getElementById('taikaisha_flag').value='1';document.furikomisha_form.submit();">
	</td>
	</tr>
	</table>

	<br>

	<table id="form">
	<tr>
	<th><font color="red">*</font>&nbsp;振込者名</th>
	<td>
		<input type="text" name="name" size="40" value="<?=$furikomisha["furikomisha_name"]?>">
	</td>
	</tr>
	<tr>
	<th>受講料振込対象外</th>
	<td><input type="checkbox" name="exclude_flag" onclick="exflag_click(this.checked)" <?= ($furikomisha["member_no"])?'':'checked="checked"' ?>></td>
	</tr>
	<tr>
	<th>振込生徒名</th>
	<td>
<table>
<?php
$array = explode(':',$furikomisha["member_no"]);
foreach ($member_list as $key => $item) {
?>
	<tr id=m<?=$item["no"]?> 
	<?php
		$flag=TRUE; foreach( $array as $member_no) { if ($member_no == $item["no"]) { $flag=FALSE; break; }}
		if ($flag) { echo("style='display:none'"); }
	?>
	><td><?=$item["name"]?></td></tr>
<?php
}
?>
</table>
	</td>
	</tr>
	</table>
	<br>
</table>
<br><br>
<table border="1" cellpadding="5">
<tr>
	<th>生徒一覧</th>
</tr>
<?php
foreach ($member_list as $key => $item) {
?>
	<tr>
		<td><input type="checkbox" name="selectMember[]" value="<?=$item["no"]?>" onclick="member_click('m<?=$item["no"]?>',this.checked)"
		<?php
			foreach( $array as $member_no) { if ($member_no == $item["no"]) { echo " checked='checked'"; break; }}
		?>
		><?=$item["name"]?></td>
	</tr>
<?php
}
?>
</table>
</form>
</div>

</body>
</html>
