<?php
/**
 * The "Job schema property" setting on each ACF field.
 *
 * @package AJS
 */

namespace AJS;

defined( 'ABSPATH' ) || exit;

add_action( 'acf/render_field_general_settings', __NAMESPACE__ . '\render_field_setting' );

/**
 * Add the select to the General tab, offering only the properties that suit this field type.
 */
function render_field_setting( array $field ): void {
	$choices = array();
	foreach ( properties() as $key => $property ) {
		if ( in_array( $field['type'], $property['types'], true ) ) {
			$choices[ $key ] = $property['label'];
		}
	}
	if ( ! $choices ) {
		return;
	}

	$instructions = 'Use this field in Google job posting markup.';
	if ( isset( $choices['employment_type'] ) || isset( $choices['salary_unit'] ) ) {
		$instructions .= ' For Employment type or Salary unit, each choice value must be one of Google\'s codes, for example "FULL_TIME : Full time" or "YEAR : Per year".';
	}

	acf_render_field_setting(
		$field,
		array(
			'label'        => 'Job schema property',
			'instructions' => $instructions,
			'type'         => 'select',
			'name'         => SETTING,
			'choices'      => array( '' => 'Not used' ) + $choices,
		)
	);
}
