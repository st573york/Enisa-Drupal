<?php

namespace Drupal\export_management\Excel;

use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Protection;

/**
 * Class Survey Excel Indicator.
 */
class SurveyExcelIndicator {
  const DNA = 'Data not available/Not willing to share';

  /**
   * Member variable withAnswers.
   *
   * @var bool
   */
  protected static $withAnswers;
  /**
   * Member variable years.
   *
   * @var array
   */
  protected static $years;
  /**
   * Member variable data.
   *
   * @var array
   */
  protected static $data;
  /**
   * Member variable rowCount.
   *
   * @var int
   */
  protected static $rowCount;
  /**
   * Member variable cellNum.
   *
   * @var int
   */
  protected static $cellNum;

  /**
   * Function styles.
   */
  public static function styles(&$sheet) {
    $sheet->getStyle('A:C')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('B:C')->getAlignment()->setWrapText(TRUE);
    $sheet->mergeCells('A1:C1');
    $sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::VERTICAL_CENTER);
  }

  /**
   * Function columnWidths.
   */
  public static function columnWidths(&$sheet) {
    $columnWidths = [
      'A' => 25,
      'B' => 100,
      'C' => 20,
    ];

    foreach ($columnWidths as $column => $width) {
      $sheet->getColumnDimension($column)->setWidth($width);
    }
  }

  /**
   * Function registerEvents.
   */
  public static function registerEvents(
    $entityTypeManager,
    $dateFormatter,
    &$sheet,
    $withAnswers,
    $indicator_data,
    $survey_indicator_data,
    $survey_indicator_configuration_data,
  ) {
    self::protectSheetAndHideColumns($sheet);

    self::$years = range(2000, \Drupal::service("date.formatter")->format('Y') + 1);
    rsort(self::$years);

    $accordion = 0;
    self::$rowCount = 3;
    $option_count = 0;

    self::$withAnswers = $withAnswers;
    $identifier = $indicator_data['identifier'];

    $sheet->setCellValue('D1', $identifier);

    foreach ($survey_indicator_configuration_data['questions'] as $question_id => $question_data) {
      $parts = explode('-', $question_id);

      $survey_indicator_answer_data = [];

      if (self::$withAnswers) {
        $survey_indicator_answer_entity = SurveyIndicatorHelper::getSurveyIndicatorAnswerEntity($entityTypeManager, $survey_indicator_data['id'], $question_data['id']);
        if (!is_null($survey_indicator_answer_entity)) {
          $survey_indicator_answer = $entityTypeManager->getStorage('node')->load($survey_indicator_answer_entity);
          $survey_indicator_answer_data = SurveyIndicatorHelper::getSurveyIndicatorAnswerData($entityTypeManager, $dateFormatter, $survey_indicator_answer);
        }
      }

      if ($accordion != $parts[1]) {
        self::$rowCount++;
      }

      self::$data = ['cell' => '', 'options' => [], 'options_with_comma' => FALSE, 'protection' => FALSE];

      self::$cellNum = self::$rowCount;

      self::setCellChoice($sheet, $question_data, $survey_indicator_answer_data);
      self::setCellInfo($sheet, $question_data);

      if (isset($question_data['options'])) {
        self::$data['options'] = [];

        foreach ($question_data['options'] as $key => $option) {
          if ($question_data['type'] == 'single-choice') {
            self::setCellSingleChoice($sheet, $survey_indicator_answer_data, $question_id, $key, $option, $option_count);
          }
          elseif ($question_data['type'] == 'multiple-choice') {
            self::setCellMultipleChoice($sheet, $survey_indicator_answer_data, $key);
          }
        }
      }

      if ($question_data['type'] == 'single-choice' ||
            $question_data['type'] == 'free-text') {
        self::$data['cell'] = 'C' . ++self::$rowCount;

        if ($question_data['type'] == 'single-choice') {
          self::$data['options_with_comma'] = TRUE;

          self::setCellDropDown($sheet);
        }
        elseif ($question_data['type'] == 'free-text') {
          self::setCellFreeText($sheet, $survey_indicator_answer_data);
        }
        self::setCellProtection($sheet);
      }
      elseif ($question_data['type'] == 'multiple-choice') {
        self::$rowCount += count($question_data['options']);
      }

      self::setCellReferenceYear($sheet, $survey_indicator_answer_data);
      self::setCellReferenceSource($sheet, $survey_indicator_answer_data);

      $accordion = $parts[1];
    }

    self::setCellRating($sheet, $survey_indicator_data);
    self::setCellComments($sheet, $survey_indicator_data);
    self::setCellIdentifier($sheet, $identifier);
  }

  /**
   * Function protectSheetAndHideColumns.
   */
  private static function protectSheetAndHideColumns($sheet) {
    // Protection.
    $protection = $sheet->getProtection();
    $protection->setSheet(TRUE);
    // Hide columns.
    $sheet->getColumnDimension('D')->setVisible(FALSE);
    $sheet->getColumnDimension('E')->setVisible(FALSE);
    $sheet->getColumnDimension('Y')->setVisible(FALSE);
    $sheet->getColumnDimension('Z')->setVisible(FALSE);
  }

  /**
   * Function setCellChoice.
   */
  private static function setCellChoice($sheet, $question_data, $survey_indicator_answer_data) {
    self::$data['cell'] = 'C' . self::$cellNum;
    self::$data['options'] = [
      ($question_data['type'] == 'free-text' ?
      'Provide your answer' : 'Choose answer'),
      self::DNA,
    ];

    self::setCellDropDown($sheet);
    self::setCellProtection($sheet);
    if (self::$withAnswers &&
          !empty($survey_indicator_answer_data) &&
          $survey_indicator_answer_data['choice']['id'] == 3) {
      $sheet->setCellValue(self::$data['cell'], self::DNA);
    }
  }

  /**
   * Function setCellInfo.
   */
  private static function setCellInfo($sheet, $question_data) {
    $sheet->setCellValue('G' . self::$cellNum, $question_data['info']);
  }

  /**
   * Function setCellSingleChoice.
   */
  private static function setCellSingleChoice($sheet, $survey_indicator_answer_data, $question_id, $key, $option, &$option_count) {
    $option_count++;
    self::$cellNum = self::$rowCount + $option_count;

    if (!isset(self::$data['range_start'])) {
      self::$data['range_start'] = self::$cellNum;
    }

    $option_name = htmlspecialchars_decode($option['name']);

    // Copy dropdown vals to other cells. Use range to fix option name commas.
    $sheet->setCellValue('Y' . self::$cellNum, $option_name);
    $sheet->setCellValue('Z' . self::$cellNum, $question_id);

    array_push(self::$data['options'], $option_name);

    if (self::$withAnswers &&
          isset($survey_indicator_answer_data['options']) &&
          in_array($key, $survey_indicator_answer_data['options'])) {
      $sheet->setCellValue('C' . self::$rowCount + 1, $option_name);
    }
  }

  /**
   * Function setCellMultipleChoice.
   */
  private static function setCellMultipleChoice($sheet, $survey_indicator_answer_data, $key) {
    self::$cellNum = self::$rowCount + $key;

    self::$data['cell'] = 'C' . self::$cellNum;
    self::$data['options'] = ['Yes'];

    self::setCellDropDown($sheet);
    self::setCellProtection($sheet);
    if (self::$withAnswers &&
          isset($survey_indicator_answer_data['options']) &&
          in_array($key, $survey_indicator_answer_data['options'])) {
      $sheet->setCellValue(self::$data['cell'], 'Yes');
    }
  }

  /**
   * Function setCellFreeText.
   */
  private static function setCellFreeText($sheet, $survey_indicator_answer_data) {
    if (self::$withAnswers &&
          !empty($survey_indicator_answer_data)) {
      $sheet->setCellValue(self::$data['cell'], htmlspecialchars_decode($survey_indicator_answer_data['free_text']));
    }
  }

  /**
   * Function setCellReferenceYear.
   */
  private static function setCellReferenceYear($sheet, $survey_indicator_answer_data) {
    self::$cellNum = self::$rowCount + 2;

    self::$data['cell'] = 'B' . self::$cellNum;
    self::$data['options'] = self::$years;
    self::$data['options_with_comma'] = FALSE;

    self::setCellDropDown($sheet);
    self::setCellProtection($sheet);
    if (self::$withAnswers &&
          !empty($survey_indicator_answer_data)) {
      $sheet->setCellValue(self::$data['cell'], $survey_indicator_answer_data['reference_year']);
    }

    self::$rowCount += 2;
  }

  /**
   * Function setCellReferenceSource.
   */
  private static function setCellReferenceSource($sheet, $survey_indicator_answer_data) {
    self::$cellNum = self::$rowCount + 2;

    self::$data['cell'] = 'B' . self::$cellNum;

    self::setCellProtection($sheet);
    $sheet->getRowDimension(self::$cellNum)->setRowHeight(30, 'pt');
    if (self::$withAnswers &&
          !empty($survey_indicator_answer_data)) {
      $sheet->setCellValue(self::$data['cell'], htmlspecialchars_decode($survey_indicator_answer_data['reference_source']));
    }

    self::$rowCount += 4;
  }

  /**
   * Function setCellRating.
   */
  private static function setCellRating($sheet, $survey_indicator_data) {
    self::$cellNum = self::$rowCount;

    self::$data['cell'] = 'B' . self::$cellNum;
    self::$data['options'] = ['1', '2', '3', '4', '5'];
    self::$data['options_with_comma'] = FALSE;

    self::setCellDropDown($sheet);
    self::setCellProtection($sheet);
    if (self::$withAnswers) {
      $sheet->setCellValue(self::$data['cell'], $survey_indicator_data['rating']);
    }
  }

  /**
   * Function setCellComments.
   */
  private static function setCellComments($sheet, $survey_indicator_data) {
    self::$cellNum += 2;

    self::$data['cell'] = 'B' . self::$cellNum;

    self::setCellProtection($sheet);
    $sheet->getRowDimension(self::$cellNum)->setRowHeight(100, 'pt');
    if (self::$withAnswers) {
      $sheet->setCellValue(self::$data['cell'], htmlspecialchars_decode($survey_indicator_data['comments']));
    }
  }

  /**
   * Function setCellIdentifier.
   */
  private static function setCellIdentifier($sheet, $identifier) {
    self::$cellNum += 2;

    $sheet->setCellValue('A' . self::$cellNum, 'Number ' . $identifier);
  }

  /**
   * Function setCellDropDown.
   */
  private static function setCellDropDown($sheet) {
    $validation = $sheet->getCell(self::$data['cell'])->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(TRUE);
    $validation->setShowInputMessage(TRUE);
    $validation->setShowErrorMessage(TRUE);
    $validation->setShowDropDown(TRUE);
    if (self::$data['options_with_comma']) {
      $validation->setFormula1('=Y' . self::$data['range_start'] . ':Y' . (self::$data['range_start'] + count(self::$data['options']) - 1));
    }
    else {
      $validation->setFormula1(sprintf('"%s"', implode(',', self::$data['options'])));
    }
  }

  /**
   * Function setCellProtection.
   */
  private static function setCellProtection($sheet) {
    $protection = $sheet->getStyle(self::$data['cell'])->getProtection();

    if (self::$data['protection']) {
      $protection->setLocked(Protection::PROTECTION_PROTECTED);
    }
    else {
      $protection->setLocked(Protection::PROTECTION_UNPROTECTED);
    }
  }

}
