<?php
require_once("./const/const.inc");
require_once("./func.inc");

$year=$_GET['y'];
$month=$_GET['m'];
$nextlink = $_GET['nextlink'];

$stmt = $db->query("SELECT update_timestamp FROM tbl_statement WHERE seikyu_year=$year and seikyu_month=$month");
$update_timestamp = $stmt->fetchColumn();
$update_timestamp = substr($update_timestamp, 0, 16);

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=EUC-JP">
<script type = "text/javascript">
<!--
//-->
</script>
<link rel="stylesheet" type="text/css" href="./script/style.css">
</head>
<body>
<br><br>
<h3>
<?= $year ?>年<?= $month ?>月請求データ作成日：　<?= $update_timestamp ?><br><br>

この日以降に対象月の請求登録変更がある場合は請求データの更新を先に実行する必要があります。<br><br>
請求データを更新しますか？<br>
</h3>
<form name="form1" method="get" action="save_statement.php">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
　　<input type="submit" value="はい、請求データを更新します"><br>
</form>
<form name="form2" method="get" action="./<?= $nextlink ?>">
<input type="hidden" name="y" value="<?= $year ?>">
<input type="hidden" name="m" value="<?= $month ?>">
　　<input type="submit" value="いいえ、処理を進めます"><br>
</form>
</body>
</html>
