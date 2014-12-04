<?php

/**
 * @file
 * Contains \Drupal\entity_reference/EntityReferenceAutocomplete.
 */

namespace Drupal\entity_reference;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\entity_reference\Plugin\Type\SelectionPluginManager;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Helper class to get autocompletion results for entity reference.
 */
class EntityReferenceAutocomplete {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The Entity reference selection handler plugin manager.
   *
   * @var \Drupal\entity_reference\Plugin\Type\SelectionPluginManager
   */
  protected $selectionHandlerManager;

  /**
   * Constructs a EntityReferenceAutocomplete object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\entity_reference\Plugin\Type\SelectionPluginManager $selection_manager
   *   The Entity reference selection handler plugin manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, SelectionPluginManager $selection_manager) {
    $this->entityManager = $entity_manager;
    $this->selectionHandlerManager = $selection_manager;
  }

  /**
   * Returns matched labels based on a given search string.
   *
   * This function can be used by other modules that wish to pass a mocked
   * definition of the field on instance.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $entity_id
   *   (optional) The entity ID the entity reference field is attached to.
   *   Defaults to ''.
   * @param string $prefix
   *   (optional) A prefix for all the keys returned by this function.
   * @param string $string
   *   (optional) The label of the entity to query by.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the current user doesn't have access to the specifies entity.
   *
   * @return array
   *   A list of matched entity labels.
   *
   * @see \Drupal\entity_reference\EntityReferenceController
   */
  public function getMatches(FieldDefinitionInterface $field_definition, $entity_type, $bundle, $entity_id = '', $prefix = '', $string = '') {
    $matches = array();
    $entity = NULL;

    if ($entity_id !== 'NULL') {
      $entity = $this->entityManager->getStorage($entity_type)->load($entity_id);
      if (!$entity || !$entity->access('view')) {
        throw new AccessDeniedHttpException();
      }
    }
    $handler = $this->selectionHandlerManager->getSelectionHandler($field_definition, $entity);

    if (isset($string)) {
      // Get an array of matching entities.
      $widget = entity_get_form_display($entity_type, $bundle, 'default')->getComponent($field_definition->getName());
      $match_operator = !empty($widget['settings']['match_operator']) ? $widget['settings']['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 10);

      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          $key = "$label ($entity_id)";
          // Strip things like starting/trailing white spaces, line breaks and
          // tags.
          $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(String::decodeEntities(strip_tags($key)))));
          // Names containing commas or quotes must be wrapped in quotes.
          $key = Tags::encode($key);
          $matches[] = array('value' => $prefix . $key, 'label' => $label);
        }
      }
    }

    return $matches;
  }

}
