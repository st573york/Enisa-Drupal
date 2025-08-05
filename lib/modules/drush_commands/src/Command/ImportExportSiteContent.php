<?php

namespace Drupal\drush_commands\Command;

use Drush\Commands\DrushCommands;
use Drupal\block_content\Entity\BlockContent;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Import Export Site Content.
 */
final class ImportExportSiteContent extends DrushCommands {
  const CONTENT = ['All', 'Menu', 'Documents', 'Pages', 'Blocks', 'Taxonomies'];

  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable aliasManager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Function __construct.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AliasManagerInterface $alias_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * Function getMenuLink.
   */
  private function getMenuLink($data) {
    $query = $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1);

    if (isset($data['title'])) {
      $query->condition('title', $data['title']);
    }
    if (isset($data['link'])) {
      $query->condition('link.uri', $data['link']);
    }
    if (isset($data['menu_name'])) {
      $query->condition('menu_name', $data['menu_name']);
    }

    $link_ids = $query->execute();

    if (!empty($link_ids)) {
      return $this->entityTypeManager->getStorage('menu_link_content')->load(reset($link_ids));
    }

    return NULL;
  }

  /**
   * Function countMenuLinks.
   */
  private function countMenuLinks($menu_links) {
    $count = 0;

    foreach ($menu_links as $link) {
      $count++;

      if (isset($link['children']) &&
            is_array($link['children'])) {
        $count += $this->countMenuLinks($link['children']);
      }
    }

    return $count;
  }

  /**
   * Function getNode.
   */
  private function getNode($data) {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $data['bundle'])
      ->condition('title', $data['title']);

    return $query->execute();
  }

  /**
   * Function getBlock.
   */
  private function getBlock($block) {
    $query = $this->entityTypeManager
      ->getStorage('block_content')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $block['type'])
      ->condition('info', $block['description']);

    return $query->execute();
  }

  /**
   * Export the selected site content to json file.
   *
   * @command commands:export-site-content
   * @description Export the selected site content to json file.
   * @aliases export-site-content
   */
  public function exportSiteContent() {
    $choice = $this->io()->choice('Select site content to export:', self::CONTENT);

    switch ($choice) {
      case 'All':
        $this->exportMenuLinks();
        $this->exportDocuments();
        $this->exportPages();
        $this->exportBlocks();
        $this->exportTaxonomies();

        break;

      case 'Menu':
        $this->exportMenuLinks();

        break;

      case 'Documents':
        $this->exportDocuments();

        break;

      case 'Pages':
        $this->exportPages();

        break;

      case 'Blocks':
        $this->exportBlocks();

        break;

      case 'Taxonomies':
        $this->exportTaxonomies();

        break;

      default:
        break;
    }

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Import the selected site content from json file.
   *
   * @command commands:import-site-content
   * @description Import the selected site content from json file.
   * @aliases import-site-content
   */
  public function importSiteContent($options = ['content' => NULL]) {
    $choice = $options['content'];

    if (is_null($choice)) {
      $choice = $this->io()->choice('Select site content to import:', self::CONTENT);
    }

    switch ($choice) {
      case 'All':
        $this->importMenuLinks();
        $this->importDocuments();
        $this->importPages();
        $this->importBlocks();
        $this->importTaxonomies();

        break;

      case 'Menu':
        $this->importMenuLinks();

        break;

      case 'Documents':
        $this->importDocuments();

        break;

      case 'Pages':
        $this->importPages();

        break;

      case 'Blocks':
        $this->importBlocks();

        break;

      case 'Taxonomies':
        $this->importTaxonomies();

        break;

      default:
        $this->io()->error('Invalid content type!');

        return DrushCommands::EXIT_FAILURE;
    }

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function exportMenuLinks.
   */
  private function exportMenuLinks() {
    $this->output()->writeln('Exporting main navigation menu links...');

    $query = $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'main')
      ->execute();

    $menu_links = $this->entityTypeManager->getStorage('menu_link_content')->loadMultiple($query);

    $progress = new ProgressBar($this->output(), count($menu_links));
    $progress->start();

    $menu_data = [];
    foreach ($menu_links as $link) {
      $uuid = $link->uuid();
      $parent_uuid = NULL;

      if (!$link->get('parent')->isEmpty()) {
        $parts = explode(':', $link->get('parent')->value);
        $parent_uuid = end($parts);
      }

      $link_data = [
        'bundle' => $link->bundle(),
        'title' => $link->getTitle(),
        'menu_name' => $link->get('menu_name')->value,
        'link' => $link->get('link')->uri,
        'weight' => $link->get('weight')->value,
        'enabled' => (bool) $link->get('enabled')->value,
        'menu_item_roles' => $link->get('menu_item_roles')->getValue(),
      ];

      if (!is_null($parent_uuid)) {
        if (!isset($menu_data[$parent_uuid]['children'])) {
          $menu_data[$parent_uuid]['children'] = [];
        }

        array_push($menu_data[$parent_uuid]['children'], $link_data);
      }
      else {
        $link_data['children'] = (isset($menu_data[$uuid]['children'])) ? $menu_data[$uuid]['children'] : [];

        $menu_data[$uuid] = $link_data;
      }

      $progress->advance();
    }

    file_put_contents('public://seeders/site-content/menu_links.json', json_encode(array_values($menu_data), JSON_PRETTY_PRINT));

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Main navigation menu links have been successfully exported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function importMenuLink.
   */
  private function importMenuLink($data, $parent = NULL) {
    if ($data['bundle'] != 'menu_link_content') {
      return NULL;
    }

    $existing_link = $this->getMenuLink([
      'title' => $data['title'],
      'link' => $data['link'],
      'menu_name' => $data['menu_name'],
    ]);

    if (!is_null($existing_link)) {
      return $existing_link;
    }

    if (!is_null($parent)) {
      $data['parent'] = 'menu_link_content:' . $parent->uuid();
    }

    $menu_link = $this->entityTypeManager->getStorage('menu_link_content')->create($data);
    $menu_link->save();

    return $menu_link;
  }

  /**
   * Function importMenuLinks.
   */
  private function importMenuLinks() {
    $this->output()->writeln('Importing main navigation menu links...');

    $menu_links = json_decode(file_get_contents('public://seeders/site-content/menu_links.json'), TRUE);

    $progress = new ProgressBar($this->output(), $this->countMenuLinks($menu_links));
    $progress->start();

    foreach ($menu_links as $parent) {
      $menu_link = $this->importMenuLink($parent);

      foreach ($parent['children'] as $child) {
        $this->importMenuLink($child, $menu_link);
      }

      $progress->advance();
    }

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Main navigation menu links have been successfully imported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function exportDocuments.
   */
  private function exportDocuments() {
    $this->output()->writeln('Exporting documents...');

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'document')
      ->execute();

    $documents = $this->entityTypeManager->getStorage('node')->loadMultiple($query);

    $progress = new ProgressBar($this->output(), count($documents));
    $progress->start();

    $documents_data = [];
    foreach ($documents as $document) {
      $document_file = $document->get('field_document_file')->entity;
      $document_file_uri = str_replace('public://', '', $document_file->getFileUri());

      array_push($documents_data, [
        'bundle' => $document->bundle(),
        'title' => $document->getTitle(),
        'file' => $document_file_uri,
        'description' => $document->field_document_description->value,
        'posted_date' => $document->field_posted_date->value,
      ]);
    }

    file_put_contents('public://seeders/site-content/documents.json', json_encode($documents_data, JSON_PRETTY_PRINT));

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Documents have been successfully exported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function importDocuments.
   */
  private function importDocuments() {
    $this->output()->writeln('Importing documents...');

    $documents = json_decode(file_get_contents('public://seeders/site-content/documents.json'), TRUE);

    $progress = new ProgressBar($this->output(), count($documents));
    $progress->start();

    $query = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', 'administrator')
      ->range(0, 1);

    $user_id = $query->execute();

    foreach ($documents as $document) {
      if ($document['bundle'] != 'document') {
        continue;
      }

      $existing_document = $this->getNode($document);

      if (!empty($existing_document)) {
        continue;
      }

      $data = [
        'type' => $document['bundle'],
        'title' => $document['title'],
        'field_document_description' => $document['description'],
        'field_posted_date' => $document['posted_date'],
      ];

      $file = reset($this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => 'public://' . $document['file']]));

      if (!$file) {
        $uri = 'public://' . $document['file'];

        $file = $this->entityTypeManager->getStorage('file')->create([
          'uri' => $uri,
          'status' => 1,
        ]);

        $file->save();
      }

      $data['field_document_file'] = [
        'target_id' => $file->id(),
        'display' => 1,
      ];

      $node = $this->entityTypeManager->getStorage('node')->create($data);
      $node->setOwnerId(reset($user_id));
      $node->save();

      $progress->advance();
    }

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Documents have been successfully imported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function exportPages.
   */
  private function exportPages() {
    $this->output()->writeln('Exporting pages...');

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'page')
      ->execute();

    $pages = $this->entityTypeManager->getStorage('node')->loadMultiple($query);

    $progress = new ProgressBar($this->output(), count($pages));
    $progress->start();

    $pages_data = [];
    foreach ($pages as $page) {
      $url_alias = $this->aliasManager->getAliasByPath('/node/' . $page->id());

      array_push($pages_data, [
        'bundle' => $page->bundle(),
        'title' => $page->getTitle(),
        'body_value' => $page->get('body')->value,
        'body_format' => $page->get('body')->format,
        'url_alias' => $url_alias,
      ]);
    }

    file_put_contents('public://seeders/site-content/pages.json', json_encode($pages_data, JSON_PRETTY_PRINT));

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Pages have been successfully exported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function importPages.
   */
  private function importPages() {
    $this->output()->writeln('Importing pages...');

    $pages = json_decode(file_get_contents('public://seeders/site-content/pages.json'), TRUE);

    $progress = new ProgressBar($this->output(), count($pages));
    $progress->start();

    $query = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', 'administrator')
      ->range(0, 1);

    $user_id = $query->execute();

    foreach ($pages as $page) {
      if ($page['bundle'] != 'page') {
        continue;
      }

      $existing_page = $this->getNode($page);

      if (!empty($existing_page)) {
        continue;
      }

      $data = [
        'type' => 'page',
        'title' => $page['title'],
        'body' => [
          'value' => $page['body_value'],
          'format' => $page['body_format'],
        ],
      ];

      $node = $this->entityTypeManager->getStorage('node')->create($data);
      $node->setOwnerId(reset($user_id));
      $node->save();

      // Set URL alias.
      $nid = $node->id();
      $source_path = '/node/' . $nid;
      $alias = $page['url_alias'];

      $path_alias = $this->entityTypeManager->getStorage('path_alias')->create([
        'path' => $source_path,
        'alias' => $alias,
      ]);

      $path_alias->save();

      $progress->advance();
    }

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Pages have been successfully imported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function exportBlocks.
   */
  private function exportBlocks() {
    $this->output()->writeln('Exporting blocks...');

    $query = $this->entityTypeManager
      ->getStorage('block_content')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    $blocks = $this->entityTypeManager->getStorage('block_content')->loadMultiple($query);

    $progress = new ProgressBar($this->output(), count($blocks));
    $progress->start();

    $blocks_data = [];
    foreach ($blocks as $block) {
      array_push($blocks_data, [
        'bundle' => 'block',
        'type' => $block->bundle(),
        'description' => $block->get('info')->value,
        'body_value' => $block->get('body')->value,
        'body_format' => $block->get('body')->format,
      ]);
    }

    file_put_contents('public://seeders/site-content/blocks.json', json_encode($blocks_data, JSON_PRETTY_PRINT));

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Blocks have been successfully exported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function importBlocks.
   */
  private function importBlocks() {
    $this->output()->writeln('Importing blocks...');

    $blocks = json_decode(file_get_contents('public://seeders/site-content/blocks.json'), TRUE);

    $progress = new ProgressBar($this->output(), count($blocks));
    $progress->start();

    foreach ($blocks as $block) {
      if ($block['bundle'] != 'block') {
        continue;
      }

      $existing_block = $this->getBlock($block);

      if (!empty($existing_block)) {
        continue;
      }

      $data = [
        'type' => $block['type'],
        'info' => $block['description'],
        'body' => [
          'value' => $block['body_value'],
          'format' => $block['body_format'],
        ],
      ];

      $block = BlockContent::create($data);
      $block->save();

      $progress->advance();
    }

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Blocks have been successfully imported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function getTaxonomyTerms.
   */
  private function getTaxonomyTerms(&$taxonomy_data) {
    $terms = GeneralHelper::getTaxonomyTerms($this->entityTypeManager, $taxonomy_data['vid'], 'entity');

    foreach ($terms as $term) {
      $term_data = [
        'bundle' => 'term',
        'name' => $term->getName(),
        'description' => [
          'value' => '',
          'format' => '',
        ],
        'weight' => $term->getWeight(),
        'custom_fields' => [],
      ];

      if ($term->hasField('description')) {
        $description = $term->get('description')->getValue();
        $term_data['description']['value'] = $description[0]['value'];
        $term_data['description']['format'] = $description[0]['format'];
      }

      foreach (array_keys($term->getFieldDefinitions()) as $field_name) {
        if (!preg_match('/^field_/', $field_name)) {
          continue;
        }

        $field_value = $term->get($field_name)->getValue();
        $term_data['custom_fields'][$field_name] = (!empty($field_value)) ? array_column($field_value, 'value')[0] : NULL;
      }

      array_push($taxonomy_data['terms'], $term_data);
    }
  }

  /**
   * Function exportTaxonomies.
   */
  private function exportTaxonomies() {
    $this->output()->writeln('Exporting taxonomies...');

    $taxonomies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();

    $taxonomies_data = [];

    foreach ($taxonomies as $taxonomy) {
      $taxonomy_data = [
        'bundle' => 'vocabulary',
        'vid' => $taxonomy->id(),
        'terms' => [],
      ];

      $this->getTaxonomyTerms($taxonomy_data);

      array_push($taxonomies_data, $taxonomy_data);
    }

    $progress = new ProgressBar($this->output(), count($taxonomies));
    $progress->start();

    file_put_contents('public://seeders/site-content/taxonomies.json', json_encode($taxonomies_data, JSON_PRETTY_PRINT));

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Taxonomies have been successfully exported!');

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Function importTaxonomyTerm.
   */
  private function importTaxonomyTerm($data) {
    if ($data['bundle'] != 'term') {
      return NULL;
    }

    $existing_term = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, $data['vid'], $data['name']);

    if (!empty($existing_term)) {
      return $existing_term;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->create($data);

    foreach ($data['custom_fields'] as $field_name => $field_value) {
      $term->set($field_name, $field_value);
    }

    $term->save();
  }

  /**
   * Function importTaxonomies.
   */
  private function importTaxonomies() {
    $this->output()->writeln('Importing taxonomies...');

    $taxonomies = json_decode(file_get_contents('public://seeders/site-content/taxonomies.json'), TRUE);

    $progress = new ProgressBar($this->output(), count($taxonomies));
    $progress->start();

    foreach ($taxonomies as $taxonomy) {
      if ($taxonomy['bundle'] != 'vocabulary') {
        continue;
      }

      usort($taxonomy['terms'], function ($a, $b) {
        return $a['weight'] <=> $b['weight'];
      });

      foreach ($taxonomy['terms'] as $term) {
        $term['vid'] = $taxonomy['vid'];

        $this->importTaxonomyTerm($term);
      }

      $progress->advance();
    }

    $progress->finish();
    $this->output()->writeln('');
    $this->output()->writeln('Taxonomies have been successfully imported!');

    return DrushCommands::EXIT_SUCCESS;
  }

}
