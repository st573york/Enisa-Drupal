<?php

namespace Drupal\data_collection_management\Controller;

use Drupal\index_management\Helper\IndexHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class Data Collection Theme Controller.
 */
final class DataCollectionThemeController extends ControllerBase {
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
    DateFormatterInterface $dateFormatter,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('date.formatter')
    );
  }

  /**
   * Function manageDataCollection.
   */
  public function manageDataCollection() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_index_entities = IndexHelper::getPublishedIndexEntities($this->entityTypeManager);
    $published_indexes = IndexHelper::getIndexesData($this->entityTypeManager, $this->dateFormatter, $published_index_entities);

    $latest_index_entity = IndexHelper::getLatestPublishedIndexEntity($this->entityTypeManager);
    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year, $latest_index_entity);

    $loaded_index = $this->entityTypeManager->getStorage('node')->load($loaded_index_entity);
    $loaded_index_data = IndexHelper::getIndexData($this->dateFormatter, $loaded_index);

    $latest_index = $this->entityTypeManager->getStorage('node')->load($latest_index_entity);
    $latest_index_data = IndexHelper::getIndexData($this->dateFormatter, $latest_index);

    return [
      '#theme' => 'manage-data-collection',
      // Pass data to Twig templates.
      '#published_indexes' => $published_indexes,
      '#loaded_index_data' => $loaded_index_data,
      '#latest_index_data' => $latest_index_data,
      '#is_latest_index' => ($latest_index_data['id'] == $loaded_index_data['id']) ? TRUE : FALSE,
      '#canDownload' => ($loaded_index_data['last_action'] == 'calculate_index' && $loaded_index_data['last_action_state'] == 'completed') ? TRUE : FALSE,
    ];
  }

}
