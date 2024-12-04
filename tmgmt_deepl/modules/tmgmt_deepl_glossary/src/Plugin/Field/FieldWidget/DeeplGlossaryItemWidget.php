<?php

namespace Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the glossary item widget.
 *
 * @FieldWidget(
 *   id = "deepl_glossary_item_widget",
 *   label = @Translation("Glossary item"),
 *   field_types = {
 *     "deepl_glossary_item"
 *   }
 * )
 */
class DeeplGlossaryItemWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $item = $items[$delta];

    // Subject.
    $element['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $item->subject ?? NULL,
    ];

    // Definition.
    $element['definition'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Definition'),
      '#default_value' => $item->definition ?? NULL,
    ];

    return $element;
  }

}
