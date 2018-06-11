<?php

/**
 * @file
 * Admin page callbacks for the mongodb_block_ui module.
 */

/**
 * Menu callback for admin/structure/mongodb_block_ui/demo.
 */
function mongodb_block_ui_admin_demo($theme = NULL) {
  drupal_add_css(drupal_get_path('module', 'mongodb_block_ui') . '/mongodb_block_ui.css', array('preprocess' => FALSE));
  return '';
}

/**
 * Menu callback for admin/structure/mongodb_block_ui.
 *
 * @param $theme
 *   The theme to display the administration page for. If not provided, defaults
 *   to the currently used theme.
 */
function mongodb_block_ui_admin_display($theme = NULL) {
  global $theme_key;

  drupal_theme_initialize();

  if (!isset($theme)) {
    // If theme is not specifically set, rehash for the current theme.
    $theme = $theme_key;
  }

  // Fetch and sort mongodb_block_uis.
  $current_blocks = _mongodb_block_rehash($theme);
  $blocks = array();
  foreach ($current_blocks as $block) {
    $module = $block['module'];
    $delta = $block['delta'];
    $blocks[$module . '_' . $delta] = $block;
  }
  $compare_theme = &drupal_static('_mongodb_block_ui_compare:theme');
  $compare_theme = $theme;
  usort($blocks, '_mongodb_block_ui_compare');

  return drupal_get_form('mongodb_block_ui_admin_display_form', $blocks, $theme);
}

/**
 * Generate main mongodb_block_uis administration form.
 */
function mongodb_block_ui_admin_display_form($form, &$form_state, $blocks, $theme) {

  drupal_add_css(drupal_get_path('module', 'mongodb_block_ui') . '/mongodb_block_ui.css', array('preprocess' => FALSE));

  $blocks_regions = system_region_list($theme, REGIONS_VISIBLE) + array(MONGODB_BLOCK_REGION_NONE => '<' . t('none') . '>');

  // Weights range from -delta to +delta, so delta should be at least half
  // of the amount of mongodb_block_uis present. This makes sure all
  // mongodb_block_uis in the same region get an unique weight.
  $weight_delta = round(count($blocks) / 2);

  // Build the form tree.
  $form['edited_theme'] = array('#type' => 'value', '#value' => $theme);
  $form['#action'] = arg(4) ? url('admin/structure/mongodb_block/list/' . $theme) : url('admin/structure/mongodb_block');
  $form['#blocks'] = $blocks;
  $form['#tree'] = TRUE;

  foreach ($blocks as $key => $block) {
    $module = $block['module'];
    $delta = $block['delta'];
    $path_prefix = "admin/structure/mongodb_block/manage/$theme/$module/$delta";
    $form[$key]['module'] = array(
      '#type' => 'value',
      '#value' => $module,
    );
    $form[$key]['delta'] = array(
      '#type' => 'value',
      '#value' => $delta,
    );
    $form[$key]['info'] = array(
      '#markup' => check_plain($block['info']),
    );
    $form[$key]['theme'] = array(
      '#type' => 'hidden',
      '#value' => $theme,
    );
    $form[$key]['weight'] = array(
      '#type' => 'weight',
      '#default_value' => $block['weight'],
      '#delta' => $weight_delta,
    );
    $form[$key]['region'] = array(
      '#type' => 'select',
      '#default_value' => $block['region'],
      '#options' => $blocks_regions,
    );
    $form[$key]['configure'] = array(
      '#type' => 'link',
      '#title' => t('configure'),
      '#href' =>  "$path_prefix",
    );
    if ($module == 'mongodb_block_ui') {
      $form[$key]['delete'] = array(
        '#type' => 'link',
        '#title' => t('delete'),
        '#href' => "$path_prefix/delete",
      );
    }
  }
  // Do not allow disabling the main system content mongodb_block_ui.
  unset($form['system_main']['region']['#options'][MONGODB_BLOCK_REGION_NONE]);

  $form['actions'] = array(
    '#tree' => FALSE,
    '#type' => 'container',
    '#attributes' => array('class' => array('form-actions')),
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save blocks'),
  );
  $form['#theme_key'] = $theme;

  return $form;
}

