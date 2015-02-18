<?php
/**
 * Plugin Name: Training Journal
 * Plugin URI: http://example.com
 * Description: Log your workouts and races, analyze statistics of your training, track personal bests and shoe mileage.
 * Version: 0.1
 * Author: Dovydas Sankauskas
 * Author URI: http://example.com
 * License: GPLv2
 */



/****************************************************/
/******************  INSTALLATION  ******************/
/****************************************************/

// if there are some errors save them in debug.txt in plugin directory
add_action('activated_plugin','trainingjournal_save_debug');
function trainingjournal_save_debug()
{
    file_put_contents(dirname(__FILE__).'/debug.txt', ob_get_contents());
}

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $gear_table, $sport_table, $wtype_table, $workout_table;
	$gear_table = $wpdb->prefix . "trainingjournal_gear";
	$sport_table = $wpdb->prefix . "trainingjournal_sport";
	$wtype_table = $wpdb->prefix . "trainingjournal_wtype";
	$workout_table = $wpdb->prefix . "trainingjournal_workout";

/*	global $gear_table, $sport_table, $wtype_table, $workout_table;
	$sport_data = $wpdb->get_results( "SELECT * FROM $sport_table" );
	$wtype_data = $wpdb->get_results("SELECT * FROM $wtype_table");
	$gear_data = $wpdb->get_results("SELECT * FROM $gear_table");
*/

function trainingjournal_installdb () {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $gear_table, $sport_table, $wtype_table, $workout_table;

$charset_collate = $wpdb->get_charset_collate();

	$fresh_install = FALSE;
	//if this is fresh install then we will insert some initial data
	$fresh_install = ( $wpdb->get_var("SHOW TABLES LIKE '$gear_table'" ) == NULL );

	$sql = "CREATE TABLE $gear_table (
		id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(50) default '' NOT NULL,
		retired BOOLEAN default FALSE,
		icon VARCHAR(100) default '' NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";
	dbDelta( $sql );

	$sql = "CREATE TABLE $sport_table (
		id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(50) default '' NOT NULL,
		icon VARCHAR(100) default '' NOT NULL,
		measure BOOLEAN default TRUE,
		UNIQUE KEY id (id)
	) $charset_collate;";
	dbDelta( $sql );

	$sql = "CREATE TABLE $wtype_table (
		id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(50) default '' NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";
	dbDelta( $sql );

	$sql = "CREATE TABLE $workout_table (
		id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
		sport SMALLINT UNSIGNED NOT NULL,
		wtype SMALLINT UNSIGNED,
		date DATETIME NOT NULL,
		duration TIME,
		distance DECIMAL(4,1),
		gear SMALLINT UNSIGNED,
		race BOOLEAN default FALSE,
		place MEDIUMINT UNSIGNED,
		ahr SMALLINT UNSIGNED,
		rhr SMALLINT UNSIGNED,
		rpm SMALLINT UNSIGNED,
		mood VARCHAR(100),
		comment VARCHAR(1000),
		UNIQUE KEY id (id)
	) $charset_collate;";
	dbDelta( $sql );

	//if this is fresh install then we will insert some initial data
	if ( $fresh_install ) {
		$wpdb->insert($gear_table, array('name' => 'Barefoot'));
		$wpdb->insert($sport_table, array('name' => 'Running'));
		$wpdb->insert($wtype_table, array('name' => 'Easy Run'));
	}

}

register_activation_hook( __FILE__, 'trainingjournal_installdb' );


/**************************************************/
/******************  ADMIN PAGE  ******************/
/**************************************************/

add_action( 'admin_enqueue_scripts', 'trainingjournal_style' );
add_action( 'admin_menu', 'trainingjournal_admin_menu' );

function trainingjournal_style() {
	/* add stylesheet to admin menu and training journal pages */
	wp_register_style( 'trainingjournal_css', plugins_url( 'training-journal.css?v=' . time(), __FILE__ ) );
	wp_enqueue_style( 'trainingjournal_css' );
}

function trainingjournal_admin_menu() {
	/* create admin menu */
	add_plugins_page( 'Training Journal Admin Menu', 'Training Journal', 'manage_options', 'trainingjournal-menu', 'trainingjournal_admin_page' );
}

