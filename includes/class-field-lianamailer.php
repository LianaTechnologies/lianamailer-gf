<?php

namespace GF_LianaMailer;

if ( !class_exists( '\GF_Field' ) ) {
	return;
}

class GF_Field_LianaMailer extends \GF_Field {

	public $type = 'lianamailer';

	/**
	 * Returns the field markup; including field label, description, validation, and the form editor admin buttons.
	 *
	 * The {FIELD} placeholder will be replaced in GFFormDisplay::get_field_content with the markup returned by GF_Field::get_field_input().
	 *
	 * @param string|array $value                The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param bool         $force_frontend_label Should the frontend label be displayed in the admin even if an admin label is configured.
	 * @param array        $form                 The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_field_content( $value, $force_frontend_label, $form ) {
		$validation_message = ( $this->failed_validation && ! empty( $this->validation_message ) ) ? sprintf( "<div class='gfield_description validation_message'>%s</div>", $this->validation_message ) : '';
		$is_form_editor     = $this->is_form_editor();
		$is_entry_detail    = $this->is_entry_detail();
		$is_admin           = $is_form_editor || $is_entry_detail;

		$admin_buttons = $this->get_admin_buttons();

		$description = $this->get_description( $this->description, 'gfield_description' );
		if ( $this->is_description_above( $form ) ) {
			$clear         = $is_admin ? "<div class='gf_clear'></div>" : '';
			$field_content = sprintf( "%s%s{FIELD}%s$clear", $admin_buttons, $description, $validation_message );
		} else {
			$field_content = sprintf( '%s{FIELD}%s%s', $admin_buttons, $description, $validation_message );
		}


		return $field_content;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$id            = $this->id;
		$field_id      = $is_entry_detail || $is_form_editor || 0 === (int) $form_id ? "input_$id" : 'input_' . $form_id . "_$id";
		$disabled_text = $is_form_editor ? 'disabled="disabled"' : '';
		$is_admin  = $is_form_editor || $is_entry_detail;
		$isPluginEnabled = (isset($form['lianamailer']['lianamailer_enabled']) && $form['lianamailer']['lianamailer_enabled']);

		$options = [
			'label' => $this->get_field_label( false, $value ),
			'consent' => '',
			'precheck' => false,
			'enabled' => true,
			'is_connection_valid' => true
		];
		$options = apply_filters('gform_lianamailer_get_integration_options', $options, $form_id);

		return sprintf( "<div ".((!$isPluginEnabled || !$options['consent']) && !$is_admin ? ' style="display:none;"' : '')." class='ginput_container ginput_container_checkbox'>%s</div>", $this->get_checkbox_choices( $value, $disabled_text, $form_id, $options) );
	}

	public function get_checkbox_choices( $value, $disabled_text, $form_id = 0, $options = []) {

		$notice_msgs = [];
		$choices         = '';
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin  = $is_form_editor || $is_entry_detail;

		$is_plugin_enabled		= $options['enabled'];
		$consent_label			= $options['label'];
		$consent				= $options['consent'];
		$precheck				= $options['precheck'];
		$is_connection_valid	= !empty($options['is_connection_valid']);

		if(!$is_connection_valid) {
			$notice_msgs[] = 'REST API connection failed. Check <a href="'.admin_url('admin.php?page=lianamailergravityforms').'" target="_blank">settings</a>';
		}

		if($is_connection_valid) {
			if(empty($consent)) {
				$notice_msgs[] = ' No consent found';
			}
			if(empty($is_plugin_enabled)) {
				$notice_msgs[] = 'Plugin not enabled';
			}
		}

		$preCheckOnPublicForm = false;
		// If consents not found or plugin is disabled do not print field into public form
		if(!$is_admin && !empty($notice_msgs) || $precheck) {
			$preCheckOnPublicForm = true;
		}

		// generate html
		$choice = array(
			'text'       => $consent_label,
			'value'      => '1',
			'isSelected' => $precheck,
		);

		$input_id = $this->id;
		if ( $is_entry_detail || $is_form_editor || 0 === (int) $form_id ) {
			$id = $this->id;
		} else {
			$id = $form_id . '_' . $this->id;
		}

		if ( ! isset( $_GET['gf_token'] ) && empty( $_POST ) && rgar( $choice, 'isSelected' ) ) {
			$checked = "checked='checked'";
		} elseif ( is_array( $value ) && \RGFormsModel::choice_value_match( $this, $choice, rgget( $input_id, $value ) ) ) {
			$checked = "checked='checked'";
		} elseif ( ! is_array( $value ) && \RGFormsModel::choice_value_match( $this, $choice, $value ) ) {
			$checked = "checked='checked'";
		} else {
			$checked = '';
		}

		if($preCheckOnPublicForm) {
			$checked = "checked='checked'";
		}

		$tabindex      			= $this->get_tabindex();
		$choice_value  			= $choice['value'];
		$choice_value  			= esc_attr( $choice_value );
		// Force to be required
		$required_attribute		= $this->isRequired ? 'aria-required="true"' : '';

		$choice_markup = "<input name='input_{$input_id}' type='checkbox' value='{$choice_value}' {$checked} id='choice_{$id}' {$tabindex} {$disabled_text} {$required_attribute} />
                        <label for='choice_{$id}' id='label_{$id}'>{$choice['text']}</label>";

		if($is_admin && !empty($notice_msgs)) {
			$choice_markup .= '<div class="notices">';
				$choice_markup .= '<ul>';
				foreach($notice_msgs as $msg) {
					$choice_markup .= '<li>'.$msg.'</li>';
				}
				$choice_markup .= '</ul>';
			$choice_markup .= '</div>';
		}

		$choices .= gf_apply_filters(
			array(
				'gform_field_choice_markup_pre_render',
				$this->formId,
				$this->id,
			),
			$choice_markup,
			$choice,
			$this,
			$value
		);

		return gf_apply_filters( array( 'gform_field_choices', $this->formId, $this->id ), $choices, $this );
	}

	public function get_form_editor_field_title() {
		return esc_attr__( 'LianaMailer for WordPress', 'lianamailer-for-wp' );
	}


	public function get_consent_description() {
		$options = apply_filters('gform_lianamailer_get_integration_options', [], null);
		return ($options['label'] ?? '');
	}

	// Set custom field label by selected consent description as default
	public function get_form_editor_inline_script_on_page_render() {
		// set the default field label for the field
		$script = sprintf( "function SetDefaultValues_%s(field) {field.label = '%s'; field.isRequired = true; }", $this->type, $this->get_consent_description() ) . PHP_EOL;

		return $script;
	}

	/**
	 * Adds the field button to the specified group.
	 *
	 * @param array $field_groups
	 *
	 * @return array
	 */
	public function add_button( $field_groups ) {

		// Check a button for the type hasn't already been added
		foreach ( $field_groups as &$group ) {
			foreach ( $group['fields'] as &$button ) {
				if ( isset( $button['data-type'] ) && $button['data-type'] == $this->type ) {
					$button['data-icon'] = $this->get_form_editor_field_icon();
					$button['data-description'] = $this->get_form_editor_field_description();
					return $field_groups;
				}
			}
		}

		$form_id = absint( rgget( 'id' ) );
		$form = \GFAPI::get_form( $form_id );
		$fields = $form['fields'];

		$alreadyFound = false;
		foreach($fields as $field_instance) {
			if($field_instance instanceof GF_Field_LianaMailer) {
				$alreadyFound = true;
			}
		}


		$new_button = $this->get_form_editor_button();
		if ( ! empty( $new_button ) ) {
			foreach ( $field_groups as &$group ) {
				if ( $group['name'] == $new_button['group'] ) {
					$group['fields'][] = array(
						'value'      =>  $new_button['text'],
						'data-icon'       =>  empty($new_button['icon']) ? $this->get_form_editor_field_icon() : $new_button['icon'],
						'data-description' => empty($new_button['description']) ? $this->get_form_editor_field_description() : $new_button['description'],
						'data-type'  => $this->type,
						'class'		=> ($alreadyFound ? 'lianamailer disabled' : 'lianamailer'),
						'onclick'    => "StartAddField_Lianamailer('{$this->type}', this);",
						'onkeypress' => "StartAddField_Lianamailer('{$this->type}', this);",
					);
					break;
				}
			}
		}

		return $field_groups;
	}

