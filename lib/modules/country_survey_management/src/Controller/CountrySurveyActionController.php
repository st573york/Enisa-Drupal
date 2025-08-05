<?php

namespace Drupal\country_survey_management\Controller;

use Drupal\country_survey_management\Helper\CountrySurveyActionHelper;
use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorAccessHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorActionHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorDataHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorRequestChangeActionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class Country Survey Action Controller.
 */
final class CountrySurveyActionController extends ControllerBase {
  const ERROR_NOT_AUTHORIZED = 'Indicator cannot be updated as you are not authorized for this action!';

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
   * Member variable csrfToken.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;
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
    AccountProxyInterface $current_user,
    CsrfTokenGenerator $csrf_token,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->csrfToken = $csrf_token;
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
      $container->get('csrf_token'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * Function loadCountrySurveyIndicatorAnswers.
   */
  public function loadCountrySurveyIndicatorAnswers(Request $request, $country_survey_id) {
    $inputs = $request->request->all();

    $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($this->entityTypeManager, $country_survey_id, $inputs['active_indicator']);

    $survey_indicator = $this->entityTypeManager->getStorage('node')->load($survey_indicator_entity);
    $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($this->entityTypeManager, $this->dateFormatter, $survey_indicator);

    if (!SurveyIndicatorAccessHelper::canLoadResetCountrySurveyIndicatorAnswers($this->currentUser, $survey_indicator_data)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $survey_indicator->set('field_answers_loaded', TRUE);
    $survey_indicator->save();

    return new JsonResponse('success');
  }

  /**
   * Function resetCountrySurveyIndicatorAnswers.
   */
  public function resetCountrySurveyIndicatorAnswers(Request $request, $country_survey_id) {
    $inputs = $request->request->all();

    $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($this->entityTypeManager, $country_survey_id, $inputs['active_indicator']);

    $survey_indicator = $this->entityTypeManager->getStorage('node')->load($survey_indicator_entity);
    $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($this->entityTypeManager, $this->dateFormatter, $survey_indicator);

    if (!SurveyIndicatorAccessHelper::canLoadResetCountrySurveyIndicatorAnswers($this->currentUser, $survey_indicator_data)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $survey_indicator->set('field_answers_loaded', FALSE);
    $survey_indicator->save();

    return new JsonResponse('success');
  }

  /**
   * Function saveCountrySurvey.
   */
  public function saveCountrySurvey(Request $request, $country_survey_id) {
    $inputs = $request->request->all();
    $indicators_list = $inputs['indicators_list'];
    $inputs['country_survey_answers'] = json_decode(htmlspecialchars_decode($inputs['country_survey_answers']), TRUE);
    $inputs['indicator_answers'] = json_decode(htmlspecialchars_decode($inputs['indicator_answers']), TRUE);
    $indicators = [];

    $country_survey = $this->entityTypeManager->getStorage('node')->load($country_survey_id);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);

    $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $country_survey_id);

    $resp = CountrySurveyActionHelper::canSaveCountrySurvey($inputs, $assignee_country_survey_data);
    if ($resp['type'] == 'warning') {
      return new JsonResponse([
        $resp['type'] => $resp['msg'],
        'indicators_assigned' => count($assignee_country_survey_data['indicators_assigned']),
      ],
      $resp['status']);
    }

    if ($inputs['action'] == 'save') {
      $indicators = [$inputs['active_indicator']];
    }
    elseif ($inputs['action'] == 'submit') {
      $messages = CountrySurveyActionHelper::validateAnswers($this->entityTypeManager, $inputs['country_survey_answers'], $assignee_country_survey_data);
      if (!empty($messages)) {
        return new JsonResponse(['error' => 'Survey answers are invalid. Please check the answers again!'], 400);
      }

      sort($indicators_list);
      sort($assignee_country_survey_data['indicators_assigned']['id']);

      $resp = CountrySurveyActionHelper::canSubmitCountrySurvey($this->currentUser, $indicators_list, $assignee_country_survey_data);
      if ($resp['type'] == 'warning') {
        return new JsonResponse([
          $resp['type'] => $resp['msg'],
          'indicators_assigned' => count($assignee_country_survey_data['indicators_assigned']),
        ],
        $resp['status']);
      }

      $indicators = $indicators_list;
    }

    $resp = SurveyIndicatorActionHelper::updateSurveyIndicatorsData(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $this->time,
      $country_survey,
      $country_survey_data,
      $indicators,
      $inputs);
    if ($resp['type'] == 'warning' ||
        $resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    if ($inputs['action'] == 'submit') {
      // Submitted by default assignee.
      if ($country_survey_data['default_assignee']['id'] == $this->currentUser->id()) {
        $country_survey->set('field_submitted_user', $this->currentUser->id());
        $country_survey->set('field_submitted_date', $this->time->getCurrentTime());
        $country_survey->save();
      }
      // Submitted by assignee.
      else {
        $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($this->entityTypeManager, ['country_survey' => $country_survey_data['id']]);

        $survey_indicators = $this->entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

        $indicators_data = SurveyIndicatorDataHelper::getSurveyIndicatorsData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $survey_indicators);

        if ($indicators_data['completed']) {
          $country_survey->set('field_completed', TRUE);
          $country_survey->save();
        }
      }

      SurveyIndicatorRequestChangeActionHelper::answerCountrySurveyRequestedChanges($this->entityTypeManager, $this->currentUser, $this->time, $country_survey_data['id']);
    }

    return new JsonResponse('success');
  }

  /**
   * Function validateCountrySurveyIndicator.
   */
  public function validateCountrySurveyIndicator(Request $request, $country_survey_id) {
    $inputs = $request->request->all();
    $indicator_answers = json_decode(htmlspecialchars_decode($inputs['indicator_answers']), TRUE);

    $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $country_survey_id);

    $messages = CountrySurveyActionHelper::validateAnswers($this->entityTypeManager, $indicator_answers, $assignee_country_survey_data);
    if (!empty($messages)) {
      return new JsonResponse(['errors' => $messages], 400);
    }

    return new JsonResponse('success');
  }

