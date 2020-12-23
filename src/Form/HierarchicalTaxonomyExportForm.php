<?php

namespace Drupal\hierarchical_taxonomy_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * HierarchicalTaxonomyExportForm class.
 */
class HierarchicalTaxonomyExportForm extends FormBase {

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\File\FileSystemInterface definition.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      // Load the service required to construct this class.
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hte_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $vocabulary_ids = array_keys(taxonomy_vocabulary_get_names());
    $vocabulary_list = [];
    foreach ($vocabulary_ids as $vocabulary_id) {
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vocabulary_id);
      // rray_push($vocabulary_list,$vocabulary->label());
      $vocabulary_list[$vocabulary_id] = $vocabulary->label();
    }

    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#options' => $vocabulary_list,
    ];

    $form['term_depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Term Depth'),
      '#options' => [1, 2, 3, 4],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export as CSV'),
    ];

    return $form;
  }

  /**
   * Form submission handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $vocabulary = $form_state->getValue('vocabulary');
    $depth = $form_state->getValue('term_depth');
    $connection = $this->database;
    $count = -1;
    $array = [];
    $level1 = $connection->query("SELECT entity_id FROM {taxonomy_term__parent} WHERE parent_target_id=0 AND bundle=:vocabulary", [':vocabulary' => $vocabulary]);
    if ($level1) {
      while ($row = $level1->fetchAssoc()) {
        $count++;
        $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($row['entity_id']);
        $category = $term->label();
        $array[$count][0] = $category;
        $array[$count][1] = NULL;
        $array[$count][2] = NULL;
        $array[$count][3] = NULL;
        if ($depth > 0) {
          $level2 = $connection->query("SELECT entity_id FROM {taxonomy_term__parent} WHERE bundle=:vocabulary AND parent_target_id=:id", [':vocabulary' => $vocabulary, ':id' => $row['entity_id']]);
          if ($level2) {
            while ($row = $level2->fetchAssoc()) {
              $count++;
              $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($row['entity_id']);
              $category = $term->label();
              $array[$count][0] = NULL;
              $array[$count][1] = $category;
              $array[$count][2] = NULL;
              $array[$count][3] = NULL;
              if ($depth > 1) {
                $level3 = $connection->query("SELECT entity_id FROM {taxonomy_term__parent} WHERE bundle=:vocabulary AND parent_target_id=:id", [':vocabulary' => $vocabulary, ':id' => $row['entity_id']]);
                if ($level3) {
                  while ($row = $level3->fetchAssoc()) {
                    $count++;
                    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($row['entity_id']);
                    $category = $term->label();
                    $array[$count][0] = NULL;
                    $array[$count][1] = NULL;
                    $array[$count][2] = $category;
                    $array[$count][3] = NULL;
                    if ($depth > 2) {
                      $level4 = $connection->query("SELECT entity_id FROM {taxonomy_term__parent} WHERE bundle=:vocabulary AND parent_target_id=:id", [':vocabulary' => $vocabulary, ':id' => $row['entity_id']]);
                      if ($level4) {
                        while ($row = $level4->fetchAssoc()) {
                          $count++;
                          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($row['entity_id']);
                          $category = $term->label();
                          $array[$count][0] = NULL;
                          $array[$count][1] = NULL;
                          $array[$count][2] = NULL;
                          $array[$count][3] = $category;
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    header("Content-Disposition: attachment; filename=\"demo.xls\"");
    header("Content-Type: application/vnd.ms-excel;");
    header("Pragma: no-cache");
    header("Expires: 0");
    $dir = 'public://hte';
    if ($this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY)) {
      $csv_filename = $vocabulary;
      $csv_filepath = $dir . '/' . $csv_filename . ".csv";
      $targs = [
        ':csv_url' => file_create_url($csv_filepath),
        '@csv_filename' => $csv_filename,
        '@csv_filepath' => $csv_filepath,
      ];
      if ($handle = fopen($csv_filepath, 'w+')) {
        foreach ($array as $data) {
          fputcsv($handle, $data, ",");
        }
        fclose($handle);
      }
      $this->messenger()->addStatus($this->t('Vocabulary Export Complete. You may download the CSV here: <a href=":csv_url">@csv_filename</a>', $targs));
    }
  }

}