/**
 * Process main blocks administration form submissions.
 */
function mongodb_block_ui_admin_display_form_submit($form, &$form_state) {
  $collection = mongodb_collection('block_customized', $form['#theme_key']);
  foreach ($form_state['values'] as $key => $block) {
    // Skip the buttons by checking for array and save only blocks that are not
    // disabled.
    if (is_array($block)) {
      $find = array(
        '_id' => array(
          'module' => $block['module'],
          'delta' => $block['delta'],
        ),
      );
      $set = array(
        'weight' => (int) $block['weight'],
        'region' => $block['region'],
      );
      $collection->update($find, array('$set' => $set), array('upsert' => TRUE) + mongodb_default_write_options());
    }
  }
  drupal_set_message(t('The block settings have been updated.'));
  cache_clear_all();
}

/**
 * Helper function for sorting mongodb_block_uis on
 * admin/structure/mongodb_block_ui.
 *
 * Active mongodb_block_uis are sorted by region, then by weight.
 * Disabled mongodb_block_uis are sorted by name.
 */
function _mongodb_block_ui_compare($a, $b) {
  global $theme_key;

  // Theme should be set before calling this function, or the current theme
  // is being used.
  $theme = &drupal_static(__FUNCTION__ . ':theme');
  if (!isset($theme)) {
    $theme = $theme_key;
  }

  $regions = &drupal_static(__FUNCTION__ . ':regions');
  // We need the region list to correctly order by region.
  if (!isset($regions)) {
    $regions = array_flip(array_keys(system_region_list($theme)));
    $regions[MONGODB_BLOCK_REGION_NONE] = count($regions);
  }

  // Separate enabled from disabled.
  $status = $b['status'] - $a['status'];
  if ($status) {
    return $status;
  }
  // Sort by region (in the order defined by theme .info file).
  if ((!empty($a['region']) && !empty($b['region'])) && ($place = ($regions[$a['region']] - $regions[$b['region']]))) {
    return $place;
  }
  // Sort by weight.
  $weight = $a['weight'] - $b['weight'];
  if ($weight) {
    return $weight;
  }
  // Sort by title.
  return strcmp($a['info'], $b['info']);
}

/**
 * Menu callback; displays the mongodb_block_ui configuration form.
 */
