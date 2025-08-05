<?php

namespace Drupal\data_collection_management\Controller;

use Drupal\data_collection_management\Helper\DataCollectionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\survey_management\Helper\SurveyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class Data Collection Datatable Controller.
 */
final class DataCollectionDatatableController extends ControllerBase {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable requestStack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;
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
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RequestStack $requestStack,
    Connection $database,
    DateFormatterInterface $dateFormatter,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * Function listDataCollection.
   */
  public function listDataCollection() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_survey_entity = SurveyHelper::getExistingPublishedSurveyForYearEntity($this->entityTypeManager, $year);

    $imported_indicators = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $year, 'manual');
    $eurostat_indicators = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $year, 'eurostat');

    $countries = DataCollectionHelper::getDataCollectionCountries(
      $this->entityTypeManager,
      $this->database,
      $this->dateFormatter,
      $imported_indicators,
      $eurostat_indicators,
      $year,
      $published_survey_entity);

    return new JsonResponse([
      'status' => 'success',
      'data' => $countries,
    ]);
  }

}
