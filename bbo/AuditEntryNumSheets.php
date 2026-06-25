<?php

require_once('fpdf186/fpdf.php');

class PDF extends FPDF
{
	// Page header
	function Header()
	{
	    // Logo
	    // Arial bold 15
	    $this->SetFont('Arial','B',15);
	    $this->SetLeftMargin(.3937);
	    $this->SetX(.3937);
	    // Move to the right
	//    $this->Cell(2);
	    // Title
	    $this->Cell(0,.5,'Bluebonnet Brewoff Entry Number Audit Sheet',1,0,'C');
	    // Line break
	    $this->Ln(.5);
	}
	
	// Page footer
	function Footer()
	{
	    // Position at 1.5 cm from bottom
	    $this->SetY(-0.5);
	    $this->SetX(.3937);
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
	  a.id
	FROM 
	  brewing a,
	  bbo_tables b
	WHERE
	      b.style = concat(a.brewCategory, a.brewSubCategory)
  ORDER by 1,3;
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
$newColumn    = 0;


while ($row = $result->fetch_assoc())
{
	$tableNumber       = $row['tableNumber'];     
	$tableName         = $row['tableName'];
	$id                = $row['id'];
	
	if ($oldtableNum <> $tableNumber)
	{
		$entries = 0;
		$newColumn = 0;
		$pdf->AddPage('P', 'Letter');
	  $pdf->SetDisplayMode('fullpage','single');
	  
	  $pdf->SetLeftMargin(.3937);
	  
	  $oldtableNum  = $tableNumber;
	  
	  $pdf->SetFont('Arial', 'B', 14);
	  
	  $pdf->Cell(0, .4, "Table: T$tableNumber $tableName", 1, 0, 'C' );
	  $pdf->SetY(1.5);
	  $pdf->Cell(1.75, .4, "Entry Number", 1, 0, 'C' );
	  $pdf->Cell(1.25, .4, "Put in Box", 1, 1, 'C' );
	}

$entries++;

$pdf->Cell(1.75, .4, "E" . $id, 1, 0, 'C' );
$pdf->Cell(1.25, .4, "", 1, 1, 'C' );

	if (($entries > 18) && ($newColumn == 0))
	{
	 $pdf->SetLeftMargin(4.5);
//	 $pdf->SetX(5);
	 $pdf->SetY(1.5);
   $pdf->Cell(1.75, .4, "Entry Number", 1, 0, 'C' );
	 $pdf->Cell(1.25, .4, "Put in Box", 1, 1, 'C' );

	 $newColumn = 1;
	}
}

$result->free();
$mysqli->close();
$pdf->Output();
unset($pdf);


?>
