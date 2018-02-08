<?php

namespace Drupal\transaction;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides a base class for transactor plugins.
 */
abstract class TransactorBase extends PluginBase implements TransactorPluginInterface {

  use StringTranslationTrait;

  /**
   * The transaction entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $transactionStorage;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Prefix for new field creation.
   *
   * @var string
   */
  protected $fieldPrefix;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, TranslationInterface $string_translation, EntityStorageInterface $transaction_storage, EntityFieldManagerInterface $field_manager, AccountInterface $current_user, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $string_translation;
    $this->transactionStorage = $transaction_storage;
    $this->configuration += $this->defaultConfiguration();
    $this->fieldManager = $field_manager;
    $this->currentUser = $current_user;
    $this->fieldPrefix = $config_factory->get('field_ui.settings')->get('field_prefix') ? : 'field_';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('entity_type.manager')->getStorage('transaction'),
      $container->get('entity_field.manager'),
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // @todo return dependencies on fields
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * Provides a form for this transactor plugin settings.
   *
   * The form provided by this method is displayed by the TransactionTypeForm
   * when creating or editing the transaction type.
   *
   * @param array $form
   *   The transaction type form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   *
   * @see \Drupal\transaction\Form\TransactionTypeFormBase
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Add reference to fields in the transaction entity.
    $form = $this->buildTransactionFieldsForm($form, $form_state);
    // Add reference to fields in the target entity.
    $form = $this->buildTargetFieldsForm($form, $form_state);
    // Add transaction options.
    $form = $this->buildTransactionOptionsForm($form, $form_state);

