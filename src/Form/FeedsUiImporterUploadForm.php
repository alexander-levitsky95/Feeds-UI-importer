<?php

namespace Drupal\feeds_ui_importer\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\node\NodeStorageInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Piebrussels upload form.
 */
class FeedsUiImporterUploadForm extends FormBase {

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
    return 'feeds_ui_importer_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -100,
    ];

    $form['text_data'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'uploading-data',
        ],
      ],
      'data' => [
        '#type' => 'textarea',
        '#attributes' => [
          'placeholder' => $this->t('julius_caesar@rome.com	Julius &emsp;Caesar &emsp; 36 &#10;walter@white.com &emsp; Walter White &emsp; 47'),
          'class' => [
            'data-to-import',
            'manually-data',
          ],
        ],
      ],
      'or_markup' => [
        '#markup' => '<div class="data-to-import data-divider">or</div>',
      ],
      'file' => [
        '#type' => 'managed_file',
        '#upload_location' => 'public://feeds',
        '#upload_validators' => [
          'file_validate_extensions' => ['csv xls'],
        ],
        '#attributes' => [
          'class' => [
            'data-to-import',
            'upload-data',
          ],
        ],
      ]
    ];

    $form['additional_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Additional settings'),
    ];

    $form['additional_settings']['delimiter'] = [
      '#type' => 'textfield',
      '#title' => t('Delimiter'),
      //'#default_value' => $config->get('delimiter'),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Continue import'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxHandleSponseesCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
        ],
      ]
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
  
  /**
   * Ajax submit callback.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   * @throws \Exception
   */
  public function ajaxHandleSponseesCallback(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    if ($form_state->getValue('file') == NULL && $form_state->getValue('data') == NULL) {
      $this->messenger()->addError($this->t('The import file is empty. Please enter a data or upload a csv file.'));
    }
    if ($form_state->getValue('file') != NULL && $form_state->getValue('data') != NULL) {
      $this->messenger()->addError($this->t('Please choose only one way. Enter data or upload a csv file.'));
    }
    if (!empty($this->messenger()->messagesByType('error'))) {
      $message = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'error' => $this->messenger()->messagesByType('error'),
        ],
        '#status_headings' => [
          'error' => $this->t('Error message'),
        ],
      ];
      $messages = $this->renderer->render($message);
      $ajax_response->addCommand(new HtmlCommand('#form-system-messages', $messages));
      $this->messenger()->deleteAll();

      return $ajax_response;
    }

    // @TODO Change the mapping process.
    if ($form_state->getValue('file')) {
      $data = $form_state->getValue('file');
      $file =\Drupal\file\Entity\File::load($data[0]);
      $path = $file->getFileUri();
      $type_file = pathinfo($path)['extension'];
      if ($type_file == 'xls') {
        $mapping_form_results = $this->mappingFileXls($path);
      }
      else {
        $mapping_form_results = $this->mappingFile($path);
      }
      $this->store->set('delimiter', $form_state->getValue('delimiter'));

      $this->store->set('columns', $mapping_form_results['columns']);
      $this->store->set('rows', $mapping_form_results['rows']);
      $this->store->set('count_rows', count($mapping_form_results['rows']));

    }
    if ($form_state->getValue('data')) {
      $this->store->set('delimiter', $form_state->getValue('delimiter'));
      $mapping_form_results = $this->mappingInputData($form_state->getValue('data'));

      $this->store->set('columns', $mapping_form_results['columns']);
      $this->store->set('rows', $mapping_form_results['rows']);
      $this->store->set('count_rows', count($mapping_form_results['rows']));
    }

    $url = Url::fromRoute('feeds_ui_importer.upload_data_continue_import');

    $ajax_response->addCommand(new RedirectCommand($url->toString()));

    return $ajax_response;

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * Helper function for mapping data entered by user.
   *
   * @param string $input_data
   *  Data entered by users.
   *
   * @return array
   *  Return mapping results array.
   */
  private function mappingInputData($input_data) {
    $lines = explode("\n", $input_data);
    $delimiter = $this->store->get('delimiter');
    $headers = str_getcsv(array_shift($lines), $delimiter);
    $header_value_array = [];
    foreach ($headers as $key_header => $header_value) {
      $header_value = $this->changeValueHeader($header_value);
      if (in_array($header_value, $header_value_array)) {
        $headers[$key_header] = $header_value . $key_header;
        $header_value_array[] = $headers[$key_header];
      }
      $header_value_array[] = $header_value;
      if (empty($header_value)) {
        $headers[$key_header] = $key_header;
      }
    }
    $mapping_form_results = [];
    $row = [];
    $data = [];
    foreach ($lines as $key_line => $line) {
      $output = [];
      foreach (str_getcsv($line, $delimiter) as $key => $field) {
        $row[$headers[$key]] = $field;
        if ($key_line < 3) {
          $columns[$headers[$key]][] = $field;
          $mapping_form_results['columns'][$headers[$key]] = [
            '#type' => 'container',
            'rows_container' =>[
              '#type' => 'container',
              'title' => [
                '#type' => 'label',
                '#title' => $headers[$key],
              ],
              '#attributes' => [
                'class' => [
                  'csv-rows'
                ],
              ],
            ],
            '#attributes' => [
              'class' => [
                'csv-values-row'
              ],
            ],
          ];
          foreach ($columns[$headers[$key]] as $key_column => $column_value) {
            $output[$headers[$key]][] = '<div class="csv-value-record ">' . $column_value . '</div>';
          }
          $mapping_form_results['columns'][$headers[ $key ]]['rows_container'][$headers[ $key ]] = [
            '#markup' => '<div class="csv-values" style="height: 100px; overflow: scroll; width: 700px;">' . implode('',$output[$headers[$key]]) . '</div>',
          ];
        }
      }
      $data[] = $row;
    }
    $mapping_form_results['rows'] = $data;
    
    return $mapping_form_results;
  }

  /**
   * Helper function for mapping data from the file.
   *
   * @param string $path
   *  Url for the file.
   *
   * @return array
   *  Return mapping results array
   */
  private function mappingFile($path) {
    $delimiter = $this->store->get('delimiter');

    $lines = explode("\n", file_get_contents($path));
    $headers = str_getcsv(array_shift($lines), $delimiter);
    $header_value_array = [];
    foreach ($headers as $key_header => $header_value) {
      $header_value = $this->changeValueHeader($header_value);
      if (in_array($header_value, $header_value_array)) {
        $headers[$key_header] = $header_value . $key_header;
        $header_value_array[] = $headers[$key_header];
      }
      $header_value_array[] = $header_value;
      if (empty($header_value)) {
        $headers[$key_header] = $key_header;
      }
    }
    $mapping_form_results = [];
    $row = [];
    $data = [];

    foreach ($lines as $key_line => $line) {
      $output = [];
      foreach (str_getcsv($line, $delimiter) as $key => $field) {
        $row[$headers[$key]] = $field;
        if ($key_line < 3) {
          $columns[$headers[$key]][] = $field;
          $mapping_form_results['columns'][$headers[$key]] = [
            '#type' => 'container',
            'rows_container' =>[
              '#type' => 'container',
              'title' => [
                '#type' => 'label',
                '#title' => $headers[$key],
              ],
              '#attributes' => [
                'class' => [
                  'csv-rows'
                ],
              ],
            ],
            '#attributes' => [
              'class' => [
                'csv-values-row'
              ],
            ],
          ];
          foreach ($columns[$headers[$key]] as $key_column => $column_value) {
            $output[$headers[$key]][] = '<div class="csv-value-record ">' . $column_value . '</div>';
          }
          $mapping_form_results['columns'][$headers[ $key ]]['rows_container'][$headers[ $key ]] = [
            '#markup' => '<div class="csv-values" style="height: 100px; overflow: scroll; width: 700px;">' . implode('',$output[$headers[$key]]) . '</div>',
          ];
        }
      }
      $data[] = $row;
    }
    $mapping_form_results['rows'] = $data;
    
    return $mapping_form_results;
  }

  /**
   * Helper function for mapping data from the file.
   *
   * @param string $path
   *  Url for the file.
   *
   * @return array
   *  Return mapping results array
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
   */
  private function mappingFileXls($path) {
    $spreadsheet = IOFactory::load($path);
    $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
    $flipped = $this->array_transpose($sheetData);
    $mapping_form_results['rows'] = $sheetData;
    $mapping_form_results['columns'] = $flipped;

    return $mapping_form_results;

  }

  /**
   * @param array $arr
   *
   * @return array
   */
  private function array_transpose(array $arr) {
    $keys    = array_keys($arr);
    $subkeys = array_keys($arr[1]);
    //@TODO Add check if there are different counts subkeys.
//    foreach ($keys as $key_subkey => $value_subkey) {
//      $subkeys = array_keys($arr[$key_subkey]);
//    }
    $transposed = array();
    $num_coll = 1;
    foreach ($subkeys as $subkey) {

      $item = array();
      foreach ($keys as $key) {
        if ($key < 5 && $key != 1) {
          $item[$key] = array_key_exists($subkey, $arr[$key]) ? '<div class="csv-value-record ">' . $arr[$key][$subkey] . '</div>' : NULL;
        }
      }
      $transposed[$subkey] =[
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'csv-values-row'
          ],
        ],
        'rows_container' => [
          '#type' => 'container',
          'title' => [
            '#type' => 'label',
            //'#title' => 'Column ' . ($num_coll),
            '#title' => 'Column ' . ($subkey),
          ],
          '#attributes' => [
            'class' => [
              'csv-rows'
            ],
          ],
          $subkey => [
            '#markup' => '<div class="csv-values" style="height: 100px; overflow: scroll; width: 700px;">' . implode('',$item) . '</div>',
          ],
        ],
      ];
      $num_coll++;
    }

    return $transposed;
  }

  /**
   * @param $string
   *
   * @return string|string[]|null
   */
  private function changeValueHeader($string) {
    //Lower case everything
    $string = strtolower($string);
    //Make alphanumeric (removes all other characters)
    $string = preg_replace("/[^a-z0-9_\s-]/", "", $string);
    //Clean up multiple dashes or whitespaces
    $string = preg_replace("/[\s-]+/", "", $string);
    //Convert whitespaces and underscore to dash
    $string = preg_replace("/[\s_]/", "", $string);
    return $string;
  }

}
