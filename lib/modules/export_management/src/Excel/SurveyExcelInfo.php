<?php

namespace Drupal\export_management\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Class Survey Excel Info.
 */
class SurveyExcelInfo {

  /**
   * Function styles.
   */
  public static function styles(&$sheet) {
    $sheet->getStyle('A:B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('B1')->applyFromArray([
      'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
      ],
    ]);
  }

  /**
   * Function columnWidths.
   */
  public static function columnWidths(&$sheet) {
    $columnWidths = [
      'A' => 27,
      'B' => 100,
      'C' => 20,
    ];

    foreach ($columnWidths as $column => $width) {
      $sheet->getColumnDimension($column)->setWidth($width);
    }
  }

  /**
   * Function drawings.
   */
  public static function drawings($fileSystem, &$sheet) {
    $drawing = new Drawing();
    $drawing->setName('ENISA');
    $drawing->setPath($fileSystem->realpath('public://enisa_logo.png'));
    $drawing->setHeight(50);
    $drawing->setWidth(200);
    $drawing->setCoordinates('A1');
    $drawing->setWorksheet($sheet);

    $sheet->getRowDimension(1)->setRowHeight(105);
  }

}
