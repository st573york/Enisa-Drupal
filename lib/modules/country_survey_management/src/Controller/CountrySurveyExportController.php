<?php

namespace Drupal\country_survey_management\Controller;

use Drupal\country_survey_management\Helper\CountrySurveyActionHelper;
use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\survey_management\Helper\SurveyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Controller for exporting country survey data to Excel format.
 */
final class CountrySurveyExportController extends ControllerBase {
  /**
   * Summary of entityTypeManager.
   *
   * @var object
   */
  protected $entityTypeManager;
  /**
   * The current user.
   *
   * @var object
   */
  protected $currentUser;
  /**
   * The file system service.
   *
   * @var object
   */
  protected $fileSystem;
  /**
   * Date formatter service.
   *
   * @var object
   */
  protected $dateFormatter;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $current_user,
    FileSystemInterface $fileSystem,
    DateFormatterInterface $dateFormatter,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->fileSystem = $fileSystem;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Creates an instance of the controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('file_system'),
      $container->get('date.formatter')
    );
  }

  /**
   * Exports the survey Excel template for a given survey ID.
   */
  public function exportSurveyExcelTemplate(Request $request, $survey_id) {
    $inputs = $request->request->all();

    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    $year = $survey_data['year'];

    $indicators_assigned = [];

    if (isset($inputs['country_survey_id'])) {
      $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $inputs['country_survey_id']);

      $indicators_assigned = (!empty($assignee_country_survey_data['indicators_assigned']))
        ? $assignee_country_survey_data['indicators_assigned']['identifier']
        : [];
    }

    $ret = CountrySurveyActionHelper::exportSurveyExcel($year);

    if (!$ret) {
      return new JsonResponse(['error' => 'Survey excel cannot be created!'], 404);
    }

    CountrySurveyActionHelper::filterSurveyExcel($this->entityTypeManager, $this->currentUser, $this->fileSystem, $year, $ret['filename'], $indicators_assigned);

    return new JsonResponse([
      'status' => 'success',
      'filename' => $ret['filename'],
    ]);
  }

  /**
   * Exports the survey Excel with answers for a survey ID and country ID.
   */
  public function exportSurveyExcelWithAnswers(Request $request, $survey_id) {
    $inputs = $request->request->all();

    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    $year = $survey_data['year'];

    $indicators_assigned = [];

    $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $inputs['country_survey_id']);

    $indicators_assigned = (!empty($assignee_country_survey_data['indicators_assigned']))
      ? $assignee_country_survey_data['indicators_assigned']['identifier']
      : [];

    $country_survey = $this->entityTypeManager->getStorage('node')->load($inputs['country_survey_id']);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);

    $country = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', $country_survey_data['country']['id'], 'tid');
    $country_iso = $country->field_iso->value;

    $ret = CountrySurveyActionHelper::exportSurveyExcel($year, $country_iso);

    if (!$ret) {
      return new JsonResponse(['error' => 'Survey excel cannot be created!'], 404);
    }

    CountrySurveyActionHelper::filterSurveyExcel($this->entityTypeManager, $this->currentUser, $this->fileSystem, $year, $ret['filename'], $indicators_assigned);

    return new JsonResponse([
      'status' => 'success',
      'filename' => $ret['filename'],
    ]);
  }

  /**
   * Downloads the survey Excel file.
   */
  public function downloadSurveyExcel($filename) {
    $file_uri = 'public://offline-survey/user-' . $this->currentUser->id() . '/' . $filename;
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->deleteFileAfterSend(TRUE);

    return $response;
  }

}
