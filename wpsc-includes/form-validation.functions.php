<?php

function wpsc_validate_form( $form_args, $validated_array = null ) {
	if ( ! is_array( $validated_array ) )
		$validated_array = $_POST;

	$error = new WP_Error();
	$a =& $error;
	if ( ! isset( $form_args['fields'] ) )
		return;

	$form = $form_args['fields'];
	foreach ( $form as $props ) {
		if ( empty( $props['rules'] ) )
			continue;

		$props = _wpsc_populate_field_default_args( $props );
		$field = $props['name'];
		$rules = $props['rules'];
		if ( is_string( $rules ) )
			$rules = explode( '|', $rules );

		$value =& $validated_array[$field];

		foreach ( $rules as $rule ) {
			if ( function_exists( $rule ) ) {
				$value = call_user_func( $rule, $value );
				continue;
			}

			if ( preg_match( '/([^\[]+)\[([^\]]+)\]/', $rule, $matches ) ) {
				$rule = $matches[1];
				$matched_field = $matches[2];
				$matched_value = isset( $validated_array[$matched_field] ) ? $validated_array[$matched_field] : null;
				$matched_props = isset( $form[$matched_field] ) ? $form[$matched_field] : array();
				$error = apply_filters( "wpsc_validation_rule_{$rule}", $error, $value, $field, $props, $matched_field, $matched_value, $matched_props );
			} else {
				$error = apply_filters( "wpsc_validation_rule_{$rule}", $error, $value, $field, $props );
			}

			if ( count( $error->get_error_codes() ) )
				break;
		}
	}

	if ( count( $error->get_error_messages() ) )
		return $error;

	return true;
}

function wpsc_validation_rule_required( $error, $value, $field, $props ) {
	if ( $value === '' ) {
		$error_message = apply_filters( 'wpsc_validation_rule_required_message', __( 'The %s field is empty.', 'wpsc' ), $value, $field, $props );
		$title = isset( $prop['title_validation'] ) ? $prop['title_validation'] : $field;
		$error->add( $field, sprintf( $error_message, $props['title_validation'] ), array( 'value' => $value, 'props' => $props ) );
	}

	return $error;
}
add_filter( 'wpsc_validation_rule_required', 'wpsc_validation_rule_required', 10, 4 );

function wpsc_validation_rule_email( $error, $value, $field, $props ) {
	$field_title = isset( $props['title_validation'] ) ? $props['title_validation'] : $field;

	if ( empty( $value ) )
		return $error;

	if ( ! is_email( $value ) ) {
		$message = apply_filters( 'wpsc_validation_rule_invalid_email_message', __( 'The %s is not valid.', 'wpsc' ) );
		$error->add( $field, sprintf( $message, $field_title ), array( 'value' => $value, 'props' => $props ) );
	}

	return $error;
}
add_filter( 'wpsc_validation_rule_email', 'wpsc_validation_rule_email', 10, 4 );

function wpsc_validation_rule_valid_username_or_email( $error, $value, $field, $props ) {
	if ( strpos( $value, '@' ) ) {
		$user = get_user_by( 'email', $value );
		if ( empty( $user ) ) {
			$message = apply_filters( 'wpsc_validation_rule_account_email_not_found_message', __( 'There is no user registered with that email address.', 'wpsc' ), $value, $field, $props );
			$error->add( $field, $message, array( 'value' => $value, 'props' => $props) );
		}
	} else {
		$user = get_user_by( 'login', $value );
		if ( empty( $user ) ) {
			$message = apply_filters( 'wpsc_validation_rule_username_not_found_message', __( 'There is no user registered with that username.', 'wpsc' ), $value, $field, $props );
			$error->add( $field, $message, array( 'value' => $value, 'props' => $props ) );
		}
	}

	return $error;
}
add_filter( 'wpsc_validation_rule_valid_username_or_email', 'wpsc_validation_rule_valid_username_or_email', 10, 4 );

function wpsc_validation_rule_matches( $error, $value, $field, $props, $matched_field, $matched_value, $matched_props ) {
	if ( is_null( $matched_value ) || $value != $matched_value ) {
		$message = apply_filters( 'wpsc_validation_rule_fields_dont_match_message', __( 'The %s and %s fields do not match.', 'wpsc' ), $value, $field, $props, $matched_field, $matched_value, $matched_props );
		$title = isset( $props['title_validation'] ) ? $props['title_validation'] : $field;
		$matched_title = isset( $matched_props['title_validation'] ) ? $matched_props['title_validation'] : $field;
		$error->add( $field, sprintf( $message, $title, $matched_title ), array( 'value' => $value, 'props' => $props ) );
	}

	return $error;
}
add_filter( 'wpsc_validation_rule_matches', 'wpsc_validation_rule_matches', 10, 7 );

function wpsc_validation_rule_username( $error, $value, $field, $props ) {
	$field_title = isset( $props['title_validation'] ) ? $props['title_validation'] : $field;

	if ( ! validate_username( $value ) ) {
		$message = apply_filters( 'wpsc_validation_rule_invalid_username_message', __( 'This %s contains invalid characters. Username may contain letters (a-z), numbers (0-9), dashes (-), underscores (_) and periods (.).', 'wpsc' ) );
		$error->add( $field, sprintf( $message, $field_title ), array( 'value' => $value, 'props' => $props ) );
	} elseif ( username_exists( $value ) ) {
		$message = apply_filters( 'wpsc_validation_rule_username_not_available_message', _x( 'This %s is already used by another account. Please choose another one.', 'username not available', 'wpsc' ) );
		$error->add( $field, sprintf( $message, $field_title ), array( 'value' => $value, 'props' => $props ) );
	}

	return $error;
}
add_filter( 'wpsc_validation_rule_username', 'wpsc_validation_rule_username', 10, 4 );

function wpsc_validation_rule_account_email( $error, $value, $field, $props ) {
	$field_title = isset( $props['title_validation'] ) ? $props['title_validation'] : $field;

	if ( ! is_email( $value ) ) {
		$message = apply_filters( 'wpsc_validation_rule_invalid_account_email_message', __( 'The %s is not valid.', 'wpsc' ) );
		$error->add( $field, sprintf( $message, $field_title ), array( 'value' => $value, 'props' => $props ) );
	} elseif ( email_exists( $value ) ) {
		$message = apply_filters( 'wpsc_validation_rule_account_email_not_available_message', _x( 'This %s is already used by another account. Please choose another one.', 'email not available', 'wpsc' ) );
		$error->add( $field, sprintf( $message, $field_title ), array( 'value' => $value, 'props' => $props ) );
	}

	return $error;
}
add_filter( 'wpsc_validation_rule_account_email', 'wpsc_validation_rule_account_email', 10, 4 );