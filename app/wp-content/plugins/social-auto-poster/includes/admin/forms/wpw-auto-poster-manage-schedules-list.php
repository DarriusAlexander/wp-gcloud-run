<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Manage posts schedules
 *
 * The html markup for the post schedules list
 *
 * @package Social Auto Poster
 * @since 1.4.0
 */

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Wpw_Auto_Poster_Manage_schedules_List extends WP_List_Table {

	var $model, $render, $per_page;

	function __construct(){

		global $wpw_auto_poster_model, $wpw_auto_poster_render;

		$this->model = $wpw_auto_poster_model;
		$this->render = $wpw_auto_poster_render;

        //Set parent defaults
        parent::__construct( array(
							            'singular'  => 'schedule',     //singular name of the listed records
							            'plural'    => 'schedules',    //plural name of the listed records
							            'ajax'      => false       //does this table support ajax?
							        ) );

		$this->per_page	= apply_filters( 'wpw_auto_poster_manage_schedules_per_page', 10 ); // Per page
	}

    /**
	 * Displaying Scheduling posts
	 *
	 * Does prepare the data for displaying Scheduling posts in the table.
	 *
	 * @package Social Auto Poster
	 * @since 1.4.0
	 */
	function display_scheduling_posts() {

		$prefix = WPW_AUTO_POSTER_META_PREFIX;

		//if search is call then pass searching value to function for displaying searching values
		$args = array();

		//Get selected tab
		$selected_tab	= !empty( $_GET['tab'] ) ? $_GET['tab'] : 'facebook';

		//Get social meta key
		$status_meta_key = $this->model->wpw_auto_poster_get_social_status_meta_key( $selected_tab );

		// Taking parameter
		$orderby 	= isset( $_GET['orderby'] )	? urldecode( $_GET['orderby'] )		: 'ID';
		$order		= isset( $_GET['order'] )	? $_GET['order']                	: 'DESC';
		$search 	= isset( $_GET['s'] ) 		? sanitize_text_field( trim($_GET['s']) )	: null;

		//Arguments
		$args = array(
						'posts_per_page'		=> $this->per_page,
						'page'					=> isset( $_GET['paged'] ) ? $_GET['paged'] : null,
						'orderby'				=> $orderby,
						'order'					=> $order,
						'offset'  				=> ( $this->get_pagenum() - 1 ) * $this->per_page,
						'wpw_auto_poster_list'	=> true,
						'meta_query'			=> array(
														'relation' => 'OR',
														array(
																'key' 	  => $status_meta_key,
																'compare' => 'NOT EXISTS',
															),
														array(
																'key' 	=> $status_meta_key,
																'value' => '',
															)
													)
					);

		//searched by search
		if( !empty( $search ) ) {
			$args['s']	= $search;
		}

		//Filter by post name
		if(isset($_REQUEST['wpw_auto_poster_post_type']) && !empty($_REQUEST['wpw_auto_poster_post_type'])) {
			$args['post_type']	= $_REQUEST['wpw_auto_poster_post_type'];
		}

		// Filter based on post category
		if(isset($_REQUEST['wpw_auto_poster_cat_id']) && !empty($_REQUEST['wpw_auto_poster_cat_id'])) {
			$term_id = $_REQUEST['wpw_auto_poster_cat_id'];
			$term = get_term($term_id);
			if(!empty($term)){

				$args['tax_query'] = array(
												array(
														'taxonomy' => $term->taxonomy,
														'field'    => 'term_id',
														'terms'    => $term->term_id,
													)
											);
			}
		}

		//Filter by status
		if(isset($_REQUEST['wpw_auto_poster_social_status']) && !empty($_REQUEST['wpw_auto_poster_social_status'])) {
			$args['meta_query']	= array(
											array(
													'key' 	=> $status_meta_key,
													'value' => $_REQUEST['wpw_auto_poster_social_status'],
												)
										);
		}

		//Get social scheduling list data from database
		$results = $this->model->wpw_auto_poster_get_scheduling_data( $args );

		$data	= isset( $results['data'] ) ? $results['data'] : '';
		$total	= isset( $results['total'] ) ? $results['total'] : 0;

		if( !empty( $data ) ) {

			foreach ($data as $key => $value){

				// Declare variable
				$category_list = '';

				//Get Author name, Author profile url
				$author_name	=	get_the_author_meta( 'display_name', $value['post_author'] );
				$author_url		=	get_edit_user_link( $value['post_author'] );

				//Get post title
				$edit_link	= get_edit_post_link( $value[ 'ID' ] );

				//Get social status
				$status	= get_post_meta( $value['ID'], $status_meta_key, true );
				$social_status 	= __( 'Unpublished','wpwautoposter' );
				if( $status == 1 ) {
					$social_status 	= __( 'Published','wpwautoposter' );
				} elseif ( $status == 2 ) {
					$social_status 	= __( 'Scheduled','wpwautoposter' );
				}

				$data[$key]['post_title'] 	= '<a target="_blank" href="'.$edit_link.'">' . $value['post_title'] . '</a>';
				$data[$key]['post_type'] 	= $value['post_type'];
				$data[$key]['social_status']= $social_status;

				// Get all taxonomies defined for that post type
    			$all_taxonomies = get_object_taxonomies( $value['post_type'], 'objects' );

    			// Loop on all taxonomies
    			foreach ($all_taxonomies as $taxonomy){

    				/**
	    			 * If taxonomy is object and it is hierarchical, than it is our category
	    			 * NOTE: If taxonomy is not hierarchical than it is tag and we should not consider this
	    			 * And we will only consider first category found in our taxonomy list
	    			 */
	    			if(is_object($taxonomy) && !empty($taxonomy->hierarchical)){

	    				$categories = get_the_terms( $value['ID'], $taxonomy->name );
	    				if(!empty($categories)){

	    					for($i = 0; $i < count($categories); $i++){

	    						$category_list .= $categories[$i]->name;
	    						if($i < ( count($categories) - 1 ))
	    							$category_list .= ', ';
	    					}
	    				}
	    			}
    			}

				$data[$key]['post_category'] = $category_list;

				$data[$key]['author'] = sprintf('<a href="%s">%s</a>', $author_url, $author_name );
			}
		}

		$result_arr['data']		= !empty($data) ? $data : array();
		$result_arr['total'] 	= $total; // Total no of data

		return $result_arr;
	}

	/**
	 * Mange column data
	 *
	 * Default Column for listing table
	 *
	 * @package Social Auto Poster
	 * @since 1.4.0
	 */
	function column_default( $item, $column_name ){
		switch( $column_name ) {
			case 'post_title':
				$title = $item[ $column_name ];
		    	if( strlen( $title ) > 50 ) {
					$title = substr( $title, 0, 50 );
					$title = $title.'...';
				}
				return $title;
			case 'post_date':
				return isset( $item[ $column_name ] ) ? $this->model->wpw_auto_poster_get_date_format( $item[ $column_name ] ) : '';
            default:
				return $item[ $column_name ];
        }
    }

	/**
	 * Mange post type column data
	 *
	 * Handles to modify post type column for listing table
	 *
	 * @package Social Auto Poster
	 * @since 1.4.0
	 */
    function column_post_type($item) {

		// get all custom post types
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$post_type_sort_link = '';
		if( !empty( $item[ 'post_type' ] ) && isset( $post_types[$item[ 'post_type' ]]->label ) ) {

			$post_type_sort_link = $post_types[$item[ 'post_type' ]]->label;
		}
		return $post_type_sort_link;
    }

    /**
     * Manage Post Title Column
     *
     * @package Social Auto Poster
     * @since 1.4.0
     */

    function column_post_title($item){

		//Get selected tab
		$selected_tab	= !empty( $_GET['tab'] ) ? $_GET['tab'] : 'facebook';

		//Get social meta key
		$status_meta_key = $this->model->wpw_auto_poster_get_social_status_meta_key( $selected_tab );

		//Get social status
		$status	= get_post_meta( $item['ID'], $status_meta_key, true );

		// Get admin page url
		$admin_page_url = add_query_arg( array( 'page' => 'wpw-auto-poster-manage-schedules', 'tab' => $selected_tab ), admin_url( 'admin.php' ) );

		if( empty( $status ) || $status == 1 ) {

			//Get schedule url
			$schedule_url = add_query_arg( array( 'action' => 'schedule', 'schedule[]' => $item['ID'] ), $admin_page_url );
			$actions['schedule'] 	= '<a href="'.$schedule_url.'">' . __( 'Schedule', 'wpwautoposter' ) . '</a>';
		} elseif (  $status == 2 ) {

			//Get Unschedule url
			$unschedule_url = add_query_arg( array( 'action' => 'unschedule', 'schedule[]' => $item['ID'] ), $admin_page_url );
			$actions['unschedule'] 	= '<a href="'.$unschedule_url.'">' . __( 'Unschedule', 'wpwautoposter' ) . '</a>';
		}

         //Return the title contents
        return sprintf('%1$s %2$s',
            /*$1%s*/ $item['post_title'],
            /*$2%s*/ $this->row_actions( $actions )
        );

    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item['ID']                //The value of the checkbox should be the record's id
        );
    }

    /**
     * Display Columns
     *
     * Handles which columns to show in table
     *
	 * @package Social Auto Poster
	 * @since 1.4.0
     */
	function get_columns(){

        $columns = array(
    						'cb'      			=>	'<input type="checkbox" />', //Render a checkbox instead of text
				            'post_title'		=>	__( 'Post Title', 'wpwautoposter' ),
				            'post_type'			=>	__(	'Post Type', 'wpwautoposter' ),
				            'social_status'		=>	__(	'Status', 'wpwautoposter' ),
				            'post_category'		=>	__(	'Category', 'wpwautoposter' ),
				            'post_date'			=>	__(	'Date', 'wpwautoposter' ),
				        );
        return $columns;
    }

    /**
     * Sortable Columns
     *
     * Handles soratable columns of the table
     *
	 * @package Social Auto Poster
	 * @since 1.4.0
     */
	function get_sortable_columns() {

		$sortable_columns	= array(
									'post_title'	=>	array( 'post_title', true ),    //true means its already sorted
									'post_type'		=>	array( 'post_type', true ),
									'post_date'		=>	array( 'post_date', true )
								);

		return $sortable_columns;
	}

	function no_items() {
		//message to show when no records in database table
		_e( 'No post found.', 'wpwautoposter' );
	}

	/**
     * Bulk actions field
     *
     * Handles Bulk Action combo box values
     *
	 * @package Social Auto Poster
	 * @since 1.4.0
     */
	function get_bulk_actions() {
		//bulk action combo box parameter
		//if you want to add some more value to bulk action parameter then push key value set in below array
		$actions = array(
							'schedule'    => __('Schedule','wpwautoposter'),
							'unschedule'  => __('Unschedule','wpwautoposter')
						);

		return $actions;
	}

	/**
     * Add filter for post types
     *
     * Handles to display records for particular post type
     *
	 * @package Social Auto Poster
	 * @since 1.4.0
     */
    function extra_tablenav( $which ) {

    	if( $which == 'top' ) {

			//Get all post type names
			$all_types = get_post_types( array( 'public' => true ), 'objects');

			//get all social status
			$social_status = array(
								'' 	=> __( 'Unpublished','wpwautoposter' ),
								1 	=> __( 'Published','wpwautoposter' ),
								2 	=> __( 'Scheduled','wpwautoposter' )
								);

			$post_parent_ids = array();

			$html = '';

    		$html .= '<div class="alignleft actions">';

					$html .= '<select name="wpw_auto_poster_post_type" id="wpw_auto_poster_post_type" data-placeholder="' . __( 'Show all post type', 'wpwautoposter' ) . '">';

					$html .= '<option value="" ' .  selected( isset( $_GET['wpw_auto_poster_post_type'] ) ? $_GET['wpw_auto_poster_post_type'] : '', '', false ) . '>'.__( 'Show all post type', 'wpwautoposter' ).'</option>';

					if ( !empty( $all_types ) ) {

						foreach ( $all_types as $key => $type ) {

							if( in_array( $key, array( 'attachment' ) ) ) continue;
							$html .= '<option value="' . $key . '" ' . selected( isset( $_GET['wpw_auto_poster_post_type'] ) ? $_GET['wpw_auto_poster_post_type'] : '', $key, false ) . '>' . $type->label . '</option>';
						}

					}
					$html .= '</select>';

					// HTML for select category starts
					$html .= '<select name="wpw_auto_poster_cat_id" id="wpw_auto_poster_cat_id" data-placeholder="' . __( 'Select Category', 'wpwautoposter' ) . '">';
					$html .= '<option value="">' . __('Select Category', 'wpwautoposter') . '</option>';
					$html .= '</select>';
					// HTML for select category ends

					$html .= '<select name="wpw_auto_poster_social_status" id="wpw_auto_poster_social_status" data-placeholder="' . __( 'Show all status', 'wpwautoposter' ) . '">';

					foreach ( $social_status as $key => $name ) {

						$html .= '<option value="' . $key . '" ' . selected( isset( $_GET['wpw_auto_poster_social_status'] ) ? $_GET['wpw_auto_poster_social_status'] : '', $key, false ) . '>' . $name . '</option>';
					}
					$html .= '</select>';

    		$html .= '	<input type="submit" value="'.__( 'Filter', 'wpwautoposter' ).'" class="button" id="post-query-submit" name="">';
    		$html .= '</div>';

			echo $html;
    	}
    }

    function prepare_items() {

        // Get how many records per page to show
        $per_page	= $this->per_page;

        // Get All, Hidden, Sortable columns
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

		// Get final column header
        $this->_column_headers = array($columns, $hidden, $sortable);

		// Get Data of particular page
		$data_res 	= $this->display_scheduling_posts();
		$data 		= $data_res['data'];

		// Get current page number
        $current_page = $this->get_pagenum();

		// Get total count
        $total_items  = $data_res['total'];

        // Get page items
        $this->items = $data;

		// We also have to register our pagination options & calculations.
        $this->set_pagination_args( array(
									            'total_items' => $total_items,                  //WE have to calculate the total number of items
									            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
									            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
									        ) );
    }
}

