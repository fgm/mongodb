<?php

/**
 * @file
 * Hooks provided by the Block module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Define all mongodb_block_uis provided by the module.
 *
 * Any module can export a mongodb_block_ui (or mongodb_block_uis) to be
 * displayed by defining the _mongodb_block_ui hook. This hook is called by
 * theme.inc to display a mongodb_block_ui, and also by
 * mongodb_block_ui.module to procure the list of available mongodb_block_uis.
 *
 * @return array
 *   An associative array whose keys define the $delta
 *   for each mongodb_block_ui and whose values contain the mongodb_block_ui
 *   descriptions. Each mongodb_block_ui description is itself an associative
 *   array, with the following key-value pairs:
 *   - 'info': (required) The human-readable name of the mongodb_block_ui.
 *   - 'cache': A bitmask of flags describing how the mongodb_block_ui should
 *     behave with respect to mongodb_block_ui caching. The following shortcut
 *     bitmasks are provided as constants in common.inc:
 *     - DRUPAL_CACHE_PER_ROLE (default): The mongodb_block_ui can change
 *       depending on the roles the user viewing the page belongs to.
 *     - DRUPAL_CACHE_PER_USER: The mongodb_block_ui can change depending on
 *       the user viewing the page. This setting can be resource-consuming for
 *       sites with large number of users, and should only be used when
 *       DRUPAL_CACHE_PER_ROLE is not sufficient.
 *     - DRUPAL_CACHE_PER_PAGE: The mongodb_block_ui can change depending on the
 *       page being viewed.
 *     - DRUPAL_CACHE_GLOBAL: The mongodb_block_ui is the same for every user on
 *       every page where it is visible.
 *     - DRUPAL_NO_CACHE: The mongodb_block_ui should not get cached.
 *   - 'weight', 'status', 'region', 'visibility', 'pages':
 *     You can give your mongodb_block_uis an explicit weight, enable them,
 *     limit them to given pages, etc. These settings will be registered when
 *     the mongodb_block_ui is first loaded at admin/mongodb_block_ui, and from
 *     there can be changed manually via mongodb_block_ui administration.
 *     Note that if you set a region that isn't available in a given theme, the
 *     mongodb_block_ui will be registered instead to that theme's default
 *     region (the first item in the _regions array).
 *
 * After completing your mongodb_block_uis, do not forget to enable them in the
 * mongodb_block_ui admin menu.
 *
 * For a detailed usage example, see mongodb_block_ui_example.module.
 */
function hook_mongodb_block_ui_info() {
  $mongodb_block_uis['exciting'] = array(
    'info' => t('An exciting mongodb_block_ui provided by Mymodule.'),
    'weight' => 0,
    'status' => 1,
    'region' => 'sidebar_first',
    // DRUPAL_CACHE_PER_ROLE will be assumed for mongodb_block_ui 0.
  );

  $mongodb_block_uis['amazing'] = array(
    'info' => t('An amazing mongodb_block_ui provided by Mymodule.'),
    'cache' => DRUPAL_CACHE_PER_ROLE | DRUPAL_CACHE_PER_PAGE,
  );

  return $mongodb_block_uis;
}

/**
 * Configuration form for the mongodb_block_ui.
 *
 * @param string $delta
 *   Which mongodb_block_ui to return. This is a descriptive string used to
 *   identify mongodb_block_uis within each module and also within the theme
 *   system.
 *   The $delta for each mongodb_block_ui is defined within the array that your
 *   module returns when the hook_mongodb_block_ui_info() implementation is
 *   called.
 *
 * @return array
 *   Optionally return the configuration form.
 *
 * For a detailed usage example, see mongodb_block_ui_example.module.
 */
function hook_mongodb_block_ui_configure($delta = '') {
  if ($delta == 'exciting') {
    $form['items'] = array(
      '#type' => 'select',
      '#title' => t('Number of items'),
      '#default_value' => variable_get('mymodule_mongodb_block_ui_items', 0),
      '#options' => array('1', '2', '3'),
    );
    return $form;
  }
}