	/**
	 * Returns the field admin buttons for display in the form editor.
	 *
	 * @return string
	 */
	public function get_admin_buttons() {

		if ( ! $this->is_form_editor() ) {
			return '';
		}

		$delete_aria_action = __( 'delete this field', 'gravityforms' );
		$delete_field_link = "
			<button
				id='gfield_delete_{$this->id}'
				class='gfield-field-action gfield-delete'
				onclick='DeleteField_Lianamailer(this);'
				onkeypress='DeleteField_Lianamailer(this); return false;'
				aria-label='" . esc_html( $this->get_field_action_aria_label( $delete_aria_action ) ) . "'
			>
				<i class='gform-icon gform-icon--trash'></i>
				<span class='gfield-field-action__description' aria-hidden='true'>" . esc_html__( 'Delete', 'gravityforms' ) . "</span>
			</button>";

		/**
		 * This filter allows for modification of a form field delete link. This will change the link for all fields
		 *
		 * @param string $delete_field_link The Delete Field Link (in HTML)
		 */
		$delete_field_link = apply_filters( 'gform_delete_field_link', $delete_field_link );

		$edit_aria_action = __( 'jump to this field\'s settings', 'gravityforms' );
		$edit_field_link = "
			<button
				id='gfield_edit_{$this->id}'
				class='gfield-field-action gfield-edit'
				onclick='EditField(this);'
				onkeypress='EditField(this); return false;'
				aria-label='" . esc_html( $this->get_field_action_aria_label( $edit_aria_action ) ) . "'
			>
				<i class='gform-icon gform-icon--settings'></i>
				<span class='gfield-field-action__description' aria-hidden='true'>" . esc_html__( 'Settings', 'gravityforms' ) . "</span>
			</button>";

		/**
		 * This filter allows for modification of a form field edit link. This will change the link for all fields
		 *
		 * @param string $edit_field_link The Edit Field Link (in HTML)
		 */
		$edit_field_link = apply_filters( 'gform_edit_field_link', $edit_field_link );

		$drag_handle = '
			<span class="gfield-field-action gfield-drag">
				<i class="gform-icon gform-icon--drag-indicator"></i>
				<span class="gfield-field-action__description">' . esc_html__( 'Move', 'gravityforms' ) . '</span>
			</span>';

		$field_icon = '<span class="gfield-field-action gfield-icon">' . \GFCommon::get_icon_markup( array( 'icon' => $this->get_form_editor_field_icon() ) ) . '</span>';

		$admin_buttons = "
			<div class='gfield-admin-icons'>
				{$drag_handle}
				{$edit_field_link}
				{$delete_field_link}
				{$field_icon}
			</div>";

		return $admin_buttons;
	}

	/**
	 * Returns the field's form editor icon.
	 *
	 * This could be an icon url or a gform-icon class.
	 *
	 * @since 2.5
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return 'gform-icon--lianamailer';
	}

	public function get_form_editor_field_settings() {
		return array(
			//'label_setting',
			//'description_setting',
			'css_class_setting',
			// Enable / disable plugin on the form
			'lianamailer_activate_setting',
			// LM Properties (select)
			'lianamailer_properties_setting',
			'rules_setting'
		);
	}
}
