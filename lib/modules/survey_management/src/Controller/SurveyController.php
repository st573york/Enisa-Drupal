<?php

namespace Drupal\survey_management\Controller;

use Drupal\country_survey_management\Helper\CountrySurveyActionHelper;
use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\survey_management\Helper\SurveyHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorActionHelper;
use Drupal\user_management\Helper\UserHelper;
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
 * Class Survey Controller.
 */
final class SurveyController extends ControllerBase {
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
   * Function manageSurveys.
   */
  public function manageSurveys() {
    return [
      '#theme' => 'manage-surveys',
    ];
  }

  /**
   * Function listSurveys.
   */
  public function listSurveys() {
    $entities = SurveyHelper::getSurveyEntities($this->entityTypeManager);
    $data = SurveyHelper::getSurveysData($this->entityTypeManager, $this->dateFormatter, $entities);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function manageSurvey.
   */
  public function manageSurvey($action, $survey_data = NULL) {
    $entities = IndexHelper::getIndexEntities($this->entityTypeManager);
    $indexes_data = IndexHelper::getIndexesData($this->entityTypeManager, $this->dateFormatter, $entities);

    return new JsonResponse($this->twig->render('@enisa/ajax/survey-management.html.twig', [
      'action' => $action,
      'selected_survey' => $survey_data,
      'indexes' => $indexes_data,
    ]));
  }

  /**
   * Function createSurvey.
   */
  public function createSurvey() {
    return $this->manageSurvey('create');
  }

  /**
   * Function storeSurvey.
   */
  public function storeSurvey(Request $request) {
    $inputs = $request->request->all();

    $errors = SurveyHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    $index = $this->entityTypeManager->getStorage('node')->load($inputs['survey_configuration_id']);
    $index_data = IndexHelper::getIndexData($this->dateFormatter, $index);

    if (!IndicatorHelper::areIndicatorsValidated($this->entityTypeManager, $index_data['year'])) {
      return new JsonResponse([
        'error' =>
        'Survey cannot be created. Please validate all indicators first!',
        'type' => 'pageAlert',
      ],
        405);
    }

    $inputs['year'] = $index_data['year'];

    SurveyHelper::storeSurvey($this->services, $inputs);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function showSurvey.
   */
  public function showSurvey($survey_id) {
    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    return $this->manageSurvey('show', $survey_data);
  }

  /**
   * Function updateSurvey.
   */
  public function updateSurvey(Request $request, $survey_id) {
    $inputs = $request->request->all();
    $inputs['id'] = $survey_id;

    $errors = SurveyHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    $index = $this->entityTypeManager->getStorage('node')->load($inputs['survey_configuration_id']);
    $index_data = IndexHelper::getIndexData($this->dateFormatter, $index);

    if (!IndicatorHelper::areIndicatorsValidated($this->entityTypeManager, $index_data['year'])) {
      return new JsonResponse([
        'error' =>
        'Survey cannot be updated. Please validate all indicators first!',
        'type' => 'pageAlert',
      ],
        405);
    }

    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    $inputs['year'] = $index_data['year'];

    SurveyHelper::updateSurvey($this->services, $survey, $survey_data, $inputs);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function showPublishSurvey.
   */
  public function showPublishSurvey($survey_id) {
    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    return $this->manageSurvey('publish', $survey_data);
  }

  /**
   * Function listPublishUsers.
   */
  public function listPublishUsers($survey_id) {
    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    $data = SurveyHelper::getSurveyUsers($this->entityTypeManager, $this->dateFormatter, $survey_data);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function createPublishSurvey.
   */
  public function createPublishSurvey(Request $request, $survey_id) {
    $inputs = $request->request->all();

    $entities = [];
    if (isset($inputs['datatable-selected']) &&
        isset($inputs['datatable-all'])) {
      if ($inputs['publish_users'] == 'radio-specific') {
        $entities = array_filter(explode(',', $inputs['datatable-selected']));
      }
      elseif ($inputs['publish_users'] == 'radio-all') {
        $entities = array_filter(explode(',', $inputs['datatable-all']));
      }
    }

    if (empty($entities)) {
      return new JsonResponse([
        'error' => 'You haven\'t selected any users!',
        'type' => 'pageModalAlert',
      ], 400);
    }

    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    if (!IndicatorHelper::areIndicatorsValidated($this->entityTypeManager, $survey_data['year'])) {
      return new JsonResponse([
        'error' => 'Survey cannot be published. Please validate all indicators first!',
        'type' => 'pageAlert',
      ],
        405);
    }

    SurveyHelper::publishSurvey($survey);

    $assigned_users = [];

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($entities);
    foreach ($users as $user) {
      $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

      $country_survey = CountrySurveyHelper::getCountrySurveyEntity($this->entityTypeManager, $survey_data['id'], $user_data['country']['id']);

      if (is_null($country_survey)) {
        $country_survey = CountrySurveyActionHelper::storeCountrySurvey($this->entityTypeManager, $survey_data, $user_data);

        $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);

        $indicators = $this->entityTypeManager->getStorage('node')->loadMultiple($survey_data['assigned_indicators']);

        foreach ($indicators as $indicator) {
          $indicator_data = IndicatorHelper::getIndicatorData($indicator);

          SurveyIndicatorActionHelper::storeSurveyIndicator($this->entityTypeManager, $country_survey_data, $user_data, $indicator_data);
        }
      }

      array_push($assigned_users, [
        'target_id' => $user_data['id'],
      ]);
    }

    $survey->set('field_assigned_users', $assigned_users);
    $survey->save();

    CountrySurveyActionHelper::exportSurveyExcel($survey_data['year']);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function deleteSurvey.
   */
  public function deleteSurvey($survey_id) {
    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    SurveyHelper::deleteSurvey($this->services, $survey, $survey_data);

    return new JsonResponse(['status' => 'success']);
  }

}
