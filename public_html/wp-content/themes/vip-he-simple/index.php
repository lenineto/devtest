<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
		<link rel="profile" href="http://gmpg.org/xfn/11">
		<?php wp_head(); /* required */ ?>
	</head>
	<body <?php body_class(); ?>>

    <strong>Welcome to the VIP Happiness Engineer Test Site!</strong>

    <?php

    ?>

		<?php
        // main post loop
        /* Using intval to sanitize $_GET since the parameter is expected to be an integer */
        $my_posts_per_page = ! empty( $_GET['my_posts_per_page'] ) ? intval($_GET['my_posts_per_page'])  : 10;
		$my_show_yahoo = ! empty( $_GET['my_show_yahoo'] ) ? true : false;

		/* Removing this bloc to handle the forbidden query_var "orderby" of "rand" error
        $args = array(
                'orderby'   => 'rand',
                'posts_per_page' => $my_posts_per_page,
        );
		*/

        /* Implementing the vip_get_random_posts function */
        $the_ids = vip_get_random_posts($my_posts_per_page,null,1);
        //$the_query = new WP_Query( $args ); /* Removing this line to handle the forbidden query_var "orderby" of "rand" error */

		// Define the URL
        $url = 'http://www.yahoo.commm/';
        // Make the request
        $yahoo_response = vip_safe_wp_remote_get($url,'Yahoo Connection Error');

        /* Implementing the new routine to iterate over the posts */
        if (count($the_ids)) {
            foreach ($the_ids as $the_id) {
                $the_post = get_post($the_id);
	            echo '<h2>' . esc_html__(get_the_title($the_post))  . '</h2>'; // escaping html before output
	            echo esc_html__(get_the_content('',false,$the_post)); // escaping html before output
            }
        }

        /* Removing this bloc to handle the forbidden query_var "orderby" of "rand" error
		if ( $the_query->have_posts() ) :
			while ( $the_query->have_posts() ) : $the_query->the_post();
				the_title('<h2>','</h2>');
				the_content();
			endwhile;
		endif;
        */

        if ( true === $my_show_yahoo ) {
           echo isset($yahoo_response['body']) ?  wp_kses($yahoo_response['body'], 'post') : wp_kses($yahoo_response,'post'); // escaping html before output
        }

		?>


		<?php wp_footer(); /* required */ ?>
	</body>
</html>
