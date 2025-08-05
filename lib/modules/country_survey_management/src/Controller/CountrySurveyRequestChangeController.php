<?php

namespace Drupal\country_survey_management\Controller;

use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorRequestChangeActionHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorRequestChangeHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Controller for handling country survey request changes.
 */
final class CountrySurveyRequestChangeController extends ControllerBase {
  const ERROR_NOT_AUTHORIZED = 'Indicator cannot be updated as you are not authorized for this action!';

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
   * Date formatter service.
   *
   * @var object
   */
  protected $dateFormatter;
  /**
   * Time service.
   *
   * @var object
   */
  protected $time;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $current_user,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
  }

  /**
   * Creates an instance of the controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * Requests changes for a country survey indicator.
   */
  public function requestChangesCountrySurveyIndicator(Request $request, $indicator_id) {
    $inputs = $request->request->all();

    if (!SurveyIndicatorRequestChangeHelper::canRequestChangesCountrySurveyIndicator($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $errors = SurveyIndicatorRequestChangeHelper::validateInputs($inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors], 400);
    }

    $resp = SurveyIndicatorRequestChangeActionHelper::requestChangesCountrySurveyIndicator(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $this->time,
      $indicator_id,
      $inputs);
    if ($resp['type'] == 'warning' ||
        $resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    return new JsonResponse('success');
  }

  /**
   * Discards requested changes for a country survey indicator.
   */
  public function discardRequestedChangesCountrySurveyIndicator(Request $request, $indicator_id) {
    $inputs = $request->request->all();

    if (!SurveyIndicatorRequestChangeHelper::canDiscardRequestedChangesCountrySurveyIndicator($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $resp = SurveyIndicatorRequestChangeActionHelper::discardRequestedChangesCountrySurveyIndicator(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $indicator_id,
      $inputs
    );
    if ($resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    return new JsonResponse('success');
  }

  /**
   * Submits country survey requested changes.
   */
  public function submitCountrySurveyRequestedChanges(Request $request) {
    $inputs = $request->request->all();

    if (!SurveyIndicatorRequestChangeHelper::canSubmitCountrySurveyRequestedChanges($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $country_survey = $this->entityTypeManager->getStorage('node')->load($inputs['country_survey_id']);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);

    SurveyIndicatorRequestChangeActionHelper::submitCountrySurveyRequestedChanges($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $country_survey_data);

    if (UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser) ||
        UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser)) {
      if (UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser)) {
        $country_survey->set('field_submitted_user', []);
        $country_survey->set('field_submitted_date', []);
        $country_survey->set('field_rc_submitted_date', strtotime($this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'd-m-Y H:i:s')));
      }
      elseif (UserPermissionsHelper::isPoC($this->entityTypeManager, $this->currentUser)) {
        $country_survey->set('field_completed', FALSE);
      }

      $country_survey->save();
    }

    return new JsonResponse('success');
  }

}
