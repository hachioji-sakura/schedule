<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
</head>
<body>
<form method="post" name="load_lms_staff" action="load_lms_staff.php">
<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");

$update_check = $_POST['update_check']? $_POST['update_check']: array();
$insert_check = $_POST['insert_check']? $_POST['insert_check']: array();
$current_staff_list = get_staff_list($db,array(),array(),array(),1);
$current_staff_name_list = array_column($current_staff_list,'name','no');
$errArray = array();

$bank_account_type_tbl = array('normal'=>1,'current'=>2,'savings'=>4);

try {
	
	$stmt = $dbc->query("SELECT st.*, ".
			"LPAD(GROUP_CONCAT(CASE WHEN tag.tag_key='manager_no' THEN tag.tag_value ELSE '' END SEPARATOR ''),6,'0') AS staff_no, ".
			"tag.tag_value,us.email ".
			"FROM managers AS st,user_tags AS tag,users AS us ".
			"WHERE st.user_id=us.id AND tag.user_id=us.id GROUP BY tag.user_id");
	$lms_staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$insert_no = 1;
	if ($_POST['update_button'])	$db->beginTransaction();

	foreach ($lms_staffs as $lms_staff) {
		
		$staff_no 										= ($lms_staff['staff_no'][0]=='2')? substr($lms_staff['staff_no'],1)+0: 0;
		
		if ($staff_no>47)	$staff_no = $staff_no+1;
		
		if ($staff_no)
			$staff_array = $current_staff_list[$staff_no];
		else
			$staff_array = array();
		
//		var_dump($lms_staff);echo'<br>';

		$staff_array["id"]						= $lms_staff['user_id'];
		$staff_array["staff_id"]			= $lms_staff['id'];
		$lms_staff['name_last'] = trim($lms_staff['name_last']); $lms_staff['name_first'] = trim($lms_staff['name_first']);
		$lms_staff['kana_last'] = trim($lms_staff['kana_last']); $lms_staff['kana_first'] = trim($lms_staff['kana_first']);
		$staff_array["name"]					= $lms_staff['name_last']?($lms_staff['name_first']?"{$lms_staff['name_last']} {$lms_staff['name_first']}":"{$lms_staff['name_last']}"):"{$lms_staff['name_first']}";
		$staff_array["furigana"]			= $lms_staff['kana_last']?($lms_staff['kana_first']?"{$lms_staff['kana_last']} {$lms_staff['kana_first']}":"{$lms_staff['kana_last']}"):"{$lms_staff['kana_first']}";
		$staff_array["furigana"]      = mb_convert_kana($staff_array["furigana"],'c');
		$staff_array["del_flag"]			= ($lms_staff['status'] == 'regular')? 0: 2;
		$staff_array["mail_address"]	= (strpos($lms_staff['email'],'@')!==false)? $lms_staff['email']: '';
		$staff_array["bank_no"]          = $lms_staff['bank_no'];
		$staff_array["bank_branch_no"]   = $lms_staff['bank_branch_no'];
		$staff_array["bank_acount_type"] = $bank_account_type_tbl[$lms_staff['bank_account_type']];
		$staff_array["bank_acount_no"]   = $lms_staff['bank_account_no'];
		$staff_array["bank_acount_name"] = $lms_staff['bank_account_name'];

//		var_dump($staff_array);echo'<br><br>';

		$str3 = '';
		if (!$staff_no) {
			$staff_no = array_search($staff_array["name"], $current_staff_name_list);
			if ($staff_no)	$str3 = ' (lms staff_no missing!) ';
		}
		if ($staff_no) {
			
			$str0 = "<td><input type=\"checkbox\" name=\"update_check[]\" value=\"$staff_no\"></td>";
			$str1 = "<td>{$staff_array['name']}</td><td>{$staff_array['id']}</td><td>{$staff_array['staff_id']}</td><td>{$staff_no}{$str3}</td>";
			if (!$current_staff_list[$staff_no]) {
				$str2 = '';
				foreach ($staff_array as $key=>$value) $str2 .= " $key:$value, ";
				$str0 = "<td><input type=\"checkbox\" name=\"insert_check[]\" value=\"$insert_no\"></td>";
				if (array_search($insert_no, $insert_check)!==false)
					if (insert_staff($db,$staff_array, $staff_no))	$str0='<td>OK</td>'; else $str0='<td>ERROR!!</td>';
				$str1 = "<td>{$staff_array['name']}</td><td>{$staff_array['id']}</td><td>{$staff_array['staff_id']}</td><td>";
				foreach ($staff_array as $key=>$value) $str1 .= " $key:$value, ";
				$new_list[] = "$str0$str1</td>\n";
				$insert_no++;
				continue;
			}
			
			$diff1 = '';
			foreach ($staff_array as $key=>$value) {
				if ($key=='id' || $key=='staff_id')	continue;
				$value0 = $current_staff_list[$staff_no][$key];
//				if ($key=='furigana' && $value0)	$value0 = mb_convert_kana($value0,'C');
				if ($value != $value0)	$diff1 .= " $key:$value0->$value, ";
			}
			if ($diff1) {
				$staff_array = $staff_array + $current_staff_list[$staff_no];
				if (array_search($staff_no, $update_check)!==false)
					if (update_staff($db, $staff_array))	$str0='<td>OK</td>'; else $str0='<td>ERROR!!</td>';
					$update_list[] = "$str0$str1<td>$diff1</td>\n";
			}
			
			unset($current_staff_list[$staff_no]);
			
		}
	}

	if ($_POST['update_button'])	$db->commit();

	foreach ($current_staff_list as $staff_no=>$staff) {
		$str1 = "{$staff['name']}: ";
		foreach ($staff as $key=>$value) $str1 .= " $key:$value, ";
		$drop_list[] = "$str1.<br>\n";
	}
	
	echo "<br>update list<br><table border=\"1\"><tr><td></td><td>name</td><td>user_id</td><td>staff_id</td><td>staff_no</td><td>update</td></tr>";
	foreach ($update_list as $val)	echo "<tr>$val</tr>";
	foreach ($family_list as $val)	echo "<tr>$val</tr>";
	echo "</table>";
	echo "<br>new list<br><table border=\"1\"><tr><td></td><td>name</td><td>user_id</td><td>staff_id</td><td>data</td></tr>";
	foreach ($new_list as $val)			echo "<tr>$val</tr>";
	echo "</table>";
	echo "<br>drop list<br>";		foreach ($drop_list as $val)	echo $val;
	
} catch (Exception $e) {
	echo $e->getMessage().'<br>';
}

/*
function ascToHex($string)
{
    $hex = dechex(ord($string));
    if (strlen($hex) == 1) $hex = '0' . $hex;
    $string = '\\' . $hex;
    return $string;
}
function strDump($str)
{
		foreach(preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY) as $char) echo ascToHex($char);echo"<BR>";
}	
*/
?>
<br>
<input type="submit" name="update_button" value="更新実行">
</form>
</body>
</html>