function mongodb_block_ui_admin_configure($form, &$form_state, $theme, $module, $delta) {
  if (!$block = mongodb_collection('block', $theme)->findOne(array('_id' => array('module' => $module, 'delta' => $delta)))) {
    $block = array();
  }
  if ($module == 'mongodb_block_ui' && ($custom_block = mongodb_block_ui_load($delta))) {
    $block += $custom_block;
    $form  = mongodb_block_ui_custom_block_form($block);
  }
  $block = (object) $block;
  $form['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Block title'),
    '#maxlength' => 64,
    '#description' => t('The title of the block as shown to the user.'),
    '#default_value' => isset($block->title) ? $block->title : '',
    '#weight' => -20,
  );
  $form['module'] = array(
    '#type' => 'value',
    '#value' => $module,
  );
  $form['delta'] = array(
    '#type' => 'value',
    '#value' => $delta,
  );

  // Get the block subject for the page title.
  $info = module_invoke($module, 'block_info');
  if (isset($info[$delta])) {
    drupal_set_title(t("'%name' block", array('%name' => $info[$delta]['info'])), PASS_THROUGH);
  }

  // Module-specific block configuration.
  if ($settings = module_invoke($module, 'block_configure', $delta)) {
    foreach ($settings as $k => $v) {
      $form['settings'][$k] = $v;
    }
  }

  $title = t('Pages');
  $description = t("Specify pages by using their paths. Enter one path per line.
    The '%' character is a wildcard. Example paths are %blog for the blog page
    and %blog-wildcard for every personal blog. %front is the front page.",
    array(
      '%blog' => 'blog',
      '%blog-wildcard' => 'blog/%',
      '%front' => '<front>'));

  // Visibility settings.
  $form['visibility_title'] = array(
    '#type' => 'item',
    '#title' => t('Visibility settings'),
  );
  $form['visibility'] = array(
    '#type' => 'vertical_tabs',
    '#attached' => array(
      'js' => array(drupal_get_path('module', 'mongodb_block_ui') . '/mongodb_block_ui.js'),
    ),
  );

  // Per-path visibility.
  $form['visibility']['path_include'] = array(
    '#type' => 'fieldset',
    '#title' => t('Pages'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'visibility',
    '#weight' => 0,
  );
  $form['visibility']['path_exclude'] = array(
    '#type' => 'fieldset',
    '#title' => t('Pages exclude'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'visibility',
    '#weight' => 0,
  );

  $access = user_access('use PHP for settings');
  if (isset($block->visibility) && $block->visibility == 2 && !$access) {
    $form['visibility']['path']['visibility'] = array(
      '#type' => 'value',
      '#value' => 2,
    );
    $form['visibility']['path']['pages'] = array(
      '#type' => 'value',
      '#value' => isset($block->pages) ? $block->pages : '',
    );
  }
  else {
    if (module_exists('php') && $access) {
      $options[] = t('Pages on which this PHP code returns <code>TRUE</code> (experts only)');
      $title = t('Pages or PHP code');
      $description .= ' ' . t('If the PHP option is chosen, enter PHP code between %php. Note that executing incorrect PHP code can break your Drupal site.', array('%php' => '<?php ?>'));
    }
    $form['visibility']['path_include']['pages'] = array(
      '#type' => 'textarea',
      '#title' => t('Show on these pages'),
      '#default_value' => isset($block->pages) ? implode("\n", $block->pages) : '',
      '#description' => $description,
    );
    $form['visibility']['path_exclude']['pages_exclude'] = array(
      '#type' => 'textarea',
      '#title' => t('Do not show on these pages'),
      '#default_value' => isset($block->pages_exclude) ? implode("\n", $block->pages_exclude) : '',
      '#description' => $description,
    );
  }
  $form['visibility']['node_type'] = array(
    '#type' => 'fieldset',
    '#title' => t('Content types'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'visibility',
    '#weight' => 5,
  );
  $form['visibility']['node_type']['types'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Show block for specific content types'),
    '#default_value' =>  isset($block->node_type) ? $block->node_type : array(),
    '#options' => node_type_get_names(),
    '#description' => t('Show this block only on pages that display content of the given type(s). If you select no types, there will be no type-specific limitation.'),
  );

  $form['actions'] = array('#type' => 'container', '#attributes' => array('class' => array('form-actions')));
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save block'),
  );
  $form['#theme_key'] = $theme;

  return $form;
}

/**
 *  Form validation handler for block_admin_configure().
 *
 *  @param array $form
 *   The form.
 *  @param array &$form_state
 *   The referenced form state.
 */
function mongodb_block_ui_admin_configure_validate($form, &$form_state) {
  if ($form_state['values']['module'] == 'mongodb_block_ui') {
    $collection = mongodb_collection('block_custom');
    $find = array(
      '_id' => array('$ne' => $form_state['values']['delta']),
      'info' => $form_state['values']['info'],
    );
    $custom_block_exists = (bool) $collection->findOne($find);
    if (empty($form_state['values']['info']) || $custom_block_exists) {
      form_set_error('info', t('Ensure that each block description is unique.'));
    }
  }
}

/**
 *  Form submission handler for block_admin_configure().
 *
 *  @param array $form
 *   The form.
 *  @param array &$form_state
 *   The referenced form state.
 */
function mongodb_block_ui_admin_configure_submit($form, &$form_state) {
  // FIXME: this stores the visibility settings, the title of the block etc.
  $collection = mongodb_collection('block_customized', $form['#theme_key']);
  $id = array(
    'module' => $form_state['values']['module'],
    'delta' => $form_state['values']['delta'],
  );
  $customized = array(
    'pages' => _mongodb_block_ui_admin_pages($form_state['values']['pages'], array('%')),
    'pages_exclude' => _mongodb_block_ui_admin_pages($form_state['values']['pages_exclude'], array()),
    'title' => $form_state['values']['title'],
  );
  // This way region and weight is preserved.
  $collection->update(array('_id' => $id), array('$set' => $customized), array('upsert' => TRUE) + mongodb_default_write_options());

  // Ensure that custom blocks have their settings saved.
  // @todo: this should probably be done via hook_block_configure().
  if ($form_state['values']['module'] == 'mongodb_block_ui') {
    $block = array(
      '_id' => $form_state['values']['delta'],
      'body' => $form_state['values']['body'],
      'info' => $form_state['values']['info'],
      'title' => $form_state['values']['title'],
    );
    mongodb_collection('block_custom')->save($block, mongodb_default_write_options());
  }
  module_invoke($form_state['values']['module'], 'block_save', $form_state['values']['delta'], $form_state['values']);
  drupal_set_message(t('The block configuration has been saved.'));
  cache_clear_all();
  $form_state['redirect'] = 'admin/structure/mongodb_block/manage/' . $form_state['values']['module'] . '/' . $form_state['values']['delta'];
}

/**
 * Admin pages
 *
 * @param array $pages
 *   Admin pages.
 * @param array $default
 *   Default vale.
 */
function _mongodb_block_ui_admin_pages($pages, $default) {
  $pages = array_map('trim', explode("\n", trim($pages)));
  if (!$pages[0]) {
    $pages = $default;
  }
  return $pages;
}

/**
 * Menu callback: display the custom mongodb_block_ui addition form.
 */
function mongodb_block_ui_add_block_form($form, &$form_state, $theme) {
  $form = mongodb_block_ui_custom_block_form();

  $form['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Block title'),
    '#maxlength' => 64,
    '#description' => t('The title of the block as shown to the user.'),
    '#default_value' => '',
    '#weight' => -20,
  );
  $form['actions'] = array(
    '#tree' => FALSE,
    '#type' => 'container',
    '#attributes' => array('class' => array('form-actions')),
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save block'),
  );
  $form['#theme_key'] = $theme;

  return $form;
}