/**
 * Save the configuration options.
 *
 * @param string $delta
 *   Which mongodb_block_ui to save the settings for. This is a descriptive
 *   string used to identify mongodb_block_uis within each module and also
 *   within the theme system.
 *   The $delta for each mongodb_block_ui is defined within the array that your
 *   module returns when the hook_mongodb_block_ui_info() implementation is
 *   called.
 * @param string $edit
 *   The submitted form data from the configuration form.
 *
 * For a detailed usage example, see mongodb_block_ui_example.module.
 */
function hook_mongodb_block_ui_save($delta = '', $edit = array()) {
  if ($delta == 'exciting') {
    variable_set('mymodule_mongodb_block_ui_items', $edit['items']);
  }
}

/**
 * Process the mongodb_block_ui when enabled in a region in order to view its
 * contents.
 *
 * @param string $delta
 *   Which mongodb_block_ui to return. This is a descriptive string used to
 *   identify mongodb_block_uis within each module and also within the theme
 *   system.
 *   The $delta for each mongodb_block_ui is defined within the array that your
 *   module returns when the hook_mongodb_block_ui_info() implementation is
 *   called.
 *
 * @return array
 *   An array which must define a 'subject' element and a 'content' element
 *   defining the mongodb_block_ui indexed by $delta.
 *
 * The functions mymodule_display_mongodb_block_ui_exciting and _amazing, as
 * used in the example, should of course be defined somewhere in your module
 * and return the content you want to display to your users. If the "content"
 * element is empty, no mongodb_block_ui will be displayed even if "subject"
 * is present.
 *
 * For a detailed usage example, see mongodb_block_ui_example.module.
 */
function hook_mongodb_block_ui_view($delta = '') {
  switch ($delta) {
    case 'exciting':
      $mongodb_block_ui = array(
        'subject' => t('Default title of the exciting mongodb_block_ui'),
        'content' => mymodule_display_mongodb_block_ui_exciting(),
      );
      break;

    case 'amazing':
      $mongodb_block_ui = array(
        'subject' => t('Default title of the amazing mongodb_block_ui'),
        'content' => mymodule_display_mongodb_block_ui_amazing(),
      );
      break;
  }
  return $mongodb_block_ui;
}

/**
 * Perform alterations to the content of a mongodb_block_ui.
 *
 * This hook allows you to modify any data returned by
 * hook_mongodb_block_ui_view().
 *
 * Note that instead of hook_mongodb_block_ui_view_alter(), which is called for
 * all mongodb_block_uis, you can also use
 * hook_mongodb_block_ui_view_MODULE_DELTA_alter() to alter a specific
 * mongodb_block_ui.
 *
 * @param array $data
 *   An array of data, as returned from the hook_mongodb_block_ui_view()
 *   implementation of the module that defined the mongodb_block_ui:
 *   - subject: The localized title of the mongodb_block_ui.
 *   - content: Either a string or a renderable array representing the content
 *     of the mongodb_block_ui. You should check that the content is an array
 *     before trying to modify parts of the renderable structure.
 * @param object $mongodb_block_ui
 *   The mongodb_block_ui object, as loaded from the database, having the main
 *   properties:
 *   - module: The name of the module that defined the mongodb_block_ui.
 *   - delta: The identifier for the mongodb_block_ui within that module, as
 *     defined within hook_mongodb_block_ui_info().
 *
 * @see hook_mongodb_block_ui_view_alter()
 * @see hook_mongodb_block_ui_view()
 */
function hook_mongodb_block_ui_view_alter(&$data, $mongodb_block_ui) {
  // Remove the contextual links on all mongodb_block_uis that provide them.
  if (is_array($data['content']) && isset($data['content']['#contextual_links'])) {
    unset($data['content']['#contextual_links']);
  }
  // Add a theme wrapper function defined by the current module to all
  // mongodb_block_uis provided by the "somemodule" module.
  if (is_array($data['content']) && $mongodb_block_ui->module == 'somemodule') {
    $data['content']['#theme_wrappers'][] = 'mymodule_special_mongodb_block_ui';
  }
}

