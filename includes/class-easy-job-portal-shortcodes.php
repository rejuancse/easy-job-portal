<?php
/**
 * File containing the class Easy_Job_Portal_Shortcodes.
 *
 * @package easy-job-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the shortcodes for Easy Job Portal.
 *
 * @since 1.0.0
 */
class Easy_Job_Portal_Shortcodes {

	/**
	 * Dashboard message.
	 *
	 * @access private
	 * @var string
	 */
	private $job_dashboard_message = '';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp', [ $this, 'shortcode_action_handler' ] );
		add_action( 'easy_job_portal_job_dashboard_content_edit', [ $this, 'edit_job' ] );
		add_action( 'easy_job_portal_job_filters_end', [ $this, 'job_filter_job_types' ], 20 );
		add_action( 'easy_job_portal_job_filters_end', [ $this, 'job_filter_results' ], 30 );
		add_action( 'easy_job_portal_output_jobs_no_results', [ $this, 'output_no_results' ] );
		add_shortcode( 'submit_job_form', [ $this, 'submit_job_form' ] );
		add_shortcode( 'job_dashboard', [ $this, 'job_dashboard' ] );
		add_shortcode( 'jobs', [ $this, 'output_jobs' ] );
		add_shortcode( 'job_listing', [ $this, 'output_job' ] );
		add_shortcode( 'job_summary', [ $this, 'output_job_summary' ] );
		add_shortcode( 'job_apply', [ $this, 'output_job_apply' ] );
	}

	/**
	 * Handles actions which need to be run before the shortcode e.g. post actions.
	 */
	public function shortcode_action_handler() {
		global $post;

		if ( is_page() && has_shortcode( $post->post_content, 'job_dashboard' ) ) {
			$this->job_dashboard_handler();
		}
	}

	/**
	 * Shows the job submission form.
	 *
	 * @param array $atts
	 * @return string|null
	 */
	public function submit_job_form( $atts = [] ) {
		return $GLOBALS['easy_job_portal']->forms->get_form( 'submit-job', $atts );
	}

	/**
	 * Handles actions on job dashboard.
	 *
	 * @throws Exception On action handling error.
	 */
	public function job_dashboard_handler() {
		if (
			! empty( $_REQUEST['action'] )
			&& ! empty( $_REQUEST['_wpnonce'] )
			&& wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'easy_job_portal_my_job_actions' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
		) {

			$action = sanitize_title( wp_unslash( $_REQUEST['action'] ) );
			$job_id = isset( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : 0;

			try {
				// Get Job.
				$job = get_post( $job_id );

				// Check ownership.
				if ( ! easy_job_portal_user_can_edit_job( $job_id ) ) {
					throw new Exception( __( 'Invalid ID', 'easy-job-portal' ) );
				}

				switch ( $action ) {
					case 'mark_filled':
						// Check status.
						if ( 1 === intval( $job->_filled ) ) {
							throw new Exception( __( 'This position has already been filled', 'easy-job-portal' ) );
						}

						// Update.
						update_post_meta( $job_id, '_filled', 1 );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = '<div class="easy-job-portal-message">' . esc_html( sprintf( __( '%s has been filled', 'easy-job-portal' ), wpjm_get_the_job_title( $job ) ) ) . '</div>';
						break;
					case 'mark_not_filled':
						// Check status.
						if ( 1 !== intval( $job->_filled ) ) {
							throw new Exception( __( 'This position is not filled', 'easy-job-portal' ) );
						}

						// Update.
						update_post_meta( $job_id, '_filled', 0 );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = '<div class="easy-job-portal-message">' . esc_html( sprintf( __( '%s has been marked as not filled', 'easy-job-portal' ), wpjm_get_the_job_title( $job ) ) ) . '</div>';
						break;
					case 'delete':
						// Trash it.
						wp_trash_post( $job_id );

						// Message.
						// translators: Placeholder %s is the job listing title.
						$this->job_dashboard_message = '<div class="easy-job-portal-message">' . esc_html( sprintf( __( '%s has been deleted', 'easy-job-portal' ), wpjm_get_the_job_title( $job ) ) ) . '</div>';

						break;
					case 'duplicate':
						if ( ! easy_job_portal_get_permalink( 'submit_job_form' ) ) {
							throw new Exception( __( 'Missing submission page.', 'easy-job-portal' ) );
						}

						$new_job_id = easy_job_portal_duplicate_listing( $job_id );

						if ( $new_job_id ) {
							wp_safe_redirect( add_query_arg( [ 'job_id' => absint( $new_job_id ) ], easy_job_portal_get_permalink( 'submit_job_form' ) ) );
							exit;
						}

						break;
					case 'relist':
					case 'continue':
						if ( ! easy_job_portal_get_permalink( 'submit_job_form' ) ) {
							throw new Exception( __( 'Missing submission page.', 'easy-job-portal' ) );
						}

						// redirect to post page.
						wp_safe_redirect( add_query_arg( [ 'job_id' => absint( $job_id ) ], easy_job_portal_get_permalink( 'submit_job_form' ) ) );
						exit;
					default:
						do_action( 'easy_job_portal_job_dashboard_do_action_' . $action, $job_id );
						break;
				}

				do_action( 'easy_job_portal_my_job_do_action', $action, $job_id );

				/**
				 * Set a success message for a custom dashboard action handler.
				 *
				 * When left empty, no success message will be shown.
				 *
				 * @since 1.31.1
				 *
				 * @param string  $message  Text for the success message. Default: empty string.
				 * @param string  $action   The name of the custom action.
				 * @param int     $job_id   The ID for the job that's been altered.
				 */
				$success_message = apply_filters( 'easy_job_portal_job_dashboard_success_message', '', $action, $job_id );
				if ( $success_message ) {
					$this->job_dashboard_message = '<div class="easy-job-portal-message">' . $success_message . '</div>';
				}
			} catch ( Exception $e ) {
				$this->job_dashboard_message = '<div class="easy-job-portal-error">' . wp_kses_post( $e->getMessage() ) . '</div>';
			}
		}
	}

	/**
	 * Handles shortcode which lists the logged in user's jobs.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function job_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			ob_start();
			get_easy_job_portal_template( 'job-dashboard-login.php' );
			return ob_get_clean();
		}

		$new_atts       = shortcode_atts(
			[
				'posts_per_page' => '25',
			],
			$atts
		);
		$posts_per_page = $new_atts['posts_per_page'];

		wp_enqueue_script( 'easy-job-portal-job-dashboard' );

		ob_start();

		// If doing an action, show conditional content if needed....
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		$action = isset( $_REQUEST['action'] ) ? sanitize_title( wp_unslash( $_REQUEST['action'] ) ) : false;
		if ( ! empty( $action ) ) {
			// Show alternative content if a plugin wants to.
			if ( has_action( 'easy_job_portal_job_dashboard_content_' . $action ) ) {
				do_action( 'easy_job_portal_job_dashboard_content_' . $action, $atts );

				return ob_get_clean();
			}
		}

		// ....If not show the job dashboard.
		$args = apply_filters(
			'easy_job_portal_get_dashboard_jobs_args',
			[
				'post_type'           => 'job_listing',
				'post_status'         => [ 'publish', 'expired', 'pending', 'draft', 'preview' ],
				'ignore_sticky_posts' => 1,
				'posts_per_page'      => $posts_per_page,
				'offset'              => ( max( 1, get_query_var( 'paged' ) ) - 1 ) * $posts_per_page,
				'orderby'             => 'date',
				'order'               => 'desc',
				'author'              => get_current_user_id(),
			]
		);

		$jobs = new WP_Query();

		echo wp_kses_post( $this->job_dashboard_message );

		$job_dashboard_columns = apply_filters(
			'easy_job_portal_job_dashboard_columns',
			[
				'job_title' => __( 'Title', 'easy-job-portal' ),
				'filled'    => __( 'Filled?', 'easy-job-portal' ),
				'date'      => __( 'Date Posted', 'easy-job-portal' ),
				'expires'   => __( 'Listing Expires', 'easy-job-portal' ),
			]
		);

		get_easy_job_portal_template(
			'job-dashboard.php',
			[
				'jobs'                  => $jobs->query( $args ),
				'max_num_pages'         => $jobs->max_num_pages,
				'job_dashboard_columns' => $job_dashboard_columns,
			]
		);

		return ob_get_clean();
	}

	/**
	 * Displays edit job form.
	 */
	public function edit_job() {
		global $easy_job_portal;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output should be appropriately escaped in the form generator.
		echo $easy_job_portal->forms->get_form( 'edit-job' );
	}

	/**
	 * Lists all job listings.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_jobs( $atts ) {
		ob_start();

		$atts = shortcode_atts(
			apply_filters(
				'easy_job_portal_output_jobs_defaults',
				[
					'per_page'                  => get_option( 'easy_job_portal_per_page' ),
					'orderby'                   => 'featured',
					'order'                     => 'DESC',

					// Filters + cats.
					'show_filters'              => true,
					'show_categories'           => true,
					'show_category_multiselect' => get_option( 'easy_job_portal_enable_default_category_multiselect', false ),
					'show_pagination'           => false,
					'show_more'                 => true,

					// Limit what jobs are shown based on category, post status, and type.
					'categories'                => '',
					'job_types'                 => '',
					'post_status'               => '',
					'featured'                  => null, // True to show only featured, false to hide featured, leave null to show both.
					'filled'                    => null, // True to show only filled, false to hide filled, leave null to show both/use the settings.

					// Default values for filters.
					'location'                  => '',
					'keywords'                  => '',
					'selected_category'         => '',
					'selected_job_types'        => implode( ',', array_values( get_job_listing_types( 'id=>slug' ) ) ),
				]
			),
			$atts
		);

		if ( ! get_option( 'easy_job_portal_enable_categories' ) ) {
			$atts['show_categories'] = false;
		}

		// String and bool handling.
		$atts['show_filters']              = $this->string_to_bool( $atts['show_filters'] );
		$atts['show_categories']           = $this->string_to_bool( $atts['show_categories'] );
		$atts['show_category_multiselect'] = $this->string_to_bool( $atts['show_category_multiselect'] );
		$atts['show_more']                 = $this->string_to_bool( $atts['show_more'] );
		$atts['show_pagination']           = $this->string_to_bool( $atts['show_pagination'] );

		if ( ! is_null( $atts['featured'] ) ) {
			$atts['featured'] = ( is_bool( $atts['featured'] ) && $atts['featured'] ) || in_array( $atts['featured'], [ 1, '1', 'true', 'yes' ], true );
		}

		if ( ! is_null( $atts['filled'] ) ) {
			$atts['filled'] = ( is_bool( $atts['filled'] ) && $atts['filled'] ) || in_array( $atts['filled'], [ 1, '1', 'true', 'yes' ], true );
		}

		// Get keywords, location, category and type from querystring if set.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		if ( ! empty( $_GET['search_keywords'] ) ) {
			$atts['keywords'] = sanitize_text_field( wp_unslash( $_GET['search_keywords'] ) );
		}
		if ( ! empty( $_GET['search_location'] ) ) {
			$atts['location'] = sanitize_text_field( wp_unslash( $_GET['search_location'] ) );
		}
		if ( ! empty( $_GET['search_category'] ) ) {
			$atts['selected_category'] = sanitize_text_field( wp_unslash( $_GET['search_category'] ) );
		}
		if ( ! empty( $_GET['search_job_type'] ) ) {
			$atts['selected_job_types'] = sanitize_text_field( wp_unslash( $_GET['search_job_type'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Array handling.
		$atts['categories']         = is_array( $atts['categories'] ) ? $atts['categories'] : array_filter( array_map( 'trim', explode( ',', $atts['categories'] ) ) );
		$atts['selected_category']  = is_array( $atts['selected_category'] ) ? $atts['selected_category'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_category'] ) ) );
		$atts['job_types']          = is_array( $atts['job_types'] ) ? $atts['job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['job_types'] ) ) );
		$atts['post_status']        = is_array( $atts['post_status'] ) ? $atts['post_status'] : array_filter( array_map( 'trim', explode( ',', $atts['post_status'] ) ) );
		$atts['selected_job_types'] = is_array( $atts['selected_job_types'] ) ? $atts['selected_job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_job_types'] ) ) );

		// Normalize field for categories.
		if ( ! empty( $atts['selected_category'] ) ) {
			foreach ( $atts['selected_category'] as $cat_index => $category ) {
				if ( ! is_numeric( $category ) ) {
					$term = get_term_by( 'slug', $category, 'job_listing_category' );

					if ( $term ) {
						$atts['selected_category'][ $cat_index ] = $term->term_id;
					}
				}
			}
		}

		$data_attributes = [
			'location'        => $atts['location'],
			'keywords'        => $atts['keywords'],
			'show_filters'    => $atts['show_filters'] ? 'true' : 'false',
			'show_pagination' => $atts['show_pagination'] ? 'true' : 'false',
			'per_page'        => $atts['per_page'],
			'orderby'         => $atts['orderby'],
			'order'           => $atts['order'],
			'categories'      => implode( ',', $atts['categories'] ),
		];

		if ( $atts['show_filters'] ) {
			get_easy_job_portal_template(
				'job-filters.php',
				[
					'per_page'                  => $atts['per_page'],
					'orderby'                   => $atts['orderby'],
					'order'                     => $atts['order'],
					'show_categories'           => $atts['show_categories'],
					'categories'                => $atts['categories'],
					'selected_category'         => $atts['selected_category'],
					'job_types'                 => $atts['job_types'],
					'atts'                      => $atts,
					'location'                  => $atts['location'],
					'keywords'                  => $atts['keywords'],
					'selected_job_types'        => $atts['selected_job_types'],
					'show_category_multiselect' => $atts['show_category_multiselect'],
				]
			);

			get_easy_job_portal_template( 'job-listings-start.php' );
			get_easy_job_portal_template( 'job-listings-end.php' );

			if ( ! $atts['show_pagination'] && $atts['show_more'] ) {
				echo '<a class="load_more_jobs" href="#" style="display:none;"><strong>' . esc_html__( 'Load More Jobs', 'easy-job-portal' ) . '</strong></a>';
			}

		} else {
			$jobs = get_job_listings(
				apply_filters(
					'easy_job_portal_output_jobs_args',
					[
						'search_location'   => $atts['location'],
						'search_keywords'   => $atts['keywords'],
						'post_status'       => $atts['post_status'],
						'search_categories' => $atts['categories'],
						'job_types'         => $atts['job_types'],
						'orderby'           => $atts['orderby'],
						'order'             => $atts['order'],
						'posts_per_page'    => $atts['per_page'],
						'featured'          => $atts['featured'],
						'filled'            => $atts['filled'],
					]
				)
			);

			if ( ! empty( $atts['job_types'] ) ) {
				$data_attributes['job_types'] = implode( ',', $atts['job_types'] );
			}

			if ( $jobs->have_posts() ) {
				get_easy_job_portal_template( 'job-listings-start.php' );
				while ( $jobs->have_posts() ) {
					$jobs->the_post();
					get_easy_job_portal_template_part( 'content', 'job_listing' );
				}
				get_easy_job_portal_template( 'job-listings-end.php' );
				if ( $jobs->found_posts > $atts['per_page'] && $atts['show_more'] ) {
					wp_enqueue_script( 'easy-job-portal-ajax-filters' );
					if ( $atts['show_pagination'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output.
						echo get_job_listing_pagination( $jobs->max_num_pages );
					} else {
						echo '<a class="load_more_jobs" href="#"><strong>' . esc_html__( 'Load More Jobs', 'easy-job-portal' ) . '</strong></a>';
					}
				}
			} else {
				do_action( 'easy_job_portal_output_jobs_no_results' );
			}
			wp_reset_postdata();
		}

		$data_attributes_string = '';
		if ( ! is_null( $atts['featured'] ) ) {
			$data_attributes['featured'] = $atts['featured'] ? 'true' : 'false';
		}
		if ( ! is_null( $atts['filled'] ) ) {
			$data_attributes['filled'] = $atts['filled'] ? 'true' : 'false';
		}
		if ( ! empty( $atts['post_status'] ) ) {
			$data_attributes['post_status'] = implode( ',', $atts['post_status'] );
		}

		$data_attributes['post_id'] = isset( $GLOBALS['post'] ) ? $GLOBALS['post']->ID : 0;

		/**
		 * Pass additional data to the job listings <div> wrapper.
		 */
		$data_attributes = apply_filters( 'easy_job_portal_jobs_shortcode_data_attributes', $data_attributes, $atts );

		foreach ( $data_attributes as $key => $value ) {
			$data_attributes_string .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$job_listings_output = apply_filters( 'easy_job_portal_job_listings_output', ob_get_clean() );

		return '<div class="job_listings" ' . $data_attributes_string . '>' . $job_listings_output . '</div>';
	}

	/**
	 * Displays some content when no results were found.
	 */
	public function output_no_results() {
		get_easy_job_portal_template( 'content-no-jobs-found.php' );
	}

	/**
	 * Gets string as a bool.
	 */
	public function string_to_bool( $value ) {
		return ( is_bool( $value ) && $value ) || in_array( $value, [ 1, '1', 'true', 'yes' ], true );
	}

	/**
	 * Shows job types.
	 *
	 * @param  array $atts
	 */
	public function job_filter_job_types( $atts ) {
		$job_types          = is_array( $atts['job_types'] ) ? $atts['job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['job_types'] ) ) );
		$selected_job_types = is_array( $atts['selected_job_types'] ) ? $atts['selected_job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_job_types'] ) ) );

		get_easy_job_portal_template(
			'job-filter-job-types.php',
			[
				'job_types'          => $job_types,
				'atts'               => $atts,
				'selected_job_types' => $selected_job_types,
			]
		);
	}

	/**
	 * Shows results div.
	 */
	public function job_filter_results() {
		echo '<div class="showing_jobs"></div>';
	}

	/**
	 * Shows a single job.
	 *
	 * @param array $atts
	 * @return string|null
	 */
	public function output_job( $atts ) {
		$atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts
		);

		if ( ! $atts['id'] ) {
			return null;
		}

		ob_start();

		$args = [
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'p'           => $atts['id'],
		];

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				echo '<h1>' . esc_html( wpjm_get_the_job_title() ) . '</h1>';
				get_easy_job_portal_template_part( 'content-single', 'job_listing' );
			}
		}

		wp_reset_postdata();

		return '<div class="job_shortcode single_job_listing">' . ob_get_clean() . '</div>';
	}

	/**
	 * Handles the Job Summary shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_job_summary( $atts ) {
		$atts = shortcode_atts(
			[
				'id'       => '',
				'width'    => '250px',
				'align'    => 'left',
				'featured' => null, // True to show only featured, false to hide featured, leave null to show both (when leaving out id).
				'limit'    => 1,
			],
			$atts
		);

		ob_start();

		$args = [
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
		];

		if ( ! $atts['id'] ) {
			$args['posts_per_page'] = $atts['limit'];
			$args['orderby']        = 'rand';
			if ( ! is_null( $atts['featured'] ) ) {
				$args['meta_query'] = [
					[
						'key'     => '_featured',
						'value'   => '1',
						'compare' => $atts['featured'] ? '=' : '!=',
					],
				];
			}
		} else {
			$args['p'] = absint( $atts['id'] );
		}

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				$width = $atts['width'] ? $atts['width'] : 'auto';
				echo '<div class="job_summary_shortcode align' . esc_attr( $atts['align'] ) . '" style="width: ' . esc_attr( $width ) . '">';
				get_easy_job_portal_template_part( 'content-summary', 'job_listing' );
				echo '</div>';
			}
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Shows the application area.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_job_apply( $atts ) {
		$new_atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts
		);
		$id       = $new_atts['id'];

		ob_start();

		$args = [
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
		];

		if ( ! $id ) {
			return '';
		} else {
			$args['p'] = absint( $id );
		}

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				$apply = get_the_job_application_method();
				do_action( 'easy_job_portal_before_job_apply_' . absint( $id ) );
				if ( apply_filters( 'easy_job_portal_show_job_apply_' . absint( $id ), true ) ) {
					echo '<div class="easy-job-portal-application-wrapper">';
					do_action( 'easy_job_portal_application_details_' . $apply->type, $apply );
					echo '</div>';
				}
				do_action( 'easy_job_portal_after_job_apply_' . absint( $id ) );
			}
			wp_reset_postdata();
		}

		return ob_get_clean();
	}
}

Easy_Job_Portal_Shortcodes::instance();
