<?php

namespace Drupal\feeds_ui_importer\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * FeedsUiImporterMappingForm form.
 */
class FeedsUiImporterMappingForm extends FormBase {

  /** @var string Config settings */
  const SETTINGS = 'feeds_ui_importer.settings';
  
  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;
  
  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;
  
  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;
  
  /**
   * An array with available node types.
   *
   * @var array|mixed
   */
  protected $nodeBundles;
  
  /**
   * Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;
  
  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;
  
  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;
  
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;
  
  /**
   * Manager block plugin.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Batch Builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  protected $batchBuilder;

  /**
   * FeedsUiImporterUploadForm constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   Manager block plugin.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The temp store factory.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager.
   */
  public function __construct(
    FormBuilderInterface $form_builder,
    RendererInterface $renderer,
    AccountInterface $current_user,
    BlockManagerInterface $block_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfo $entity_type_bundle_info,
    NodeStorageInterface $node_storage,
    PrivateTempStoreFactory $temp_store_factory,
    SessionManagerInterface $session_manager
  ) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeBundles = $entity_type_bundle_info->getBundleInfo('node');
    array_walk($this->nodeBundles, function (&$a) {
      $a = $a['label'];
    });
    $this->nodeStorage = $node_storage;
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    
    $this->store = $this->tempStoreFactory->get('multistep_data');

    $this->batchBuilder = new BatchBuilder();
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $container->get('form_builder');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $container->get('renderer');
    /* @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = $container->get('plugin.manager.block');
    /** @var \Drupal\Core\Session\AccountInterface $current_user */
    $current_user = $container->get('current_user');
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info */
    $entity_type_bundle_info = $container->get('entity_type.bundle.info');
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory */
    $temp_store_factory = $container->get('tempstore.private');
    /** @var \Drupal\Core\Session\SessionManagerInterface $session_manager */
    $session_manager = $container->get('session_manager');
    
    return new static (
      $form_builder,
      $renderer,
      $current_user,
      $block_manager,
      $entity_type_manager,
      $entity_type_bundle_info,
      $node_storage,
      $temp_store_factory,
      $session_manager
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'feeds_ui_importer_mapping_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['help'] = [
      '#type' => 'container',
      'help_text' => [
        '#markup' => $this->t('@count records were found. Please match the fields below.', ['@count' => $this->store->get('count_rows') ? $this->store->get('count_rows') : $this->t('The next')]),
      ],
      '#attributes' => [
        'class' => [
          'mapping-form-help-text'
        ],
      ],
    ];
    $form['node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $this->nodeBundles,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::buildAjaxGetFieldsConfigForm',
        'wrapper' => 'available-fields-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];
    if (!empty($this->store->get('columns')) && $this->store->get('rows')) {
      $this->buildGetFieldsConfigForm($form, $form_state);
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['run'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Import'),
      '#button_type' => 'primary',
    ];

    return $form;

  }

  public function buildGetFieldsConfigForm(array &$form, FormStateInterface $form_state) {
    $configs = $this->config(static::SETTINGS);
    $bundle = $form_state->getValue('node_type');
    if ($bundle) {
      $fields = $configs->get($bundle);
    }
    $excluded_fields = isset($fields["select_excluded_fields"]) ? array_keys($fields["select_excluded_fields"]) : [];
    $common_fields = isset($fields["select_excluded_fields"]) ? array_keys($fields["select_common_fields"]) : [];
    $entity_type_id = 'node';
    $bundleFields = [];
    $commonFields = [];
    foreach (\Drupal::entityManager()->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if ((!empty($field_definition->getTargetBundle()) || $field_name == 'title') && !in_array($field_name, $excluded_fields) && !in_array($field_name, $common_fields)) {
        $bundleFields[$bundle][$field_name] = $field_definition->getLabel();
      }
      if ((!empty($field_definition->getTargetBundle()) || $field_name == 'title') && in_array($field_name, $common_fields)) {
        //Create an empty representative entity
        $node = \Drupal::service('entity_type.manager')->getStorage($entity_type_id)->create(array(
            'type' => $bundle
          )
        );
        $entity_form = \Drupal::service('entity.form_builder')->getForm($node);
        foreach ($entity_form as $key_entity_form => $entity_form_item) {
          if ($key_entity_form == $field_name) {
            $commonFields[$field_name] = $entity_form_item;
          }
        }
      }
    }
    $form['columns'] = $this->store->get('columns');
    foreach ($form['columns'] as $key => $column) {
      $form['columns'][$key]['selected_fields'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'available-fields'
          ],
        ],
        'select_available_fields' => [
          '#type' => 'select',
          '#title' => $this->t('Belongs to'),
          '#options' => $bundleFields,
          '#empty_option'  => t('Do not import'),
        ],
      ];
    }
    $form['columns']['#type'] = 'container';
    $form['columns']['#attributes'] = [
      'id' => 'available-fields-config-form',
    ];
    $form['columns']['#tree'] = TRUE;

