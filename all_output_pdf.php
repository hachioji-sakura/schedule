<?php
ini_set( 'display_errors', 0 );
//ini_set('display_errors', 'on');
//error_reporting(E_ALL);
//error_reporting(E_ALL ^E_NOTICE ^E_DEPRECATED);	// 警告が出るとpdfに出力できない
require_once("./const/const.inc");
require_once("./func.inc");
require_once("./const/login_func.inc");

ini_set('include_path', FPDF_PATH);
//require_once "Google/autoload.php";
//require_once('../vendor/autoload.php');
//use Google\Spreadsheet\DefaultServiceRequest;
//use Google\Spreadsheet\ServiceRequestFactory;
//set_time_limit(60);
//require_once("./google_drive_util.php");

//require('fpdf17/mbfpdf.php');	// require_once('./mbfpdf.php');はダメ。
require_once('../tfpdf/tfpdf.php');
require_once("./calculate_fees.php");

$result = check_user($db, "1");

$year = $_GET["y"];
$month = $_GET["m"];
//$member_no = $_GET["no"];

// PDFファイルを出力
//$pdf=new MBFPDF();
//$pdf = new MBFPDF('L', 'mm', 'A4');	//「P」は縦長、「L」は横長
$pdf = new tFPDF('L', 'mm', 'A4');	//「P」は縦長、「L」は横長
//$first_x = 20;	// 左上の座標
//$first_y = 20;	// 左上の座標
$cell_h = 7;		// セルの高さ（mm）
$cell_narrow_w = 20;	// セルの幅（mm）
$cell_w = 35;					// セルの幅（mm）
$cell_wide_w = 140;		// セルの幅（mm）

// マージン設定
//$pdf->SetMargins($first_x, $first_y, 30.0);
//$pdf->SetMargins(30.0, 30.0, 30.0);
$left_margin = 10.0;
$top_margin = 10.0;
$right_margin = 10.0;
$bottom_margin = 10.0;
$pdf->SetMargins($left_margin, $top_margin, $right_margin);

// フォント設定
// ２つ目は出力文字コードSJIS。FPDFはUTF8で出力できないためSJISに
//$pdf->AddMBFont(GOTHIC ,'SJIS');
//$pdf->AddMBFont(PGOTHIC,'SJIS');
$pdf->AddFont('IPAG','','ipag.ttf',true);

// タイトル設定	
//$pdf->SetTitle('月謝計算_'.$member_array["name"].'_'.$_GET["y"].'年'.$_GET["m"].'月');
//$pdf->SetTitle('fees_'.$_GET["y"].'_'.sprintf('%02d',$_GET["m"]));

$errArray = array();

