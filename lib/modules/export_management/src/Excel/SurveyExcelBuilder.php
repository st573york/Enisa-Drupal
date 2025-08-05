<?php

namespace Drupal\export_management\Excel;

use Drupal\export_management\Helper\ExportHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class Survey Excel Builder.
 */
class SurveyExcelBuilder {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable fileSystem.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;
  /**
   * Member variable database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * Member variable dateFormatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;
  /**
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;
  /**
   * Member variable year.
   *
   * @var int
   */
  protected $year;
  /**
   * Member variable countrySurvey.
   *
   * @var object
   */
  protected $countrySurvey;

  /**
   * Function __construct.
   */
  public function __construct($entityTypeManager, $fileSystem, $database, $dateFormatter, $twig, $year, $countrySurvey) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $fileSystem;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->twig = $twig;
    $this->year = $year;
    $this->countrySurvey = $countrySurvey;
  }

  /**
   * Function getSpreadsheet.
   */
  public function getSpreadsheet() {
    $reader = new HtmlReader();

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    // Info.
    $indicator_entities = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $this->year, 'survey');
    $indicators_data = IndicatorHelper::getIndicatorsData($this->entityTypeManager, $this->database, $indicator_entities);

    $html = $this->twig->render('@enisa/export/survey-info-template.html.twig', [
      'indicators_data' => $indicators_data,
    ]);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Info');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    SurveyExcelInfo::styles($to_sheet);
    SurveyExcelInfo::columnWidths($to_sheet);
    SurveyExcelInfo::drawings($this->fileSystem, $to_sheet);

    // Indicators.
    foreach ($indicators_data as $indicator_data) {
      $survey_indicator_configuration_data = ExportHelper::getSurveyIndicatorConfigurationData($this->entityTypeManager, $indicator_data);

      $html = $this->twig->render('@enisa/export/survey-indicator-template.html.twig', $survey_indicator_configuration_data);

      $from_sheet = $reader->loadFromString($html)->getActiveSheet();

      $to_sheet = $spreadsheet->createSheet();
      $to_sheet->setTitle($indicator_data['order']);

      ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

      SurveyExcelIndicator::styles($to_sheet);
      SurveyExcelIndicator::columnWidths($to_sheet);

      $withAnswers = (!is_null($this->countrySurvey)) ? TRUE : FALSE;

      $survey_indicator_data = [];

      if ($withAnswers) {
        $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($this->entityTypeManager, $this->countrySurvey, $indicator_data['id']);

        $survey_indicator = $this->entityTypeManager->getStorage('node')->load($survey_indicator_entity);
        $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($this->entityTypeManager, $this->dateFormatter, $survey_indicator);
      }

      SurveyExcelIndicator::registerEvents(
            $this->entityTypeManager,
            $this->dateFormatter,
            $to_sheet,
            $withAnswers,
            $indicator_data,
            $survey_indicator_data,
            $survey_indicator_configuration_data);
    }

    return $spreadsheet;
  }

}
