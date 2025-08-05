<?php

namespace Drupal\index_and_survey_configuration_management\Controller;

use Drupal\index_and_survey_configuration_management\Helper\AreaHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_and_survey_configuration_management\Helper\SubareaHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Twig\Environment;

/**
 * Class Subarea Controller.
 */
final class SubareaController extends ControllerBase {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable requestStack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;
  /**
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;

  /**
   * Function __construct.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, Environment $twig) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('twig')
    );
  }

  /**
   * Function listSubareas.
   */
  public function listSubareas() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $entities = SubareaHelper::getSubareaEntities($this->entityTypeManager, $year);
    $data = SubareaHelper::getSubareasData($this->entityTypeManager, $entities);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function createOrShowSubarea.
   */
  public function createOrShowSubarea($action = 'create', $subarea_data = NULL) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $entities = AreaHelper::getAreaEntities($this->entityTypeManager, $year);
    $areas_data = AreaHelper::getAreasData($this->entityTypeManager, $entities);

    return new JsonResponse($this->twig->render('@enisa/ajax/subarea-management.html.twig', [
      'action' => $action,
      'selected_subarea' => $subarea_data,
      'areas' => $areas_data,
    ]));
  }

  /**
   * Function showSubarea.
   */
  public function showSubarea($subarea_id) {
    $subarea = $this->entityTypeManager->getStorage('node')->load($subarea_id);
    $subarea_data = SubareaHelper::getSubareaData($subarea);

    return $this->createOrShowSubarea('show', $subarea_data);
  }

  /**
   * Function storeSubarea.
   */
  public function storeSubarea(Request $request) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['year'] = $year;

    $errors = SubareaHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    SubareaHelper::storeSubarea($this->entityTypeManager, $inputs);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function updateSubarea.
   */
  public function updateSubarea(Request $request, $subarea_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['id'] = $subarea_id;
    $inputs['year'] = $year;

    $errors = SubareaHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    $subarea = $this->entityTypeManager->getStorage('node')->load($subarea_id);

    SubareaHelper::updateSubarea($subarea, $inputs);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function deleteSubarea.
   */
  public function deleteSubarea($subarea_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $subarea = $this->entityTypeManager->getStorage('node')->load($subarea_id);

    SubareaHelper::deleteSubarea($subarea);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

}
