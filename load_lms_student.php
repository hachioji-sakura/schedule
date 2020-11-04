<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
</head>
<body>
<form method="post" name="load_lms_student" action="load_lms_student.php">
<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");

$update_check = $_POST['update_check']? $_POST['update_check']: array();
$insert_check = $_POST['insert_check']? $_POST['insert_check']: array();

$grade_list = array('toddler'=>1,'e1'=>2,'e2'=>3,'e3'=>4,'e4'=>5,'e5'=>6,'e6'=>7,'j1'=>8,'j2'=>9,'j3'=>10,'h1'=>11,'h2'=>12,'h3'=>13,'adult'=>14);

$course_list = get_course_list($db);
$teacher_list = get_teacher_list($db);
$current_student_list = get_member_list($db, array("(tbl_member.del_flag = '0' or tbl_member.del_flag = '2' or tbl_member.del_flag = '3' or tbl_member.del_flag = '4')"), array(), array(), '2');

$errArray = array();

try {

	$stmt = $dbc->query("SELECT st.*, ".
			"LPAD(GROUP_CONCAT(CASE WHEN tag.tag_key='student_no' THEN tag.tag_value ELSE '' END SEPARATOR ''),6,'0') AS student_no, ".
			"GROUP_CONCAT(CASE WHEN tag.tag_key='grade' THEN tag.tag_value ELSE '' END SEPARATOR '') AS grade, ".
			"GROUP_CONCAT(CASE WHEN tag.tag_key='grade_adj' THEN tag.tag_value ELSE '' END SEPARATOR '') AS grade_adj, ".
			"GROUP_CONCAT(CASE WHEN tag.tag_key='student_type' THEN tag.tag_value ELSE '' END SEPARATOR '') AS student_type, ".
			"par.id as parent_id,".
			"us.email ".
			"FROM students AS st,user_tags AS tag,student_relations as rel,student_parents as par,users AS us ".
			"WHERE tag.user_id=st.user_id ".
//			"AND st.user_id=797 ".
			"AND rel.student_id=st.id ".
			"AND par.id=rel.student_parent_id ".
			"AND us.id=par.user_id ".
			"GROUP BY tag.user_id "
			);
	$lms_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$insert_no = 1;
	if ($_POST['update_button'])	$db->beginTransaction();

	foreach ($lms_students as $lms_student) {

		$student_array = array();
		$student_no 										= $lms_student['student_no'];
		$student_array["id"]						= $lms_student['user_id'];
		$student_array["student_id"]		= $lms_student['id'];
		$lms_student['name_last'] = trim($lms_student['name_last']); $lms_student['name_first'] = trim($lms_student['name_first']);
		$lms_student['kana_last'] = trim($lms_student['kana_last']); $lms_student['kana_first'] = trim($lms_student['kana_first']);
		$student_array["name"]					= $lms_student['name_last']?($lms_student['name_first']?"{$lms_student['name_last']} {$lms_student['name_first']}":"{$lms_student['name_last']}"):"{$lms_student['name_first']}";
		$student_array["furigana"]			= $lms_student['kana_last']?($lms_student['kana_first']?"{$lms_student['kana_last']} {$lms_student['kana_first']}":"{$lms_student['kana_last']}"):"{$lms_student['kana_first']}";
		$student_array["furigana"]      = mb_convert_kana($student_array["furigana"],'c');
//		$student_array["sei"]						= $lms_student['kana_last'];
//		$student_array["mei"]						= $lms_student['kana_first'];
		$student_array["grade"]					= $grade_list[$lms_student['grade']];
		if (!$student_array["grade"])	$student_array["grade"] = 0;
//		$student_array["membership_fee"];
		$student_array["del_flag"]			= ($lms_student['status'] == 'regular')? 0: (($lms_student['status'] == 'unsubscribe')? 2: (($lms_student['status'] == 'trial')? 4: $lms_student['status']));
		$student_array["jyukensei"]     = (strpos($lms_student['student_type'],'juken')!==false)? 1: 0;
		$birth_day = explode('-', $lms_student['birth_day']);
		if ($birth_day[0]!=9999) {
			$student_array["birth_year"]		= $birth_day[0];
			$student_array["birth_month"]		= $birth_day[1];
			$student_array["birth_day"]			= $birth_day[2];
		} else {
			$student_array["birth_year"]		= 0;
			$student_array["birth_month"]		= 0;
			$student_array["birth_day"]			= 0;
		}
		$student_array["grade_adj"]			= $lms_student['grade_adj']+0;
		$student_array["fee_free"]      = (strpos($lms_student['student_type'],'fee_free')!==false)? 1: 0;
//		$student_array["yuge_price"];
		$student_array["gender"]				= ($lms_student['gender'] == 1)? 'M': (($lms_student['gender'] == 2)? 'F': 0);
		$student_array["mail_address"]	= (strpos($lms_student['email'],'@')!==false)? $lms_student['email']: '';
//		var_dump($student_array);echo'<br>';

		if ($student_no) {
			
			$str0 = "<td><input type=\"checkbox\" name=\"update_check[]\" value=\"$student_no\"></td>";
			$str1 = "<td>{$student_array['name']}</td><td>{$student_array['id']}</td><td>{$student_array['student_id']}</td><td>{$student_no}</td>";
			if (!$current_student_list[$student_no]) {
				$str2 = '';
				foreach ($student_array as $key=>$value) $str2 .= " $key:$value, ";
					
				$student_array["tax_flag"] = 1;
				if ($student_array["del_flag"]!==0 && $student_array["del_flag"]!==2) $student_array["del_flag"] = 4;
				$str0 = "<td><input type=\"checkbox\" name=\"insert_check[]\" value=\"$insert_no\"></td>";
				if (array_search($insert_no, $insert_check)!==false)
					if (insert_student($db,$student_array,$student_no))	$str0='<td>OK</td>'; else $str0='<td>ERROR!!</td>';
				$str1 = "<td>{$student_array['name']}</td><td>{$student_array['id']}</td><td>{$student_array['student_id']}</td><td>";
				foreach ($student_array as $key=>$value) $str1 .= " $key:$value, ";
				$new_list[] = "$str0$str1</td>\n";
				$insert_no++;

				continue;
			}
			
			$diff1 = '';
			foreach ($student_array as $key=>$value) {
				if ($key=='id' || $key=='student_id')	continue;
				$value0 = $current_student_list[$student_no][$key];
//				if ($key=='furigana' && $value0)	$value0 = mb_convert_kana($value0,'C');
				if ($value != $value0)	$diff1 .= " $key:$value0->$value, ";
			}
			
			if ($diff1) {
				$student_array = $student_array + $current_student_list[$student_no];
				if (array_search($student_no, $update_check)!==false)
					if (update_student($db, $student_array))	$str0='<td>OK</td>'; else $str0='<td>ERROR!!</td>';
				if (mb_substr_count($student_array['name'],' ') <= 1)
					$update_list[] = "$str0$str1<td>$diff1</td>\n";
				else
					$family_list[] = "$str0$str1<td>$diff1</td>\n";
			}
			
			unset($current_student_list[$student_no]);
	
		}
	}

	if ($_POST['update_button'])	$db->commit();

	foreach ($current_student_list as $student_no=>$student) {
		$str1 = "{$student['name']}: ";
		foreach ($student as $key=>$value) $str1 .= " $key:$value, ";
		$drop_list[] = "$str1.<br>\n";
	}
	
	echo "<br>update list<br><table border=\"1\"><tr><td></td><td>name</td><td>user_id</td><td>student_id</td><td>student_no</td><td>update</td></tr>";
	foreach ($update_list as $val)	echo "<tr>$val</tr>";
	foreach ($family_list as $val)	echo "<tr>$val</tr>";
	echo "</table>";
	echo "<br>new list<br><table border=\"1\"><tr><td></td><td>name</td><td>user_id</td><td>student_id</td><td>data</td></tr>";
	foreach ($new_list as $val)			echo "<tr>$val</tr>";
	echo "</table>";
//	echo "<br>null list<br><table border=\"1\"><tr><td>name</td><td>user_id</td><td>student_id</td><td>student_no</td><td>data</td></tr>";
//	foreach ($null_list as $val)		echo "<tr>$val</tr>";
//	echo "</table>";
//	echo "<br>trial list<br><table border=\"1\"><tr><td>name</td><td>user_id</td><td>student_id</td><td>student_no</td><td>data</td></tr>";
//	foreach ($trial_list as $val)		echo "<tr>$val</tr>";
//	echo "</table>";
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
