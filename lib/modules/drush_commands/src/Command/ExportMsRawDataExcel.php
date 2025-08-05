<?php

namespace Drupal\drush_commands\Command;

use Drush\Commands\DrushCommands;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\export_management\Excel\DataCalculationValuesExcelBuilder;
use Drupal\export_management\Excel\DataExcelBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

/**
 * Class Export M S Raw Data Excel.
 */
final class ExportMsRawDataExcel extends DrushCommands {
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
   * Member variable path.
   *
   * @var string
   */
  protected $path = 'public:/';
  /**
   * Member variable year.
   *
   * @var int
   */
  protected $year;

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileSystemInterface $fileSystem,
    Connection $database,
    DateFormatterInterface $dateFormatter,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $fileSystem;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('twig')
    );
  }

  /**
   * Function validateOptions.
   */
  private function validateOptions($options) {
    if (empty($options['year'])) {
      $this->io()->error('The year option is required.');

      return FALSE;
    }

    if (!is_numeric($options['year'])) {
      $this->io()->error('The year option must be integer.');

      return FALSE;
    }

    if (empty($options['country_iso'])) {
      $this->io()->error('The country_iso option is required.');

      return FALSE;
    }

    if (!empty($options['country_iso'])) {
      $iso = GeneralHelper::getTaxonomyTerms($this->entityTypeManager, 'countries', 'field_iso');

      if (!in_array($options['country_iso'], $iso)) {
        $this->io()->error('The country_iso option is not in the available iso codes list.');

        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Function getTableOfContentsSpreadsheet.
   */
  private function getTableOfContentsSpreadsheet($table_of_contents) {
    $table_of_contents_xls = $this->fileSystem->realpath($this->path . '/seeders/import-files/' . $this->year . '/' . $table_of_contents . '.xlsx');

    $reader = IOFactory::createReaderForFile($table_of_contents_xls);

    return $reader->load($table_of_contents_xls);
  }

  /**
   * Function getDataSpreadsheet.
   */
  private function getDataSpreadsheet($options) {
    $data_excel_builder = new DataExcelBuilder(
      $this->entityTypeManager,
      $this->database,
      $this->dateFormatter,
      $this->twig,
      $this->year,
      [$options['country_iso']],
      ['survey', 'eurostat', 'shodan']);

    return $data_excel_builder->getSpreadsheet();
  }

  /**
   * Function getRawDataSpreadsheet.
   */
  private function getRawDataSpreadsheet($results, $raw_data_xls) {
    $this->fileSystem->copy($results, $raw_data_xls, FileExists::Rename);

    $reader = IOFactory::createReaderForFile($raw_data_xls);

    $raw_data_spreadsheet = $reader->load($raw_data_xls);

    $raw_data_sheets = $raw_data_spreadsheet->getAllSheets();

    foreach ($raw_data_sheets as $raw_data_sheet) {
      $title = $raw_data_sheet->getTitle();

      if (!preg_match('/Meta|Effective/', $title) || str_contains($title, 'Analysis.Treated')) {
        $raw_data_spreadsheet->removeSheetByIndex(
          $raw_data_spreadsheet->getIndex(
            $raw_data_spreadsheet->getSheetByName($title)
          )
        );
      }
    }

    return $raw_data_spreadsheet;
  }

  /**
   * Function getDataCalculationValuesSpreadsheet.
   */
  private function getDataCalculationValuesSpreadsheet($published_index_data, $results_xls, $options) {
    $data_calculation_values_excel_builder = new DataCalculationValuesExcelBuilder(
      $this->entityTypeManager,
      $this->twig,
      $published_index_data,
      $results_xls,
      $options['country_iso']);

    return $data_calculation_values_excel_builder->getSpreadsheet();
  }

  /**
   * Function setTableOfContentsHyperlink.
   */
  private function setTableOfContentsHyperlink($table_of_contents_sheet, &$raw_data_spreadsheet) {
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B2')->getHyperlink()->setUrl("sheet://'Overview'!A1");
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B4')->getHyperlink()->setUrl("sheet://'Survey - Raw Values'!A1");
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B6')->getHyperlink()->setUrl("sheet://'Eurostat - Raw Values'!A1");
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B8')->getHyperlink()->setUrl("sheet://'Shodan - Indicator Values'!A1");
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B10')->getHyperlink()->setUrl("sheet://'Data Calculation Values'!A1");
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B19')->getHyperlink()->setUrl("sheet://'Meta.Ind'!A1");
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B36')->getHyperlink()->setUrl("sheet://'Meta.Lineage'!A1");
    $raw_data_spreadsheet->getSheetByName($table_of_contents_sheet)->getCell('B38')->getHyperlink()->setUrl("sheet://'Effective.Weights'!A1");
  }

  /**
   * Export MS raw data excel for given year and country iso.
   *
   * @command commands:export-ms-raw-data-excel
   * @description Export MS raw data excel for given year and country iso.
   * @aliases export-ms-raw-data-excel
   */
  public function exportMsRawDataExcel($options = ['year' => NULL, 'country_iso' => NULL]) {
    $ret = $this->validateOptions($options);
    if (!$ret) {
      return DrushCommands::EXIT_FAILURE;
    }

    $this->year = $options['year'];

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $this->year);

    if (is_null($published_index_entity)) {
      $this->io()->error("Index record was not found for year: '{$this->year}'!");

      return DrushCommands::EXIT_FAILURE;
    }

    $results = $this->path . '/index-calculations/' . $this->year . '/' . $published_index_entity . '/results/EUCSI-results.xlsx';
    $results_xls = $this->fileSystem->realpath($results);

    if (!file_exists($results_xls)) {
      $this->io()->error("Results excel was not found for year: '{$this->year}'!");

      return DrushCommands::EXIT_FAILURE;
    }

    try {
      $published_index = $this->entityTypeManager->getStorage('node')->load($published_index_entity);
      $published_index_data = IndexHelper::getIndexData($this->dateFormatter, $published_index);

      $raw_data_filename = 'EUCSI-MS-raw-data-' . $this->year . '-' . $options['country_iso'] . '.xlsx';
      $raw_data_xls = $this->fileSystem->realpath($this->path . '/' . $raw_data_filename);

      // Get Table of Contents.
      $table_of_contents = 'Table-of-Contents';
      $table_of_contents_spreadsheet = $this->getTableOfContentsSpreadsheet($table_of_contents);

      // Get Data.
      $data_spreadsheet = $this->getDataSpreadsheet($options);

      $data_sheets = $data_spreadsheet->getAllSheets();

      // Get Raw Data.
      $raw_data_spreadsheet = $this->getRawDataSpreadsheet($results, $raw_data_xls);

      // Add Overview, Survey - Raw Values, Eurostat - Raw Values.
      $index = 0;
      foreach ($data_sheets as $data_sheet) {
        $title = $data_sheet->getTitle();

        if ($title != 'Indicator Values') {
          $raw_data_spreadsheet->addExternalSheet($data_sheet, $index++);
        }
      }

      // After you're done processing the spreadsheet, unload it to free memory.
      $data_spreadsheet->disconnectWorksheets();
      unset($data_spreadsheet);

      // Get Data Calculation Values.
      $data_calculation_values_spreadsheet = $this->getDataCalculationValuesSpreadsheet($published_index_data, $results_xls, $options);

      // Add Data Calculation Values.
      $raw_data_spreadsheet->addExternalSheet($data_calculation_values_spreadsheet->getSheet(0), $index++);

      // After you're done processing the spreadsheet, unload it to free memory.
      $data_calculation_values_spreadsheet->disconnectWorksheets();
      unset($data_calculation_values_spreadsheet);

      $raw_data_spreadsheet->addExternalSheet($table_of_contents_spreadsheet->getSheet(0), 0);

      // After you're done processing the spreadsheet, unload it to free memory.
      $table_of_contents_spreadsheet->disconnectWorksheets();
      unset($table_of_contents_spreadsheet);

      // Add hyperlinks.
      $table_of_contents_sheet = str_replace('-', ' ', $table_of_contents);

      $this->setTableOfContentsHyperlink($table_of_contents_sheet, $raw_data_spreadsheet);

      $raw_data_spreadsheet->setActiveSheetIndex(0);

      $writer = new Xlsx($raw_data_spreadsheet);
      $writer->save($raw_data_xls);
    }
    catch (\Exception $e) {
      $this->io()->error($e->getMessage());

      return DrushCommands::EXIT_FAILURE;
    }

    return json_encode(['filename' => $raw_data_filename]);
  }

}
