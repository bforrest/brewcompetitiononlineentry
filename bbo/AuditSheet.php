<?php

require_once('fpdf186/fpdf.php');

class PDF extends FPDF
{
	public $title_text;
	
	// Page header
	function Header()
	{
	    // Logo
	    // Arial bold 15
	    $this->SetFont('Arial','B',15);
	    // Move to the right
	//    $this->Cell(2);
	    // Title
	    $this->Cell(0,.5,'Bluebonnet Brewoff Audit Sheet',1,0,'C');
	    // Line break
	    $this->Ln(.5);
	    
	    $this->Cell(0,.5,$this->title_text ,1,0,'C');
	    $this->Ln(.5);
	 	  $this->Cell(1.75, .4, "Judging Number", 1, 0, 'C' );
	  	$this->Cell(1.2, .4, "Entry Num", 1, 0, 'C' );
	  	$this->Cell(1, .4, "Seq#", 1, 0, 'C' );
	  	$this->Cell(1, .4, "Found?", 1, 0, 'C' );
	  	$this->Cell(0, .4, "Two Bottles? Notes", 1, 1, 'C' );

	    
	}
	
	// Page footer
	function Footer()
	{
	    // Position at 1.5 cm from bottom
	    $this->SetY(-0.5);
	    // Arial italic 8
	    $this->SetFont('Arial','I',8);
	    // Page number
	    $this->Cell(0,.2,'Page '.$this->PageNo().'/{nb}',0,0,'C');
	}
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(30);

$pdf = new PDF('P', 'in', 'Letter');
$pdf->AliasNbPages();

require '../paths.php';
require '../site/config.php';
$mysqli = new mysqli($hostname, $username, $password, $database);

if ($mysqli->connect_errno)
{
  echo "Database connect failed.<br>";
  echo "Errno: $mysqli->connect_errno <br>";
  echo "Error: $mysqli->connect_error <br>";
  exit;
}


	$sql = <<<EOT
	SELECT 
	  b.tableNumber,
	  b.tableName,
	  a.brewJudgingNumber,
	  a.brewCategorySort,
	  a.brewSubCategory,
	  a.id
	FROM 
	  brewing a,
	  bbo_tables b
	WHERE
    concat(a.brewCategory, a.brewSubCategory) = b.style
	  and a.brewReceived = 1
	ORDER by
	  b.tableNumber asc,
    a.brewJudgingNumber asc
EOT;


if (!$result = $mysqli->query($sql)) {
    echo "Error: Our query failed to execute and here is why: \n";
    echo "Query: " . $sql . "<br>";
    echo "Errno: " . $mysqli->errno . "<br>";
    echo "Error: " . $mysqli->error . "<br>";
    exit;
}

if ($result->num_rows === 0) {
    echo "No rows returned, Please try again.";
    exit;
}

$oldtableNum  = 0;
$entries      = 0;

while ($row = $result->fetch_assoc())
{
	$tableNumber       = $row['tableNumber'];     
	$tableName         = $row['tableName'];
	$style             = $row['brewCategorySort'] . $row['brewSubCategory'];
	$brewJudgingNumber = $row['brewJudgingNumber'];
	$brewEntryNumber   = $row['id'];
	
	if (($oldtableNum <> $tableNumber))
	{
		$entries = 0;
		$pdf->title_text = "Table: T$tableNumber $tableName";
		$pdf->AddPage('P', 'Letter');
	  $pdf->SetDisplayMode('fullpage','single');
	  $oldtableNum  = $tableNumber;
	  $pdf->SetFont('Arial', 'B', 14);
	}
	
	$entries++;
	
	$pdf->Cell(1.75, .4, $brewJudgingNumber, 1, 0, 'C' );
	$pdf->Cell(1.2, .4, "E$brewEntryNumber", 1, 0, 'C' );
	$pdf->Cell(1, .4, $entries, 1, 0, 'C' );
	$pdf->Cell(1, .4, "", 1, 0, 'C' );
	$pdf->Cell(0, .4, "", 1, 1, 'C' );


}

$result->free();
$mysqli->close();
$pdf->Output();
unset($pdf);

?>