function trainingjournal_show_workout( $race = NULL, $limit = NULL) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	global $sport_table;
	global $wtype_table;
	global $gear_table;
	$show_limit = isset($limit) ? " LIMIT $limit" : "";
	$show_race = !empty($race) ? " AND workout.race = 1 " : "";
	$workout_data = $wpdb->get_results( 
		"SELECT 
			workout.id, date, sport.icon, wtype.name AS wtype, 
			distance, duration, ahr, rhr, gear.name AS gear, 
			race, place, mood, comment
		FROM 
			$workout_table AS workout, 
			$sport_table AS sport, 
			$wtype_table AS wtype, 
			$gear_table As gear
		WHERE 
			sport = sport.id AND
			wtype = wtype.id AND
			gear = gear.id $show_race
		ORDER BY date DESC $show_limit"
	);
	echo '
	<div class="trainingjournal-data">
	<table class="trainingjournal-data">
		<thead class="trainingjournal-data">
			<tr><th>Latest ' . $limit . ' workouts</th><th>Edit</th><th>Remove</th></tr>
		</thead>
		<tbody>';
		if ( !empty($workout_data) ) {
			foreach ( $workout_data as $workout ) {
				echo '
				<tr>
				<form method="POST" name="form-workout-show-' . $workout->id . '" action="">
				<td class="trainingjournal-data">
					<div class="trainingjournal-data">
						<div class="trainingjournal-data-items">
							<ul class="trainingjournal-data-item">
								<li class="trainingjournal-data-item-l">
									' . date( 'M j G:i', strtotime($workout->date) ) . '</li>
								<li class="trainingjournal-data-item-l">
									<img src="' . plugins_url( $workout->icon, __FILE__ ) . '" height="16" width="16"></li>
								<li class="trainingjournal-data-item-l">
									' . $workout->distance . ' km</li>
								<li class="trainingjournal-data-item-l">
									' . date( 'G:i:s', strtotime($workout->duration) ) . '</li>';
								if ( !$workout->race ) {
									echo '<li class="trainingjournal-data-item-l">
									' . $workout->wtype . '</li>';
								}
/*								<li class="trainingjournal-data-item-l">
									ahr ' . $workout->ahr . '</li>
								<li class="trainingjournal-data-item-l">
									rhr ' . $workout->rhr . '</li>
*/
/*								<li class="trainingjournal-data-item-r">
									<img src="' . plugins_url( 'icons/mood-medium.png', __FILE__ ) . '"></li>
*/
									echo '<li class="trainingjournal-data-item-r">
										' . $workout->place . '</li>';
								if ( $workout->race ) {
									echo '<li class="trainingjournal-data-item-r">
										<img src="' . plugins_url( 'icons/medal.png', __FILE__ ) . '" height="16" width="16"></li>';
								}
							echo '<li class="trainingjournal-data-item-r">
									' . $workout->gear . '</li>
								</ul>
						</div>
					</div>
					<div class="trainingjournal-data-comment">
						' . $workout->comment . '
					</div>
				</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id = "' . $workout->id .'"
						name="workout-edit"
						src="' . plugins_url( 'icons/edit.png', __FILE__ ) . ' " height="16" width="16"
						value="' . $workout->id .'" 
						alt="Edit ' . $workout->id . '">
				</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id="' . $workout->id .'"
						name="workout-remove"
						src="' . plugins_url( 'icons/remove.png', __FILE__ ) . '"  height="16" width="16"
						value="' . $workout->id .'" 
						alt="Remove ' . $workout->id . '">
					<input 
						type="hidden" 
						name="workout-remove-item"
						value="' . $workout->id .'" 
				</td>
			</form>
			</tr>';
			}
		}
		echo '
		</tbody>
		</table>
	</div>';
}

function trainingjournal_show_weekly( $year = NULL) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	global $sport_table;
	global $wtype_table;
	global $gear_table;
	$show_year = isset($year) ? " AND YEARWEEK(date, 3) LIKE '%$show_year%' " : "" ;
	$weekly_data = $wpdb->get_results( 
		"SELECT 
			WEEK(date,3) AS week, COUNT(workout.id) AS id, 
			SUM(distance) AS distance, YEAR(date) as year,
			SEC_TO_TIME(SUM(TIME_TO_SEC(duration))) AS duration 
		FROM 
			$workout_table AS workout, 
			$sport_table AS sport 
		WHERE 
			sport = sport.id $show_year
		GROUP BY WEEK(date, 3)
		ORDER by year DESC, week DESC"
	);
	echo '
	<div class="trainingjournal-data">
	<table class="trainingjournal-data">
		<thead class="trainingjournal-data">
			<tr><th>Year</th><th>Week</th><th>KM</th><th>Events</th><th>Time</th></tr>
		</thead>
		<tbody>';
		if ( !empty($weekly_data) ) {
			foreach ( $weekly_data as $weekly ) {
				echo '
				<tr>
					<td class="trainingjournal-data-item">
						' . $weekly->year . '</td>
					<td class="trainingjournal-data-item">
						' . $weekly->week . '</td>
					<td class="trainingjournal-data-item">
						' . $weekly->distance . ' km</td>
					<td class="trainingjournal-data-item">
						' . $weekly->id . '</td>
					<td class="trainingjournal-data-item">
						' . $weekly->duration . '</td>
				</tr>';
			}
		}
		echo '
		</tbody>
		</table>
	</div>';
}

