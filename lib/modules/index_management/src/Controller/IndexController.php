<?php

namespace Drupal\index_management\Controller;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\index_management\Helper\IndexHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Class Index Controller.
 */
final class IndexController extends ControllerBase {
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
   * Member variable time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;
  /**
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;
  /**
   * Member variable services.
   *
   * @var array<string, object>
   */
  protected $services = [];

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    RequestStack $requestStack,
    Connection $database,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->twig = $twig;

    $this->services = [
      'entityTypeManager' => $entityTypeManager,
      'currentUser' => $currentUser,
      'requestStack' => $requestStack,
      'database' => $database,
      'dateFormatter' => $dateFormatter,
      'time' => $time,
      'twig' => $twig,
    ];
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
      $container->get('datetime.time'),
      $container->get('twig')
    );
  }

  /**
   * Function manageIndexes.
   */
  public function manageIndexes() {
    return [
      '#theme' => 'manage-indexes',
    ];
  }

  /**
   * Function listIndexes.
   */
  public function listIndexes() {
    $entities = IndexHelper::getIndexEntities($this->entityTypeManager);
    $data = IndexHelper::getIndexesData($this->entityTypeManager, $this->dateFormatter, $entities);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function createOrShowIndex.
   */
  public function createOrShowIndex($index_data = []) {
    return new JsonResponse($this->twig->render('@enisa/ajax/index-management.html.twig', [
      'years' => ConstantHelper::getYearsToDateAndNext($this->dateFormatter, $this->time),
      'index_data' => $index_data,
    ]));
  }

  /**
   * Function showIndex.
   */
  public function showIndex($index_id) {
    $index = $this->entityTypeManager->getStorage('node')->load($index_id);
    $index_data = IndexHelper::getIndexData($this->dateFormatter, $index);

    $eu_index_entities = IndexHelper::getEuIndexEntities($this->entityTypeManager, $index_id);
    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $index_id);

    $index_data['eu_indexes_count'] = count($eu_index_entities);
    $index_data['country_indexes_count'] = count($country_index_entities);

    return $this->createOrShowIndex($index_data);
  }

  /**
   * Function storeIndex.
   */
  public function storeIndex(Request $request) {
    $inputs = $request->request->all();
    $inputs['year'] = (isset($inputs['year']) && $inputs['year']) ? $inputs['year'] : '';

    $errors = IndexHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    IndexHelper::storeIndex($this->services, $inputs);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function updateIndex.
   */
  public function updateIndex(Request $request, $index_id) {
    $inputs = $request->request->all();
    $inputs['id'] = $index_id;

    $index = $this->entityTypeManager->getStorage('node')->load($index_id);
    $index_data = IndexHelper::getIndexData($this->dateFormatter, $index);

    $inputs['name'] = (!isset($inputs['name'])) ? $index_data['title'] : $inputs['name'];
    $inputs['description'] = (!isset($inputs['description'])) ? $index_data['description'] : $inputs['description'];
    $inputs['year'] = (!isset($inputs['year'])) ? $index_data['year'] : $inputs['year'];

    if ($index_data['status'] == 'Unpublished') {
      $errors = IndexHelper::validateInputs($this->entityTypeManager, $inputs);
      if (!empty($errors)) {
        return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
      }
    }

    $error = IndexHelper::validateIndexStatusAndData($this->entityTypeManager, $inputs);
    if ($error) {
      return new JsonResponse(['error' => $error], 405);
    }

    IndexHelper::updateIndex($this->services, $index, $index_data, $inputs);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function deleteIndex.
   */
  public function deleteIndex($index_id) {
    $index = $this->entityTypeManager->getStorage('node')->load($index_id);
    $index_data = IndexHelper::getIndexData($this->dateFormatter, $index);

    IndexHelper::deleteIndex($this->services, $index, $index_data);

    return new JsonResponse(['status' => 'success']);
  }

}