  /**
   * Function validateCountrySurveyOffline.
   */
  public function validateCountrySurveyOffline($country_survey_id) {
    $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $country_survey_id);

    if (empty($assignee_country_survey_data['indicators_assigned'])) {
      return new JsonResponse(['warning' => 'You haven\'t been assigned any indicators!'], 403);
    }

    return new JsonResponse('success');
  }

  /**
   * Function uploadCountrySurvey.
   */
  public function uploadCountrySurvey(Request $request) {
    $inputs = $request->request->all();

    if (!$this->csrfToken->validate($inputs['csrf-token'], 'upload-survey-token')) {
      return new JsonResponse(['error' => 'The request is invalid!'], 403);
    }

    // $file = $request->files->get('file');
    return new JsonResponse('success');
  }

  /**
   * Function updateCountrySurveyIndicator.
   */
  public function updateCountrySurveyIndicator(Request $request, $indicator_id) {
    $inputs = $request->request->all();

    if (!SurveyIndicatorAccessHelper::canUpdateSurveyIndicatorData($this->entityTypeManager, $this->currentUser, $inputs)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $country_survey = $this->entityTypeManager->getStorage('node')->load($inputs['country_survey_id']);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);

    if ($inputs['action'] == 'edit') {
      $errors = SurveyIndicatorAccessHelper::validateInputs($this->dateFormatter, $this->time, $country_survey_data, $inputs);
      if (!empty($errors)) {
        return new JsonResponse(['errors' => $errors], 400);
      }
    }

    $resp = SurveyIndicatorActionHelper::updateSurveyIndicatorsData(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $this->time,
      $country_survey,
      $country_survey_data,
      [$indicator_id],
      $inputs);
    if ($resp['type'] == 'warning' ||
        $resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    if ($inputs['action'] == 'final_approve') {
      $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($this->entityTypeManager, ['country_survey' => $country_survey_data['id']]);

      $survey_indicators = $this->entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

      $indicators_data = SurveyIndicatorDataHelper::getSurveyIndicatorsData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $survey_indicators);

      return new JsonResponse(['approved' => $indicators_data['approved']], 200);
    }

    return new JsonResponse('success');
  }

  /**
   * Function updateCountrySurveyIndicators.
   */
  public function updateCountrySurveyIndicators(Request $request) {
    $inputs = $request->request->all();

    if (!SurveyIndicatorAccessHelper::canUpdateSurveyIndicatorData($this->entityTypeManager, $this->currentUser, $inputs)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $country_survey = $this->entityTypeManager->getStorage('node')->load($inputs['country_survey_id']);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);

    if ($inputs['action'] == 'edit') {
      $errors = SurveyIndicatorAccessHelper::validateInputs($this->dateFormatter, $this->time, $country_survey_data, $inputs);
      if (!empty($errors)) {
        return new JsonResponse(['errors' => $errors], 400);
      }
    }

    $indicator_entities = array_filter(explode(',', $inputs['datatable-selected']));

    $resp = SurveyIndicatorActionHelper::updateSurveyIndicatorsData(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $this->time,
      $country_survey,
      $country_survey_data,
      $indicator_entities,
      $inputs);
    if ($resp['type'] == 'warning' ||
        $resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    if ($inputs['action'] == 'final_approve') {
      $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($this->entityTypeManager, ['country_survey' => $country_survey_data['id']]);

      $survey_indicators = $this->entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

      $indicators_data = SurveyIndicatorDataHelper::getSurveyIndicatorsData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $survey_indicators);

      return new JsonResponse(['approved' => $indicators_data['approved']], 200);
    }

    return new JsonResponse('success');
  }

  /**
   * Function finaliseCountrySurvey.
   */
  public function finaliseCountrySurvey(Request $request) {
    $inputs = $request->request->all();

    if (!CountrySurveyActionHelper::canFinaliseCountrySurvey($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => 'Survey cannot be finalised as you are not authorized for this action!'], 403);
    }

    $country_survey = $this->entityTypeManager->getStorage('node')->load($inputs['country_survey_id']);

    $country_survey->set('field_approved_user', $this->currentUser->id());
    $country_survey->save();

    return new JsonResponse('success');
  }

}
