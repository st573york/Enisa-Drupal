<?php

namespace Drupal\data_collection_management\Controller;

use Drupal\data_collection_management\Helper\DataCollectionHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class Data Collection Action Controller.
 */
final class DataCollectionActionController extends ControllerBase {
  const ERROR_NOT_AUTHORIZED = 'You are not authorized for this action!';
  const ERROR_NOT_ALLOWED = 'The requested action is not allowed!';

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
   * Member variable fileSystem.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;
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
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    RequestStack $requestStack,
    Connection $database,
    FileSystemInterface $fileSystem,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->database = $database;
    $this->fileSystem = $fileSystem;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
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
      $container->get('file_system'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * Function storeDataCollectionImportData.
   */
  public function storeDataCollectionImportData(Request $request) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['file'] = $file = $request->files->get('file');

    $errors = DataCollectionHelper::validateDataCollectionImport($inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors], 400);
    }

    if (!UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);
    $latest_index_entity = IndexHelper::getLatestPublishedIndexEntity($this->entityTypeManager);
    if ($published_index_entity != $latest_index_entity) {
      return new JsonResponse(['error' => self::ERROR_NOT_ALLOWED], 405);
    }

    $index = $this->entityTypeManager->getStorage('node')->load($published_index_entity);

    $directory = 'public://import-data/' . $year;

    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $original_name = $file->getClientOriginalName();
    $filename = time() . '_' . $original_name;

    $file->move($directory, $filename);

    $excel = $directory . '/' . $filename;

    $index->set('field_last_action', 'import_data');
    $index->set('field_last_action_date', $this->time->getCurrentTime());

    try {
      $resp = DataCollectionHelper::importData($this->entityTypeManager, $this->fileSystem, $this->database, $year, $excel);
      if ($resp['type'] == 'error') {
        throw new \Exception($resp['msg']);
      }

      $index->set('field_last_action_state', 'completed');
      $index->save();

      return new JsonResponse('success');
    }
    catch (\Exception $e) {
      $index->set('field_last_action_state', 'failed');
      $index->save();

      $this->fileSystem->unlink($excel);

      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Function exportDataCollectionDataExcel.
   */
  public function exportDataCollectionDataExcel() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $ret = DataCollectionHelper::exportDataExcel($year, 'all', 'all');

    if (!$ret) {
      return new JsonResponse(['error' => 'Data excel cannot be created!'], 404);
    }

    return new JsonResponse([
      'status' => 'success',
      'filename' => $ret['filename'],
    ]);
  }

  /**
   * Function downloadDataCollectionDataExcel.
   */
  public function downloadDataCollectionDataExcel($filename) {
    $file_uri = 'public://' . $filename;
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->deleteFileAfterSend(TRUE);

    return $response;
  }

}