function trainingjournal_show_monthly( $year = NULL) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	global $sport_table;
	global $wtype_table;
	global $gear_table;
	$show_year = isset($year) ? " AND YEAR(date) = $year " : "" ;
	$monthly_data = $wpdb->get_results( 
		"SELECT 
			MONTHNAME(date) AS month, COUNT(workout.id) AS id, 
			SUM(distance) AS distance, YEAR(date) as year,
			SEC_TO_TIME(SUM(TIME_TO_SEC(duration))) AS duration 
		FROM 
			$workout_table AS workout, 
			$sport_table AS sport 
		WHERE 
			sport = sport.id $show_year
		GROUP BY MONTH(date)
		ORDER BY year DESC, MONTH(date) DESC"
	);
	echo '
	<div class="trainingjournal-data">
	<table class="trainingjournal-data">
		<thead class="trainingjournal-data">
			<tr><th>Year</th><th>Month</th><th>KM</th><th>Events</th><th>Time</th></tr>
		</thead>
		<tbody>';
		if ( !empty($monthly_data) ) {
			foreach ( $monthly_data as $monthly ) {
				echo '
				<tr>
					<td class="trainingjournal-data-item">
						' . $monthly->year . '</td>
					<td class="trainingjournal-data-item">
						' . $monthly->month . '</td>
					<td class="trainingjournal-data-item">
						' . $monthly->distance . ' km</td>
					<td class="trainingjournal-data-item">
						' . $monthly->id . '</td>
					<td class="trainingjournal-data-item">
						' . $monthly->duration . '</td>
				</tr>';
			}
		}
		echo '
		</tbody>
		</table>
	</div>';
}

function trainingjournal_show_yearly() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	global $sport_table;
	global $wtype_table;
	global $gear_table;
	$yearly_data = $wpdb->get_results( 
		"SELECT 
			YEAR(date) AS year, COUNT(workout.id) AS id, 
			SUM(distance) AS distance, 
			SEC_TO_TIME(SUM(TIME_TO_SEC(duration))) AS duration 
		FROM 
			$workout_table AS workout, 
			$sport_table AS sport 
		WHERE 
			sport = sport.id
		GROUP BY YEAR(date)
		ORDER BY year DESC"
	);
	echo '
	<div class="trainingjournal-data">
	<table class="trainingjournal-data">
		<thead class="trainingjournal-data">
			<tr><th>Year</th><th>KM</th><th>Events</th><th>Time</th></tr>
		</thead>
		<tbody>';
		if ( !empty($yearly_data) ) {
			foreach ( $yearly_data as $yearly ) {
				echo '
				<tr>
					<td class="trainingjournal-data-item">
						' . $yearly->year . '</td>
					<td class="trainingjournal-data-item">
						' . $yearly->distance . ' km</td>
					<td class="trainingjournal-data-item">
						' . $yearly->id . '</td>
					<td class="trainingjournal-data-item">
						' . $yearly->duration . '</td>
				</tr>';
			}
		}
		echo '
		</tbody>
		</table>
	</div>';
}

function trainingjournal_show_total() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	global $sport_table;
	global $wtype_table;
	global $gear_table;
	$total_data = $wpdb->get_results( 
		"SELECT 
			sport.name as name, COUNT(workout.id) AS workout, 
			SUM(distance) AS distance, 
			SEC_TO_TIME(SUM(TIME_TO_SEC(duration))) AS duration 
		FROM 
			$workout_table AS workout, 
			$sport_table AS sport 
		WHERE 
			sport = sport.id
		GROUP BY sport"
	);
	echo '
	<div class="trainingjournal-data">
	<table class="trainingjournal-data">
		<thead class="trainingjournal-data">
			<tr><th>Sport</th><th>KM</th><th>Events</th><th>Time</th></tr>
		</thead>
		<tbody>';
		if ( !empty($total_data) ) {
			foreach ( $total_data as $total ) {
				echo '
				<tr>
						<td class="trainingjournal-data-item">
							' . $total->name . '</td>
						<td class="trainingjournal-data-item">
							' . ( isset($total->distance) ? $total->distance : 0 ) . ' km</td>
						<td class="trainingjournal-data-item">
							' . $total->workout . '</td>
						<td class="trainingjournal-data-item">
							' . $total->duration . '</td>
				</tr>';
			}
		}
		echo '
		</tbody>
		</table>
	</div>';
}

