<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

$errArray = array();

if (isset($_POST['add'])) {
	$action = 'add';
} else {
	$action = "";
}
$select_list = $_POST['selectMember'];
$taikaisha_flag = $_POST['t'];

if (is_null($taikaisha_flag) == true || $taikaisha_flag == "") {
	$taikaisha_flag = $_GET["t"];
}

if ($taikaisha_flag)
	$member_list = get_simple_member_list( $db, array("name <> ? and name <> ?"), array("体験生徒","職員"), array(), 1);
else
	$member_list = get_simple_member_list( $db, array("name <> ? and name <> ?"), array("体験生徒","職員"));

try{
	$stmt = $db->prepare("SELECT member_no,member_name,furikomisha_name FROM tbl_furikomisha ORDER BY tbl_furikomisha.furikomisha_name asc");
	$stmt->execute(array($search_name));
	$furikomisha_array = $stmt->fetchAll(PDO::FETCH_ASSOC);
}catch (PDOException $e){
	$errArray[] =  $e->getMessage();
}
$furikomisha_list = array();
foreach ( $furikomisha_array as $row ) {
	$furikomisha_list[$row["furikomisha_name"]][] = $row["member_name"];
}


$furikomisha = array();
if ($action == 'add') {
// 登録処理

	$furikomisha["no"] = trim($_POST["no"]);
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
			$furikomisha_no = insert_furikomisha($db, $furikomisha);
			if (!$furikomisha_no) $errFlag = 1;
		}catch (PDOException $e){
			$errFlag = 1;
			array_push($errArray, 'Error:'.$e->getMessage());
		}
		if ($errFlag == 0) {
			$db->commit();
			header('Location: furikomisha_list.php?sort_type=2');
			exit;
		} else {
			$db->rollback();
			array_push($errArray, "登録中にエラーが発生しました。");
		}
	}
	// エラー時、編集画面を再表示する

} else {
	// 初期表示処理

	$furikomisha["no"] = "";
	$furikomisha["member_no"] = "";
	$furikomisha["member_name"] = "";
	$furikomisha["furikomisha_name"] = "";
	$furikomisha["del_flag"] = "0";	// 初期値：現振込者名

}


?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">
<!--
//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript">
function member_click( member_no, checked ) {
	var elem1 = document.getElementById(member_no);
	if (checked){
		elem1.style.display = "";
	} else {
		elem1.style.display = "none";
	}
}
</script>
</head>
<body>

<div id="header">
	事務システム 
</div>

<div id="content" align="center">

<h3>振込者名の登録 - 新規登録</h3>

<a href="furikomisha_list.php">振込者名一覧へ</a>&nbsp;&nbsp;
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

<form method="post" name="furikomisha_form" action="./furikomisha_add.php">
	<input type="hidden" name="no" value="<?=$furikomisha["no"]?>">
	<input type="hidden" name="del_flag" value="0">
	<input type="hidden" name="t" id="taikaisha_flag" value="<?=$taikaisha_flag?>">

	<table>
	<tr>
  <td align="center">
		新規登録する場合は、振込者名を入力し振込生徒を選択したのち、登録ボタンを押してください。<br>
		<input type="submit" name="add" value="登録">
		<input type="reset" name="reset" value="リセット">
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
	<td><input type="checkbox" name="exclude_flag"></td>
	</tr>
	<tr>
	<th>振込生徒名</th>
	<td>
<table>
<?php
foreach ($member_list as $key => $item) {
?>
	<tr id=m<?=$item["no"]?> style="display:none"><td><?=$item["name"]?></td></tr>
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
		<td><input type="checkbox" name="selectMember[]" value="<?=$item["no"]?>" onclick="member_click('m<?=$item["no"]?>',this.checked)"><?=$item["name"]?></td>
	</tr>
<?php
}
?>
</table>
</form>
</div>

</body>
</html>
