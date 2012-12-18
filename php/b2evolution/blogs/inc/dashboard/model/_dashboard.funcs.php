<?php
/**
 * This file implements the support functions for the dashboard.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 *
 * @copyright (c)2003-2011 by Francois Planque - {@link http://fplanque.com/}
 *
 * {@internal Open Source relicensing agreement:
 * }}
 *
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE.
 *
 * @version $Id: _dashboard.funcs.php 9 2011-10-24 22:32:00Z fplanque $
 */

 /**
 * Get updates from b2evolution.net
 *
 * @param boolean useful when trying to upgrade to a release that has just been published (in the last 12 hours)
 * @return NULL|boolean True if there have been updates, false on error,
 *                      NULL if the user has turned off updates.
 */
function b2evonet_get_updates( $force_short_delay = false )
{
	global $allow_evo_stats; // Possible values: true, false, 'anonymous'
	global $DB, $debug, $evonetsrv_host, $evonetsrv_port, $evonetsrv_uri, $servertimenow, $evo_charset;
	global $Messages, $Settings, $baseurl, $instance_name, $app_name, $app_version, $app_date;
	global $Debuglog;
	global $Timer;

	if( ! isset( $allow_evo_stats ) )
	{	// Set default value:
		$allow_evo_stats = true; // allow (non-anonymous) stats
	}
	if( $allow_evo_stats === false )
	{ // Get outta here:
		return NULL;
	}

	if( $debug == 2 )
	{
		$update_every = 8;
		$attempt_every = 3;
	}
	elseif( $force_short_delay )
	{
		$update_every = 180; // 3 minutes
		$attempt_every = 60; // 1 minute
	}
	else
	{
		$update_every = 3600*12; // 12 hours
		$attempt_every = 3600*4; // 4 hours
	}

	// Note: do not put $baseurl in here since it would cause too frequently updates, when you have the same install with different $baseurls.
	//           Everytime this method gets called on another baseurl, there's a new check for updates!
	$version_id = $instance_name.' '.$app_name.' '.$app_version.' '.$app_date;
	// This is the last version we checked against the server:
	$last_version_checked =  $Settings->get( 'evonet_last_version_checked' );

	$servertime_last_update = $Settings->get( 'evonet_last_update' );
	$servertime_last_attempt = $Settings->get( 'evonet_last_attempt' );

	if( $last_version_checked == $version_id )
	{	// Current version has already been checked, don't check too often:

		if( $servertime_last_update > $servertimenow - $update_every )
		{	// The previous update was less than 12 hours ago, skip this
			// echo 'recent update';
			return false;
		}

		if( $servertime_last_attempt > $servertimenow - $attempt_every)
		{	// The previous update attempt was less than 4 hours ago, skip this
			// This is so all b2evo's don't go crazy if the server ever is down
			// echo 'recent attempt';
			return false;
		}
	}

	$Timer->resume('evonet: check for updates');
	$Debuglog->add( sprintf('Getting updates from %s.', $evonetsrv_host), 'evonet' );
	if( $debug )
	{
		$Messages->add( sprintf(T_('Getting updates from %s.'), $evonetsrv_host), 'note' );
	}
	$Settings->set( 'evonet_last_attempt', $servertimenow );
	$Settings->dbupdate();

	// Construct XML-RPC client:
	load_funcs('xmlrpc/model/_xmlrpc.funcs.php');
	$client = new xmlrpc_client( $evonetsrv_uri, $evonetsrv_host, $evonetsrv_port );
	if( $debug > 1 )
	{
		$client->debug = 1;
	}

	// Run system checks:
	load_funcs( 'tools/model/_system.funcs.php' );

	// Get system stats to display:
	$system_stats = get_system_stats();

	// Construct XML-RPC message:
	$message = new xmlrpcmsg(
								'b2evo.getupdates',                           // Function to be called
								array(
									new xmlrpcval( ( $allow_evo_stats === 'anonymous' ? md5( $baseurl ) : $baseurl ), 'string'),	// Unique identifier part 1
									new xmlrpcval( $instance_name, 'string'),		// Unique identifier part 2
									new xmlrpcval( $app_name, 'string'),		    // Version number
									new xmlrpcval( $app_version, 'string'),	  	// Version number
									new xmlrpcval( $app_date, 'string'),		    // Version number
									new xmlrpcval( array(
											'this_update' => new xmlrpcval( $servertimenow, 'string' ),
											'last_update' => new xmlrpcval( $servertime_last_update, 'string' ),
											'mediadir_status' => new xmlrpcval( $system_stats['mediadir_status'], 'int' ), // If error, then the host is potentially borked
											'install_removed' => new xmlrpcval( ($system_stats['install_removed'] == 'ok') ? 1 : 0, 'int' ), // How many people do go through this extra measure?
											'evo_charset' => new xmlrpcval( $system_stats['evo_charset'], 'string' ),			// Do people actually use UTF8?
											'evo_blog_count' => new xmlrpcval( $system_stats['evo_blog_count'], 'int'),   // How many users do use multiblogging?
											'cachedir_status' => new xmlrpcval( $system_stats['cachedir_status'], 'int'),
                      'cachedir_size' => new xmlrpcval( $system_stats['cachedir_size'], 'int'),
                      'general_pagecache_enabled' => new xmlrpcval( $system_stats['general_pagecache_enabled'] ? 1 : 0, 'int' ),
                      'blog_pagecaches_enabled' => new xmlrpcval( $system_stats['blog_pagecaches_enabled'], 'int' ),
											'db_version' => new xmlrpcval( $system_stats['db_version'], 'string'),	// If a version >95% we make it the new default.
											'db_utf8' => new xmlrpcval( $system_stats['db_utf8'] ? 1 : 0, 'int' ),	// if support >95%, we'll make it the default
											// How many "low security" hosts still active?; we'd like to standardize security best practices... on suphp?
											'php_uid' => new xmlrpcval( $system_stats['php_uid'], 'int' ),
											'php_uname' => new xmlrpcval( $system_stats['php_uname'], 'string' ),	// Potential unsecure hosts will use names like 'nobody', 'www-data'
											'php_gid' => new xmlrpcval( $system_stats['php_gid'], 'int' ),
											'php_gname' => new xmlrpcval( $system_stats['php_gname'], 'string' ),	// Potential unsecure hosts will use names like 'nobody', 'www-data'
											'php_version' => new xmlrpcval( $system_stats['php_version'], 'string' ),			// Target minimum version: PHP 5.2
											'php_reg_globals' => new xmlrpcval( $system_stats['php_reg_globals'] ? 1 : 0, 'int' ), // if <5% we may actually refuse to run future version on this
											'php_allow_url_include' => new xmlrpcval( $system_stats['php_allow_url_include'] ? 1 : 0, 'int' ),
											'php_allow_url_fopen' => new xmlrpcval( $system_stats['php_allow_url_fopen'] ? 1 : 0, 'int' ),
											// TODO php_magic quotes
											'php_upload_max' => new xmlrpcval( $system_stats['php_upload_max'], 'int' ),
											'php_post_max' => new xmlrpcval( $system_stats['php_post_max'], 'int' ),
											'php_memory' => new xmlrpcval( $system_stats['php_memory'], 'int'), // how much room does b2evo have to move on a typical server?
											'php_mbstring' => new xmlrpcval( $system_stats['php_mbstring'] ? 1 : 0, 'int' ),
											'php_xml' => new xmlrpcval( $system_stats['php_xml'] ? 1 : 0, 'int' ),
											'php_imap' => new xmlrpcval( $system_stats['php_imap'] ? 1 : 0, 'int' ),	// Does it make sense to rely on IMAP to handle undelivered emails (for user registrations/antispam)
											'php_opcode_cache' => new xmlrpcval( $system_stats['php_opcode_cache'], 'string' ), // How many use one? Which is the most popular?
											'gd_version' => new xmlrpcval( $system_stats['gd_version'], 'string' ),
											// TODO: add missing system stats
										), 'struct' ),
								)
							);

	$result = $client->send($message);

	if( $ret = xmlrpc_logresult( $result, $Messages, false ) )
	{ // Response is not an error, let's process it:
		$response = $result->value();
		if( $response->kindOf() == 'struct' )
		{ // Decode struct:
			$response = xmlrpc_decode_recurse($response);

			/**
			 * @var AbstractSettings
			 */
			global $global_Cache;

			foreach( $response as $key=>$data )
			{
				$global_Cache->set( $key, serialize($data) );
			}

			$global_Cache->delete( 'evonet_updates' );	// Cleanup

			$global_Cache->dbupdate();

			$Settings->set( 'evonet_last_update', $servertimenow );
			$Settings->set( 'evonet_last_version_checked', $version_id );
			$Settings->dbupdate();

			$Debuglog->add( 'Updates saved', 'evonet' );

			$Timer->pause('evonet: check for updates');
			return true;
		}
		else
		{
			$Debuglog->add( 'Invalid updates received', 'evonet' );
			$Messages->add( T_('Invalid updates received'), 'error' );
		}
	}

	$Timer->pause('evonet: check for updates');
	return false;
}


