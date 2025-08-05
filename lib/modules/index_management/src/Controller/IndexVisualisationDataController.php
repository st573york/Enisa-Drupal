<?php

namespace Drupal\index_management\Controller;

use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_management\Helper\IndexVisualisationDataHelper;
use Drupal\index_management\Helper\IndexVisualisationSunburstDataHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Class Index Visualisation Data Controller.
 */
final class IndexVisualisationDataController extends ControllerBase {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable currentUser.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
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
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
    Connection $database,
    DateFormatterInterface $dateFormatter,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
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
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('twig')
    );
  }

  /**
   * Function viewVisualisations.
   */
  public function viewVisualisations() {
    return [
      '#theme' => 'visualisation',
      '#years' => IndexHelper::getIndexYearChoices($this->entityTypeManager, $this->dateFormatter),
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'visualisation' => [
            'is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
          ],
        ],
      ],
    ];
  }

  /**
   * Function getVisualisationsData.
   */
  public function getVisualisationsData() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);

    $published_index = $this->entityTypeManager->getStorage('node')->load($published_index_entity);
    $published_index_data = IndexHelper::getIndexData($this->dateFormatter, $published_index);

    $eu_index_entities = IndexHelper::getEuIndexEntities($this->entityTypeManager, $published_index_entity);
    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $published_index_entity);

    $data = [
      'data_available' => (!empty($eu_index_entities) && !empty($country_index_entities)) ? TRUE : FALSE,
      'published_index_data' => $published_index_data,
      'visualisation_data' => [],
    ];

    if ($data['data_available']) {
      $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

      $data['visualisation_data'] = IndexVisualisationDataHelper::getVisualisationDataForComparison($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $published_index_data, $countries);
    }

    return new JsonResponse($this->twig->render('@enisa/index/visualisation-data.html.twig', $data));
  }

  /**
   * Function getVisualisationsNodeData.
   */
  public function getVisualisationsNodeData($node) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);

    $published_index = $this->entityTypeManager->getStorage('node')->load($published_index_entity);
    $published_index_data = IndexHelper::getIndexData($this->dateFormatter, $published_index);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $data = IndexVisualisationDataHelper::getNodeVisualisationDataForComparison($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $published_index_data, $countries, $node);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function getVisualisationsSunburstData.
   */
  public function getVisualisationsSunburstData() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);

    $published_index = $this->entityTypeManager->getStorage('node')->load($published_index_entity);
    $published_index_data = IndexHelper::getIndexData($this->dateFormatter, $published_index);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $data = IndexVisualisationSunburstDataHelper::getSunburstVisualisationDataForComparison($this->entityTypeManager, $this->database, $this->dateFormatter, $published_index_data, $countries);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function getVisualisationsSliderChartData.
   */
  public function getVisualisationsSliderChartData() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);

    $published_index = $this->entityTypeManager->getStorage('node')->load($published_index_entity);
    $published_index_data = IndexHelper::getIndexData($this->dateFormatter, $published_index);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $data = IndexVisualisationDataHelper::getNodeVisualisationDataForComparison($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $published_index_data, $countries, 'Index');

    return new JsonResponse($this->twig->render('@enisa/index/visualisation-chart-data.html.twig', $data));
  }

}
