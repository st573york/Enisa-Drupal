<?php

namespace Drupal\user_management\Controller;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\user_management\Helper\UserHelper;
use Drupal\user_management\Helper\UserPermissionsActionHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Class User Controller.
 */
final class UserController extends ControllerBase {
  const ERROR_NOT_AUTHORIZED = 'User cannot be deleted as you are not authorized for this action!';

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
   * Member variable renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;
  /**
   * Member variable mailManager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;
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
    RendererInterface $renderer,
    MailManagerInterface $mailManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->twig = $twig;
    $this->renderer = $renderer;
    $this->mailManager = $mailManager;

    $this->services = [
      'entityTypeManager' => $entityTypeManager,
      'currentUser' => $currentUser,
      'requestStack' => $requestStack,
      'database' => $database,
      'dateFormatter' => $dateFormatter,
      'time' => $time,
      'twig' => $twig,
      'renderer' => $renderer,
      'mailManager' => $mailManager,
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
      $container->get('twig'),
      $container->get('renderer'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * Function manageUsers.
   */
  public function manageUsers() {
    return [
      '#theme' => 'manage-users',
    ];
  }

  /**
   * Function listUsers.
   */
  public function listUsers() {
    $entities = UserHelper::getUserEntities($this->entityTypeManager, $this->currentUser, $this->dateFormatter);

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($entities);

    $data = [];
    foreach ($users as $user) {
      $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

      array_push($data, $user_data);
    }

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function userDetails.
   */
  private function userDetails($users) {
    $logged_in_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $logged_in_user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $logged_in_user);

    $term = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', ConstantHelper::USER_GROUP);

    $yourself = (count($users) == 1 && $users[0]['id'] == $logged_in_user_data['id']) ? TRUE : FALSE;

    return new JsonResponse($this->twig->render('@enisa/ajax/user-edit.html.twig', [
      'USER_GROUP' => $term->id(),
      'logged_in_user_data' => $logged_in_user_data,
      'users' => $users,
      'countries' => UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'entity'),
      'roles' => UserPermissionsHelper::getUserRoles($this->entityTypeManager, $this->currentUser, 'entity', $yourself),
    ]));
  }

  /**
   * Function getUser.
   */
  public function getUser($user_id) {
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

    return $this->userDetails([$user_data]);
  }

  /**
   * Function updateUser.
   */
  public function updateUser(Request $request, $user_id) {
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

    $inputs = $request->request->all();
    $inputs['toggle'] = FALSE;

    $errors = UserPermissionsHelper::validateInputsForEdit($inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    $db_permission_country = (isset($user_data['country'])) ? $user_data['country'] : NULL;
    $country = $this->entityTypeManager->getStorage('taxonomy_term')->load($inputs['country']);
    $db_permission_role = (isset($user_data['role'])) ? $user_data['role'] : NULL;
    $role = $this->entityTypeManager->getStorage('user_role')->load($inputs['role']);

    $resp = UserPermissionsHelper::canUpdateUserCountryRole(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $user,
      $db_permission_country,
      $country,
      $db_permission_role,
      $role);
    if ($resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    $resp = UserPermissionsHelper::canUpdateUserStatus(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $user,
      $inputs,
      $db_permission_country,
      $db_permission_role);
    if ($resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    $resp = UserPermissionsHelper::canDowngradePrimaryPoC($db_permission_country, $db_permission_role, $role);
    if ($resp['type'] == 'warning') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    UserPermissionsActionHelper::updateUserPermissions($this->services, $user, $user_data, $country, $role);
    UserPermissionsActionHelper::updateUserStatus($this->services, $user, $inputs);

    // Logged in user?
    if ($user->id() == $this->currentUser->id()) {
      // Downgrade to operator or viewer then redirect to home page.
      if ($role->id() == 'operator' ||
          $role->id() == 'viewer') {
        return new JsonResponse(['action' => 'redirect', 'url' => '/'], 302);
      }
      // Downgrade to PoC then redirect to current page.
      elseif ($db_permission_role['id'] == 'enisa_administrator' &&
              ($role->id() == 'primary_poc' ||
               $role->id() == 'poc')) {
        return new JsonResponse(['action' => 'reload'], 302);
      }
    }

    return new JsonResponse('success');
  }

  /**
   * Function getUsers.
   */
  public function getUsers(Request $request) {
    $inputs = $request->query->all();

    $entities = array_filter(explode(',', $inputs['users']));

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($entities);

    $users_data = [];
    foreach ($users as $user) {
      $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

      array_push($users_data, $user_data);
    }

    return $this->userDetails($users_data);
  }

  /**
   * Function updateUsers.
   */
  public function updateUsers(Request $request) {
    $inputs = $request->request->all();
    $inputs['toggle'] = FALSE;

    $entities = array_filter(explode(',', $inputs['datatable-selected']));

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($entities);

    $error = '';
    $status = 200;
    foreach ($users as $user) {
      $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

      $db_permission_country = (isset($user_data['country'])) ? $user_data['country'] : NULL;
      $db_permission_role = (isset($user_data['role'])) ? $user_data['role'] : NULL;

      $resp = UserPermissionsHelper::canUpdateUserStatus(
        $this->entityTypeManager,
        $this->currentUser,
        $this->dateFormatter,
        $user,
        $inputs,
        $db_permission_country,
        $db_permission_role);
      if ($resp['type'] == 'error') {
        // Get only first error - skip the others if any.
        if (empty($error)) {
          $error = $resp['msg'];
          $status = $resp['status'];
        }

        continue;
      }

      UserPermissionsActionHelper::updateUserStatus($this->services, $user, $inputs);
    }

    if (!empty($error)) {
      return new JsonResponse(['error' => $error], $status);
    }

    return new JsonResponse('success', $status);
  }

  /**
   * Function toggleBlockUser.
   */
  public function toggleBlockUser($user_id) {
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

    $inputs['toggle'] = TRUE;

    $db_permission_country = (isset($user_data['country'])) ? $user_data['country'] : NULL;
    $db_permission_role = (isset($user_data['role'])) ? $user_data['role'] : NULL;

    $resp = UserPermissionsHelper::canUpdateUserStatus(
      $this->entityTypeManager,
      $this->currentUser,
      $this->dateFormatter,
      $user,
      $inputs,
      $db_permission_country,
      $db_permission_role);
    if ($resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    UserPermissionsActionHelper::updateUserStatus($this->services, $user);

    return new JsonResponse('success');
  }

  /**
   * Function deleteUser.
   */
  public function deleteUser($user_id) {
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

    if (!UserPermissionsHelper::canDeleteUser($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    UserHelper::deleteUser($this->services, $user, $user_data);

    return new JsonResponse('success');
  }

  /**
   * Function deleteUsers.
   */
  public function deleteUsers(Request $request) {
    $inputs = $request->request->all();

    $user_ids = array_filter(explode(',', $inputs['datatable-selected']));

    if (!UserPermissionsHelper::canDeleteUser($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    foreach ($user_ids as $user_id) {
      $user = $this->entityTypeManager->getStorage('user')->load($user_id);
      $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

      UserHelper::deleteUser($this->services, $user, $user_data);
    }

    return new JsonResponse('success');
  }

  /**
   * Function viewUserAccount.
   */
  public function viewUserAccount() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $user_data = UserHelper::getUserData($this->entityTypeManager, $this->dateFormatter, $user);

    return [
      '#theme' => 'account',
      // Pass data to Twig templates.
      '#user_data' => $user_data,
      '#countries' => GeneralHelper::getTaxonomyTerms($this->entityTypeManager, 'countries', 'entity'),
    ];
  }

  /**
   * Function updateUserAccount.
   */
  public function updateUserAccount(Request $request) {
    $inputs = $request->request->all();

    $errors = UserHelper::validateInputsForEdit($inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors], 400);
    }

    UserHelper::updateUserDetails($this->entityTypeManager, $this->currentUser, $inputs);

    return new JsonResponse(['success' => 'User details have been successfully updated!']);
  }

}