/**
 * Show comments awaiting moderation
 *
 * @todo fp> move this to a more appropriate place
 *
 * @param integer blog ID
 * @param integer limit
 * @param array comment IDs to exclude
 */
function show_comments_awaiting_moderation( $blog_ID, $limit = 5, $comment_IDs = array(), $script = true )
{
	global $current_User, $dispatcher;

	$BlogCache = & get_BlogCache();
	$Blog = & $BlogCache->get_by_ID( $blog_ID, false, false );

	$CommentList = new CommentList2( $Blog );
	$exlude_ID_list = NULL;
	if( !empty($comment_IDs) )
	{
		$exlude_ID_list = '-'.implode( ",", $comment_IDs );
	}

	// Filter list:
	$CommentList->set_filters( array(
			'types' => array( 'comment', 'trackback', 'pingback' ),
			'statuses' => array ( 'draft' ),
			'comment_ID_list' => $exlude_ID_list,
			'order' => 'DESC',
			'comments' => $limit,
		) );

	// Get ready for display (runs the query):
	$CommentList->display_init();

	$new_comment_IDs = array();
	while( $Comment = & $CommentList->get_next() )
	{ // Loop through comments:
		$new_comment_IDs[] = $Comment->ID;

		echo '<div id="comment_'.$Comment->ID.'" class="dashboard_post dashboard_post_'.($CommentList->current_idx % 2 ? 'even' : 'odd' ).'">';
		echo '<div class="floatright"><span class="note status_'.$Comment->status.'">';
		$Comment->status();
		echo '</div>';

		echo '<h3 class="dashboard_post_title">';
		echo $Comment->get_title(array('author_format'=>'<strong>%s</strong>'));
		$comment_Item = & $Comment->get_Item();
		echo ' '.T_('in response to')
				.' <a href="?ctrl=items&amp;blog='.$comment_Item->get_blog_ID().'&amp;p='.$comment_Item->ID.'"><strong>'.$comment_Item->dget('title').'</strong></a>';

		echo '</h3>';

		echo '<div class="notes">';
		$Comment->rating( array(
				'before'      => '',
				'after'       => ' &bull; ',
				'star_class'  => 'top',
			) );
		$Comment->date();
		$Comment->author_url_with_actions( '', true );
		$Comment->author_email( '', ' &bull; Email: <span class="bEmail">', '</span> &bull; ' );
		$Comment->author_ip( 'IP: <span class="bIP">', '</span> &bull; ' );
		$Comment->spam_karma( T_('Spam Karma').': %s%', T_('No Spam Karma') );
		echo '</div>';

		echo '<div class="small">';
		$Comment->content( 'htmlbody', true );
		echo '</div>';

		echo '<div class="dashboard_action_area">';
		// Display edit button if current user has the rights:
		$redirect_to = NULL;
		if( ! $script )
		{ // Set page, where to redirect, because the function is called from async.php (regenerate_url gives => async.php)
			global $admin_url;
			$redirect_to = $admin_url.'?ctrl=dashboard&blog='.$blog_ID;
		}
		$Comment->edit_link( ' ', ' ', '#', '#', 'ActionButton', '&amp;', true, $redirect_to );

		// Display publish NOW button if current user has the rights:
		$Comment->publish_link( ' ', ' ', '#', '#', 'PublishButton', '&amp;', true, true );

		// Display deprecate button if current user has the rights:
		$Comment->deprecate_link( ' ', ' ', '#', '#', 'DeleteButton', '&amp;', true, true );

		// Display delete button if current user has the rights:
		$Comment->delete_link( ' ', ' ', '#', '#', 'DeleteButton', false, '&amp;', true, true );
		echo '<div class="clear"></div>';
		echo '</div>';
		echo '</div>';
	}

	if( !$script )
	{
		echo '<input type="hidden" id="new_badge" value="'.get_comments_awaiting_moderation_number( $blog_ID ).'"/>';
	}
}


/*
 * $Log: _dashboard.funcs.php,v $
 */
?>