/**
 * Perform alterations to a specific mongodb_block_ui.
 *
 * Modules can implement hook_mongodb_block_ui_view_MODULE_DELTA_alter() to
 * modify a specific mongodb_block_ui, rather than implementing
 * hook_mongodb_block_ui_view_alter().
 *
 * Note that this hook fires before hook_mongodb_block_ui_view_alter().
 * Therefore, all implementations of
 * hook_mongodb_block_ui_view_MODULE_DELTA_alter() will run before all
 * implementations of hook_mongodb_block_ui_view_alter(), regardless of the
 * module order.
 *
 * @param array $data
 *   An array of data, as returned from the hook_mongodb_block_ui_view()
 *   implementation of the module that defined the mongodb_block_ui:
 *   - subject: The localized title of the mongodb_block_ui.
 *   - content: Either a string or a renderable array representing the content
 *     of the mongodb_block_ui. You should check that the content is an array
 *     before trying to modify parts of the renderable structure.
 * @param object $mongodb_block_ui
 *   The mongodb_block_ui object, as loaded from the database, having the main
 *   properties:
 *   - module: The name of the module that defined the mongodb_block_ui.
 *   - delta: The identifier for the mongodb_block_ui within that module, as
 *     defined within hook_mongodb_block_ui_info().
 *
 * @see hook_mongodb_block_ui_view_alter()
 * @see hook_mongodb_block_ui_view()
 */
function hook_mongodb_block_ui_view_MODULE_DELTA_alter(&$data, $mongodb_block_ui) {
  // This code will only run for a specific mongodb_block_ui. For example, if
  // MODULE_DELTA in the function definition above is set to
  // "mymodule_somedelta", the code will only run on the "somedelta"
  // mongodb_block_ui provided by the "mymodule" module.
  // Change the title of the "somedelta" mongodb_block_ui provided by the
  // "mymodule" module.
  $data['subject'] = t('New title of the mongodb_block_ui');
}

/**
 * Act on mongodb_block_uis prior to rendering.
 *
 * This hook allows you to add, remove or modify mongodb_block_uis in the
 * mongodb_block_ui list. The mongodb_block_ui list contains the
 * mongodb_block_ui definitions not the rendered mongodb_block_uis. The
 * mongodb_block_uis are rendered after the modules have had a chance to
 * manipulate the mongodb_block_ui list.
 * Alternatively you can set $mongodb_block_ui->content here, which will
 * override the content of the mongodb_block_ui and prevent
 * hook_mongodb_block_ui_view() from running.
 *
 * @param array $mongodb_block_uis
 *   An array of $mongodb_block_uis, keyed by $bid
 *
 * This example shows how to achieve language specific visibility setting for
 * mongodb_block_uis.
 */
function hook_mongodb_block_ui_info_alter(&$mongodb_block_uis) {
  global $language, $theme_key;

  $result = db_query('SELECT module, delta, language FROM {my_table}');
  $mongodb_block_ui_languages = array();
  foreach ($result as $record) {
    $mongodb_block_ui_languages[$record->module][$record->delta][$record->language] = TRUE;
  }

  foreach ($mongodb_block_uis as $key => $mongodb_block_ui) {
    // Any module using this alter should inspect the data before changing it,
    // to ensure it is what they expect.
    if (!isset($mongodb_block_ui->theme) || !isset($mongodb_block_ui->status) || $mongodb_block_ui->theme != $theme_key || $mongodb_block_ui->status != 1) {
      // This mongodb_block_ui was added by a contrib module, leave it in the
      // list.
      continue;
    }

    if (!isset($mongodb_block_ui_languages[$mongodb_block_ui->module][$mongodb_block_ui->delta])) {
      // No language setting for this mongodb_block_ui, leave it in the list.
      continue;
    }

    if (!isset($mongodb_block_ui_languages[$mongodb_block_ui->module][$mongodb_block_ui->delta][$language->language])) {
      // This mongodb_block_ui should not be displayed with the active language,
      // remove from the list.
      unset($mongodb_block_uis[$key]);
    }
  }
}

/**
 * @} End of "addtogroup hooks".
 */