$student_list = array();
//$member_list = get_simple_member_list($db, array("kind = ?","name <> ?","no='001239'"), array("3","体験生徒"));
$member_list = get_simple_member_list($db, array("kind = ?","name <> ?"), array("3","体験生徒"), array(), 1);
$student_list = get_calculated_list($member_list, $year, $month);
if ($result == false) {
	throw new Exception('月謝計算中にエラーが発生しました。');
}
/*
foreach ($member_list as $member_no => $member) {
	$calculator = new calculate_fees();
	$result = $calculator->calculate($member_no, $year, $month);
	if ($result == false) {
		array_push($errArray, "月謝計算中にエラーが発生しました。");
	}
	$student = array();
	$student["name"] = $member["name"];
	$student["total_hours"] = $calculator->get_total_hours();;
	$student["total_fees"] = $calculator->get_total_fees();
	$student["membership_fee"] = $calculator->get_membership_fee();
	$student["textbook_price"] = $calculator->get_textbook_price();
	$student["others_price"] = $calculator->get_others_price();
	$student["last_total_price"] = $calculator->get_last_total_price();
	$student["lesson_detail_list"] = $calculator->get_lesson_detail_list();
	$student["buying_textbook_list"] = $calculator->get_buying_textbook_list();
	$student["others_list"] = $calculator->get_others_list();
	$student_list[$member["no"]] = $student;
	$calculator = null;
}
uasort($student_list, 'cmp');
*/
foreach ($student_list as $student) {
	
	if (!$student["last_total_price"])	continue;

	// ページ追加（マージンとフォントを継続する）
	// 生徒ごとに新しいページを用意する
	$pdf->AddPage();
	// これをセットしないと文字が出力されない。
	$pdf->SetFont('IPAG','',11);

	// 自動改ページをセットする
	// 下のマージンが20.0mmになったら、自動で改ページする
	$pdf->SetAutoPageBreak(true, $bottom_margin);

	// 左上の座標をセットする
	//$pdf->SetXY($first_x, $first_y);
	//$pdf->SetXY($first_x, $first_y);
	//$pdf->Write(10, convert("テスト様"));

	$pdf->Ln();
	// セルを並べて出力してみる
	// １つ目の引数に0を指定して、右端まで広げる。
	// ４つ目の引数に0を指定して、境界線を消す。
	// ５つ目の引数に2を指定して、出力したセルの下へ移動する
	//　　　　　　　「0」を指定するとセルが出力された右に移動します。「1」を指定すると左端に戻った上でセルが出力された下へ移動します。「2」を指定するとセルのX座標は変化せずに下へ移動します。デフォルトの値は「0」です。 
	// ６つ目の引数は、セル内の位置を指定する。（L、C、R）

	$row_h = 6;
	$pdf->Cell(0, $row_h, convert($student["name"]."様"), 0, 1, 'L');// 左端に戻った上でセルが出力された下へ移動
	$pdf->Ln();
	$pdf->Cell(0, $row_h, convert($year."年".$month."月"), 0, 1, 'L');// 左端に戻った上でセルが出力された下へ移動

//$tmp_divided_payment_array = array();
//if (count($student["divided_payment_list"]) > 0) {
//$tmp_divided_payment_array = $student["divided_payment_list"][0];
//}
//if ($tmp_divided_payment_array['year'] != $year && $tmp_divided_payment_array['month'] != $month)  {
	$pdf->Cell(45, $row_h, convert("授業時間："), 0, 0, 'L');
	$pdf->Cell(20, $row_h, convert($student["total_hours"]."時間 "), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
	if ($student["total_fees"] > 0) {
		$pdf->Cell(45, $row_h, convert("授業料金："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($student["total_fees"]."円"), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
	}
	// 分割払い
	foreach ($student["divided_payment_list"] as $divided_payment_array) {
		$pdf->Cell(45, $row_h, convert("授業料金分割払い："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($divided_payment_array["price"]."円"), 0, 0, 'R');
		$pdf->Cell(0, $row_h, convert($divided_payment_array["memo"]), 0, 1, 'L');// 左端に戻った上でセルが出力された下へ移動
	}
	// 入会金
	if ($student["entrance_fee"] > 0) {
		$pdf->Cell(45, $row_h, convert("入会金："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($student["entrance_fee"]."円"), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
	}
	// 月会費
	if ($student["membership_fee"] > 0) {
		$pdf->Cell(45, $row_h, convert("月会費："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($student["membership_fee"]."円"), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
	}
	// 税抜きの生徒さんの場合
	if ($student["tax_flag"] == "1") {
		$pdf->Cell(45, $row_h, convert("合計金額："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($student["simple_total_price"]."円"), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
		$pdf->Cell(45, $row_h, convert("消費税："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($student["consumption_tax_price"]."円"), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
	}
	// テキスト代
	if ($student["textbook_price"] > 0) {
		$pdf->Cell(45, $row_h, convert("テキスト代："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($student["textbook_price"]."円"), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
	}
	// その他項目
	foreach ($student["others_list"] as $others_array) {
		$pdf->Cell(45, $row_h, convert($others_array["name"]."："), 0, 0, 'L');
		$pdf->Cell(20, $row_h, convert($others_array["price"]."円"), 0, 0, 'R');
		$pdf->Cell(0, $row_h, convert($others_array["memo"]), 0, 1, 'L');// 左端に戻った上でセルが出力された下へ移動
	}
	$pdf->SetTextColor(255, 0, 0);
	$pdf->Cell(45, $row_h, convert("総合計金額："), 0, 0, 'L');
	//$pdf->SetFont(GOTHIC,'B');
	$pdf->Cell(20, $row_h, convert($student["last_total_price"]."円"), 0, 1, 'R');// 左端に戻った上でセルが出力された下へ移動
	//$pdf->SetFont(GOTHIC,'');
	$pdf->SetTextColor(0, 0, 0);

	// 授業明細
	$absent_flag1 = false;
	$absent_flag2 = false;
	foreach ($student["lesson_detail_list"] as $lesson) {
		$pdf->Ln();
		$pdf->Cell(0, $cell_h, convert("■ ".$lesson["lesson_name"]." ".$lesson["subtotal_hours"]."時間 ".number_format(floor($lesson["subtotal_fees"]))."円"), 0, 1, 'L');
		if (count($lesson["event_list"]) > 0) {
			$pdf->Cell($cell_w, $cell_h, convert("日付"), 1, 0, 'L');
			$pdf->Cell($cell_w, $cell_h, convert("時刻"), 1, 0, 'L');
			//$pdf->Cell($cell_w, $cell_h, convert("教室"), 1, 0, 'L');
			$pdf->Cell($cell_w, $cell_h, convert("科目"), 1, 0, 'L');
			$pdf->Cell($cell_w*1.5, $cell_h, convert("タイプ"), 1, 0, 'L');
			$pdf->Cell($cell_narrow_w, $cell_h, convert("時間"), 1, 0, 'L');
			$pdf->Cell($cell_narrow_w, $cell_h, convert("単価"), 1, 0, 'L');
			$pdf->Cell($cell_narrow_w, $cell_h, convert("金額"), 1, 0, 'L');
			$pdf->Cell(0, $cell_h, "", 1, 1, 'L');							// 左端に戻った上でセルが出力された下へ移動
			foreach ($lesson["event_list"] as $starttimestamp => $event) {

				// 20151230 面談の表示変更
				if (mb_strpos($event["comment"],"面談") !== FALSE) {
					$pdf->Cell($cell_w, $cell_h, convert($event["date"]."（".$event["weekday"]."）"), 1, 0, 'L');
					$pdf->Cell($cell_w, $cell_h, convert($event["time"]), 1, 0, 'L');
					//$pdf->Cell($cell_w, $cell_h, convert($event["lesson_name"]), 1, 0, 'L');
					$pdf->Cell($cell_w, $cell_h, convert("―"), 1, 0, 'L');
					$pdf->Cell($cell_w*1.5, $cell_h, convert("―"), 1, 0, 'L');
					$pdf->Cell($cell_narrow_w, $cell_h, convert("―"), 1, 0, 'C');
					$pdf->Cell($cell_narrow_w, $cell_h, convert("―"), 1, 0, 'C');
					$pdf->Cell($cell_narrow_w, $cell_h, convert("―"), 1, 0, 'C');
					$pdf->Cell(0, $cell_h, convert($event["comment"]), 1, 1, 'L');							// 左端に戻った上でセルが出力された下へ移動
				} else {
					$pdf->Cell($cell_w, $cell_h, convert($event["date"]."（".$event["weekday"]."）"), 1, 0, 'L');
					$pdf->Cell($cell_w, $cell_h, convert($event["time"]), 1, 0, 'L');
					//$pdf->Cell($cell_w, $cell_h, convert($event["lesson_name"]), 1, 0, 'L');
					$pdf->Cell($cell_w, $cell_h, convert($event["subject_name"]), 1, 0, 'L');
					$pdf->Cell($cell_w*1.5, $cell_h, convert($event["course_name"]), 1, 0, 'L');
					$pdf->Cell($cell_narrow_w, $cell_h, convert($event["diff_hours"]."時間"), 1, 0, 'L');
					if ($event["monthly_fee_flag"]) {
						$pdf->Cell($cell_narrow_w, $cell_h, convert("―"), 1, 0, 'C');
						$pdf->Cell($cell_narrow_w, $cell_h, convert("―"), 1, 0, 'C');
					} else {
						$pdf->Cell($cell_narrow_w, $cell_h, convert(str_replace('.00','',$event["fee_for_an_hour"])."円"), 1, 0, 'R');
						$pdf->Cell($cell_narrow_w, $cell_h, convert($event["fees"]."円"), 1, 0, 'R');
					}
					$pdf->Cell(0, $cell_h, convert($event["comment"]), 1, 1, 'L');							// 左端に戻った上でセルが出力された下へ移動
				}

				if ($event["absent_flag"] == "1") {
					$absent_flag1 = true;
				}
				if ($event["absent_flag"] == "2") {
					$absent_flag2 = true;
				}
			}
		}
	}

	if ($absent_flag1 === true || $absent_flag2 === true) {
		$pdf->Ln();
	}
	if ($absent_flag1 === true) {
		$pdf->Cell(0, $cell_h, convert("お休み１= 授業料が発生しないお休み。"), 0, 1, 'L');	// 左端に戻った上でセルが出力された下へ移動
	}
	if ($absent_flag2 === true) {
		$pdf->Cell(0, $cell_h, convert("お休み２= 授業料が発生するお休み。"), 0, 1, 'L');	// 左端に戻った上でセルが出力された下へ移動
	}

	// テキスト代
	if ($student["textbook_price"] > 0) {
		$pdf->Ln();
		$pdf->Cell(0, $cell_h, convert("■ テキスト代　".$student["textbook_price"]."円"), 0, 1, 'L');	// 左端に戻った上でセルが出力された下へ移動
		$pdf->Cell($cell_wide_w, $cell_h, convert("テキスト名"), 1, 0, 'L');
		$pdf->Cell($cell_narrow_w, $cell_h, convert("金額"), 1, 1, 'L');	// 左端に戻った上でセルが出力された下へ移動
		foreach ($student["buying_textbook_list"] as $buying) {
			$pdf->Cell($cell_wide_w, $cell_h, convert($buying["name"]), 1, 0, 'L');
			$pdf->Cell($cell_narrow_w, $cell_h, convert(number_format($buying["price"])."円"), 1, 1, 'R');	// 左端に戻った上でセルが出力された下へ移動
		}
	}
	// ダウンロード用ダイアログを表示させて、保存してもらう
	//$pdf->Output($member_array["name"].'_'.$_GET["y"].'年'.$_GET["m"].'月', 'I');
}	// 生徒ごとに処理を繰り返す


// 全員分を1つのpdfファイルにまとめて保存する
//$pdf->Output('./fees_pdf/fees_'.$_GET["y"].sprintf('%02d',$_GET["m"]).'.pdf', 'F');
$dir_name = './fees_pdf/'.$_GET["y"].sprintf('%02d',$_GET["m"]).'/';
if (file_exists($dir_name) === false) {
	if (mkdir($dir_name, 0777, true) === false) {
	  //die('Failed to create folders...');
		throw new Exception('フォルダの作成に失敗しました。');
	}
}

$tmp_pdfFileName_array = array();
if ($handle = opendir($dir_name)) {
  while (false !== ($entry = readdir($handle))) {
   if ($entry != "." && $entry != "..") {
     $tmp_pdfFileName_array[] = $entry;
   }
  }
  closedir($handle);
}

//$pdfFileName = 'fees_'.$_GET["y"].sprintf('%02d',$_GET["m"])."_".date("Ymdhis");
// pdfファイル名を連番にする場合
$pdfFileName = 'fees_'.$_GET["y"].sprintf('%02d',$_GET["m"])."_1.pdf";
$no = 1;
do {
	$same_name_flag = false;
	if (in_array($pdfFileName, $tmp_pdfFileName_array)) {
	// 同じ名前があったら、新しい名前にして再度比較する
		$no = $no + 1;
		$pdfFileName = 'fees_'.$_GET["y"].sprintf('%02d',$_GET["m"])."_".$no.'.pdf';
		$same_name_flag = true;
	}
} while ($same_name_flag == true);

$pdf->Output($dir_name.$pdfFileName, 'F');

$partPdfFileName = str_replace(".pdf","",$pdfFileName);


function convert($str_text) {
//	return mb_convert_encoding($str_text,"SJIS","UTF-8");
	return $str_text;
}


?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" type="text/css" href="./script/style.css">
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
<div id="header">
	事務システム
</div>
<div id="content" align="center">
<!--
<div class="title_box">明細</div>
-->
<h3>生徒の月謝計算 - PDF明細書出力</h3>

<?php
if ($_SESSION['login']['kind'] == "1") {
	//if ($_SESSION['login']['id'] != $_SESSION['member_id']) {
?> 
	<div>
		<a href="student_list.php?y=<?=$year?>&m=<?=$month?>">生徒一覧へ戻る</a>&nbsp;&nbsp;
		<a href="menu.php">メニューへ戻る</a>
	</div>
<?php
	//}
}
?>
<br>
<?php
	if (count($errArray) > 0) {
		foreach( $errArray as $error) {
?>
			<font color="red" size="3"><?= $error ?></font><br>
<?php
		}
?>
	<br>
<?php
	} else {
?>
<p>すべての生徒の明細書をまとめて、PDFの明細書に出力しました。</p>
<a href="./detail_pdf.php?nm=<?=$partPdfFileName?>" target="_blank">PDFの明細書へ</a>


<?php
}
?>
</div>

</body></html>