function trainingjournal_show_pb() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	global $sport_table;
	global $wtype_table;
	global $gear_table;
	$pb_data = $wpdb->get_results( 
		"SELECT 
			distance, date, place, comment, 
			SEC_TO_TIME(MIN(TIME_TO_SEC(duration))) AS duration 
		FROM 
			$workout_table
		WHERE 
			race=1 AND 
			distance IN (1, 3, 5, 10, 15, 20, 21.1, 42.2)
		GROUP BY distance"
	);
	echo '
	<div class="trainingjournal-data">
	<table class="trainingjournal-data">
		<thead class="trainingjournal-data">
			<tr><th>KM</th><th>Time</th><th>Date</th><th>Race</th><th>Place</th></tr>
		</thead>
		<tbody>';
		if ( !empty($pb_data) ) {
			foreach ( $pb_data as $pb ) {
				echo '
				<tr>
						<td class="trainingjournal-data-item">
							' . $pb->distance . '</td>
						<td class="trainingjournal-data-item">
							' . $pb->duration . '</td>
						<td class="trainingjournal-data-item">
							' . date( 'Y-M-j', strtotime($pb->date) ) . '</td>
						<td class="trainingjournal-data-item">
							' . $pb->comment . '</td>
						<td class="trainingjournal-data-item">
							<img src="' . plugins_url( 'icons/medal.png', __FILE__ ) . '" height="16" width="16">' . $pb->place . '</td>
				</tr>';
			}
		}
		echo '
		</tbody>
		</table>
	</div>';
}

function trainingjournal_show_gear_stats () {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	global $sport_table;
	global $wtype_table;
	global $gear_table;
/*	global $gear_data;*/
	$gear_data = $wpdb->get_results( 
		"SELECT 
			gear.id AS id, gear.name as name,
			COUNT(workout.id) AS workout, SUM(distance) AS distance, 
			SEC_TO_TIME(SUM(TIME_TO_SEC(duration))) AS duration 
		FROM 
			$workout_table AS workout, 
			$gear_table AS gear 
		WHERE 
			gear = gear.id
		GROUP BY id"
	);
	echo '
	<div class="trainingjournal-data">
	<table class="trainingjournal-data">
		<thead class="trainingjournal-data">
			<tr><th>Model</th><th>KM</th><th>Time</th><th>Events</th></tr>
		</thead>
		<tbody>';
			if ( !empty($gear_data) ) {
				foreach ( $gear_data as $gear ) {
				echo '
				<tr>
						<td class="trainingjournal-data-item">
							' . $gear->name . '</td>
						<td class="trainingjournal-data-item">
							' . ( isset($gear->distance) ? $gear->distance : 0 ) . ' km</td>
						<td class="trainingjournal-data-item">
							' . $gear->duration . '</td>
						<td class="trainingjournal-data-item">
							' . $gear->workout . '</td>
				</tr>';
			}
		}
		echo '
		</tbody>
		</table>
	</div>';
}

function trainingjournal_show_sport() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $sport_table;
	$sport_data = $wpdb->get_results( "SELECT * FROM $sport_table" );

	echo '<div class="trainingjournal-data">
		<table class="trainingjournal-data">
			<thead class="trainingjournal-data">
				<tr><th>Sport</th><th>Measure distance</th><th>Edit</th><th>Remove</th></tr>
			</thead>
			<tbody>';
			if ( !empty($sport_data) ) {
				foreach ( $sport_data as $sport ) {
				echo '<tr>
				<form method="POST" name="form-sport-show-' . $sport->id . '" action="" onsubmit="return confirm(\'Do you really want to remove the item?\');">
				<td class="trainingjournal-data">' . $sport->name . '</td>
				<td class="trainingjournal-data">';
				if ( $sport->measure == TRUE ) {
					echo 'Measure';
				} else { 
					echo 'No';
				}
				echo '</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id = "' . $sport->id .'"
						name="sport-edit"
						src="' . plugins_url( 'icons/edit.png', __FILE__ ) . '" height="16" width="16" 
						value="' . $sport->id .'" 
						alt="Edit ' . $sport->name . '">
				</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id="' . $sport->id .'"
						name="sport-remove"
						src="' . plugins_url( 'icons/remove.png', __FILE__ ) . '" height="16" width="16" 
						value="' . $sport->id .'" 
						alt="Remove ' . $sport->name . '">
					<input 
						type="hidden" 
						name="sport-remove-item"
						value="' . $sport->id .'" 
				</td>
				</tr></form>';
				}
			}
			echo '
			</tbody>
		</table>
	</div>';
}