    return $form;
  }

  /**
   * Build configuration form fields to the transaction.
   *
   * @param array $form
   *   The transaction type form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  protected function buildTransactionFieldsForm(array $form, FormStateInterface $form_state) {
    if (!empty($this->pluginDefinition['transaction_fields'])) {
      $form['transaction_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('Transaction fields'),
        '#description' => $this->t('Fields in the transaction entity used by this type of transaction.'),
        '#open' => TRUE,
        '#tree' => FALSE,
        '#weight' => 10,
      ];

      /** @var \Drupal\transaction\TransactionTypeInterface $transaction_type */
      $transaction_type = $form_state->getFormObject()->getEntity();
      $transactor_settings = $transaction_type->getPluginSettings();

      // Plugin field definitions.
      foreach ($this->pluginDefinition['transaction_fields'] as $field) {
        // Set transaction as the entity type in the field info.
        $field['entity_type'] = 'transaction';

        $field['settings'] = isset($field['settings']) ? $field['settings'] : [];
        // Entity reference fields in the transaction entity with no target
        // type in settings points to the target entity type.
        if ($field['type'] == 'entity_reference' && !isset($field['settings']['target_type'])) {
          $field['settings']['target_type'] = $transaction_type->getTargetEntityTypeId();
          $field['handler_settings']['target_bundles'] = $transaction_type->getBundles(TRUE);
        }

        $form['transaction_fields'] += $this->fieldReferenceSettingsFormField(
          $field,
          isset($transactor_settings[$field['name']]) ? $transactor_settings[$field['name']] : NULL,
          $this->getAvailableFields('transaction', $field['type'], [], $field['settings'])
        );

        $form_state->setTemporaryValue('field_info_' . $field['name'], $field);
      }
    }

    return $form;
  }

  /**
   * Build configuration form fields to the target entity.
   *
   * @param array $form
   *   The transaction type form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  protected function buildTargetFieldsForm(array $form, FormStateInterface $form_state) {
    if (!empty($this->pluginDefinition['target_entity_fields'])) {
      $form['target_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('Target entity fields'),
        '#description' => $this->t('Fields in the target entity used by this type of transaction.'),
        '#open' => TRUE,
        '#tree' => FALSE,
        '#weight' => 20,
      ];

      /** @var \Drupal\transaction\TransactionTypeInterface $transaction_type */
      $transaction_type = $form_state->getFormObject()->getEntity();
      $transactor_settings = $transaction_type->getPluginSettings();

      // Plugin field definitions.
      foreach ($this->pluginDefinition['target_entity_fields'] as $field) {
        // Set target entity type as the entity type in the field info.
        $field['entity_type'] = $transaction_type->getTargetEntityTypeId();

        $field['settings'] = isset($field['settings']) ? $field['settings'] : [];
        // Entity reference fields in the target entity with no target type in
        // settings points to the transaction entity.
        if ($field['type'] == 'entity_reference' && !isset($field['settings']['target_type'])) {
          $field['settings']['target_type'] = 'transaction';
          $field['handler_settings']['target_bundles'] = [$transaction_type->id()];
        }

        $form['target_fields'] += $this->fieldReferenceSettingsFormField(
          $field,
          isset($transactor_settings[$field['name']]) ? $transactor_settings[$field['name']] : NULL,
          $this->getAvailableFields($field['entity_type'], $field['type'], [], $field['settings'])
        );

        $form_state->setTemporaryValue('field_info_' . $field['name'], $field);
      }
    }

    return $form;
  }

  /**
   * Build transaction options configuration form.
   *
   * @param array $form
   *   The transaction type form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  protected function buildTransactionOptionsForm(array $form, FormStateInterface $form_state) {
    // Add transactor general options to $form['options'].
    return $form;
  }

  /**
   * Search for fields of a given type in a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID to search in.
   * @param string $field_type
   *   A field type, @see https://www.drupal.org/node/2302735 for a list.
   * @param string|string[] $bundles
   *   (optional) Limit to selected bundle or bundles.
   * @param array $settings_match
   *   (optional) A keyed name/value array of settings the field must match.
   *
   * @return string[]
   *   An array with the names of matching fields keyed the field id.
   */
  protected function getAvailableFields($entity_type_id, $field_type, $bundles = [], array $settings_match = []) {
    // Params adjustment.
    if (!empty($bundles) && !is_array($bundles)) {
      $bundles = [$bundles];
    }

    $options = [];
    $fields = $this->fieldManager->getFieldMapByFieldType($field_type);
    if (!isset($fields[$entity_type_id])) {
      return $options;
    }

    foreach ($fields[$entity_type_id] as $field_name => $field) {
      if (!$field_definition = FieldStorageConfig::loadByName($entity_type_id, $field_name)) {
        continue;
      }

      // Filter by bundle.
      foreach ($bundles as $bundle) {
        if (!isset($field['bundles'][$bundle])) {
          continue 2;
        }
      }

      // Filter by settings.
      foreach ($settings_match as $key => $value) {
        if ($field_definition->getSetting($key) != $value) {
          continue 2;
        }
      }

      $field_config = FieldConfig::loadByName($entity_type_id, array_pop($field['bundles']), $field_name);
      $options[$field_name] = $field_config
        ? $field_config->label() . ' (' . $field_name . ')'
        : $field_name;
    }

    return $options;
  }

  /**
   * Builds a form field to reference a field.
   *
   * @param array $field_info
   *   An array with the form field info:
   *   - name: The form field machine name.
   *   - title: The form field title.
   *   - description: (optional) The form field description.
   *   - required: (opional) TRUE if the field is required.
   *   - entity_type: The entity type of the target field.
   *   - type: The type of the target field.
   *   - settings: An array with settings of the target field. Empty array
   *     for no settings.
   * @param string $default_value
   *   The form field default value.
   * @param array $options
   *   (optional) A list of existent fields in the target entity. Same-type
   *   fields found in the target entity type by default.
   *
   * @return array
   *   The field reference form field definition.
   */
  protected function fieldReferenceSettingsFormField(array $field_info, $default_value, array $options = NULL) {
    // Search for same-type fields in the target entity type if no options
    // provided.
    if (!is_array($options)) {
      $options = $this->getAvailableFields($field_info['entity_type'], $field_info['type'], [], $field_info['settings']);
    }

    $result = [];
    $result[$field_info['name']] = [
      '#type' => 'select',
      '#title' => $field_info['title'],
      '#description' => $field_info['description'],
      '#default_value' => $default_value,
      '#required' => $field_info['required'],
      '#options' => $options,
      '#empty_option' => $this->t('- None -'),
    ];

    // Add an option to create a new field if current user is allowed to do so.
    if ($this->currentUser->hasPermission('administer ' . $field_info['entity_type'] . ' fields')) {
      $result[$field_info['name']]['#options']['_create'] = $this->t('- Create -');

      $states = [
        'visible' => [
          ':input[name="' . $field_info['name'] . '"]' => ['value' => '_create'],
        ],
        'required' => [
          ':input[name="' . $field_info['name'] . '"]' => ['value' => '_create'],
        ],
      ];

      $machine_maxlength = FieldStorageConfig::NAME_MAX_LENGTH - strlen($this->fieldPrefix);
      $result[$field_info['name'] . '_new'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Create a new field for @name', ['@name' => $field_info['title']]),
        '#states' => $states,
        $field_info['name'] . '_label' => [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#default_value' => $field_info['title'],
          '#size' => 15,
          '#states' => $states,
        ],
        $field_info['name'] . '_field_name' => [
          '#type' => 'machine_name',
          '#size' => 15,
          '#description' => $this->t('A unique machine-readable name containing letters, numbers, and underscores.'),
          '#required' => FALSE,
          // Calculate characters depending on the length of the field prefix
          // setting. Maximum length is 32.
          '#maxlength' => $machine_maxlength,
          '#machine_name' => [
            'source' => [
              $field_info['entity_type'] == 'transaction' ? 'transaction_fields' : 'target_fields',
              $field_info['name'] . '_new',
              $field_info['name'] . '_label',
            ],
            'exists' => '\Drupal\transaction\TransactorBase::fieldExists',
          ],
          '#states' => $states,
          '#field_prefix' => '<span dir="ltr">' . $this->fieldPrefix,
          '#field_suffix' => '</span>&lrm;',
          '#default_value' => preg_replace('@[^a-z0-9_]+@', '_', strtolower($field_info['title'])),
        ],
      ];
    }

    return $result;
  }

  /**
   * Machine name exists callback for "inline" field creation.
   *
   * @param string $field_name
   *   Field name to check.
   * @param array $form_element
   *   Form element array.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return boolean
   *   TRUE if a field with the given name exists in the target entity type.
   */
  public static function fieldExists($field_name, array $form_element, FormStateInterface $form_state) {
    // Done in form validation. Required data like the current field prefix is
    // not available in static context.
    return FALSE;
  }

  /**
   * Handles the settings form submit for this transactor plugin.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\transaction\TransactionTypeInterface $transaction_type */
    if (!$transaction_type = $form_state->getFormObject()->getEntity()) {
      return;
    }

    // Process transactor fields.
    $values = $form_state->getValues();
    foreach ($values as $key => $value) {
      if (empty($value) || !$field_info = $form_state->getTemporaryValue('field_info_' . $key)) {
        // No value or not a field.
        continue;
      }

      $field_name = $value;

      // Create new fields.
      if ($value === '_create') {
        // New field, get the name from the given machine name in form.
        // Add prefix to the given field name.
        $field_name = $this->fieldPrefix . $values[$key . '_field_name'];

        // Field storage.
        $new_field = FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => $field_info['entity_type'],
          'type' => $field_info['type'],
          'settings' => $field_info['settings'],
        ]);
        $new_field->save();
        $form_state->setValue($key, $new_field->getName());
      }

      // Add field to applicable bundles.
      $bundles = [];
      if ($field_info['entity_type'] == 'transaction') {
        // Transaction field.
        $bundles[] = $transaction_type->id();
      }
      elseif ($field_info['entity_type'] == $transaction_type->getTargetEntityTypeId()) {
        // Target entity field.
        $bundles = $transaction_type->getBundles(TRUE);
      }
      foreach ($bundles as $bundle) {
        if (FieldConfig::loadByName($field_info['entity_type'], $bundle, $field_name)) {
          // Field already exists in bundle.
          continue;
        }

        // Set the new transaction type id in reference fields to this
        // transaction type.
        $handler_settings = [];
        if (isset($field_info['handler_settings']) && isset($field_info['handler_settings']['target_bundles'])) {
          $handler_settings = $field_info['handler_settings'];
          foreach ($field_info['handler_settings']['target_bundles'] as $target_bundle_key => $target_bundle) {
            if ($target_bundle === NULL) {
              $handler_settings['target_bundles'][$target_bundle_key] = $values['id'];
            }
          }
        }

        // Attach to bundle.
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => $field_info['entity_type'],
          'bundle' => $bundle,
          'label' => $values[$key . '_label'],
          'settings' => [
            'handler' => 'default',
            'handler_settings' => $handler_settings,
          ],
          'required' => $field_info['required'],
        ])->save();

        // Field form display.
        $display_id = $field_info['entity_type'] . '.' . $bundle . '.' . 'default';
        $display_values = [
          'targetEntityType' => $field_info['entity_type'],
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ];

        // Enable new field in transaction form.
        if ($field_info['entity_type'] == 'transaction') {
          if (!$form_display = EntityFormDisplay::load($display_id)) {
            $form_display = EntityFormDisplay::create($display_values);
          }

          $form_display->setComponent(
            $field_name,
            [
              'weight' => 0,
            ]
          );
          $form_display->save();
        }

        // Enable the display of the new field.
        if (!$view_display = EntityViewDisplay::load($display_id)) {
          $view_display = EntityViewDisplay::create($display_values);
        }

        $view_display->setComponent(
          $field_name,
          [
            'weight' => 0,
          ]
        );
        $view_display->save();
      }
    }

    // Save settings.
    $settings = $transaction_type->getPluginSettings();
    foreach (['transaction_fields', 'target_fields', 'options'] as $group) {
      if (!isset($form[$group])) {
        continue;
      }
      foreach (array_keys($form[$group]) as $key) {
        if ($value = $form_state->getValue($key)) {
          $settings[$key] = $value;
        }
      }
    }

    $transaction_type->setPluginSettings($settings);
  }

  /**
   * Handles the validation for the transactor plugin settings form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validate field mapping.
    $values = $form_state->getValues();
    $field_names = [];
    foreach ($values as $key => $value) {
      if (empty($value) || !$field_info = $form_state->getTemporaryValue('field_info_' . $key)) {
        // No value or not a field.
        continue;
      }

      // Validate new fields.
      if ($value === '_create') {
        $machine_name_field = $key . '_field_name';
        $field_name = $this->fieldPrefix . $values[$machine_name_field];
        $field_id = $field_info['entity_type'] . '.' . $field_name;

        // Check for existing fields.
        if (FieldStorageConfig::load($field_id) != NULL) {
          $form_state->setErrorByName($machine_name_field, $this->t('The machine-readable name is already in use. It must be unique.'));
        }
      }
      else {
        $machine_name_field = $key;
        $field_id = $field_info['entity_type'] . '.' . $value;
      }

      // Check for duplicates in new field names.
      if (isset($field_names[$field_id])) {
        $msg = $this->t('A field name can not be used more than once in the same group.');
        $form_state->setErrorByName($field_names[$field_id], $msg);
        $form_state->setErrorByName($machine_name_field, $msg);
      }
      else {
        $field_names[$field_id] = $machine_name_field;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateTransaction(TransactionInterface $transaction) {
    return $transaction->isPending() && $transaction->getTargetEntityId();
  }

  /**
   * {@inheritdoc}
   */
  public function executeTransaction(TransactionInterface $transaction, TransactionInterface $last_executed = NULL) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransactionDescription(TransactionInterface $transaction, $langcode = NULL) {
    $t_options = $langcode ? ['langcode' => $langcode] : [];

    if ($transaction->isNew()) {
      $description = $transaction->isPending()
        ? $this->t('Unsaved transaction (pending)', [], $t_options)
        : $this->t('Unsaved transaction', [], $t_options);
    }
    else {
      $t_args = ['@number' => $transaction->id()];
      $description = $transaction->isPending()
        ? $this->t('Transaction @number (pending)', $t_args, $t_options)
        : $this->t('Transaction @number', $t_args, $t_options);
    }

    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransactionDetails(TransactionInterface $transaction, $langcode = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutionIndications(TransactionInterface $transaction, $langcode = NULL) {
    $t_options = $langcode ? ['langcode' => $langcode] : [];
    return $this->t('The target entity %label may be altered by the transaction.', ['%label' => $transaction->getTargetEntity()->label()], $t_options);
  }

}