/**
 * Form validation handler for mongodb_block_ui_add_block_form
 *
 *  @param array $form
 *   The form.
 *  @param array &$form_state
 *   The referenced form state.
 */
function mongodb_block_ui_add_block_form_validate($form, &$form_state) {
  $blocks = module_invoke_all('block_info');
  foreach ($blocks as $block) {
    if ($form_state['values']['info'] == $block['info']) {
      form_set_error('info', t('Ensure that each block description is unique.'));
    }
  }
}

/**
 * Save the new custom block.
 *
 *  @param array $form
 *   The form.
 *  @param array &$form_state
 *   The referenced form state.
 */
function mongodb_block_ui_add_block_form_submit($form, &$form_state) {
  $collection = mongodb_collection('block_custom');
  $delta = mongodb_next_id('block_custom');
  $block = array(
    '_id' => (string) $delta,
    'body' => $form_state['values']['body'],
    'info' => $form_state['values']['info'],
    'title' => $form_state['values']['title'],
  );
  $collection->insert($block, mongodb_default_write_options());
  drupal_set_message(t('The block has been created.'));
  cache_clear_all();
  $theme = $form['#theme_key'];
  $form_state['redirect'] = "admin/structure/mongodb_block/manage/$theme/mongodb_block_ui/" . $delta;
}

/**
 * Menu callback; confirm deletion of custom block.
 */
