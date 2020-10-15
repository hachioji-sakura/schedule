<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./array_column.php");
require_once("./const/login_func.inc");
$result = check_user($db, "1");

try {

$year = $_GET["y"];
$month = $_GET["m"];
if ((is_null($year) == true || $year == "") || (is_null($month) == true || $month == "")) {
	throw new Exception('年月が不明です。');
}

$member_list = get_member_list($db,array(),array(),array(),1);

$stmt = $db->prepare("SELECT * FROM tbl_mibarai WHERE year=? AND month=?");
$stmt->execute(array($year, $month));
$mibarai_array = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($mibarai_array as &$item) {
	$no_array = explode(',', $item['nos']);
	$name_array = array(); $furigana_array =array();
	foreach ($no_array as $no) {
		$name_array[] = $member_list[$no]['name'];
		$furigana_array[] = $member_list[$no]['furigana'];
	}
	$item['name'] = implode(',', $name_array);
	$item['furigana'] = implode(',', $furigana_array);
}
unset($item);

array_multisort(array_column($mibarai_array,'furigana'), SORT_ASC, SORT_NATURAL, $mibarai_array);

} catch (Exception $e) {
	$db->rollback();
	// 処理を中断するほどの致命的なエラー
	array_push($errArray, $e->getMessage());
}

?>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
<meta name="robots" content="noindex,nofollow">
<title>事務システム　督促</title>
<script type = "text/javascript">
function confirm() {
	return window.confirm("未払い金の督促メールを一括送信します。よろしいですか？");
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

<h3>督促　　<?= $year ?>年<?= $month ?>月</h3>

<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br><br>
<?php
		}
?>
	<a href="../schedule/menu.php">メニューへ戻る</a>
	<br>
<?php
	exit();
	}
?>

<a href="../bank-check/bank-check.php?y=<?= $year ?>&m=<?= $month ?>">振込請求一覧へ</a>&nbsp;&nbsp;
<a href="menu.php">メニューへ戻る</a><br><br>

<form method="post" name="tokusoku_list" action="">

<table border="1">
<tr>
<th>生徒名</th>
<th>未払い金額</th>
<th>前月請求-未払い</th>
<th>メールアドレス</th>
</tr>
<?php
foreach ($mibarai_array as $item) {
?>
	<tr>
		<td><?= $item["name"] ?></td>
		<td align="right"><?= number_format($item["mibarai"]) ?></td>
		<td align="right"><?= number_format($item["last_total_price"]-$item["mibarai"]) ?></td>
		<td align="left"><?= $member_list[explode(',', $item['nos'])[0]]['mail_address'] ?></td>
	</tr>
<?php
}
?>
</table>

</form>
</div>

<div id="footer">
</div>

</body>
</html>
