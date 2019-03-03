<?php

namespace Drupal\feeds_ui_importer\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Provides a block with link which opening popup for import..
 *
 * @Block(
 *   id = "feeds_ui_importer_link_block",
 *   admin_label = @Translation("Feeds UI Importer popup link"),
 *   category = @Translation("Feeds UI Importer")
 * )
 */
class FeedsUiImporterBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * FeedsUiImporterBlock constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FormBuilderInterface $form_builder,
    RendererInterface $renderer
  ) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $container->get('form_builder');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $container->get('renderer');
    return new static($configuration, $plugin_id, $plugin_definition, $form_builder, $renderer);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#maxlength' => 255,
    ];

    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('link_title', $form_state->getValue('link_title'));
  }
  
  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $block = [
      '#type' => 'link',
      '#title' => $config['link_title'] ? $config['link_title'] : $this->t('Import'),
      '#url' => Url::fromRoute('feeds_ui_importer.upload_data'),
      '#options' => [
        'attributes' => [
          'class' => [
            'use-ajax',
            'import-content',
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 700,
          ]),
        ],
      ],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];

    return $block;
  }

}
