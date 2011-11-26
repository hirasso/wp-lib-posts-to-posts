<?php

class P2P_Query {

	function handle_qv( &$q ) {
		// Handle shortcut args
		$qv_map = array(
			'connected' => 'any',
			'connected_to' => 'to',
			'connected_from' => 'from',
		);

		foreach ( $qv_map as $key => $direction ) {
			if ( !empty( $q[ $key ] ) ) {
				$q['connected_items'] = _p2p_pluck( $q, $key );
				$q['connected_direction'] = $direction;
			}
		}

		// Handle connected_type arg
		if ( !isset( $q['connected_items'] ) || !isset( $q['connected_type'] ) )
			return;

		$ctype = p2p_type( _p2p_pluck( $q, 'connected_type' ) );

		if ( !$ctype )
			return false;

		if ( isset( $q['connected_direction'] ) )
			$directed = $ctype->set_direction( _p2p_pluck( $q, 'connected_direction' ) );
		else {
			$directed = $ctype->find_direction( $q['connected_items'] );
		}

		if ( !$directed )
			return false;

		$q = $directed->get_connected_args( $q );

		return true;
	}

	function alter_clauses( $clauses, $q ) {
		global $wpdb;

		$clauses['fields'] .= ", $wpdb->p2p.*";

		$clauses['join'] .= " INNER JOIN $wpdb->p2p";

		// Handle main query
		if ( $q['p2p_type'] )
			$clauses['where'] .= $wpdb->prepare( " AND $wpdb->p2p.p2p_type = %s", $q['p2p_type'] );

		if ( 'any' == $q['items'] ) {
			$search = false;
		} else {
			$search = implode( ',', array_map( 'absint', (array) $q['items'] ) );
		}

		$fields = array( 'p2p_from', 'p2p_to' );

		switch ( $q['direction'] ) {

		case 'from':
			$fields = array_reverse( $fields );
			// fallthrough
		case 'to':
			list( $from, $to ) = $fields;

			$clauses['where'] .= " AND $wpdb->posts.ID = $wpdb->p2p.$from";
			if ( $search ) {
				$clauses['where'] .= " AND $wpdb->p2p.$to IN ($search)";
			}

			break;
		default:
			if ( $search ) {
				$clauses['where'] .= " AND (
					($wpdb->posts.ID = $wpdb->p2p.p2p_to AND $wpdb->p2p.p2p_from IN ($search)) OR
					($wpdb->posts.ID = $wpdb->p2p.p2p_from AND $wpdb->p2p.p2p_to IN ($search))
				)";
			} else {
				$clauses['where'] .= " AND ($wpdb->posts.ID = $wpdb->p2p.p2p_to OR $wpdb->posts.ID = $wpdb->p2p.p2p_from)";
			}
		}

		// Handle custom fields
		if ( !empty( $q['meta'] ) ) {
			$meta_clauses = _p2p_meta_sql_helper( $q['meta'] );
			foreach ( $meta_clauses as $key => $value ) {
				$clauses[ $key ] .= $value;
			}
		}

		// Handle ordering
		if ( $q['orderby'] ) {
			$clauses['join'] .= $wpdb->prepare( "
				LEFT JOIN $wpdb->p2pmeta AS p2pm_order ON (
					$wpdb->p2p.p2p_id = p2pm_order.p2p_id AND p2pm_order.meta_key = %s
				)
			", $q['orderby'] );

			$order = ( 'DESC' == strtoupper( $q['order'] ) ) ? 'DESC' : 'ASC';

			$field = 'meta_value';

			if ( $q['order_num'] )
				$field .= '+0';

			$clauses['orderby'] = "p2pm_order.$field $order";
		}

		return $clauses;
	}
}

