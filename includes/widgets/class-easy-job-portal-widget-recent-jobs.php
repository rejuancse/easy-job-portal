<?php
/**
 * File containing the class Easy_Job_Portal_Widget_Recent_Jobs.
 *
 * @package easy-job-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recent Jobs widget.
 *
 * @package easy-job-portal
 * @since 1.0.0
 */
class Easy_Job_Portal_Widget_Recent_Jobs extends Easy_Job_Portal_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wp_post_types;

		// translators: Placeholder %s is the plural label for the job listing post type.
		$this->widget_name        = sprintf( __( 'Recent %s', 'easy-job-portal' ), $wp_post_types['job_listing']->labels->name );
		$this->widget_cssclass    = 'easy_job_portal widget_recent_jobs';
		$this->widget_description = __( 'Display a list of recent listings on your site, optionally matching a keyword and location.', 'easy-job-portal' );
		$this->widget_id          = 'widget_recent_jobs';
		$this->settings           = [
			'title'     => [
				'type'  => 'text',
				// translators: Placeholder %s is the plural label for the job listing post type.
				'std'   => sprintf( __( 'Recent %s', 'easy-job-portal' ), $wp_post_types['job_listing']->labels->name ),
				'label' => __( 'Title', 'easy-job-portal' ),
			],
			'keyword'   => [
				'type'  => 'text',
				'std'   => '',
				'label' => __( 'Keyword', 'easy-job-portal' ),
			],
			'location'  => [
				'type'  => 'text',
				'std'   => '',
				'label' => __( 'Location', 'easy-job-portal' ),
			],
			'number'    => [
				'type'  => 'number',
				'step'  => 1,
				'min'   => 1,
				'max'   => '',
				'std'   => 10,
				'label' => __( 'Number of listings to show', 'easy-job-portal' ),
			],
			'show_logo' => [
				'type'  => 'checkbox',
				'std'   => 0,
				'label' => esc_html__( 'Show Company Logo', 'easy-job-portal' ),
			],
		];

		parent::__construct();
	}

	/**
	 * Echoes the widget content.
	 *
	 * @see WP_Widget
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		wp_enqueue_style( 'easy-job-portal-job-listings' );

		if ( $this->get_cached_widget( $args ) ) {
			return;
		}

		$instance = array_merge( $this->get_default_instance(), $instance );

		ob_start();

		$title     = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$number    = absint( $instance['number'] );
		$jobs      = get_job_listings(
			[
				'search_location' => $instance['location'],
				'search_keywords' => $instance['keyword'],
				'posts_per_page'  => $number,
				'orderby'         => 'date',
				'order'           => 'DESC',
			]
		);
		$show_logo = absint( $instance['show_logo'] );

		/**
		 * Runs before Recent Jobs widget content.
		 *
		 * @since 1.29.1
		 *
		 * @param array    $args
		 * @param array    $instance
		 * @param WP_Query $jobs
		 */
		do_action( 'easy_job_portal_recent_jobs_widget_before', $args, $instance, $jobs );

		if ( $jobs->have_posts() ) : ?>

			<?php echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php
			if ( $title ) {
				echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<ul class="job_listings">

				<?php
				while ( $jobs->have_posts() ) :
					$jobs->the_post();
					?>

					<?php get_easy_job_portal_template( 'content-widget-job_listing.php', [ 'show_logo' => $show_logo ] ); ?>

				<?php endwhile; ?>

			</ul>

			<?php echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php else : ?>

			<?php get_easy_job_portal_template_part( 'content-widget', 'no-jobs-found' ); ?>

			<?php
		endif;

		/**
		 * Runs after Recent Jobs widget content.
		 *
		 * @since 1.29.1
		 *
		 * @param array    $args
		 * @param array    $instance
		 * @param WP_Query $jobs
		 */
		do_action( 'easy_job_portal_recent_jobs_widget_after', $args, $instance, $jobs );

		wp_reset_postdata();

		$content = ob_get_clean();

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->cache_widget( $args, $content );
	}
}

register_widget( 'Easy_Job_Portal_Widget_Recent_Jobs' );
