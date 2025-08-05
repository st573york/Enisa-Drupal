<?php

namespace Drupal\export_management\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Class Data Excel Indicator Values.
 */
class DataExcelIndicatorValues {

  /**
   * Function styles.
   */
  public static function styles(&$sheet, $categories) {
    $sheet->getStyle('1')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    if (count($categories) > 1) {
      $sheet->getStyle('G')->applyFromArray([
        'font' => ['bold' => TRUE],
      ]);
      $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    $sheet->getStyle('B:D')->getAlignment()->setWrapText(TRUE);
  }

  /**
   * Function columnWidths.
   */
  public static function columnWidths(&$sheet) {
    $columnWidths = [
      'B' => 70,
      'C' => 70,
      'D' => 70,
    ];

    foreach ($columnWidths as $column => $width) {
      $sheet->getColumnDimension($column)->setWidth($width);
    }
  }

}
