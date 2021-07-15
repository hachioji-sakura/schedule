<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

$course_list = get_course_list($db);

if ($_POST["search_name"]) {
	$search_name = trim($_POST["search_name"]);
}
if ($_POST["gensensei"]=='0') {
	$gensensei = '0';
} else {
	$gensensei = '1';
}
if ($_POST["zensensei"]=='1') {
	$zensensei = '1';
} else {
	$zensensei = '0';
}

$param_array = array();
$value_array = array();
if ($search_name) {
	array_push($param_array," tbl_staff.name like concat('%',?,'%') ");
	array_push($value_array, $search_name);
}

if ($lms_mode) {
	if (preg_match('/^2.....$/', $_GET['staff_id']))
		$staff_id = preg_replace('/^20*/', '', $_GET['staff_id']);
	else
		$staff_id = '';
	if ($staff_id>200047) $staff_id++;
	array_push($param_array," tbl_staff.no = '$staff_id' ");
}

// 20150816 ふりがなの50音順にソートする
$order_array = array("tbl_staff.furigana asc");

// 事務員一覧を取得
if ($gensensei=='1' && $zensensei=='0') {
	$all_flag = "0";	// 現事務員を抽出
	$staff_list = get_staff_list($db, $param_array, $value_array, $order_array, $all_flag);
} else if ($gensensei=='1' && $zensensei=='1') {
	$all_flag = "1";	// 前事務員と現事務員を抽出
	$staff_list = get_staff_list($db, $param_array, $value_array, $order_array, $all_flag);
} else if ($gensensei=='0' && $zensensei=='1') {
	$all_flag = "3";	// 前事務員を抽出
	$staff_list = get_staff_list($db, $param_array, $value_array, $order_array, $all_flag);
}
$now_count = count($staff_list);

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム</title>
<script type = "text/javascript">

function edit_staff(no) {
	document.forms["staff_list"].elements["no"].value = no;
	document.forms["staff_list"].action = "staff_edit.php";
	document.forms["staff_list"].submit();
}

function entry_staff(no) {
	document.forms["staff_list"].elements["no"].value = no;
	document.forms["staff_list"].action = "season_class_staff_entry.php";
	document.forms["staff_list"].submit();
}

function payadj_staff(no) {
	document.forms["staff_list"].elements["no"].value = no;
	document.forms["staff_list"].action = "payadj_edit.php";
	document.forms["staff_list"].submit();
}

function tatekae_staff(no) {
	document.forms["staff_list"].elements["no"].value = no;
	document.forms["staff_list"].action = "tatekae_edit.php";
	document.forms["staff_list"].submit();
}

function search_clear() {
	document.forms["staff_list"].elements["search_name"].value = "";
	document.forms["staff_list"].submit();
}

function dispPass(flag) {
	if(flag) {
		document.forms["staff_list"].elements["pass"].value = "1";
	} else {
		document.forms["staff_list"].elements["pass"].value = "";
	}
	document.forms["staff_list"].submit();
}

function dispGensensei(flag) {
	if(flag) {
		document.forms["staff_list"].elements["gensensei"].value = "1";
	} else {
		document.forms["staff_list"].elements["gensensei"].value = "0";
	}
	document.forms["staff_list"].submit();
}

function dispZensensei(flag) {
	if(flag) {
		document.forms["staff_list"].elements["zensensei"].value = "1";
	} else {
		document.forms["staff_list"].elements["zensensei"].value = "0";
	}
	document.forms["staff_list"].submit();
}

</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
</head>
<body>

<div id="header">
	事務システム 
</div>


<div id="content" align="center">

<?php if ($lms_mode) { ?>
<h3>事務員の登録</h3>
<?php } else { ?>
<h3>事務員の登録 - 事務員一覧</h3>

<a href="staff_add.php">新規登録へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br><br>
<?php } ?>

<form method="post" name="staff_list" action="staff_list.php">
	<input type="hidden" name="type" value="<?= STAFF ?>">
	<input type="hidden" name="no" value="">	<?php /* 編集ボタンクリック時にstaff_noをセットする */ ?>
	<input type="hidden" name="pass" value="">
	<input type="hidden" name="gensensei" value="<?= $gensensei ?>">
	<input type="hidden" name="zensensei" value="<?= $zensensei ?>">

<?php if (!$lms_mode) { ?>
<table border="1">
	<tr>
		<th>氏名</th>
		<td><input type="text" name="search_name" value="<?= $search_name; ?>">※氏名の部分一致で検索します</td>
	</tr>
	<tr>
		<td colspan="4" align="center">
			<input type="submit" value="検索">&nbsp;&nbsp;
			<input type="button" value="検索解除" onclick="search_clear()">
		</td>
	</tr>
</table>

<br>
<br>
表示事務員：&nbsp;&nbsp;<input type="checkbox" onclick="dispGensensei(this.checked)" <?php if ($gensensei=="1") echo "checked"; ?>>現事務員&nbsp;&nbsp;
<input type="checkbox" onclick="dispZensensei(this.checked)" <?php if ($zensensei=="1") echo "checked"; ?>>前事務員&nbsp;&nbsp;（前事務員の欄はグレーで表示されます。）
<br>
<?= $now_count?>件&nbsp;<br>
<?php } ?>

<table border="1">
		<tr>
<th>氏名</th>
<!--
<th>　教室　</th>
<th>メールアドレス</th>
-->
<th>編集</th>
<th>給与調整</th>
<th>立替経費</th>
		</tr>
	<?php
			foreach ($staff_list as $item) {
			
			if ($item["del_flag"]=='0') {
				echo "<tr>";
			} else {
				echo "<tr bgcolor=\"#eeeeee\">";
			}
	?>
			<td><?= $item["name"] ?></td>
<!--
			<td align="center">
			<?php
							echo $lesson_list[$item["lesson_id"]];
							if ($item["lesson_id2"]) { echo ",".$lesson_list[$item["lesson_id2"]]; }
			?>
			</td>
			<td align="left"><?= $item["mail_address"] ?></td>
-->
			<td align="center">
			<input type="button" value="編集" onclick="edit_staff('<?= $item['no']?>')" target="_blank"> <?php /* ボタンにはnameを付けないこと。jsに渡すnoは'で囲むことStringとして渡すため*/ ?>
			</td>
			<td>
			<input type="button" value="給与調整登録" onclick="payadj_staff('<?= $item['no']?>')" target="_blank"> <?php /* ボタンにはnameを付けないこと。jsに渡すnoは'で囲むことStringとして渡すため*/ ?>
			</td>
			<td>
			<input type="button" value="立替経費登録" onclick="tatekae_staff('<?= $item['no']?>')" target="_blank"> <?php /* ボタンにはnameを付けないこと。jsに渡すnoは'で囲むことStringとして渡すため*/ ?>
			</td>
		</tr>
	<?php
			}
	?>
</table>

<?php if ($lms_mode) { ?>
<br><input type="button" onclick="window.close()" value="閉じる">
<?php } ?>

</form>
</div>

<div id="footer">
</div>

</body>
</html>
