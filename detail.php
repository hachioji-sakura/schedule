<?php
ini_set( 'display_errors', 0 );

require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");
require_once("./calculate_fees.php");
$result = check_user($db, "1");

$year = $_GET["y"];
$month = $_GET["m"];
$member_no = $_GET["no"];

$member = get_member($db, array("tbl_member.no = ?"), array($member_no));

$errArray = array();

$calculator = new calculate_fees();
$result = $calculator->calculate($member_no, $year, $month);
if ($result == false) {
	array_push($errArray, "月謝計算中にエラーが発生しました。");
}
$total_hours = $calculator->get_total_hours();
$total_fees = $calculator->get_total_fees();
$entrance_fee = $calculator->get_entrance_fee();
$membership_fee = $calculator->get_membership_fee();
$textbook_price = $calculator->get_textbook_price();
//$others_price = $calculator->get_others_price();
$divided_price = $calculator->get_divided_price();
$simple_total_price = $calculator->get_simple_total_price();
$consumption_tax_price = $calculator->get_consumption_tax_price();
$last_total_price = $calculator->get_last_total_price();
$member_array = $calculator->get_member_array();
$lesson_detail_list = $calculator->get_lesson_detail_list();
$buying_textbook_list = $calculator->get_buying_textbook_list();
$others_list = $calculator->get_others_list();
$divided_payment_list = $calculator->get_divided_payment_list();

if ($divided_price > 0) {
//$total_fees = $total_fees - ($divided_price*5);
}

errMsgFileCheck($errArray);

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<link rel="stylesheet" type="text/css" href="./script/print.css" media="print" />
<script type="text/javascript" src="./script/calender.js"></script>
<script type = "text/javascript">
<!--
function back() {
	document.back_form.submit();
}
//-->
</script>
</head>
<body>
<!-- 印刷しない範囲ここから -->
<div id="header" class="noprint">
	事務システム
</div>
<!-- 印刷しない範囲ここまで -->

<div id="content">

<center>
<!-- 印刷しない範囲ここから -->
	<div class="noprint">
		<h3>生徒の月謝計算 - 明細</h3>
		<?php
		if ($_SESSION['login']['kind'] == "1") {
			//if ($_SESSION['login']['id'] != $_SESSION['member_id']) {
		?> 
			<div>
				<a href="#" onclick="javascript:back()">生徒一覧へ戻る</a>&nbsp;&nbsp;
				<a href="menu.php">メニューへ戻る</a>&nbsp;&nbsp;
				<a href="#" onclick="window.print(); return false;">印刷する</a>&nbsp;&nbsp;
			</div>
		<?php
			//}
		}
		?>
	</div>
<!-- 印刷しない範囲ここまで -->
</center>

<!-- 印刷範囲ここから -->
<!--
<div class="pagebreak">
-->
<?php
	if (count($errArray) > 0)
		foreach( $errArray as $error)
			echo "<font color=\"red\" size=\"3\">$error</font><br>";
?>
<div>
<h3><?= $member_array["name"] ?>様</h3>
<table>
<tr>
<td colspan="3"><font size="4"><?= $year ?>年<?= $month ?>月</font></td>
</tr>
<tr>
<td><font size="4">授業時間：</font></td><td align="right"><font size="4"><?=$total_hours?>時間</font></td><td></td>
</tr>
<?php
if ($total_fees > 0)  {
?>
<tr>
<td><font size="4">授業料金：</font></td><td align="right"><font size="4"><?= $total_fees?>円</font></td><td></td>
</tr>
<?php
}
?>
<?php
foreach ($divided_payment_list as $divided_payment) {
?>
<tr>
<td><font size="4">授業料金分割払い：</font></td><td align="right"><font size="4"><?=$divided_payment["price"]?>円</td><td align="left"><?=$divided_payment["memo"]?></td>
</tr>
<?php
}
?>
<?php
// 入会金
if ($entrance_fee > 0) {
?>
<tr>
<td><font size="4">入会金：</font></td><td align="right"><font size="4"><?=$entrance_fee?>円</td><td></td>
</tr>
<?php
}
?>
<?php
if ($membership_fee > 0) {
?>
<tr>
<td><font size="4">月会費：</font></td><td align="right"><font size="4"><?=$membership_fee?>円</td><td></td>
</tr>
<?php
}
?>
<?php
if ($member["tax_flag"] == "1") {
?>
<tr>
<td><font size="4">合計金額：</font></td><td align="right"><font size="4"><?= $simple_total_price?>円</font></td><td></td>
</tr>
<tr>
<td><font size="4">消費税：</font></td><td align="right"><font size="4"><?= $consumption_tax_price?>円</font></td><td></td>
<?php
}
?>
<?php
//if ($textbook_price > 0 && $total_fees > 0) {
if ($textbook_price > 0) {
?>
<tr>
<td><font size="4">テキスト代（税込）：</font></td><td align="right"><font size="4"><?=$textbook_price?>円</td><td></td>
</tr>
<?php
}
?>
<?php
// その他項目
foreach ($others_list as $others_array) {
?>
<tr>
<td><font size="4"><?= $others_array["name"]; ?>：</font></td><td align="right"><font size="4"><?=$others_array["price"]?>円</td><td align="left"><?=$others_array["memo"]?></td>
</tr>
<?php
}
//}
?>
<tr>
<td><font size="4" color="red">総合計金額：</font></td><td align="right"><font size="4" color="red"><?= $last_total_price?>円</font></td><td></td>
</tr>
</table>
<br><br>

