<?php

namespace Drupal\export_management\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Class Configuration Excel Indicator.
 */
class ConfigurationExcelIndicator {

  /**
   * Function styles.
   */
  public static function styles(&$sheet) {
    $sheet->getStyle('A:L')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('1')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('A:L')->getAlignment()->setWrapText(TRUE);
  }

  /**
   * Function columnWidths.
   */
  public static function columnWidths(&$sheet) {
    $columnWidths = [
      'A' => 5,
      'B' => 30,
      'C' => 30,
      'D' => 30,
      'E' => 30,
      'F' => 30,
      'G' => 30,
      'H' => 30,
      'I' => 30,
      'J' => 30,
      'K' => 30,
      'L' => 30,
    ];

    foreach ($columnWidths as $column => $width) {
      $sheet->getColumnDimension($column)->setWidth($width);
    }
  }

}
