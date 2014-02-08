<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Shortcodes class.
 */
class WP_Job_Manager_Shortcodes {

	private $job_dashboard_message = '';

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'shortcode_action_handler' ) );

		add_shortcode( 'submit_job_form', array( $this, 'submit_job_form' ) );
		add_shortcode( 'job_dashboard', array( $this, 'job_dashboard' ) );
		add_shortcode( 'jobs', array( $this, 'output_jobs' ) );
		add_shortcode( 'job', array( $this, 'output_job' ) );
		add_shortcode( 'job_summary', array( $this, 'output_job_summary' ) );
	}

	/**
	 * Handle actions which need to be run before the shortcode e.g. post actions
	 */
	public function shortcode_action_handler() {
		global $post;

		if ( is_page() && strstr( $post->post_content, '[job_dashboard' ) ) {
			$this->job_dashboard_handler();
		}
	}

	/**
	 * Show the job submission form
	 */
	public function submit_job_form() {
		return $GLOBALS['job_manager']->forms->get_form( 'submit-job' );
	}

	/**
	 * Handles actions on job dashboard
	 */
	public function job_dashboard_handler() {
		if ( ! empty( $_REQUEST['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'job_manager_my_job_actions' ) ) {

			$action = sanitize_title( $_REQUEST['action'] );
			$job_id = absint( $_REQUEST['job_id'] );

			try {
				// Get Job
				$job    = get_post( $job_id );

				// Check ownership
				if ( $job->post_author != get_current_user_id() )
					throw new Exception( __( 'Invalid Job ID', 'wp-job-manager' ) );

				switch ( $action ) {
					case 'mark_filled' :
						// Check status
						if ( $job->_filled == 1 )
							throw new Exception( __( 'This job is already filled', 'wp-job-manager' ) );

						// Update
						update_post_meta( $job_id, '_filled', 1 );

						// Message
						$this->job_dashboard_message = '<div class="job-manager-message">' . sprintf( __( '%s has been filled', 'wp-job-manager' ), $job->post_title ) . '</div>';
						break;
					case 'mark_not_filled' :
						// Check status
						if ( $job->_filled != 1 )
							throw new Exception( __( 'This job is already not filled', 'wp-job-manager' ) );

						// Update
						update_post_meta( $job_id, '_filled', 0 );

						// Message
						$this->job_dashboard_message = '<div class="job-manager-message">' . sprintf( __( '%s has been marked as not filled', 'wp-job-manager' ), $job->post_title ) . '</div>';
						break;
					case 'delete' :
						// Trash it
						wp_trash_post( $job_id );

						// Message
						$this->job_dashboard_message = '<div class="job-manager-message">' . sprintf( __( '%s has been deleted', 'wp-job-manager' ), $job->post_title ) . '</div>';

						break;
				}

				do_action( 'job_manager_my_job_do_action', $action, $job_id );

			} catch ( Exception $e ) {
				$this->job_dashboard_message = '<div class="job-manager-error">' . $e->getMessage() . '</div>';
			}
		}
	}

	/**
	 * Shortcode which lists the logged in user's jobs
	 */
	public function job_dashboard( $atts ) {
		global $job_manager;

		if ( ! is_user_logged_in() ) {
			_e( 'You need to be signed in to manage your job listings.', 'wp-job-manager' );
			return;
		}

		extract( shortcode_atts( array(
			'posts_per_page' => '25',
		), $atts ) );

		wp_enqueue_script( 'wp-job-manager-job-dashboard' );

		// If doing an action, show conditional content if needed....
		if ( ! empty( $_REQUEST['action'] ) ) {

			$action = sanitize_title( $_REQUEST['action'] );
			$job_id = absint( $_REQUEST['job_id'] );

			switch ( $action ) {
				case 'edit' :
					return $job_manager->forms->get_form( 'edit-job' );
			}
		}

		// ....If not show the job dashboard
		$args     = apply_filters( 'job_manager_get_dashboard_jobs_args', array(
			'post_type'           => 'job_listing',
			'post_status'         => array( 'publish', 'expired', 'pending' ),
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => $posts_per_page,
			'offset'              => ( max( 1, get_query_var('paged') ) - 1 ) * $posts_per_page,
			'orderby'             => 'date',
			'order'               => 'desc',
			'author'              => get_current_user_id()
		) );

		$jobs = new WP_Query;

		ob_start();

		echo $this->job_dashboard_message;

		get_job_manager_template( 'job-dashboard.php', array( 'jobs' => $jobs->query( $args ), 'max_num_pages' => $jobs->max_num_pages ) );

		return ob_get_clean();
	}

	/**
	 * output_jobs function.
	 *
	 * @access public
	 * @param mixed $args
	 * @return void
	 */
	public function output_jobs( $atts ) {
		global $job_manager;

		ob_start();

		extract( $atts = shortcode_atts( apply_filters( 'job_manager_output_jobs_defaults', array(
			'per_page'        => get_option( 'job_manager_per_page' ),
			'orderby'         => 'featured',
			'order'           => 'DESC',
			'show_filters'    => true,
			'show_categories' => get_option( 'job_manager_enable_categories' ),
			'categories'      => '',
			'job_types'       => '',
			'location'        => '', 
			'keywords'        => ''
		) ), $atts ) );

		$categories = array_filter( array_map( 'trim', explode( ',', $categories ) ) );
		$job_types  = array_filter( array_map( 'trim', explode( ',', $job_types ) ) );

		if ( $show_filters && $show_filters !== 'false' ) {

			get_job_manager_template( 'job-filters.php', array( 'per_page' => $per_page, 'orderby' => $orderby, 'order' => $order, 'show_categories' => $show_categories, 'categories' => $categories, 'job_types' => $job_types, 'atts' => $atts, 'location' => $location, 'keywords' => $keywords ) );

			?><ul class="job_listings"></ul><a class="load_more_jobs" href="#" style="display:none;"><strong><?php _e( 'Load more job listings', 'wp-job-manager' ); ?></strong></a><?php

		} else {

			$jobs = get_job_listings( apply_filters( 'job_manager_output_jobs_args', array(
				'search_location'   => $location,
				'search_keywords'   => $keywords,
				'search_categories' => $categories,
				'job_types'         => $job_types,
				'orderby'           => $orderby,
				'order'             => $order,
				'posts_per_page'    => $per_page
			) ) );

			if ( $jobs->have_posts() ) : ?>

				<ul class="job_listings">

					<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

						<?php get_job_manager_template_part( 'content', 'job_listing' ); ?>

					<?php endwhile; ?>

				</ul>

				<?php if ( $jobs->found_posts > $per_page ) : ?>

					<?php wp_enqueue_script( 'wp-job-manager-ajax-filters' ); ?>

					<a class="load_more_jobs" href="#"><strong><?php _e( 'Load more job listings', 'wp-job-manager' ); ?></strong></a>

				<?php endif; ?>

			<?php endif;

			wp_reset_postdata();
		}

		return '<div class="job_listings" data-location="' . esc_attr( $location ) . '" data-keywords="' . esc_attr( $keywords ) . '" data-show_filters="' . ( $show_filters && $show_filters !== 'false' ? 1 : 0 ) . '" data-per_page="' . esc_attr( $per_page ) . '" data-orderby="' . esc_attr( $orderby ) . '" data-order="' . esc_attr( $order ) . '" data-categories="' . esc_attr( implode( ',', $categories ) ) . '">' . ob_get_clean() . '</div>';
	}

	/**
	 * output_job function.
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_job( $atts ) {
		global $job_manager;

		extract( shortcode_atts( array(
			'id' => '',
		), $atts ) );

		if ( ! $id )
			return;

		ob_start();

		$args = array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'p'           => $id
		);

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) : ?>

			<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

				<h1><?php the_title(); ?></h1>

				<?php get_job_manager_template_part( 'content-single', 'job_listing' ); ?>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return '<div class="job_shortcode single_job_listing">' . ob_get_clean() . '</div>';
	}

	/**
	 * Job Summary shortcode
	 *
	 * @access public
	 * @param array $args
	 * @return string
	 */
	public function output_job_summary( $atts ) {
		global $job_manager;

		extract( shortcode_atts( array(
			'id'    => '',
			'width' => '250px',
			'align' => 'left'
		), $atts ) );

		if ( ! $id )
			return;

		ob_start();

		$args = array(
			'post_type'   => 'job_listing',
			'post_status' => 'publish',
			'p'           => $id
		);

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) : ?>

			<?php while ( $jobs->have_posts() ) : $jobs->the_post(); ?>

				<div class="job_summary_shortcode align<?php echo $align ?>" style="width: <?php echo $width ? $width : auto; ?>">

					<?php get_job_manager_template_part( 'content-summary', 'job_listing' ); ?>

				</div>

			<?php endwhile; ?>

		<?php endif;

		wp_reset_postdata();

		return ob_get_clean();
	}
}

new WP_Job_Manager_Shortcodes();