<?php
$absent_flag1 = false;
$absent_flag2 = false;

foreach ($lesson_detail_list as $lesson) {
?>
<table>
<tr>
<td><font size="4">■&nbsp;<?php echo $lesson["lesson_name"]; ?>&nbsp;&nbsp;
	<?php echo $lesson["subtotal_hours"]; ?>時間&nbsp;&nbsp;
	<?php echo str_replace('.00','',$lesson["subtotal_fees"]); ?>円</font></td>
</tr>
</table>
<?php
if (count($lesson["event_list"]) > 0) {
?>
<table class="meisai" cellpadding="2">
	<tr>
	<th class="meisai">日付</th><th class="meisai">時刻</th><th class="meisai">科目</th><th class="meisai">タイプ</th>
	<th class="meisai">時間</th><th class="meisai">単価</th><th class="meisai">料金</th><th class="meisai">&nbsp;</th>
	</tr>
<?php
	foreach ($lesson["event_list"] as $starttimestamp => $event) {
		// 20151230 面談の表示変更
		if (mb_strpos($event["comment"],"面談") !== FALSE) {
?>
			<tr>
				<td width="120" align="left" class="meisai"><?php echo $event["date"]."(".$event["weekday"].")"; ?></td>
				<td width="140" align="left" class="meisai"><?= $event["time"] ?></td>
				<td width="150" align="left" class="meisai">―</td>
				<td width="220" align="left" class="meisai">―</td>
				<td width="100" align="center" class="meisai">―</td>
				<td width="80" align="center" class="meisai">―</td>
				<td width="80" align="center" class="meisai">―</td>
				<td width="250" align="left" class="meisai"><?= $event["comment"]?></td>
			</tr>
<?php
		} else {
?>
			<tr>
				<td width="120" align="left" class="meisai"><?php echo $event["date"]."(".$event["weekday"].")"; ?></td>
				<td width="140" align="left" class="meisai"><?= $event["time"] ?></td>
				<td width="150" align="left" class="meisai"><?= $event["subject_name"] ?></td>
				<td width="220" align="left" class="meisai"><?= $event["course_name"] ?></td>
				<td width="100" align="center" class="meisai"><?= preg_replace('/^(\d)$/','$1.00',$event["diff_hours"]) ?>時間</td>
				<td width="100" align="right" class="meisai"><?php 
//					if ($event["subject_name"]=='演習' || $event['monthly_fee_flag']) {echo "―";} else {echo $event['fee_for_an_hour']."円";}
					if ($event['monthly_fee_flag']) {echo "―";} else {echo str_replace('.00','',$event['fee_for_an_hour'])."円";}
					?></td>
				<td width="100" align="right" class="meisai"><?php
					if ($event['monthly_fee_flag']) {echo "―";} else {echo $event['fees']."円";}
					?></td>
				<td width="250" align="left" class="meisai"><?= $event["comment"]?></td>
			</tr>
<?php
		}

		if ($event["absent_flag"] == "1" || $event["absent1_num"] > 0) {
			$absent_flag1 = true;
		}
		if ($event["absent_flag"] == "2" || $event["absent2_num"] > 0) {
			$absent_flag2 = true;
		}
	}
?>
</table>
<?php
}
?>
<br><br>
<?php
}
?>

<div>
<font color="#000000">
<?php
if ($absent_flag1 === true) {
?>
お休み１= 授業料が発生しないお休み。<br>
<?php
}
if ($absent_flag2 === true) {
?>
お休み２= 授業料が発生するお休み。<br>
</font>
</div>
<?php
}
?>
</div>

<br>
<?php
// テキスト代
if ($textbook_price > 0) {
?>
<table>
<tr>
<td><font size="4">■&nbsp;テキスト代（税込）&nbsp;&nbsp;
	<?php echo $textbook_price; ?>円</font></td>
</tr>
</table>
<?php
?>
<table class="meisai" border="1" cellpadding="2">
	<tr>
<!--
<th class="meisai">購入日</th>
-->
	<th class="meisai">テキスト名</th><th class="meisai">金額</th>
	</tr>
<?php
 	foreach ($buying_textbook_list as $buying) {
?>
<!--
			<td width="80" align="right" class="meisai"><?= $buying["fees"] ?></td>
-->
			<td width="200" align="left" class="meisai"><?= $buying["name"] ?></td>
			<td width="100" align="right" class="meisai"><?= number_format($buying["price"])?>円</td>
		</tr>
<?php
	}
}
?>
<!-- 印刷範囲ここまで -->


<form name="back_form" method="post" action="student_list.php">
<input type="hidden" name="y" value="<?=$year?>">
<input type="hidden" name="m" value="<?=$month?>">
</form> 



</div>

</body></html>

