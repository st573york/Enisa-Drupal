<?php

namespace Drupal\export_management\Excel;

use Drupal\export_management\Helper\ExportHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class Data Excel Builder.
 */
class DataExcelBuilder {
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
   * Member variable countriesIso.
   *
   * @var array
   */
  protected $countriesIso;
  /**
   * Member variable countryEntities.
   *
   * @var array
   */
  protected $countryEntities;
  /**
   * Member variable categories.
   *
   * @var array
   */
  protected $categories;
  /**
   * Member variable data.
   *
   * @var array
   */
  protected $data;

  /**
   * Function __construct.
   */
  public function __construct(
    $entityTypeManager,
    $database,
    $dateFormatter,
    $twig,
    $year,
    $countriesIso,
    $categories,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->twig = $twig;
    $this->year = $year;
    $this->countriesIso = $countriesIso;
    $this->categories = $categories;

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $this->year);

    $this->countryEntities = [];
    foreach ($this->countriesIso as $countryIso) {
      $term = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', $countryIso, 'field_iso');

      array_push($this->countryEntities, $term->id());
    }

    $this->data = ExportHelper::prepareIndexPropertiesOverviewData($this->entityTypeManager, $this->dateFormatter, $published_index_entity, $this->countryEntities);
  }

  /**
   * Function getSpreadsheet.
   */
  public function getSpreadsheet() {
    $reader = new HtmlReader();

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    if ($this->data['hasData']) {
      $this->renderOverview($reader, $spreadsheet);
    }

    $data = ExportHelper::prepareIndicatorValuesData($this->entityTypeManager, $this->database, $this->year, $this->countryEntities, $this->categories, $this->data);

    if ($data['hasData']) {
      $this->renderIndicatorValues($reader, $spreadsheet, $data);
    }

    if (in_array('manual', $this->categories) &&
          count($this->countriesIso) > 1) {
      $data = ExportHelper::prepareEuWideIndicatorValuesData($this->entityTypeManager, $this->database, $this->year, $this->countryEntities, $this->categories);

      if ($data['hasData']) {
        $this->renderEuWide($reader, $spreadsheet, $data);
      }
    }

    if (in_array('survey', $this->categories)) {
      $data = ExportHelper::prepareSurveyIndicatorRawData($this->entityTypeManager, $this->dateFormatter, $this->year, $this->countryEntities);

      if ($data['hasData']) {
        $this->renderSurvey($reader, $spreadsheet, $data);
      }
    }

    if (in_array('eurostat', $this->categories)) {
      $data = ExportHelper::prepareEurostatIndicatorRawData($this->entityTypeManager, $this->database, $this->year, $this->countryEntities);

      if ($data['hasData']) {
        $this->renderEurostat($reader, $spreadsheet, $data);
      }
    }

    if (in_array('shodan', $this->categories)) {
      $data = ExportHelper::prepareShodanIndicatorRawData($this->entityTypeManager, $this->database, $this->year, $this->countryEntities);

      if ($data['hasData']) {
        $this->renderShodan($reader, $spreadsheet, $data);
      }
    }

    return $spreadsheet;
  }

  /**
   * Function renderOverview.
   */
  private function renderOverview($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/index-properties-overview.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Overview');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    DataExcelIndexPropertiesOverview::styles($to_sheet);
    DataExcelIndexPropertiesOverview::columnWidths($to_sheet);
  }

  /**
   * Function renderIndicatorValues.
   */
  private function renderIndicatorValues($reader, $spreadsheet, $data) {
    $html = $this->twig->render('@enisa/export/indicator-values.html.twig', $data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Indicator Values');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    DataExcelIndicatorValues::styles($to_sheet, $this->categories);
    DataExcelIndicatorValues::columnWidths($to_sheet);
  }

  /**
   * Function renderEuWide.
   */
  private function renderEuWide($reader, $spreadsheet, $data) {
    $html = $this->twig->render('@enisa/export/eu-wide-indicator-values.html.twig', $data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('EU Wide - Indicator Values');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    DataExcelEUWideIndicatorValues::styles($to_sheet);
    DataExcelEUWideIndicatorValues::columnWidths($to_sheet);
  }

  /**
   * Function renderSurvey.
   */
  private function renderSurvey($reader, $spreadsheet, $data) {
    $html = $this->twig->render('@enisa/export/survey-indicator-raw-data.html.twig', $data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Survey - Raw Values');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    DataExcelSurveyIndicatorRawData::styles($to_sheet);
    DataExcelSurveyIndicatorRawData::columnWidths($to_sheet);
  }

  /**
   * Function renderEurostat.
   */
  private function renderEurostat($reader, $spreadsheet, $data) {
    $html = $this->twig->render('@enisa/export/eurostat-indicator-raw-data.html.twig', $data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Eurostat - Raw Values');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    DataExcelEurostatIndicatorRawData::styles($to_sheet);
    DataExcelEurostatIndicatorRawData::columnWidths($to_sheet);
  }

  /**
   * Function renderShodan.
   */
  private function renderShodan($reader, $spreadsheet, $data) {
    $html = $this->twig->render('@enisa/export/shodan-indicator-values.html.twig', $data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Shodan - Indicator Values');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    DataExcelShodanIndicatorValues::styles($to_sheet);
    DataExcelShodanIndicatorValues::columnWidths($to_sheet);
  }

}
