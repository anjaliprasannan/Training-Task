<?php

declare(strict_types=1);

namespace Drupal\ajax_forms_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\ajax_forms_test\Callbacks;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds a form where each Form API element triggers a simple Ajax callback.
 *
 * @internal
 */
class AjaxFormsTestAjaxElementsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_forms_test_ajax_elements_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['date'] = [
      '#type' => 'date',
      '#ajax' => [
        'callback' => [Callbacks::class, 'dateCallback'],
      ],
      '#suffix' => '<div id="ajax_date_value">No date yet selected</div>',
    ];

    $form['datetime'] = [
      '#type' => 'datetime',
      '#ajax' => [
        'callback' => [Callbacks::class, 'datetimeCallback'],
        'wrapper' => 'ajax_datetime_value',
      ],
    ];

    $form['datetime_result'] = [
      '#type' => 'markup',
      '#markup' => '<div id="ajax_datetime_value">No datetime selected.</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
