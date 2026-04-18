<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table extension for the FitnessPro orders screen.
 * Queries wp_fitness_user_plans joined with wp_users for customer + coach names.
 * Supports status-filter tabs and column-click sorting.
 */
class FitnessPro_Orders_Table extends WP_List_Table {

	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct( array(
			'singular' => 'fp-order',
			'plural'   => 'fp-orders',
			'ajax'     => false,
		) );
	}

	// ─── Column Definitions ───────────────────────────────────────────────────

	public function get_columns(): array {
		return array(
			'id'          => __( 'شناسه', 'fitnesspro' ),
			'user'        => __( 'مشتری', 'fitnesspro' ),
			'coach'       => __( 'مربی', 'fitnesspro' ),
			'type'        => __( 'نوع برنامه', 'fitnesspro' ),
			'status'      => __( 'وضعیت', 'fitnesspro' ),
			'expiry_date' => __( 'تاریخ انقضا', 'fitnesspro' ),
			'created_at'  => __( 'تاریخ ثبت', 'fitnesspro' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'id'          => array( 'id', false ),
			'status'      => array( 'status', false ),
			'expiry_date' => array( 'expiry_date', false ),
			'created_at'  => array( 'created_at', true ),
		);
	}

	protected function get_primary_column_name(): string {
		return 'user';
	}

	// ─── Status Filter Tabs ───────────────────────────────────────────────────

	protected function get_views(): array {
		global $wpdb;

		$raw = $wpdb->get_results(
			"SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}fitness_user_plans GROUP BY status",
			ARRAY_A
		);

		$counts = array();
		foreach ( (array) $raw as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}
		$total = array_sum( $counts );

		// phpcs:ignore WordPress.Security.NonceVerification
		$current = isset( $_GET['status_filter'] ) ? sanitize_key( $_GET['status_filter'] ) : '';

		$base = admin_url( 'admin.php?page=fitnesspro-orders' );

		$views = array();
		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $base ),
			'' === $current ? ' class="current"' : '',
			esc_html__( 'همه', 'fitnesspro' ),
			$total
		);

		$statuses = array(
			'active'  => __( 'فعال', 'fitnesspro' ),
			'pending' => __( 'در انتظار', 'fitnesspro' ),
			'expired' => __( 'منقضی', 'fitnesspro' ),
		);

		foreach ( $statuses as $slug => $label ) {
			$views[ $slug ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status_filter', $slug, $base ) ),
				$current === $slug ? ' class="current"' : '',
				esc_html( $label ),
				$counts[ $slug ] ?? 0
			);
		}

		return $views;
	}

	// ─── Data Query ───────────────────────────────────────────────────────────

	public function prepare_items() {
		global $wpdb;

		// Sorting
		$allowed_orderby = array( 'id', 'status', 'expiry_date', 'created_at' );
		// phpcs:ignore WordPress.Security.NonceVerification
		$orderby = ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) )
			// phpcs:ignore WordPress.Security.NonceVerification
			? sanitize_key( $_GET['orderby'] )
			: 'created_at';
		// phpcs:ignore WordPress.Security.NonceVerification
		$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ) ? 'ASC' : 'DESC';

		// Status filter
		$allowed_statuses = array( 'active', 'expired', 'pending' );
		// phpcs:ignore WordPress.Security.NonceVerification
		$status_filter = ( isset( $_GET['status_filter'] ) && in_array( $_GET['status_filter'], $allowed_statuses, true ) )
			// phpcs:ignore WordPress.Security.NonceVerification
			? sanitize_key( $_GET['status_filter'] )
			: '';

		$where = '';
		if ( $status_filter ) {
			$where = $wpdb->prepare( 'WHERE up.status = %s', $status_filter );
		}

		// Pagination
		$per_page     = self::PER_PAGE;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM {$wpdb->prefix}fitness_user_plans up {$where}"
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT up.id, up.user_id, up.coach_id, up.order_id,
			        up.type, up.status, up.expiry_date, up.created_at,
			        u.display_name  AS user_name,
			        c.display_name  AS coach_name
			 FROM   {$wpdb->prefix}fitness_user_plans up
			 LEFT JOIN {$wpdb->users} u ON u.ID = up.user_id
			 LEFT JOIN {$wpdb->users} c ON c.ID = up.coach_id
			 {$where}
			 ORDER BY up.{$orderby} {$order}
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		$this->items = $wpdb->get_results( $sql, ARRAY_A ) ?: array();

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	// ─── Column Renderers ─────────────────────────────────────────────────────

	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {

			case 'id':
				$link = esc_url( admin_url( 'admin.php?page=fitnesspro-orders&view=' . (int) $item['id'] ) );
				return '<strong><a href="' . $link . '">#' . (int) $item['id'] . '</a></strong>';

			case 'user':
				if ( empty( $item['user_name'] ) ) {
					return '<span class="fp-empty-val">' . esc_html__( 'کاربر حذف‌شده', 'fitnesspro' ) . '</span>';
				}
				$profile_url = esc_url( get_edit_user_link( (int) $item['user_id'] ) );
				return '<a href="' . $profile_url . '">' . esc_html( $item['user_name'] ) . '</a>';

			case 'coach':
				if ( empty( $item['coach_name'] ) || 0 === (int) $item['coach_id'] ) {
					return '<span class="fp-badge fp-badge--unassigned">'
						. esc_html__( 'تخصیص‌نیافته', 'fitnesspro' )
						. '</span>';
				}
				$coach_url = esc_url( get_edit_user_link( (int) $item['coach_id'] ) );
				return '<a href="' . $coach_url . '">' . esc_html( $item['coach_name'] ) . '</a>';

			case 'type':
				return 'workout' === $item['type']
					? '<span class="fp-type-badge fp-type-badge--workout">' . esc_html__( 'تمرینی', 'fitnesspro' ) . '</span>'
					: '<span class="fp-type-badge fp-type-badge--meal">' . esc_html__( 'تغذیه', 'fitnesspro' ) . '</span>';

			case 'status':
				$map = array(
					'active'  => array( 'active',  __( 'فعال', 'fitnesspro' ) ),
					'expired' => array( 'expired', __( 'منقضی', 'fitnesspro' ) ),
					'pending' => array( 'pending', __( 'در انتظار', 'fitnesspro' ) ),
				);
				$s = $map[ $item['status'] ] ?? array( '', $item['status'] );
				return '<span class="fitnesspro-badge fitnesspro-badge--' . esc_attr( $s[0] ) . '">'
					. esc_html( $s[1] ) . '</span>';

			case 'expiry_date':
				if ( empty( $item['expiry_date'] ) ) {
					return '&mdash;';
				}
				$ts   = strtotime( $item['expiry_date'] );
				$past = $ts < time();
				return '<span class="' . ( $past ? 'fp-date--expired' : 'fp-date--future' ) . '">'
					. esc_html( date_i18n( get_option( 'date_format' ), $ts ) ) . '</span>';

			case 'created_at':
				return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item['created_at'] ) ) );

			default:
				return '&mdash;';
		}
	}

	public function no_items() {
		esc_html_e( 'هیچ سفارش فیتنسی ثبت نشده است.', 'fitnesspro' );
	}
}
