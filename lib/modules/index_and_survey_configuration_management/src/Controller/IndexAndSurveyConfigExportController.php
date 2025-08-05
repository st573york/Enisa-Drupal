<?php

namespace Drupal\index_and_survey_configuration_management\Controller;

use Drupal\index_and_survey_configuration_management\Helper\IndexAndSurveyConfigurationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;

/**
 * Class Index And Survey Config Export Controller.
 */
final class IndexAndSurveyConfigExportController extends ControllerBase {

  /**
   * Member variable fileSystem.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Function __construct.
   */
  public function __construct(FileSystemInterface $fileSystem) {
    $this->fileSystem = $fileSystem;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * Function exportIndexAndSurveyConfigurationExcel.
   */
  public function exportIndexAndSurveyConfigurationExcel($year) {
    $ret = IndexAndSurveyConfigurationHelper::exportConfigurationExcel($year);

    if (!$ret) {
      return new JsonResponse(['error' => 'Configuration excel cannot be created!'], 404);
    }

    return new JsonResponse([
      'status' => 'success',
      'filename' => $ret['filename'],
    ]);
  }

  /**
   * Function downloadIndexAndSurveyConfigurationExcel.
   */
  public function downloadIndexAndSurveyConfigurationExcel($filename) {
    $file_uri = 'public://' . $filename;
    $file_path = $this->fileSystem->realpath($file_uri);

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->deleteFileAfterSend(TRUE);

    return $response;
  }

}
