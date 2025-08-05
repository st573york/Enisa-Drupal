<?php

namespace Drupal\export_management\Excel;

use Drupal\index_and_survey_configuration_management\Helper\AreaHelper;
use Drupal\export_management\Helper\ExportHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\index_and_survey_configuration_management\Helper\SubareaHelper;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class Configuration Excel Builder.
 */
class ConfigurationExcelBuilder {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
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
   * Function __construct.
   */
  public function __construct($entityTypeManager, $database, $twig, $year) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->twig = $twig;
    $this->year = $year;
  }

  /**
   * Function getSpreadsheet.
   */
  public function getSpreadsheet() {
    $reader = new HtmlReader();

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    // Areas.
    $area_entities = AreaHelper::getAreaEntities($this->entityTypeManager, $this->year);
    $areas_data = AreaHelper::getAreasData($this->entityTypeManager, $area_entities);

    $html = $this->twig->render('@enisa/export/areas-configuration.html.twig', [
      'areas_data' => $areas_data,
    ]);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Areas');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ConfigurationExcelProperties::styles($to_sheet);
    ConfigurationExcelProperties::columnWidths($to_sheet);

    // Subareas.
    $subarea_entities = SubareaHelper::getSubareaEntities($this->entityTypeManager, $this->year);
    $subareas_data = SubareaHelper::getSubareasData($this->entityTypeManager, $subarea_entities);

    $html = $this->twig->render('@enisa/export/subareas-configuration.html.twig', [
      'subareas_data' => $subareas_data,
    ]);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Subareas');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ConfigurationExcelProperties::styles($to_sheet);
    ConfigurationExcelProperties::columnWidths($to_sheet);

    // Indicators.
    $indicator_entities = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $this->year);
    $indicators_data = IndicatorHelper::getIndicatorsData($this->entityTypeManager, $this->database, $indicator_entities, TRUE);

    $html = $this->twig->render('@enisa/export/indicators-configuration.html.twig', [
      'indicators_data' => $indicators_data,
    ]);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Indicators');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ConfigurationExcelProperties::styles($to_sheet);
    ConfigurationExcelProperties::columnWidths($to_sheet);

    // Indicators Survey.
    foreach ($indicators_data as $indicator_data) {
      if ($indicator_data['category'] == 'survey') {
        $survey_indicator_configuration_data = ExportHelper::getSurveyIndicatorConfigurationData($this->entityTypeManager, $indicator_data);

        $html = $this->twig->render('@enisa/export/survey-indicator-configuration.html.twig', $survey_indicator_configuration_data);

        $from_sheet = $reader->loadFromString($html)->getActiveSheet();

        $to_sheet = $spreadsheet->createSheet();
        $to_sheet->setTitle(mb_substr($indicator_data['identifier'] . '. ' . $indicator_data['name'], 0, 31));

        ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

        ConfigurationExcelIndicator::styles($to_sheet);
        ConfigurationExcelIndicator::columnWidths($to_sheet);
      }
    }

    return $spreadsheet;
  }

}
