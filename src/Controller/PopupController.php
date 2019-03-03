<?php

namespace Drupal\feeds_ui_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Defines a route controller for popups.
 */
class PopupController extends ControllerBase {

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
  public function formUploadData() {

    $upload_form = $this->formBuilder->getForm('\Drupal\feeds_ui_importer\Form\FeedsUiImporterUploadForm');
    $output = [
      '#text' => $this->t('Imports can be done by copying and pasting an excel sheet or just text in this area. Each record should start on a new line.
In the next step, we\'ll ask you to match the columns. You can find more info on our <a href="#">Blog</a>'),
      '#form' => $upload_form,
      '#theme' => 'feeds_ui_importer',
    ];

    return $output;
  }

}
