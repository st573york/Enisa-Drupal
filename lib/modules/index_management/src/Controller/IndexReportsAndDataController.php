<?php

namespace Drupal\index_management\Controller;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_management\Helper\IndexReportsAndDataHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Class Index Reports And Data Controller.
 */
final class IndexReportsAndDataController extends ControllerBase {
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
    FileSystemInterface $fileSystem,
    DateFormatterInterface $dateFormatter,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->fileSystem = $fileSystem;
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
      $container->get('file_system'),
      $container->get('date.formatter'),
      $container->get('twig')
    );
  }

  /**
   * Function viewReportsAndData.
   */
  public function viewReportsAndData() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $loaded_index = $this->entityTypeManager->getStorage('node')->load($loaded_index_entity);
    $loaded_index_data = IndexHelper::getIndexData($this->dateFormatter, $loaded_index);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $eu_index_entities = IndexHelper::getEuIndexEntities($this->entityTypeManager, $loaded_index_entity);
    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $loaded_index_entity, $countries);

    $countries_index_data = IndexHelper::getCountriesIndexData($this->entityTypeManager, $this->dateFormatter, $country_index_entities);

    $countries_index = array_filter($countries_index_data, function ($country_index_data) {
      return !empty($country_index_data['report_json_data']);
    });

    $data_available = (!empty($eu_index_entities) && !empty($countries_index)) ? TRUE : FALSE;

    $data = [];
    if ($loaded_index_data['ms_published']) {
      array_push($data, [
        'type' => 'ms_report',
        'country' => TRUE,
        'title' => 'MS Report',
      ]);
    }
    if ($loaded_index_data['eu_published']) {
      array_push($data, [
        'type' => 'eu_report',
        'country' => FALSE,
        'title' => 'EU Report',
      ]);
    }
    if ($loaded_index_data['ms_published']) {
      array_push($data, [
        'type' => 'ms_raw_data',
        'country' => TRUE,
        'title' => 'MS Raw Data',
      ]);

      if (UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser)) {
        array_push($data, [
          'type' => 'ms_results',
          'country' => FALSE,
          'title' => 'MS Results',
        ]);
      }
    }

    return [
      '#theme' => 'reports-and-data',
      '#is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
      '#is_enisa' => UserPermissionsHelper::isEnisa($this->entityTypeManager, $this->currentUser, $this->dateFormatter),
      '#years' => IndexHelper::getIndexYearChoices($this->entityTypeManager, $this->dateFormatter),
      '#loaded_index_data' => $loaded_index_data,
      '#countries_index' => $countries_index,
      '#data_available' => $data_available,
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'reports_and_data' => [
            'is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
            'loaded_index_data' => $loaded_index_data,
            'data_available' => $data_available,
            'data' => $data,
          ],
        ],
      ],
    ];
  }

  /**
   * Function viewReportsAndDataMsReport.
   */
  public function viewReportsAndDataMsReport($country_index_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $loaded_index_entity, $countries);
    if (!in_array($country_index_id, $country_index_entities)) {
      return new JsonResponse(['error' => 'You are not authorized!'], 403);
    }

    $country_index = $this->entityTypeManager->getStorage('node')->load($country_index_id);
    $country_index_data = IndexHelper::getCountryIndexData($this->dateFormatter, $country_index);

    $map_content = file_get_contents('public://reports/countries/maps/' . $country_index_data['country']['name'] . '.svg');

    return new JsonResponse($this->twig->render('@enisa/index/report-ms.html.twig', [
      'country_index' => $country_index_data,
      'data' => $country_index_data['report_json_data'][0],
      'map_content' => $map_content,
    ]));
  }

  /**
   * Function getReportsAndDataMsReportChartData.
   */
  public function getReportsAndDataMsReportChartData($country_index_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $loaded_index_entity, $countries);
    if (!in_array($country_index_id, $country_index_entities)) {
      return new JsonResponse(['error' => 'You are not authorized!'], 403);
    }

    $country_index = $this->entityTypeManager->getStorage('node')->load($country_index_id);
    $country_index_data = IndexHelper::getCountryIndexData($this->dateFormatter, $country_index);

    $data = IndexReportsAndDataHelper::getReportChartMsData($country_index_data);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function viewReportsAndDataEuReport.
   */
  public function viewReportsAndDataEuReport() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $eu_index_entity = IndexHelper::getEuIndexEntity($this->entityTypeManager, $loaded_index_entity);

    $eu_index = $this->entityTypeManager->getStorage('node')->load($eu_index_entity);
    $eu_index_data = IndexHelper::getEuIndexData($this->dateFormatter, $eu_index);

    IndexReportsAndDataHelper::calculateEuRange($eu_index_data);

    return new JsonResponse($this->twig->render('@enisa/index/report-eu.html.twig', [
      'data' => $eu_index_data['report_json_data'][0],
    ]));
  }

  /**
   * Function getReportsAndDataEuReportChartData.
   */
  public function getReportsAndDataEuReportChartData() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $eu_index_entity = IndexHelper::getEuIndexEntity($this->entityTypeManager, $loaded_index_entity);

    $eu_index = $this->entityTypeManager->getStorage('node')->load($eu_index_entity);
    $eu_index_data = IndexHelper::getEuIndexData($this->dateFormatter, $eu_index);

    $data = IndexReportsAndDataHelper::getReportChartEuData($eu_index_data);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function downloadReportsAndDataMsReportPdf.
   */
  public function downloadReportsAndDataMsReportPdf($country_index_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $loaded_index_entity, $countries);
    if (!in_array($country_index_id, $country_index_entities)) {
      return new JsonResponse(['error' => 'You are not authorized!'], 403);
    }

    $country_index = $this->entityTypeManager->getStorage('node')->load($country_index_id);
    $country_index_data = IndexHelper::getCountryIndexData($this->dateFormatter, $country_index);

    $filename = 'EUCSI-MS-report-' . $year . '-' . $country_index_data['country']['iso'] . '.pdf';

    $file_uri = 'public://reports/pdf/' . $year . '/' . $filename;
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

    return $response;
  }

  /**
   * Function downloadReportsAndDataEuReportPdf.
   */
  public function downloadReportsAndDataEuReportPdf() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $filename = 'EUCSI-EU-report-' . $year . '.pdf';

    $file_uri = 'public://reports/pdf/' . $year . '/' . $filename;
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

    return $response;
  }

  /**
   * Function exportReportsAndDataReportExcel.
   */
  public function exportReportsAndDataReportExcel($country_id = NULL) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $country_iso = NULL;
    if (!is_null($country_id)) {
      $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

      if (!in_array($country_id, $countries)) {
        return new JsonResponse(['error' => 'You are not authorized!'], 403);
      }

      $country = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', $country_id, 'tid');
      $country_iso = $country->field_iso->value;
    }

    $ret = IndexReportsAndDataHelper::exportReportExcel($year, $country_iso);

    if (!$ret) {
      return new JsonResponse(['error' => 'Report excel cannot be created!'], 404);
    }

    return new JsonResponse([
      'status' => 'success',
      'filename' => $ret['filename'],
    ]);
  }

  /**
   * Function downloadReportsAndDataReportExcel.
   */
  public function downloadReportsAndDataReportExcel($filename) {
    $file_uri = 'public://' . $filename;
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->deleteFileAfterSend(TRUE);

    return $response;
  }

  /**
   * Function exportReportsAndDataMsRawDataExcel.
   */
  public function exportReportsAndDataMsRawDataExcel($country_index_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $countries = UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id');

    $country_index_entities = IndexHelper::getCountryIndexEntities($this->entityTypeManager, $loaded_index_entity, $countries);
    if (!in_array($country_index_id, $country_index_entities)) {
      return new JsonResponse(['error' => 'You are not authorized!'], 403);
    }

    $country_index = $this->entityTypeManager->getStorage('node')->load($country_index_id);
    $country_index_data = IndexHelper::getCountryIndexData($this->dateFormatter, $country_index);

    $ret = IndexReportsAndDataHelper::ExportMsRawDataExcel($year, $country_index_data['country']['iso']);

    if (!$ret) {
      return new JsonResponse(['error' => 'MS raw data excel cannot be created!'], 404);
    }

    return new JsonResponse([
      'status' => 'success',
      'filename' => $ret['filename'],
    ]);
  }

  /**
   * Function downloadReportsAndDataMsRawDataExcel.
   */
  public function downloadReportsAndDataMsRawDataExcel($filename) {
    $file_uri = 'public://' . $filename;
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->deleteFileAfterSend(TRUE);

    return $response;
  }

  /**
   * Function downloadReportsAndDataMsResultsExcel.
   */
  public function downloadReportsAndDataMsResultsExcel() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $loaded_index_entity = IndexHelper::getLoadedPublishedIndexEntity($this->entityTypeManager, $year);

    $filename = 'EUCSI-results';

    $file_uri = 'public://index-calculations/' . $year . '/' . $loaded_index_entity . '/results/' . $filename . '.xlsx';
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename . '-' . $year . '.xlsx');

    return $response;
  }

}