function trainingjournal_show_wtype () {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $wtype_table;
/*	global $wype_data;*/
	$wtype_data = $wpdb->get_results("SELECT * FROM $wtype_table");

	echo '<div class="trainingjournal-data">
		<table class="trainingjournal-data">
			<thead class="trainingjournal-data">
				<tr><th>Workout Type</th><th>Edit</th><th>Remove</th></tr>
			</thead>
			<tbody>';
			if ( !empty( $wtype_data ) ) {
				foreach ( $wtype_data as $wtype ) {
				echo '<tr>
				<form method="POST" name="form-wtype-show-' . $wtype->id . '" action="" onsubmit="return confirm(\'Do you really want to remove the item?\');">
				<td class="trainingjournal-data">' . $wtype->name . '</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id = "' . $wtype->id .'"
						name="wtype-edit"
						src="' . plugins_url( 'icons/edit.png', __FILE__ ) . '" height="16" width="16" 
						value="' . $wtype->id .'" 
						alt="Edit ' . $wtype->name . '">
				</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id="' . $wtype->id .'"
						name="wtype-remove"
						src="' . plugins_url( 'icons/remove.png', __FILE__ ) . '" height="16" width="16" 
						value="' . $wtype->id .'" 
						alt="Remove ' . $wtype->name . '">
					<input 
						type="hidden" 
						name="wtype-remove-item"
						value="' . $wtype->id .'" 
				</td>
				</tr></form>';
				}
			}
			echo '
			</tbody>
		</table>
	</div>';
}

function trainingjournal_show_gear () {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $gear_table;
/*	global $gear_data;*/
	$gear_data = $wpdb->get_results("SELECT * FROM $gear_table");

	echo '<div class="trainingjournal-data">
		<table class="trainingjournal-data">
			<thead class="trainingjournal-data">
				<tr><th>Model</th><th>Retired</th><th>Edit</th><th>Remove</th></tr>
			</thead>
			<tbody>';
			if ( !empty($gear_data) ) {
				foreach ( $gear_data as $gear ) {
				echo '<tr>
				<form method="POST" name="form-wtype-show-' . $gear->id . '" action="" onsubmit="return confirm(\'Do you really want to remove the item?\');">
				<td class="trainingjournal-data">' . $gear->name . '</td>
				<td class="trainingjournal-data">';
				if ( $gear->retired == TRUE ) {
					echo 'Retired';
				} else { 
					echo 'In use';
				}
				echo '</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id = "' . $gear->id .'"
						name="gear-edit"
						src="' . plugins_url( 'icons/edit.png', __FILE__ ) . '" height="16" width="16" 
						value="' . $gear->id .'" 
						alt="Edit ' . $gear->name . '">
				</td>
				<td class="trainingjournal-data">
					<input 
						type="image" 
						id="' . $gear->id .'"
						name="gear-remove"
						src="' . plugins_url( 'icons/remove.png', __FILE__ ) . '" height="16" width="16" 
						value="' . $gear->id .'" 
						alt="Remove ' . $gear->name . '">
					<input 
						type="hidden" 
						name="gear-remove-item"
						value="' . $gear->id .'" 
				</td>
				</tr></form>';
				}
			}
			echo '</tbody>
		</table>
	</div>';
}

function trainingjournal_add_workout ( $sport, $wtype, $date, $duration, $distance, $gear, $ahr, $rhr, $mood, $comment, $race, $place ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $workout_table;
	$wpdb->insert( 
		$workout_table, 
		array( 
			'sport' => $sport, 
			'wtype' => $wtype,
			'date' => $date, 
			'duration' => $duration, 
			'distance' => $distance, 
			'gear' => $gear, 
			'race' => $race, 
			'place' => $place, 
			'ahr' => $ahr, 
			'rhr' => $rhr, 
			'mood' => $mood, 
			'comment' => $comment
		), 
		array( 
			'%d', '%d', '%s', '%s', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s'
		) 
	);
}

