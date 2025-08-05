<?php

namespace Drupal\drush_commands\Command;

use Drush\Commands\DrushCommands;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\export_management\Excel\ReportExcelBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

/**
 * Class Export Report Excel.
 */
final class ExportReportExcel extends DrushCommands {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
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
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    DateFormatterInterface $dateFormatter,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->dateFormatter = $dateFormatter;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
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
   * Export EU report excel, and MS report if iso is given, for given year.
   *
   * @command commands:export-report-excel
   * @description Export EU report data excel for given year.
   *  When country iso is provided, MS report excel is exported.
   * @aliases export-report-excel
   */
  public function exportReportExcel($options = ['year' => NULL, 'country_iso' => NULL]) {
    $ret = $this->validateOptions($options);
    if (!$ret) {
      return DrushCommands::EXIT_FAILURE;
    }

    $filename = 'SurveyTemplate.xlsx';

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $options['year']);

    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $published_index_entity);

    if (empty($country_index_entities)) {
      $this->io()->error("Report data record was not found for year: '{$options['year']}'!");

      return DrushCommands::EXIT_FAILURE;
    }

    if (!is_null($options['country_iso'])) {
      $term = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', $options['country_iso'], 'field_iso');

      $country_index_entity = IndexHelper::getCountryIndexEntity($this->entityTypeManager, $published_index_entity, $term->id());

      $country_index = $this->entityTypeManager->getStorage('node')->load($country_index_entity);
      $country_index_data = IndexHelper::getCountryIndexData($this->dateFormatter, $country_index);

      $data = $country_index_data['report_json_data'][0];
      $filename = 'EUCSI-MS-report-' . $options['year'] . '-' . $term->field_iso->value . '.xlsx';
    }
    else {
      $eu_index_entity = IndexHelper::getEuIndexEntity($this->entityTypeManager, $published_index_entity);

      $eu_index = $this->entityTypeManager->getStorage('node')->load($eu_index_entity);
      $eu_index_data = IndexHelper::getEuIndexData($this->dateFormatter, $eu_index);

      $data = $eu_index_data['report_json_data'][0];
      $filename = 'EUCSI-EU-report-' . $options['year'] . '.xlsx';
    }

    try {
      $report_excel_builder = new ReportExcelBuilder(
        $this->twig,
        $options['country_iso'],
        $data);
      $spreadsheet = $report_excel_builder->getSpreadsheet();
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
