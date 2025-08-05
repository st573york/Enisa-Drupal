<?php

namespace Drupal\invitation_management\Controller;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\invitation_management\Helper\InvitationHelper;
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
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Class Invitation Controller.
 */
final class InvitationController extends ControllerBase {
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
   * Function manageInvitations.
   */
  public function manageInvitations() {
    return [
      '#theme' => 'manage-invitations',
    ];
  }

  /**
   * Function listInvitations.
   */
  public function listInvitations() {
    $entities = InvitationHelper::getInvitationEntities($this->entityTypeManager, $this->currentUser, $this->dateFormatter);
    $invitations = $this->entityTypeManager->getStorage('node')->loadMultiple($entities);

    $data = [];
    foreach ($invitations as $invitation) {
      $invitation_data = InvitationHelper::getInvitationData($this->dateFormatter, $invitation);

      array_push($data, $invitation_data);
    }

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function createInvitation.
   */
  public function createInvitation() {
    $term = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', ConstantHelper::USER_GROUP);

    return new JsonResponse($this->twig->render('@enisa/ajax/invitation-create.html.twig', [
      'USER_GROUP' => $term->id(),
      'countries' => UserPermissionsHelper::getUserCountries($this->entityTypeManager, $this->currentUser, $this->dateFormatter, 'entity'),
      'roles' => UserPermissionsHelper::getUserRoles($this->entityTypeManager, $this->currentUser, 'entity'),
    ]));
  }

  /**
   * Function storeInvitation.
   */
  public function storeInvitation(Request $request) {
    $inputs = $request->request->all();
    $inputs['name'] = $inputs['firstname'] . ' ' . $inputs['lastname'];

    $errors = InvitationHelper::validateInputsForCreate($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    $resp = InvitationHelper::canInviteUser($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $inputs);
    if ($resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg'], 'type' => 'pageAlert'], $resp['status']);
    }

    InvitationHelper::storeInvitation($this->services, $inputs);

    return new JsonResponse('success');
  }

}
