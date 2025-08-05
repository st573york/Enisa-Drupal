<?php

namespace Drupal\drush_commands\Command;

use Drush\Commands\DrushCommands;
use Drupal\export_management\Excel\ConfigurationExcelBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

/**
 * Class Export Configuration Excel.
 */
final class ExportConfigurationExcel extends DrushCommands {
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
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
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

    return TRUE;
  }

  /**
   * Export configuration excel for given year.
   *
   * @command commands:export-configuration-excel
   * @description Export configuration excel for given year.
   * @aliases export-configuration-excel
   */
  public function exportConfigurationExcel($options = ['year' => NULL]) {
    $ret = $this->validateOptions($options);
    if (!$ret) {
      return DrushCommands::EXIT_FAILURE;
    }

    $filename = 'Index_Properties_' . $options['year'] . '.xlsx';

    try {
      $configuration_template_builder = new ConfigurationExcelBuilder(
        $this->entityTypeManager,
        $this->database,
        $this->twig,
        $options['year']);
      $spreadsheet = $configuration_template_builder->getSpreadsheet();
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
