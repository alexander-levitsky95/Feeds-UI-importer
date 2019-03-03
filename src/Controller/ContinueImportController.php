<?php

namespace Drupal\feeds_ui_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Defines a route controller for popups.
 */
class ContinueImportController extends ControllerBase {

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
   * PopupController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   */
  public function __construct(
    FormBuilderInterface $form_builder,
    RendererInterface $renderer
  ) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $container->get('form_builder');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $container->get('renderer');
    return new static($form_builder, $renderer);
  }

  /**
   * {@inheritdoc}
   */
  public function formMappingData() {

    $upload_form = $this->formBuilder->getForm('\Drupal\feeds_ui_importer\Form\FeedsUiImporterMappingForm');
    $output = [
      '#form' => $upload_form,
      '#theme' => 'feeds_ui_importer',
    ];

    return $output;
  }

}
