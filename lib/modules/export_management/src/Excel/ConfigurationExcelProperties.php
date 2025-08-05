<?php

namespace Drupal\export_management\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Configuration Excel Properties definition.
 */
class ConfigurationExcelProperties {

  /**
   * Set the properties of the Excel sheet.
   */
  public static function styles(&$sheet) {
    $sheet->getStyle('A:S')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('1')->applyFromArray([
      'font' => ['bold' => TRUE],
    ]);
    $sheet->getStyle('A:S')->getAlignment()->setWrapText(TRUE);
  }

  /**
   * Set the column widths for the Excel sheet.
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
      'M' => 30,
      'N' => 30,
      'O' => 30,
      'P' => 30,
      'Q' => 30,
      'R' => 30,
      'S' => 30,
    ];

    foreach ($columnWidths as $column => $width) {
      $sheet->getColumnDimension($column)->setWidth($width);
    }
  }

}
