<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF8">
</head>
<body>
<form method="post" name="load_lms_teacher" action="load_lms_teacher.php">
<?php
ini_set( 'display_errors', 0 );
require_once("./const/const.inc");
require_once("./func.inc");

$update_check = $_POST['update_check']? $_POST['update_check']: array();
$insert_check = $_POST['insert_check']? $_POST['insert_check']: array();

$course_list = get_course_list($db);
$current_teacher_list = get_teacher_list($db,array(),array(),array(),1);

$errArray = array();

try {
	
	$stmt = $dbc->query("SELECT te.*, ".
			"LPAD(GROUP_CONCAT(CASE WHEN tag.tag_key='teacher_no' THEN tag.tag_value ELSE '' END SEPARATOR ''),6,'0') AS teacher_no, ".
			"GROUP_CONCAT(CASE WHEN tag.tag_key='lesson' THEN tag.tag_value ELSE '' END SEPARATOR ',') AS lesson,".
			"tag.tag_value,us.email ".
			"FROM teachers AS te,user_tags AS tag,users AS us ".
			"WHERE te.user_id=us.id AND tag.user_id=us.id GROUP BY tag.user_id");
	$lms_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$insert_no = 1;
	if ($_POST['update_button'])	$db->beginTransaction();

	foreach ($lms_teachers as $lms_teacher) {
		
		$teacher_no 										= ($lms_teacher['teacher_no'][0]=='1')? substr($lms_teacher['teacher_no'],1)+0: 0;
		
		if ($teacher_no)
			$teacher_array = $current_teacher_list[$teacher_no];
		else
			$teacher_array = array();
		
		$teacher_array["id"]						= $lms_teacher['user_id'];
		$teacher_array["teacher_id"]		= $lms_teacher['id'];
		$lms_teacher['name_last'] = trim($lms_teacher['name_last']); $lms_teacher['name_first'] = trim($lms_teacher['name_first']);
		$lms_teacher['kana_last'] = trim($lms_teacher['kana_last']); $lms_teacher['kana_first'] = trim($lms_teacher['kana_first']);
		$teacher_array["name"]					= $lms_teacher['name_last']?($lms_teacher['name_first']?"{$lms_teacher['name_last']} {$lms_teacher['name_first']}":"{$lms_teacher['name_last']}"):"{$lms_teacher['name_first']}";
		$teacher_array["furigana"]			= $lms_teacher['kana_last']?($lms_teacher['kana_first']?"{$lms_teacher['kana_last']} {$lms_teacher['kana_first']}":"{$lms_teacher['kana_last']}"):"{$lms_teacher['kana_first']}";
		$teacher_array["furigana"]      = mb_convert_kana($teacher_array["furigana"],'c');
		$teacher_array["del_flag"]			= ($lms_teacher['status'] == 'regular')? 0: 2;
		$teacher_array["mail_address"]	= (strpos($lms_teacher['email'],'@')!==false)? $lms_teacher['email']: '';
		$lesson_list = array_unique(explode(',',$lms_teacher['lesson']));
		foreach ($lesson_list as $key => $val) if (!$val) unset($lesson_list[$key]);
		sort($lesson_list);
		$teacher_array["lesson_id"]			= $lesson_list[0]+0;
		$teacher_array["lesson_id2"]		= $lesson_list[1]+0;

//		var_dump($teacher_array);echo'<br>';

		if ($teacher_no) {
			
			$str0 = "<td><input type=\"checkbox\" name=\"update_check[]\" value=\"$teacher_no\"></td>";
			$str1 = "<td>{$teacher_array['name']}</td><td>{$teacher_array['id']}</td><td>{$teacher_array['teacher_id']}</td><td>{$teacher_no}</td>";
			if (!$current_teacher_list[$teacher_no]) {
				$str2 = '';
				foreach ($teacher_array as $key=>$value) $str2 .= " $key:$value, ";
				$str0 = "<td><input type=\"checkbox\" name=\"insert_check[]\" value=\"$insert_no\"></td>";
				if (array_search($insert_no, $insert_check)!==false)
					if (insert_teacher($db,$teacher_array,$teacher_no))	$str0='<td>OK</td>'; else $str0='<td>ERROR!!</td>';
				$str1 = "<td>{$teacher_array['name']}</td><td>{$teacher_array['id']}</td><td>{$teacher_array['teacher_id']}</td><td>";
				foreach ($teacher_array as $key=>$value) $str1 .= " $key:$value, ";
				$new_list[] = "$str0$str1</td>\n";
				$insert_no++;
				continue;
			}
			
			$diff1 = '';
			foreach ($teacher_array as $key=>$value) {
				if ($key=='id' || $key=='teacher_id')	continue;
				$value0 = $current_teacher_list[$teacher_no][$key];
//				if ($key=='furigana' && $value0)	$value0 = mb_convert_kana($value0,'C');
				if ($key=='lesson_id') {
					$array1 = array($teacher_array['lesson_id'],$teacher_array['lesson_id2']); sort($array1);
					$array2 = array($current_teacher_list[$teacher_no]['lesson_id'],$current_teacher_list[$teacher_no]['lesson_id2']); sort($array2);
					if ($array1 == $array2)	continue;
					$diff1 .= " $key:$value0->$value, lesson_id2:{$current_teacher_list[$teacher_no]['lesson_id2']}->{$teacher_array['lesson_id2']}, ";
					continue;
				} else if ($key=='lesson_id2')	continue;
				if ($value != $value0)	$diff1 .= " $key:$value0->$value, ";
			}
			if ($diff1) {
				$teacher_array = $teacher_array + $current_teacher_list[$teacher_no];
				if (array_search($teacher_no, $update_check)!==false)
					if (update_teacher($db, $teacher_array))	$str0='<td>OK</td>'; else $str0='<td>ERROR!!</td>';
					$update_list[] = "$str0$str1<td>$diff1</td>\n";
			}
			
			unset($current_teacher_list[$teacher_no]);
			
		}
	}

	if ($_POST['update_button'])	$db->commit();

	foreach ($current_teacher_list as $teacher_no=>$teacher) {
		$str1 = "{$teacher['name']}: ";
		foreach ($teacher as $key=>$value) $str1 .= " $key:$value, ";
		$drop_list[] = "$str1.<br>\n";
	}
	
	echo "<br>update list<br><table border=\"1\"><tr><td></td><td>name</td><td>user_id</td><td>teacher_id</td><td>teacher_no</td><td>update</td></tr>";
	foreach ($update_list as $val)	echo "<tr>$val</tr>";
	foreach ($family_list as $val)	echo "<tr>$val</tr>";
	echo "</table>";
	echo "<br>new list<br><table border=\"1\"><tr><td></td><td>name</td><td>user_id</td><td>teacher_id</td><td>data</td></tr>";
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
