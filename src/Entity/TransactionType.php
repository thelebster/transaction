<?php

namespace Drupal\transaction\Entity;

use Drupal\transaction\TransactionTypeInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Provides a type of transaction.
 *
 * @ConfigEntityType(
 *   id = "transaction_type",
 *   label = @Translation("Transaction type"),
 *   label_singular = @Translation("Transaction type"),
 *   label_plural = @Translation("Transaction types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count transaction type",
 *     plural = "@count transaction types",
 *   ),
 *   admin_permission = "administer transaction types",
 *   handlers = {
 *     "list_builder" = "Drupal\transaction\TransactionTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\transaction\Form\TransactionTypeAddForm",
 *       "edit" = "Drupal\transaction\Form\TransactionTypeEditForm",
 *       "delete" = "Drupal\transaction\Form\TransactionTypeDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer transactions",
 *   config_prefix = "type",
 *   bundle_of = "transaction",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/workflow/transaction/add/{target_entity_type}/{transactor}",
 *     "edit-form" = "/admin/config/workflow/transaction/edit/{transaction_type}",
 *     "delete-form" = "/admin/config/workflow/transaction/delete/{transaction_type}",
 *     "collection" = "/admin/config/workflow/transaction",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "target_entity_type",
 *     "transactor",
 *     "bundles",
 *     "options",
 *   },
 * )
 */
class TransactionType extends ConfigEntityBundleBase implements TransactionTypeInterface {

  /**
   * The transaction type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The transaction type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The target entity type ID.
   *
   * @var string
   */
  protected $target_entity_type;

  /**
   * The transactor plugin ID and its settings.
   *
   * @var string[]
   */
  protected $transactor;

  /**
   * Applicable bundles of the target entity type.
   *
   * @var string[]
   */
  protected $bundles = [];

  /**
   * Additional options.
   *
   * @var array
   */
  protected $options = [];

  /**
   * A collection to store the transactor plugin.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId() {
    return $this->target_entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntityTypeId($entity_type_id) {
    $this->target_entity_type = $entity_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles($applicable = FALSE) {
    $bundles = $this->bundles;

    if (empty($bundles) && $applicable) {
      // If the setting is empty, return all bundle names for the target entity
      // type.
      /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service */
      $bundle_info_service = \Drupal::service('entity_type.bundle.info');
      $bundle_info = $bundle_info_service->getBundleInfo($this->target_entity_type);
      $bundles = array_keys($bundle_info);
    }

    return $bundles;
  }

  /**
   * Single plugin collection that encapsulates the transactor plugin.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   *   The transactor plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      // Empty configuration, to be set from the transaction type form.
      $this->pluginCollection = new DefaultSingleLazyPluginCollection(
        \Drupal::service('plugin.manager.transaction.transactor'),
        $this->transactor['id'],
        $this->transactor['settings']
      );
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return isset($this->transactor['id']) ? $this->transactor['id'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin_id) {
    $this->transactor['id'] = $plugin_id;
    $this->transactor['settings'] = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginSettings() {
    return isset($this->transactor['settings']) ? $this->transactor['settings'] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginSettings(array $settings) {
    $this->transactor['settings'] = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    $plugin = NULL;
    if ($plugin_id = $this->getPluginId()) {
      $plugin = $this->getPluginCollection()->get($plugin_id);
    }
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name, $default_value = NULL) {
    return isset($this->options[$name]) ? $this->options[$name] : $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $value) {
    $this->options[$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $bundles = array_filter($this->get('bundles'));
    sort($bundles);

    $this->set('bundles', $bundles);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // Add dependency on the target entity type.
    $this->addDependency('config', $this->entityTypeManager()->getDefinition($this->target_entity_type)->getConfigDependencyKey());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    if ($rel === 'add-form') {
      $uri_route_parameters['target_entity_type'] = $this->getTargetEntityTypeId();
      $uri_route_parameters['transactor'] = $this->getPluginId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    if ($property_name === 'bundles') {
      // Sanitize values for bundles.
      $clean_value = [];
      if (is_array($value)) {
        foreach ($value as $key => $item) {
          if (!empty($item) && is_string($item)) {
            $clean_value[] = $item;
          }
        }
      }
      $value = $clean_value;
    }

    return parent::set($property_name, $value);
  }

}