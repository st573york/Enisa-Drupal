<?php

namespace Drupal\export_management\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Class Report Excel E U Overview.
 */
class ReportExcelEUOverview {

  /**
   * Function styles.
   */
  public static function styles(&$sheet) {
    $sheet->getStyle('1')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('D')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('B:M')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A:C')->getAlignment()->setWrapText(TRUE);
  }

  /**
   * Function columnWidths.
   */
  public static function columnWidths(&$sheet) {
    $columnWidths = [
      'A' => 70,
      'B' => 30,
      'C' => 30,
    ];

    foreach ($columnWidths as $column => $width) {
      $sheet->getColumnDimension($column)->setWidth($width);
    }
  }

}