function trainingjournal_add_sport ( $name, $measure ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $sport_table;
	$wpdb->insert( 
		$sport_table, 
		array( 
			'name' => $name, 
			'measure' => $measure
		), 
		array( 
			'%s', '%d' 
		) 
	);
}

function trainingjournal_add_wtype ( $name ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $wtype_table;
	$wpdb->insert( 
		$wtype_table, 
		array( 
			'name' => $name 
		), 
		array( '%s' )
	);
}

function trainingjournal_add_gear ( $name, $retired ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $gear_table;
	$wpdb->insert( 
		$gear_table, 
		array( 
			'name' => $name, 
			'retired' => $retired
		), 
		array( 
			'%s', '%d' 
		) 
	);
}

function trainingjournal_remove_sport ( $id ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $sport_table;
	$wpdb->delete( 
		$sport_table, 
		array( 
			'id' => $id 
		), 
		array( 
			'%d' 
		) 
	);
}

function trainingjournal_remove_wtype ( $id ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $wtype_table;
	$wpdb->delete( 
		$wtype_table, 
		array( 
			'id' => $id 
		), 
		array( 
			'%d' 
		) 
	);
}

function trainingjournal_remove_gear ( $id ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $gear_table;
	$wpdb->delete( 
		$gear_table, 
		array( 
			'id' => $id 
		), 
		array( 
			'%d' 
		) 
	);
}

function trainingjournal_select_sport () {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $sport_table;
	$sport_data = $wpdb->get_results( "SELECT * FROM $sport_table" );
	echo '
	<div class="trainingjournal-addnew-input">
	Sport: <select name="workout-sport" value="">';
	if ( !empty($sport_data) ) {
		foreach ( $sport_data as $sport ) {
		echo '
			<option value="' . $sport->id . '">' . $sport->name . '</option>';
		}
	}
	echo '
		</select>
	</div>
	';
}

function trainingjournal_select_wtype () {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $wtype_table;
	$wtype_data = $wpdb->get_results( "SELECT * FROM $wtype_table" );
	echo '
	<div class="trainingjournal-addnew-input">
	Workout type: <select name="workout-wtype" value="">';
	if ( !empty($wtype_data) ) {
		foreach ( $wtype_data as $wtype ) {
		echo '
			<option value="' . $wtype->id . '">' . $wtype->name . '</option>';
		}
	}
	echo '
		</select>
	</div>
	';
}

function trainingjournal_select_gear () {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $gear_table;
	$gear_data = $wpdb->get_results( "SELECT * FROM $gear_table WHERE retired=FALSE" );
	echo '
	<div class="trainingjournal-addnew-input">
	Gear: <select name="workout-gear" value="">';
	if ( !empty($gear_data) ) {
		foreach ( $gear_data as $gear ) {
		echo '
			<option value="' . $gear->id . '">' . $gear->name . '</option>';
		}
	}
	echo '
		</select>
	</div>
	';
}

