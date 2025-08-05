<?php

namespace Drupal\country_survey_management\Controller;

use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorDataHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;
use Drupal\user_management\Helper\UserHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Class Country Survey Twig Controller.
 */
final class CountrySurveyTwigController extends ControllerBase {
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
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
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
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('twig')
    );
  }

  /**
   * Function previewCountrySurvey.
   */
  public function previewCountrySurvey(Request $request, $indicator_id = NULL) {
    $inputs = $request->query->all();
    $with_answers = filter_var($inputs['with_answers'], FILTER_VALIDATE_BOOLEAN);

    if ($with_answers) {
      $assignee_country_survey_data = CountrySurveyHelper::getAssigneeCountrySurveysData($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $inputs['country_survey_id']);

      $indicators = IndicatorHelper::getIndicatorsWithSurveyAndAnswers($this->entityTypeManager, $this->dateFormatter, $inputs['country_survey_id'], $assignee_country_survey_data);
    }
    else {
      $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

      $indicators = IndicatorHelper::getIndicatorsWithSurvey($this->entityTypeManager, $this->dateFormatter, $year);
    }

    if (!is_null($indicator_id)) {
      $indicators = [
        $indicator_id => $indicators[$indicator_id],
      ];
    }

    $years = range(2000, $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'Y') + 1);
    rsort($years);

    return new JsonResponse($this->twig->render('@enisa/survey/preview.html.twig', [
      'indicators' => $indicators,
      'with_answers' => $with_answers,
      'years' => $years,
    ]));
  }

  /**
   * Function getCountrySurveyIndicatorInfo.
   */
  public function getCountrySurveyIndicatorInfo(Request $request, $indicator_id) {
    $inputs = $request->query->all();

    $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($this->entityTypeManager, $inputs['country_survey_id'], $indicator_id);

    $survey_indicator = $this->entityTypeManager->getStorage('node')->load($survey_indicator_entity);
    $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($this->entityTypeManager, $this->dateFormatter, $survey_indicator);

    $entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($this->entityTypeManager, $indicator_id);
    $accordions = $this->entityTypeManager->getStorage('node')->loadMultiple($entities);

    foreach ($accordions as $accordion) {
      $survey_indicator_data['accordions'][$accordion->id()] =
        IndicatorAccordionHelper::getIndicatorAccordionData($accordion);

      $entities = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntities($this->entityTypeManager, [$accordion->id()]);
      $questions = $this->entityTypeManager->getStorage('node')->loadMultiple($entities);

      foreach ($questions as $question) {
        $survey_indicator_data['accordions'][$accordion->id()]['questions'][$question->id()] =
          IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);
      }
    }

    return new JsonResponse($this->twig->render('@enisa/ajax/country-survey-indicator-info.html.twig', [
      'survey_indicator_data' => $survey_indicator_data,
    ]));
  }

  /**
   * Function indicatorManagement.
   */
  public function indicatorManagement($inputs, $indicators) {
    $country_survey = $this->entityTypeManager->getStorage('node')->load($inputs['country_survey_id']);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);

    if (count($indicators) == 1) {
      $indicator = &$indicators[0];

      $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($this->entityTypeManager, $inputs['country_survey_id'], $indicator['id']);

      $survey_indicator = $this->entityTypeManager->getStorage('node')->load($survey_indicator_entity);
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($this->entityTypeManager, $this->dateFormatter, $survey_indicator);

      $indicator['country_survey'] = $survey_indicator_data['country_survey'];
      $indicator['deadline'] = $survey_indicator_data['deadline'];
      $indicator['assignee'] = $survey_indicator_data['assignee'];
    }

    $user_entities = UserPermissionsHelper::getUserEntitiesByCountryAndRole(
      $this->entityTypeManager,
      [
        'countries' => UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'id'),
        'roles' => UserPermissionsHelper::getRolesBetweenWeights($this->entityTypeManager, 'id', 6, 8),
      ]
    );

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($user_entities);

    $users_data = [];
    foreach ($users as $user) {
      array_push($users_data, UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user));
    }

    return new JsonResponse($this->twig->render('@enisa/ajax/country-survey-indicator-management.html.twig', [
      'country_survey' => $country_survey_data,
      'indicators' => $indicators,
      'processed_indicators' => SurveyIndicatorDataHelper::getSurveyIndicatorsProcessed($this->entityTypeManager, $this->dateFormatter, $inputs['country_survey_id'], $indicators),
      'users' => $users_data,
    ]));
  }

  /**
   * Function getCountrySurveyIndicatorData.
   */
  public function getCountrySurveyIndicatorData(Request $request, $indicator_id) {
    $inputs = $request->query->all();

    $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_id);
    $indicator_data = IndicatorHelper::getIndicatorData($indicator);

    return $this->indicatorManagement($inputs, [$indicator_data]);
  }

  /**
   * Function getCountrySurveyIndicatorsData.
   */
  public function getCountrySurveyIndicatorsData(Request $request) {
    $inputs = $request->query->all();

    $indicators = $this->entityTypeManager->getStorage('node')->loadMultiple(array_filter(explode(',', $inputs['indicators'])));

    $indicators_data = [];
    foreach ($indicators as $indicator) {
      array_push($indicators_data, IndicatorHelper::getIndicatorData($indicator));
    }

    return $this->indicatorManagement($inputs, $indicators_data);
  }

}