function mongodb_block_ui_custom_block_delete($form, &$form_state, $theme, $module, $delta) {
  $block = mongodb_block_ui_load($delta);
  $form['info'] = array('#type' => 'hidden', '#value' => $block['info'] ? $block['info'] : $block['title']);
  $form['module'] = array('#type' => 'hidden', '#value' => $module);
  $form['delta'] = array('#type' => 'hidden', '#value' => $delta);
  $path = 'admin/structure/mongodb_block';
  if ($theme != variable_get('theme_default', 'bartik')) {
    $path .= "/$theme";
  }

  return confirm_form($form, t('Are you sure you want to delete the block %name?', array('%name' => $block['info'])), $path, '', t('Delete'), t('Cancel'));
}

/**
 * Deletion of custom mongodb_block_uis.
 */
function mongodb_block_ui_custom_block_delete_submit($form, &$form_state) {
  // Remove the custom block.
  mongodb_collection('block_custom')->remove(array('_id' => $form_state['values']['delta']), mongodb_default_write_options());
  // Also remove customization for every theme.
  $id = array(
    'module' => $form_state['values']['module'],
    'delta' => $form_state['values']['delta'],
  );
  foreach (list_themes() as $theme) {
    mongodb_collection('block_customized', $theme->name)->remove(array('_id' => $id), mongodb_default_write_options());
  }
  drupal_set_message(t('The block %name has been removed.', array('%name' => $form_state['values']['info'])));
  cache_clear_all();
  $form_state['redirect'] = 'admin/structure/mongodb_block';
  return;
}

/**
 * Process variables for mongodb_block_ui-admin-display.tpl.php.
 *
 * The $variables array contains the following arguments:
 * - $form
 *
 * @see mongodb_block_ui-admin-display.tpl.php
 * @see theme_mongodb_block_ui_admin_display()
 */
function template_preprocess_mongodb_block_ui_admin_display_form(&$variables) {

  $block_regions = system_region_list($variables['form']['edited_theme']['#value'], REGIONS_VISIBLE);
  $variables['block_regions'] = $block_regions + array(MONGODB_BLOCK_REGION_NONE => t('Disabled'));

  foreach ($block_regions as $key => $value) {
    // Initialize an empty array for the region.
    $variables['block_listing'][$key] = array();
  }

  // Initialize disabled mongodb_block_uis array.
  $variables['block_listing'][MONGODB_BLOCK_REGION_NONE] = array();

  // Set up to track previous region in loop.
  $last_region = '';
  foreach (element_children($variables['form']) as $i) {
    $block = &$variables['form'][$i];

    // Only take form elements that are mongodb_block_uis.
    if (isset($block['info'])) {
      // Fetch region for current mongodb_block_ui.
      $region = $block['region']['#default_value'];

      // Set special classes needed for table drag and drop.
      $variables['form'][$i]['region']['#attributes']['class'] = array('block-region-select', 'block-region-' . $region);
      $variables['form'][$i]['weight']['#attributes']['class'] = array('block-weight', 'block-weight-' . $region);

      $variables['block_listing'][$region][$i] = new stdClass();
      $variables['block_listing'][$region][$i]->row_class = !empty($block['#attributes']['class']) ? implode(' ', $block['#attributes']['class']) : '';
      $variables['block_listing'][$region][$i]->block_modified = !empty($block['#attributes']['class']) && in_array('block-modified', $block['#attributes']['class']);
      $variables['block_listing'][$region][$i]->block_title = drupal_render($block['info']);
      $variables['block_listing'][$region][$i]->region_select = drupal_render($block['region']) . drupal_render($block['theme']);
      $variables['block_listing'][$region][$i]->weight_select = drupal_render($block['weight']);
      $variables['block_listing'][$region][$i]->configure_link = drupal_render($block['configure']);
      $variables['block_listing'][$region][$i]->delete_link = !empty($block['delete']) ? drupal_render($block['delete']) : '';
      $variables['block_listing'][$region][$i]->printed = FALSE;

      $last_region = $region;
    }
  }

  $variables['form_submit'] = drupal_render_children($variables['form']);
}
