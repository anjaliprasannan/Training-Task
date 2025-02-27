<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\form_test\Callbacks;

/**
 * Provides a base class for tableselect forms.
 *
 * @internal
 */
abstract class FormTestTableSelectFormBase extends FormBase {

  /**
   * Build a form to test the tableselect element.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $element_properties
   *   An array of element properties for the tableselect element.
   *
   * @return array
   *   A form with a tableselect element and a submit button.
   */
  public function tableselectFormBuilder($form, FormStateInterface $form_state, $element_properties) {
    [$header, $options] = Callbacks::tableselectGetData();

    $form['tableselect'] = $element_properties;

    $form['tableselect'] += [
      '#prefix' => '<div id="tableselect-wrapper">',
      '#suffix' => '</div>',
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#multiple' => FALSE,
      '#empty' => $this->t('Empty text.'),
      '#ajax' => [
        'callback' => '::tableselectAjaxCallback',
        'wrapper' => 'tableselect-wrapper',
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Ajax callback that returns the form element.
   */
  public function tableselectAjaxCallback(array $form, FormStateInterface $form_state): array {
    return $form['tableselect'];
  }

}