function trainingjournal_admin_page() {
	/* create admin menu page content */
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions fghfgh to access this page.' ) );
	}
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $gear_table, $sport_table, $wtype_table, $workout_table;

	if ( isset( $_POST['workout-add'] ) ) {
	  trainingjournal_add_workout( 
			$_POST['workout-sport'], 
			$_POST['workout-wtype'], 
			$_POST['workout-date'], 
			$_POST['workout-duration'], 
			$_POST['workout-distance'], 
			$_POST['workout-gear'], 
			$_POST['workout-ahr'], 
			$_POST['workout-rhr'], 
			$_POST['workout-mood'], 
			$_POST['workout-comment'],
			isset( $_POST['workout-race'] ) ? 1 : 0, 
			isset( $_POST['workout-race'] ) ? $_POST['workout-place'] : 0
		);
	}
	if ( isset( $_POST['sport-add'] ) ) {
	  trainingjournal_add_sport( 
			$_POST['sport-name'], 
			isset( $_POST['sport-measure'] ) ? 1 : 0 
		);
	}
	if ( isset( $_POST['sport-remove'] ) ) {
	  trainingjournal_remove_sport( 
			$_POST['sport-remove-item'] 
		);
	}
	if ( isset( $_POST['wtype-add'] ) ) {
	  trainingjournal_add_wtype( 
			$_POST['wtype-name'] 
		);
	}
	if ( isset( $_POST['wtype-remove'] ) ) {
	  trainingjournal_remove_wtype( 
			$_POST['wtype-remove-item'] 
		);
	}
	if ( isset( $_POST['gear-add'] ) ) {
	  trainingjournal_add_gear( 
			$_POST['gear-name'], 
			isset( $_POST['gear-retired'] ) ? 1 : 0 
		);
	}
	if ( isset( $_POST['gear-remove'] ) ) {
	  trainingjournal_remove_gear( 
			$_POST['gear-remove-item'] 
		);
	}

	echo '<div class="wrap">';
	/* admin header */
	echo '<h2>Training Journal</h2>
	<div style="display: block; margin-top: 0.5em; margin-bottom: 0.5em; margin-left: 0; margin-right: 0; border-style: solid none none none; border-width: 1px;"><br></div>';
	/* admin workouts content block */
	echo '<div class="trainingjournal-header">
		<h3>Workout</h3>
	</div>
	<div class="trainingjournal-addnew">
		<form method="POST" name="form-workout-add" action="">';
		trainingjournal_select_sport ();
		trainingjournal_select_wtype ();
		echo '
		<div class="trainingjournal-addnew-input">
			Date: <input type="datetime-local" step="1800" name="workout-date" value=""><br>yyyy-mm-dd hh:mm
		</div>
		<div class="trainingjournal-addnew-input">
			Duration: <input type="text" name="workout-duration" value="" placeholder="01:23:45"><br>hh:mm:ss
		</div>
		<div class="trainingjournal-addnew-input">
			Distance: <input type="number" step="0.1" name="workout-distance" value="">
		</div>';
		trainingjournal_select_gear ();
		echo '
		<div class="trainingjournal-addnew-input">
			Race: <input type="checkbox" name="workout-race" value="">
		</div>
		<div class="trainingjournal-addnew-input">
			Place taken: <input type="number" name="workout-place" value="">
		</div>
		<div class="trainingjournal-addnew-input">
			AHR: <input type="number" name="workout-ahr" value="">
		</div>
		<div class="trainingjournal-addnew-input">
			RHR: <input type="number" name="workout-rhr" value="">
		</div>
		<div class="trainingjournal-addnew-input">
			Mood: <input type="text" name="workout-mood" value="">
		</div>
		<div class="trainingjournal-addnew-input">
			Comment: <input type="textbox" name="workout-comment" value="">
		</div>
		<div class="trainingjournal-addnew-submit">
			<div style="margin-left:auto;margin-right:auto;"><button type="submit" name="workout-add">Add Workout</button></div>
		</div>
		</form>
	</div>';
	trainingjournal_show_workout( 0, 5 );
	echo'
	<div class="trainingjournal-text">
		<p>Add your new training workouts.</p>
	</div>
	<div style="display: block; margin-top: 0.5em; margin-bottom: 0.5em; margin-left: 0; margin-right: 0; border-style: solid none none none; border-width: 1px;"><br></div>';
	/* admin sports content block */
	echo '
	<div class="trainingjournal-header">
	<h3>Sport</h3>
	</div>
	<div class="trainingjournal-addnew">
		<form method="POST" name="form-sport-add" action="">
		<div class="trainingjournal-addnew-input">
			Sport Name: <input type="text" name="sport-name"> 
		</div>
		<div class="trainingjournal-addnew-input">
			<input type="checkbox" name="sport-measure" value="1" checked>Measure Distance
		</div>
		<div class="trainingjournal-addnew-submit">
			<div style="margin-left:auto;margin-right:auto;"><button type="submit" name="sport-add">Add Sport</button></div>
		</div></form>
	</div>';
  trainingjournal_show_sport();
	echo '<div class="trainingjournal-text">
		<p>Add your main sports like \'Running\', \'Rowing\', \'Yoga\'. For some sports like \'Yoga\' you may want not to measure distance.</p>
	</div>
	<div style="display: block; margin-top: 0.5em; margin-bottom: 0.5em; margin-left: 0; margin-right: 0; border-style: solid none none none; border-width: 1px;"><br></div>';
	/* admin workout type content block */
	echo '
	<div class="trainingjournal-header">
	<h3>Workout type</h3>
	</div>
	<div class="trainingjournal-addnew">
		<form method="POST" name="form-wtype-add" action="">
		<div class="trainingjournal-addnew-input">
			Workout Type Name: <input type="text" name="wtype-name"> 
		</div>
		<div class="trainingjournal-addnew-submit">
			<div style="margin-left:auto;margin-right:auto;"><button type="submit" name="wtype-add">Add Workout Type</button></div>
		</div>
		</form>
	</div>';
  trainingjournal_show_wtype();
	echo '<div class="trainingjournal-text">
		<p>You can log different workout types for each sport. For example in \'Running\' sport you can have \'Easy Run\', \'Long Run\' and \'Tempo Run\' workout types.</p>
	</div>
	<div style="display: block; margin-top: 0.5em; margin-bottom: 0.5em; margin-left: 0; margin-right: 0; border-style: solid none none none; border-width: 1px;"><br></div>';
	/* admin gear content block */
	echo '
	<div class="trainingjournal-header">
	<h3>Gear</h3>
	</div>
	<div class="trainingjournal-addnew">
		<form method="POST" name="form-gear-add" action="">
		<div class="trainingjournal-addnew-input">
			Model Name: <input type="text" name="gear-name"> 
		</div>
		<div class="trainingjournal-addnew-input">
			<input type="checkbox" name="gear-retired" value="1">Retired
		</div>
		<div class="trainingjournal-addnew-submit">
			<div style="margin-left:auto;margin-right:auto;"><button type="submit" name="gear-add">Add Gear</button></div>
		</div>
		</form>
	</div>';
	trainingjournal_show_gear();
	echo '<div class="trainingjournal-text">
		<p>You can add shoes, bicycles, boats or any other training gear. If you don\'t use your shoes anymore you can mark them as "retired". Retired shoes will be visible in statistics but it will be NOT visible in the drop down list for new workouts.</p>
	</div>
	</div>';
}



