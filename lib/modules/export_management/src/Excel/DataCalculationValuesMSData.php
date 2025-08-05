<?php

namespace Drupal\export_management\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Class Data Calculation Values M S Data.
 */
class DataCalculationValuesMSData {

  /**
   * Function styles.
   */
  public static function styles(&$sheet) {
    $sheet->getStyle('1')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('2')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('C:O')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B')->getAlignment()->setWrapText(TRUE);
    $sheet->mergeCells('C1:D1');
    $sheet->mergeCells('E1:F1');
    $sheet->mergeCells('G1:H1');
    $sheet->mergeCells('I1:J1');
    $sheet->mergeCells('K1:L1');
    $sheet->mergeCells('N1:O1');
  }

  /**
   * Function columnWidths.
   */
  public static function columnWidths(&$sheet) {
    $columnWidths = [
      'B' => 70,
    ];

    foreach ($columnWidths as $column => $width) {
      $sheet->getColumnDimension($column)->setWidth($width);
    }
  }

}
