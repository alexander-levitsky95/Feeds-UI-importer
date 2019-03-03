<?php

namespace Drupal\feeds_ui_importer\Form;

use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * FeedsUiImporter settings form.
 */
class FeedsUiImporterSettingsForm extends ConfigFormBase {

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
   * FeedsUiImporterSettingsForm constructor.
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
    return 'feeds_ui_importer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $configs = $this->config(static::SETTINGS);
    $content_types = $configs->get();
    if(isset($content_types['num_elements'])) {
      unset($content_types['num_elements']);
    }

    $form['#tree'] = TRUE;
    $num_elements = $configs->get('num_elements');
    $num_elements = !empty($num_elements) ? $num_elements : $form_state->get('num_elements');

    if (empty($num_elements)) {
      $form_state->set('num_elements', 1);
      $num_elements = $form_state->getStorage()['num_elements'];
    }

    $form['node_type_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Please select content types and fields'),
      '#prefix' => '<div id="node-type-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $user_input_bundles = isset($form_state->getUserInput()["node_type_fieldset"]["nodes"]) ? $form_state->getUserInput()["node_type_fieldset"]["nodes"] : ['0' => ['node_type' => '']];

    if (!empty($content_types)) {
      $key_type = 0;
      foreach ($content_types as $key_content_type => $content_type) {
        $user_input_bundles[$key_type]['node_type'] = $key_content_type;
        foreach ($content_type as $key_fields => $selected_fields) {
          $user_input_fields[$key_content_type][$key_fields] = array_keys($selected_fields);
        }
        $key_type++;
      }
    }

    $entity_type_id = 'node';
    for ($i = 0; $i < $num_elements; $i++) {
        $bundleFields = [];
        if (!isset($user_input_bundles[$i]['node_type'])) {
          $user_input_bundles[$i]['node_type'] = 'page';
        }
        foreach (\Drupal::entityManager()->getFieldDefinitions($entity_type_id, $user_input_bundles[$i]['node_type']) as $field_name => $field_definition) {
          if ((!empty($field_definition->getTargetBundle()) || $field_name == 'title')) {
            $bundleFields[$user_input_bundles[$i]['node_type']][$field_name] = $field_definition->getLabel();
          }
        }
        $form['node_type_fieldset']['nodes'][$i] = [
          'node_type' => [
            '#type' => 'select',
            '#title' => $this->t('Content type'),
            '#options' => $this->nodeBundles,
            '#empty_option'  => t('Select content type'),
            '#default_value' => $user_input_bundles[$i]['node_type'],
            '#required' => TRUE,
            '#ajax' => [
              'callback' => '::buildAjaxGetFieldsConfigForm',
              'wrapper' => 'available-fields-config-form-' . $i,
              'method' => 'replace',
              'effect' => 'fade',
            ],
          ],
          'selected_fields' => [
            '#type' => 'container',
            '#attributes' => [
              'id' => 'available-fields-config-form-' . $i,
              'class' => [
                'available-fields',
              ],
            ],
            '#tree' => TRUE,
            'select_common_fields' => [
              '#type' => 'select',
              '#title' => $this->t('Common fields'),
              '#options' => $bundleFields[$user_input_bundles[$i]['node_type']],
              '#empty_option'  => t('Select fields'),
              '#default_value' => isset($user_input_fields[$user_input_bundles[$i]['node_type']]['select_common_fields']) ? $user_input_fields[$user_input_bundles[$i]['node_type']]['select_common_fields'] : '',
              '#multiple' =>TRUE,
              '#size' => 5,
            ],
            'select_excluded_fields' => [
              '#type' => 'select',
              '#title' => $this->t('Excluded fields'),
              '#options' => $bundleFields[$user_input_bundles[$i]['node_type']],
              '#empty_option'  => t('Select fields'),
              '#default_value' => isset($user_input_fields[$user_input_bundles[$i]['node_type']]['select_excluded_fields']) ? $user_input_fields[$user_input_bundles[$i]['node_type']]['select_excluded_fields'] : '',
              '#multiple' =>TRUE,
              '#size' => 5,
            ],
          ],
        ];
      }

    $form['node_type_fieldset']['actions']['add_node_type'] = [
      '#type' => 'submit',
      '#value' => t('Add one more'),
      '#submit' => array('::addOne'),
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'node-type-fieldset-wrapper',
      ],
    ];
    if ($num_elements > 1) {
      $form['node_type_fieldset']['actions']['remove_name'] = [
        '#type' => 'submit',
        '#value' => t('Remove one'),
        '#submit' => array('::removeCallback'),
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'node-type-fieldset-wrapper',
        ]
      ];
    }
    $form_state->setCached(FALSE);

    return parent::buildForm($form, $form_state);

  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function buildAjaxGetFieldsConfigForm(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    return $form['node_type_fieldset']['nodes'][$triggering_element["#parents"][2]]['selected_fields'];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $configs = $this->config(static::SETTINGS);

    $num_elements = $configs->get('num_elements') ? $configs->get('num_elements') : $form_state->get('num_elements');
    $add_button = $num_elements + 1;
    $configs->set('num_elements', $add_button);
    $configs->save();
    $form_state->set('num_elements', $add_button);
    $form_state->setRebuild();
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['node_type_fieldset'];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $configs = $this->config(static::SETTINGS);
    $num_elements = $configs->get('num_elements') ? $configs->get('num_elements') : $form_state->get('num_elements');

    if ($num_elements > 1) {
      $remove_button = $num_elements - 1;
      $configs->set('num_elements', $remove_button);
      $configs->save();
      $form_state->set('num_elements', $remove_button);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Retrieve the configuration
    $configs = $this->configFactory->getEditable(static::SETTINGS);
    $values = $form_state->getValues();
    if (isset($values["node_type_fieldset"]["nodes"]) && !empty($values["node_type_fieldset"]["nodes"])) {
      foreach ($values["node_type_fieldset"]["nodes"] as $key_element => $node) {
        // Set the submitted configuration setting
        $configs->set($node["node_type"], $node["selected_fields"]);
      }
      $configs->set('num_elements', $key_element+1);
      $configs->save();
    }

     parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }
}
