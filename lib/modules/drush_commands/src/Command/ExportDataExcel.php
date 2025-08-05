<?php

namespace Drupal\drush_commands\Command;

use Drush\Commands\DrushCommands;
use Drupal\general_management\Helper\ConstantHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\export_management\Excel\DataExcelBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

/**
 * Class Export Data Excel.
 */
final class ExportDataExcel extends DrushCommands {
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
   * Member variable defaultCountriesIso.
   *
   * @var array
   */
  protected $defaultCountriesIso;
  /**
   * Member variable defaultCategories.
   *
   * @var array
   */
  protected $defaultCategories;
  /**
   * Member variable countriesIso.
   *
   * @var array
   */
  protected $countriesIso;
  /**
   * Member variable categories.
   *
   * @var array
   */
  protected $categories;

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
    DateFormatterInterface $dateFormatter,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
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

    if (empty($this->countriesIso)) {
      $this->io()->error('The country_iso option is required.');

      return FALSE;
    }

    if (!empty($this->countriesIso) &&
        !in_array('all', $this->countriesIso)) {
      if (!empty(array_diff($this->countriesIso, $this->defaultCountriesIso))) {
        $this->io()->error('The country_iso option is not in the available iso codes list.');

        return FALSE;
      }
    }

    if (empty($this->categories)) {
      $this->io()->error('The source option is required.');

      return FALSE;
    }

    if (!empty($this->categories) &&
        !in_array('all', $this->categories)) {
      if (!empty(array_diff($this->categories, $this->defaultCategories))) {
        $this->io()->error('The source option is not in the available sources list.');

        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Export data excel for given year, country iso and source.
   *
   * @command commands:export-data-excel
   * @description Export data excel for given year, country iso and source.
   * @aliases export-data-excel
   */
  public function exportDataExcel($options = ['year' => NULL, 'country_iso' => NULL, 'source' => NULL]) {
    $iso = GeneralHelper::getTaxonomyTerms($this->entityTypeManager, 'countries', 'field_iso');

    $this->defaultCountriesIso = array_filter($iso, fn($value) => !is_null($value));
    $this->defaultCategories = array_merge(array_keys(ConstantHelper::DEFAULT_CATEGORIES), ['shodan']);
    $this->countriesIso = (!empty($options['country_iso'])) ? explode(',', $options['country_iso']) : [];
    $this->categories = (!empty($options['source'])) ? explode(',', $options['source']) : [];

    $ret = $this->validateOptions($options);
    if (!$ret) {
      return DrushCommands::EXIT_FAILURE;
    }

    if (in_array('all', $this->countriesIso)) {
      $this->countriesIso = $this->defaultCountriesIso;
    }

    if (in_array('all', $this->categories)) {
      $this->categories = $this->defaultCategories;
    }

    $filename = 'EUCSI-' . $options['year'] . '-all-data.xlsx';

    try {
      $data_excel_builder = new DataExcelBuilder(
        $this->entityTypeManager,
        $this->database,
        $this->dateFormatter,
        $this->twig,
        $options['year'],
        $this->countriesIso,
        $this->categories);
      $spreadsheet = $data_excel_builder->getSpreadsheet();
      $spreadsheet->setActiveSheetIndex(0);

      $writer = new Xlsx($spreadsheet);
      $writer->save('public://' . $filename);
    }
    catch (\Exception $e) {
      $this->io()->error($e->getMessage());

      return DrushCommands::EXIT_FAILURE;
    }

    return json_encode(['filename' => $filename]);
  }

}