    $form['columns']['common_fields'] = [
      '#type' => 'container',
      'common_fields_label' => [
        '#type' => 'label',
        '#title' => $this->t('Standard values (used for missing values)'),
      ],
      $commonFields,
      '#attributes' => [
        'class' => [
          'common-fields'
        ],
      ],
      '#tree' => TRUE,
    ];



  }

  public function buildAjaxGetFieldsConfigForm(array $form, FormStateInterface $form_state) {
    return $form['columns'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $configs = $this->config(static::SETTINGS);
    $node_type = $form_state->getValue(['node_type']);
    $fields = $configs->get($node_type);

    $common_fields = isset($fields["select_excluded_fields"]) ? array_keys($fields["select_common_fields"]) : [];

    $data_rows = $this->store->get('rows');
    $user_input = $form_state->getUserInput();
    $selected_fields = $user_input['columns'];
    $commonFields = [];
    foreach ($common_fields as $field) {
      if (isset($user_input[$field]) && !empty($user_input[$field])) {
        $commonFields[$field] = $user_input[$field];
      }
    }

    $this->batchBuilder
      ->setTitle($this->t('Processing'))
      ->setInitMessage($this->t('Initializing.'))
      ->setProgressMessage($this->t('Completed @current of @total.'))
      ->setErrorMessage($this->t('An error has occurred.'));

    $this->batchBuilder->setFile(drupal_get_path('module', 'feeds_ui_importer') . '/src/Form/FeedsUiImporterMappingFormForm.php');
    $this->batchBuilder->addOperation([$this, 'processItems'], [$node_type, $data_rows, $selected_fields, $commonFields]);
    $this->batchBuilder->setFinishCallback([$this, 'finished']);

    batch_set($this->batchBuilder->toArray());
    $this->deleteStore();
  }

  /**
   * Processor for batch operations.
   */
  public function processItems($node_type, $data_rows, $selected_fields, $commonFields, array &$context) {
    // Elements per operation.
    $limit = 50;
    // Set default progress values.
    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($data_rows);
    }
    // Save items to array which will be changed during processing.
    if (empty($context['sandbox']['items'])) {
      $context['sandbox']['items'] = $data_rows;
    }
    $counter = 0;
    if (!empty($context['sandbox']['items'])) {
      // Remove already processed items.
      if ($context['sandbox']['progress'] != 0) {
        array_splice($context['sandbox']['items'], 0, $limit);
      }
      foreach ($context['sandbox']['items'] as $data_row) {
        if ($counter != $limit) {
          $this->processItem($node_type, $data_row, $selected_fields, $commonFields, $context);
          $counter++;
          $context['sandbox']['progress']++;
          // Increment total processed item values. Will be used in finished
          // callback.
          $context['results']['processed'] = $context['sandbox']['progress'];
        }
      }
    }
    // If not finished all tasks, we count percentage of process. 1 = 100%.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Process single item.
   *
   * @param $node_type
   * @param $data_row
   *
   * @param $selected_fields
   * @param $commonFields
   * @param $context
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processItem($node_type, $data_row, $selected_fields, $commonFields, &$context) {
    $node = Node::create([
      'type' => $node_type,
      'langcode' => 'en',
      'uid' => 1,
      'status' => 1,
    ]);
    foreach ($data_row as $key => $field_value) {
      if (!empty($field_value)) {
        foreach ($selected_fields as $key_field => $field_name) {
          if ($key == $key_field && !empty($field_name["selected_fields"]['select_available_fields'])) {
            $field_type = $node->get($field_name["selected_fields"]['select_available_fields'])->getFieldDefinition()->getType();
            if ($field_type == 'entity_reference') {
              $field_settings = $node->get($field_name["selected_fields"]['select_available_fields'])->getFieldDefinition()->getSettings();
              if ($field_settings["handler"] == 'default:taxonomy_term') {
                $target_bundle = $field_settings["handler_settings"]["target_bundles"]["type_of_business"];
                $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($target_bundle);
                // @TODO set taxonomy term value.
              }
            }
            $node->set($field_name["selected_fields"]['select_available_fields'], $field_value);
          }
        }
      }
    }
    foreach ($commonFields as $key_common_field => $common_field) {
      if ($key_common_field == 'field_account_manager') {
        $values = explode('-', $common_field);
        $values = [
          'target_type' => $values[0],
          'target_id' => $values[1],
        ];
        if($values['target_type'] != '_none' && $values['target_id']) {
          $node->set($key_common_field, $values);
        }
      }
      else {
        $node->set($key_common_field, $common_field);
      }
    }
    try {
      $node->save();
    } catch (EntityStorageException $e) {
    }
    $context['message'] = $this->t('Now processing node :progress of :count. Creating the node :node_label', [
      ':progress' => $context['sandbox']['progress'],
      ':count' => $context['sandbox']['max'],
      ':node_label' => $node->label(),
    ]);
  }

  /**
   * Finished callback for batch.
   */
  public function finished($success, $results, $operations) {
    $message = $this->t('Number of nodes created after import: @count', [
      '@count' => $results['processed'],
    ]);

    $this->messenger()
      ->addStatus($message);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Helper method that removes all the keys from the store collection used for
   * the multistep form.
   */
  protected function deleteStore() {
    $keys = ['columns', 'rows', 'delimiter', 'count_rows'];
    foreach ($keys as $key) {
      $this->store->delete($key);
    }
  }

}
