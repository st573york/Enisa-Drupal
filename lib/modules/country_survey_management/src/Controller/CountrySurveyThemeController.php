<?php

namespace Drupal\country_survey_management\Controller;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\country_survey_management\Helper\CountrySurveySummaryDataHelper;
use Drupal\country_survey_management\Helper\CountrySurveyThemeHelper;
use Drupal\survey_management\Helper\SurveyHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorRequestChangeHelper;
use Drupal\user_management\Helper\UserHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class Country Survey Theme Controller.
 */
final class CountrySurveyThemeController extends ControllerBase {
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
    RequestStack $request_stack,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->csrfToken = $csrf_token;
    $this->requestStack = $request_stack;
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
      $container->get('request_stack'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * Function manageUserSurveys.
   */
  public function manageUserSurveys() {
    $assignee_country_surveys_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter);

    $surveys = $assignee_country_surveys_data;
    $surveys_assigned = array_filter($surveys, function ($survey) {
      return (!empty($survey['indicators_assigned']));
    });
    $surveys_assigned = array_values($surveys_assigned);

    return [
      '#theme' => 'manage-user-surveys',
      // Pass data to Twig templates.
      '#is_operator' => UserPermissionsHelper::isOperator($this->entityTypeManager, $this->currentUser),
      '#is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
      '#is_primary_poc' => UserPermissionsHelper::isPrimaryPoC($this->entityTypeManager, $this->currentUser),
      '#surveys' => $surveys,
      '#surveys_assigned' => $surveys_assigned,
      '#upload_survey_token' => $this->csrfToken->get('upload-survey-token'),
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'manage_user_surveys' => [
            'user_group' => ConstantHelper::USER_GROUP,
            'is_operator' => UserPermissionsHelper::isOperator($this->entityTypeManager, $this->currentUser),
            'is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
            'is_primary_poc' => UserPermissionsHelper::isPrimaryPoC($this->entityTypeManager, $this->currentUser),
            'surveys' => $surveys,
            'surveys_assigned' => $surveys_assigned,
            'view_survey_token' => $this->csrfToken->get('view-survey-token'),
          ],
        ],
      ],
    ];
  }

  /**
   * Function viewCountrySurvey.
   */
  public function viewCountrySurvey(Request $request, $country_survey_id) {
    $inputs = $request->request->all();

    if (!$this->csrfToken->validate($inputs['csrf-token'], 'view-survey-token')) {
      return new JsonResponse(['error' => 'The request is invalid!'], 403);
    }

    $action = $inputs['action'];
    $requested_indicator = (isset($inputs['requested_indicator'])) ? $inputs['requested_indicator'] : NULL;
    $requested_action = (isset($inputs['requested_action'])) ? $inputs['requested_action'] : NULL;

    $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $country_survey_id);

    $last_country_survey_data = CountrySurveyThemeHelper::getLastCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $assignee_country_survey_data);
    $last_survey_indicators_data = CountrySurveyThemeHelper::getLastSurveyIndicatorsData($this->entityTypeManager, $this->dateFormatter, $last_country_survey_data);
    $survey_indicators_data = CountrySurveyThemeHelper::getSurveyIndicatorsData($this->entityTypeManager, $this->dateFormatter, $assignee_country_survey_data);

    $page_data = CountrySurveyHelper::getPageData(
      $this->entityTypeManager,
      $this->currentUser,
      $this->requestStack,
      $assignee_country_survey_data);

    $current_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $current_user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $current_user);

    $view_all = (($assignee_country_survey_data['indicators_submitted'] &&
                  UserPermissionsHelper::isOperator($this->entityTypeManager, $this->currentUser)) ||
                 (!empty($assignee_country_survey_data['submitted_user']) &&
                  UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser)) ||
                 !empty($assignee_country_survey_data['approved_user']) ||
                 $action == 'export') ? TRUE : FALSE;

    $years = range(2000, $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'Y') + 1);
    rsort($years);

    return [
      '#theme' => 'view-country-survey',
      '#page_data' => $page_data,
      '#current_user_data' => $current_user_data,
      '#user_inactive' => ConstantHelper::USER_INACTIVE,
      '#user_group' => ConstantHelper::USER_GROUP,
      '#is_operator' => UserPermissionsHelper::isOperator($this->entityTypeManager, $this->currentUser),
      '#is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
      '#is_primary_poc' => UserPermissionsHelper::isPrimaryPoC($this->entityTypeManager, $this->currentUser),
      '#is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
      '#action' => $action,
      '#view_all' => $view_all,
      '#assignee_country_survey_data' => $assignee_country_survey_data,
      '#last_country_survey_data' => $last_country_survey_data,
      '#survey_indicators_data' => $survey_indicators_data,
      '#last_survey_indicators_data' => $last_survey_indicators_data,
      '#requested_indicator' => $requested_indicator,
      '#requested_action' => $requested_action,
      '#years' => $years,
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'view_country_survey' => [
            'current_user_data' => $current_user_data,
            'user_group' => ConstantHelper::USER_GROUP,
            'is_operator' => UserPermissionsHelper::isOperator($this->entityTypeManager, $this->currentUser),
            'is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
            'is_primary_poc' => UserPermissionsHelper::isPrimaryPoC($this->entityTypeManager, $this->currentUser),
            'is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
            'action' => $action,
            'view_all' => $view_all,
            'assignee_country_survey_data' => $assignee_country_survey_data,
            'pending_requested_changes' => SurveyIndicatorRequestChangeHelper::getCountrySurveyRequestedChangeEntities($this->entityTypeManager, $this->currentUser, $country_survey_id, [1]),
            'requested_indicator' => $requested_indicator,
            'requested_action' => $requested_action,
            'view_survey_token' => $this->csrfToken->get('view-survey-token'),
          ],
        ],
      ],
    ];
  }

  /**
   * Function manageSurveyDashboard.
   */
  public function manageSurveyDashboard($survey_id) {
    $survey = $this->entityTypeManager->getStorage('node')->load($survey_id);
    $survey_data = SurveyHelper::getSurveyData($this->dateFormatter, $survey);

    $published_survey_entities = SurveyHelper::getPublishedSurveyEntities($this->entityTypeManager);
    $published_surveys = SurveyHelper::getSurveysData($this->entityTypeManager, $this->dateFormatter, $published_survey_entities);

    return [
      '#theme' => 'dashboard',
      '#survey_data' => $survey_data,
      '#published_surveys' => $published_surveys,
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'dashboard' => [
            'survey_data' => $survey_data,
            'view_survey_token' => $this->csrfToken->get('view-survey-token'),
          ],
        ],
      ],
    ];
  }

  /**
   * Function manageCountrySurveyDashboard.
   */
  public function manageCountrySurveyDashboard($country_survey_id) {
    $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $country_survey_id);

    return [
      '#theme' => 'country-dashboard',
      // Pass data to Twig templates.
      '#is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
      '#is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
      '#assignee_country_survey_data' => $assignee_country_survey_data,
      '#pending_requested_changes' => SurveyIndicatorRequestChangeHelper::getCountrySurveyRequestedChangeEntities($this->entityTypeManager, $this->currentUser, $country_survey_id, [1]),
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'country_dashboard' => [
            'user_group' => ConstantHelper::USER_GROUP,
            'is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
            'is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
            'assignee_country_survey_data' => $assignee_country_survey_data,
            'view_survey_token' => $this->csrfToken->get('view-survey-token'),
          ],
        ],
      ],
    ];
  }

  /**
   * Function viewCountrySurveySummaryData.
   */
  public function viewCountrySurveySummaryData($country_survey_id) {
    $country_survey = $this->entityTypeManager->getStorage('node')->load($country_survey_id);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);
    $country_survey_additional_data = CountrySurveyHelper::getCountrySurveyAdditionalData($this->entityTypeManager, $this->dateFormatter, $country_survey_data);

    $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($this->entityTypeManager, ['country_survey' => $country_survey_id]);

    $survey_indicators = $this->entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

    $requested_changes_data = [];
    $requested_change_entities = SurveyIndicatorRequestChangeHelper::getCountrySurveyRequestedChangeEntities($this->entityTypeManager, $this->currentUser, $country_survey_id, [], FALSE);
    $requested_changes = $this->entityTypeManager->getStorage('node')->loadMultiple($requested_change_entities);
    foreach ($requested_changes as $requested_change) {
      array_push($requested_changes_data, SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($this->entityTypeManager, $this->dateFormatter, $requested_change));
    }
    $data_not_available = CountrySurveySummaryDataHelper::getCountrySurveyDataNotAvailable($this->entityTypeManager, $this->dateFormatter, $survey_indicators);
    $references = CountrySurveySummaryDataHelper::getCountrySurveyReferences($this->entityTypeManager, $this->dateFormatter, $survey_indicators);
    $comments = CountrySurveySummaryDataHelper::getCountrySurveyComments($this->entityTypeManager, $this->dateFormatter, $survey_indicators);

    return [
      '#theme' => 'country-summary-data',
      // Pass data to Twig templates.
      '#is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
      '#is_admin' => UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser),
      '#country_survey_data' => array_merge($country_survey_data, $country_survey_additional_data),
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'country_summary_data' => [
            'user_group' => ConstantHelper::USER_GROUP,
            'is_poc' => UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser),
            'requested_changes_data' => $requested_changes_data,
            'data_not_available' => $data_not_available,
            'references' => $references,
            'comments' => $comments,
          ],
        ],
      ],
    ];
  }

}
