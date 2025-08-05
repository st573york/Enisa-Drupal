<?php

namespace Drupal\export_management\Excel;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\export_management\Helper\ExportHelper;
use Drupal\index_and_survey_configuration_management\Helper\AreaHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\index_and_survey_configuration_management\Helper\SubareaHelper;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class Data Calculation Values Excel Builder.
 */
class DataCalculationValuesExcelBuilder {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;
  /**
   * Member variable index.
   *
   * @var object
   */
  protected $index;
  /**
   * Member variable results.
   *
   * @var object
   */
  protected $results;
  /**
   * Member variable countryIso.
   *
   * @var object
   */
  protected $countryIso;
  /**
   * Member variable country.
   *
   * @var object
   */
  protected $country;

  /**
   * Function __construct.
   */
  public function __construct($entityTypeManager, $twig, $index, $results, $countryIso) {
    $this->entityTypeManager = $entityTypeManager;
    $this->twig = $twig;
    $this->index = $index;
    $this->results = $results;
    $this->countryIso = $countryIso;

    $this->country = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', $this->countryIso, 'field_iso');
  }

  /**
   * Function getSpreadsheet.
   */
  public function getSpreadsheet() {
    $reader = IOFactory::createReaderForFile($this->results);
    $reader->setReadDataOnly(TRUE);

    $spreadsheet = $reader->load($this->results);

    $sheets = $spreadsheet->getAllSheets();

    $data = [];

    foreach ($sheets as $sheet) {
      $codes = [];
      $country_data = [];
      $eu = [];

      $title = $sheet->getTitle();

      if (preg_match('/data|treated_points|fullscore/', mb_strtolower($title))) {
        $parts = explode('.', $title);
        $type = mb_strtolower(end($parts));

        $rows = $sheet->toArray();

        foreach ($rows as $row) {
          if ($row[0] == 'uCode') {
            $codes = $row;
          }

          if ($row[0] == $this->country->field_code->value) {
            $country_data = $row;
          }
          elseif ($row[0] == $this->country->field_iso->value) {
            $country_data = $row;
          }

          if ($row[0] == ConstantHelper::EU_AVERAGE_CODE) {
            $eu = $row;
          }
        }

        $country_data = array_combine($codes, $country_data);
        ksort($country_data, SORT_NATURAL);

        if (!empty($eu)) {
          $eu = array_combine($codes, $eu);
          ksort($eu, SORT_NATURAL);
        }

        foreach ($codes as $code) {
          $strtolower_code = mb_strtolower($code);

          if (!preg_match('/^ucode|ind_|subarea_|area_|index/', $strtolower_code)) {
            continue;
          }

          $this->getData($type, $country_data, $eu, $code, $strtolower_code, $data);
        }
      }
    }

    $reader = new HtmlReader();

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    // Data Calculation Values.
    $html = $this->twig->render('@enisa/export/ms-data-calculation-values.html.twig', $data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Data Calculation Values');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    DataCalculationValuesMSData::styles($to_sheet);
    DataCalculationValuesMSData::columnWidths($to_sheet);

    return $spreadsheet;
  }

  /**
   * Function getData.
   */
  private function getData($type, $country_data, $eu, $code, $strtolower_code, &$data) {
    if ($code == 'uCode') {
      $data[$code][$type]['country'] = $country_data[$code];
      if (!empty($eu)) {
        $data[$code][$type]['eu'] = $eu[$code];
      }
    }
    else {
      if (preg_match('/^ind_/', $strtolower_code)) {
        $parts = explode('_', $code);

        $indicator_entity = IndicatorHelper::getIndicatorEntity(
              $this->entityTypeManager,
              [
                'identifier' => $parts[1],
                'year' => $this->index['year'],
              ]
          );

        $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_entity);
        $indicator_data = IndicatorHelper::getIndicatorData($indicator);

        $data['data'][$code]['name'] = $indicator_data['name'];
      }
      elseif (preg_match('/^subarea_/', $strtolower_code)) {
        $parts = explode('_', $code);

        $subarea_entity = SubareaHelper::getSubareaEntity(
              $this->entityTypeManager,
              [
                'identifier' => $parts[1],
                'year' => $this->index['year'],
              ]
          );

        $subarea = $this->entityTypeManager->getStorage('node')->load($subarea_entity);
        $subarea_data = SubareaHelper::getSubareaData($subarea);

        $data['data'][$code]['name'] = $subarea_data['name'];
      }
      elseif (preg_match('/^area_/', $strtolower_code)) {
        $parts = explode('_', $code);

        $area_entity = AreaHelper::getAreaEntity(
              $this->entityTypeManager,
              [
                'identifier' => $parts[1],
                'year' => $this->index['year'],
              ]
          );

        $area = $this->entityTypeManager->getStorage('node')->load($area_entity);
        $area_data = AreaHelper::getAreaData($area);

        $data['data'][$code]['name'] = $area_data['name'];
      }
      elseif (preg_match('/^index/', $strtolower_code)) {
        $data['data'][$code]['name'] = $this->index['title'];
      }

      $data['data'][$code][$type]['country'] = $country_data[$code];
    }
  }

}
