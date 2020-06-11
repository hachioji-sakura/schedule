<?php
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");

if (isset($_POST['button'])) {
		$result = login($db, $_POST['id'], $_POST['passwd']);
		// ログインエラーの時、$resultにfalseを格納する
		// ログイン成功の時、$resultに$login=array("id"=>*, "name"=>*, "kind"=>*)を格納する
		if ($result != false) {
			if ($result['kind'] == 1) {
				header('location: menu.php');
				exit();//忘れずに
			} else {
				$_SESSION['member_id'] = $result['id'];
				header('location: calender.php');
				exit();//忘れずに
			}
		}
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="./script/style.css">
<script type="text/javascript" src="./script/calender.js"></script>
</head>
<body>
<div id="header">
	事務システム
</div>

<div id="content" align="center">

<form method="post" action="login.php">
<table>
	<tr>
		<th>ID</th>
		<td>
			<input type="text" name="id">
		</td>
	</tr>
	<tr>
		<th>パスワード</th>
		<td>
			<input type="password" name="passwd">
		</td>
	</tr>
	<tr>
		<td colspan="2" align="center">
			<input type="submit" value="ログイン" name="button">
		</td>
	</tr>
</table>
</form>
<!--
現在メンテナンス中です。<br>
ご不便をおかけし申し訳ありません。<br>
-->
</div>

</body></html>