global $wpw_auto_poster_options;

//Create an instance of our package class...
$WpwAutoPosterManageSchedulesListTable = new Wpw_Auto_Poster_Manage_schedules_List();

//Fetch, prepare, sort, and filter our data...
$WpwAutoPosterManageSchedulesListTable->prepare_items();
?>

<div class="wrap wpw-scheduling-wrap">

    <!-- wpweb logo -->
	<img src="<?php echo WPW_AUTO_POSTER_IMG_URL . '/wpw-auto-poster-logo.png'; ?>" class="wpw-auto-poster-logo" alt="<?php _e( 'Logo', 'wpwautoposter' );?>" />

	<h2><?php _e( 'Manage Schedules', 'wpwautoposter' ); ?></h2>

    <?php
    	//showing sorting links on the top of the list
    	$WpwAutoPosterManageSchedulesListTable->views();

		if( empty( $wpw_auto_poster_options['schedule_wallpost_option'] ) ) { //check message

				echo '<div class="error fade" id="message">
						<p><strong>'.__("Please go to Social Auto Poster Settings -> General settings -> Schedule Wall Posts and select schedule option first.",'wpwautoposter').'</strong></p>
					</div>';
		} else {

			if(isset($_GET['message']) && !empty($_GET['message']) ) { //check message

				if( $_GET['message'] == '1' ) { //check message

					echo '<div class="updated fade" id="message">
							<p><strong>'.__("Post(s) Scheduled successfully.",'wpwautoposter').'</strong></p>
						</div>';

				} elseif ( $_GET['message'] == '2' ) { //check message

					echo '<div class="updated fade" id="message">
							<p><strong>'.__("Post(s) Unscheduled successfully.",'wpwautoposter').'</strong></p>
						</div>';

				}
			}

		//Get selected tab
		$selected_tab	= !empty( $_GET['tab'] ) ? $_GET['tab'] : '';

		//Get admin url
		$admin_url = admin_url('admin.php');
    ?>

	<h2 class="nav-tab-wrapper wpw-auto-poster-h2" style="margin-bottom:10px;">
		<a class="nav-tab <?php echo empty( $selected_tab ) || $selected_tab == 'facebook' ? 'nav-tab-active' : ''; ?>" href="<?php echo add_query_arg( array( 'page' =>  $_GET['page'], 'tab' =>  'facebook' ), $admin_url );?>">
			<img src="<?php echo WPW_AUTO_POSTER_URL; ?>includes/images/facebook_set.png" width="24" height="24" alt="fb" title="<?php _e( 'Facebook', 'wpwautoposter' ); ?>" />
		</a>
		<a class="nav-tab <?php echo $selected_tab == 'twitter' ? 'nav-tab-active' : ''; ?>" href="<?php echo add_query_arg( array( 'page' =>  $_GET['page'], 'tab' =>  'twitter' ), $admin_url );?>">
			<img src="<?php echo WPW_AUTO_POSTER_URL; ?>includes/images/twitter_set.png" width="24" height="24" alt="tw" title="<?php _e( 'Twitter', 'wpwautoposter' ); ?>" />
		</a>
		<a class="nav-tab <?php echo $selected_tab == 'linkedin' ? 'nav-tab-active' : ''; ?>" href="<?php echo add_query_arg( array( 'page' =>  $_GET['page'], 'tab' =>  'linkedin' ), $admin_url );?>">
			<img src="<?php echo WPW_AUTO_POSTER_URL; ?>includes/images/linkedin_set.png" width="24" height="24" alt="li" title="<?php _e( 'LinkedIn', 'wpwautoposter' ); ?>" />
		</a>
		<a class="nav-tab <?php echo $selected_tab == 'tumblr' ? 'nav-tab-active' : ''; ?>" href="<?php echo add_query_arg( array( 'page' =>  $_GET['page'], 'tab' =>  'tumblr' ), $admin_url );?>">
			<img src="<?php echo WPW_AUTO_POSTER_URL; ?>includes/images/tumblr_set.png" width="24" height="24" alt="tb" title="<?php _e( 'Tumblr', 'wpwautoposter' ); ?>" />
		</a>
		<a class="nav-tab <?php echo $selected_tab == 'bufferapp' ? 'nav-tab-active' : ''; ?>" href="<?php echo add_query_arg( array( 'page' =>  $_GET['page'], 'tab' =>  'bufferapp' ), $admin_url );?>">
			<img src="<?php echo WPW_AUTO_POSTER_URL; ?>includes/images/bufferapp_set.png" width="24" height="24" alt="ba" title="<?php _e( 'BufferApp', 'wpwautoposter' ); ?>" />
		</a>
		<a class="nav-tab <?php echo $selected_tab == 'instagram' ? 'nav-tab-active' : ''; ?>" href="<?php echo add_query_arg( array( 'page' =>  $_GET['page'], 'tab' =>  'instagram' ), $admin_url );?>">
			<img src="<?php echo WPW_AUTO_POSTER_URL; ?>includes/images/instagram_set.png" width="24" height="24" alt="ins" title="<?php _e( 'Instagram', 'wpwautoposter' ); ?>" />
		</a>
		<a class="nav-tab <?php echo $selected_tab == 'pinterest' ? 'nav-tab-active' : ''; ?>" href="<?php echo add_query_arg( array( 'page' =>  $_GET['page'], 'tab' =>  'pinterest' ), $admin_url );?>">
			<img src="<?php echo WPW_AUTO_POSTER_URL; ?>includes/images/pinterest_set.png" width="24" height="24" alt="ins" title="<?php _e( 'Pinterest', 'wpwautoposter' ); ?>" />
		</a>
	</h2>

    <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
    <form id="product-filter" method="get" class="wpw-auto-poster-form">

    	<!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : '';?>" />

        <!-- Search Title -->
        <?php $WpwAutoPosterManageSchedulesListTable->search_box( __( 'Search', 'wpwautoposter' ), 'wpwautoposter' ); ?>

        <!-- Now we can render the completed list table -->
        <?php $WpwAutoPosterManageSchedulesListTable->display(); ?>

    </form>
    <?php } ?>
</div><!--wrap-->