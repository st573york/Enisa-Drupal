<?php

namespace Drupal\export_management\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Class Data Excel E U Wide Indicator Values.
 */
class DataExcelEUWideIndicatorValues {

  /**
   * Function styles.
   */
  public static function styles(&$sheet) {
    $sheet->getStyle('1')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C:E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B')->getAlignment()->setWrapText(TRUE);
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