/****************************************************/
/******************  VISITOR PAGE  ******************/
/****************************************************/


add_shortcode( 'insert-trainingjournal', 'trainingjournal_menu' );
add_action( 'wp_enqueue_scripts', 'trainingjournal_style' );

//[insert-trainingjournal]
function trainingjournal_menu ( $atts ) {
	if ( isset( $_POST['trainingjournal-navmenu-item'] ) ) {
		$active = $_POST['trainingjournal-navmenu-item'];
	} else {
		$active = 'daily';
	}
	trainingjournal_show_menu ( $active );
	trainingjournal_show_data ( $active );
}

function trainingjournal_show_menu ( $active ) {
	echo '<div id="trainingjournal-navcontainer">
	<ul id="trainingjournal-navlist">';
	$menu_items = array ( 
		'daily' => "Dienos",
		'weekly' => "Savaitės",
		'monthly' => "Mėnesiai",
		'yearly' => "Metai",
		'total' => "Iš viso",
		'races' => "Varžybos",
		'pb' => "Rekordai",
		'gear' => "Bateliai"
	);
	foreach ( $menu_items as $name => $value) {
		echo '
		<li';
		if ( $name == $active ) {
			echo ' id="trainingjournal-navlistactive"';
		}
		echo '>
			<form method="POST" name="form-navmenu-' . $name . '" action="">
				<input name="trainingjournal-navmenu-item" type="hidden" value="' . $name . '" id="' . $name . '">
				<input id="trainingjournal-navlist" value="' . $value . '" name="' . $name . '" type="submit">
			</form>
		</li>';
	}
	echo '
	</ul>
	</div>';
	/* show second submenu */
	$yearly_items = array ( 
		'daily',
		'weekly',
		'monthly'
	);
	foreach ( $yearly_items as $item ) {
		if ( $item == $active ) {
/*			echo 'Show menu of list of years for ' . $item . ' menu<br>';*/
		}
	}
}

function trainingjournal_show_data ( $active ) {
	echo '<br>';
	switch ( $active ) {
		case 'daily':
			trainingjournal_show_workout ( 0, 300 );
			break;
		case 'weekly':
			trainingjournal_show_weekly (  );
			break;
		case 'monthly':
			trainingjournal_show_monthly (  );
			break;
		case 'yearly':
			trainingjournal_show_yearly ();
			break;
		case 'total':
			trainingjournal_show_total ();
			break;
		case 'races':
			trainingjournal_show_workout ( 1  );
			break;
		case 'pb':
			trainingjournal_show_pb ();
			break;
		case 'gear':
			trainingjournal_show_gear_stats ();
			break;
	}
/*	echo "Čia bus daug gražios statistikos";*/
}

?>
