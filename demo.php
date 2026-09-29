<?php

require_once __DIR__ . '/vendor/autoload.php';

$pdf = new FPDF();

$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

$pdf->Cell(0, 10, 'AMIRTHAM - SALES INVOICE', 0, 1, 'C');

$pdf->Output();