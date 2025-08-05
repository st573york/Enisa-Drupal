<?php

namespace Drupal\drush_commands\Command;

use Drush\Commands\DrushCommands;
use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\survey_management\Helper\SurveyHelper;
use Drupal\export_management\Excel\SurveyExcelBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

/**
 * Class Export Survey Excel.
 */
final class ExportSurveyExcel extends DrushCommands {
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
   * Export survey, and MS survey if iso is given, for given year.
   *
   * @command commands:export-survey-excel
   * @description Export survey excel for given year.
   * When country iso is provided, survey answers are included.
   * @aliases export-survey-excel
   */
  public function exportSurveyExcel($options = ['year' => NULL, 'country_iso' => NULL]) {
    $ret = $this->validateOptions($options);
    if (!$ret) {
      return DrushCommands::EXIT_FAILURE;
    }

    $directory = 'public://offline-survey/' . $options['year'];
    $filename = 'SurveyTemplate.xlsx';

    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $indicator_entities = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $options['year'], 'survey');

    if (empty($indicator_entities)) {
      $this->io()->error("Survey indicators were not found for year: '{$options['year']}'!");

      return DrushCommands::EXIT_FAILURE;
    }

    $country_survey = NULL;

    if (!is_null($options['country_iso'])) {
      $published_survey_entity = SurveyHelper::getExistingPublishedSurveyForYearEntity($this->entityTypeManager, $options['year']);

      if (is_null($published_survey_entity)) {
        $this->io()->error("Survey record was not found for year: '{$options['year']}'!");

        return DrushCommands::EXIT_FAILURE;
      }

      $term = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', $options['country_iso'], 'field_iso');

      $country_survey = CountrySurveyHelper::getCountrySurveyEntity($this->entityTypeManager, $published_survey_entity, $term->id());

      $filename = 'SurveyWithAnswers' . $term->field_iso->value . '.xlsx';
    }

    try {
      $survey_template_builder = new SurveyExcelBuilder(
        $this->entityTypeManager,
        $this->fileSystem,
        $this->database,
        $this->dateFormatter,
        $this->twig,
        $options['year'],
        $country_survey);
      $spreadsheet = $survey_template_builder->getSpreadsheet();
      $spreadsheet->setActiveSheetIndex(0);

      $writer = new Xlsx($spreadsheet);
      $writer->save($directory . '/' . $filename);
    }
    catch (\Exception $e) {
      $this->io()->error($e->getMessage());

      return DrushCommands::EXIT_FAILURE;
    }

    return json_encode(['filename' => $filename]);
  }

}
