<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Set Up Macaw</title>
<?php
	include_once('system/application/config/version.php');
?>
	<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/combo?2.9.0/build/reset-fonts-grids/reset-fonts-grids.css&2.9.0/build/base/base-min.css&2.9.0/build/assets/skins/sam/skin.css">
	<link rel="stylesheet" type="text/css" href="/css/macaw.css" id="macaw_css" />
	<link rel="stylesheet" type="text/css" href="/inc/magnifier/assets/image-magnifier.css" />
	
	<!-- Combo-handled YUI JS files: -->
	<script type="text/javascript" src="http://yui.yahooapis.com/combo?2.9.0/build/utilities/utilities.js&2.9.0/build/datasource/datasource-min.js&2.9.0/build/autocomplete/autocomplete-min.js&2.9.0/build/container/container-min.js&2.9.0/build/menu/menu-min.js&2.9.0/build/button/button-min.js&2.9.0/build/json/json-min.js&2.9.0/build/swf/swf-min.js&2.9.0/build/charts/charts-min.js&2.9.0/build/paginator/paginator-min.js&2.9.0/build/datatable/datatable-min.js&2.9.0/build/resize/resize-min.js&2.9.0/build/layout/layout-min.js&2.9.0/build/logger/logger-min.js&2.9.0/build/progressbar/progressbar-min.js&2.9.0/build/slider/slider-min.js&2.9.0/build/stylesheet/stylesheet-min.js&2.9.0/build/tabview/tabview-min.js&2.9.0/build/treeview/treeview-min.js"></script>
	<!-- http://developer.yahoo.com/yui/articles/hosting/?animation&autocomplete&base&button&charts&connection&container&datasource&datatable&dom&dragdrop&event&fonts&grids&json&layout&logger&menu&paginator&progressbar&reset&resize&slider&stylesheet&swf&tabview&treeview&yahoo&MIN -->
	
	<script type="text/javascript" src="/inc/magnifier/image-magnifier.js"></script>
	<script type="text/javascript" src="/inc/swf/swfobject.js"></script>
	<script type="text/javascript" src="/main/js_config"></script>
	<script type="text/javascript" src="/js/macaw.js"></script>
	<script type="text/javascript" src="/js/macaw-barcode.js"></script>
	<script type="text/javascript" src="/js/macaw-scanning.js"></script>
	<script type="text/javascript" src="/js/macaw-dashboard.js"></script>
	<script type="text/javascript" src="/js/macaw-general.js"></script>
	<script type="text/javascript" src="/js/macaw-book.js"></script>
	<script type="text/javascript" src="/js/macaw-pages.js"></script>
	<script type="text/javascript" src="/js/macaw-page.js"></script>
	<script type="text/javascript" src="/js/macaw-metadata.js"></script>
	<script type="text/javascript" src="/js/macaw-user.js"></script>
	<script type="text/javascript" src="/js/macaw-admin.js"></script>
	<script type="text/javascript" src="/js/macaw-import.js"></script>

<?php
	// include_once('system/application/views/global/head_view.php');
	require_once('system/application/libraries/Authentication/phpass-0.1/PasswordHash.php');

	error_reporting(E_ALL & ~E_NOTICE); 
	ini_set('display_errors', '1');

	$success = 1;
	$db_created = 0;
	$step = 0;
	$paths = array();

	if (isset($_REQUEST['step'])) {$step = $_REQUEST['step'];}

	// Verify that we are installed in a relatively sane manner
	// 1. Identify base directory of the site (the location of the install.php script)
	$base_path = realpath(dirname(__FILE__));
	define("BASEPATH", $base_path);

	// 3. Make sure we can write to the configuration directory and the files contained therein
	$config_path = $base_path.'/system/application/config';
	$write_perm_error = 'Please make sure that the web server has read and write permissions.<br><br>';

	$errormessage = '';
	$message = '';
	$done = 0;
	$ci_config = $config_path.'/config.php';
	$def_ci_config = $config_path.'/config.default.php';

	$db_config = $config_path.'/database.php';
	$def_db_config = $config_path.'/database.default.php';

	$macaw_config = $config_path.'/macaw.php';
	$macaw_def_config = $config_path.'/macaw.default.php';
	$organization_name = '';

	$continue = false;
	// determine if we are already installed. We 
	if (file_exists($db_config)) {
		require_once($db_config);

		$conn = "host=".$db['default']['hostname'].
			($db['default']['port'] ? " port=".$db['default']['port'] : '').
			" dbname=".$db['default']['database'].
			" user=".$db['default']['username'].
			" password=".$db['default']['password'];
		
		// Connect and query
		$conn = pg_connect($conn);
		$result = pg_query('select * from settings where name = \'installed\'');
		$row = pg_fetch_assoc($result);		

		// Do we have a setting
		if (isset($row)) {
			// Is it 1?
			if (isset($row['value']) && $row['value'] == 1) {
				$url = getBaseURL().'/';
				$errormessage .= '<div class="error">Macaw has already been installed!</div> <h3>Start using Macaw at this URL: <a href="'.$url.'">'.$url.'</a></h3>' ;	 

			} else {
				$continue = true;
			} // if (isset($row['value']) && $row['value'] == 1)

		} else {
			$continue = true;
		} // if (isset($row))

	} else {
		$continue = true;
	} // if (file_exists($db_config))
	
	if ($continue) {
	
		if (is_writable($base_path)) {
			// Build our filenames
	
		} else {
			$errormessage .= 'The installer cannot write to the base directory: <blockquote style="font-weight:bold">'.$base_path.'</blockquote>'.$write_perm_error;
		}
	
		if (is_writable($config_path)) {
			// Build our filenames
	
			// Make sure we have a config.php, we should.
			if (file_exists($ci_config)) {
				if (!is_writable($ci_config)) {
					$errormessage .= 'The installer cannot write to the configuration file: <blockquote style="font-weight:bold">'.$ci_config.'</blockquote>'.$write_perm_error;
				}
			} else {
				# Try to copy the file
				if (file_exists($def_ci_config)) {
					if (!copy($def_ci_config, $ci_config)) {
						$errormessage .= 'The installer was unable to create the <strong>config.php</strong> file from the default: <blockquote style="font-weight:bold">'.$def_ci_config.'</blockquote>'.$write_perm_error;
					}
				} else {		
					$errormessage .= 'The installer cannot find the file: <blockquote style="font-weight:bold">'.$def_ci_config.'</blockquote>';
				}
			}
	
			// Make sure we have a database.php, we should.
			if (file_exists($db_config)) {
				if (!is_writable($db_config)) {
					$errormessage .= 'The installer cannot write to the configuration file: <blockquote style="font-weight:bold">'.$db_config.'</blockquote>'.$write_perm_error;
				}
			} else {
				# Try to copy the file
				if (file_exists($def_db_config)) {
					if (!copy($def_db_config, $db_config)) {
						$errormessage .= 'The installer was unable to create the <strong>database.php</strong> file from the default: <blockquote style="font-weight:bold">'.$def_db_config.'</blockquote>'.$write_perm_error;
					}			
				} else {		
					$errormessage .= 'The installer cannot find the file: <blockquote style="font-weight:bold">'.$def_db_config.'</blockquote>';
				}			
			}
	
			// Make sure we have a macaw.php, if not create it from the default.
			if (file_exists($macaw_config)) {
				if (!is_writable($macaw_config)) {
					$errormessage .= 'The installer cannot write to the configuration file: <blockquote style="font-weight:bold">'.$macaw_config.'</blockquote>'.$write_perm_error;
				}
			} else {
				// try to create the macaw configuration file
				if (file_exists($macaw_def_config)) {
					if (!copy($macaw_def_config, $macaw_config)) {
						$errormessage .= 'The installer was unable to create the <strong>macaw.php</strong> file from the default: <blockquote style="font-weight:bold">'.$macaw_def_config.'</blockquote>'.$write_perm_error;
					}
				} else {
					$errormessage .= 'The installer cannot find the file: <blockquote style="font-weight:bold">'.$macaw_def_config.'</blockquote>';
				}
			}
		} else {
			$errormessage .= 'The installer cannot write to the configuration directory: <blockquote style="font-weight:bold">'.$config_path.'</blockquote>'.$write_perm_error;
		}
		
		
		// Verify system components
		// PHP Version
		$matches = array();
		if (!defined('PHP_VERSION_ID')) {
			$version = explode('.', PHP_VERSION);
			define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
		}
		if (PHP_VERSION_ID < 50300) {
			$errormessage .= 'PHP must be version 5.3 or higher. Current version is "'.PHP_VERSION.'".<br><br>';
		}
	
		// PHP ZIP
		$extensions = get_loaded_extensions();
		if (!in_array('zip', $extensions)) {
			$errormessage .= 'PHP <strong>zip</strong> extension not found. Please install it using PECL.<br><br>';
		}
	
		// PHP Archive_Tar
		if (!include('Archive/Tar.php')) {
			$errormessage .= 'PHP <strong>Archive_Tar</strong> extension not found. Please install it using PEAR.<br><br>';
		}
	
		// PHP XSL
		if (!in_array('xsl', $extensions)) {
			$errormessage .= 'PHP <strong>xs</strong>l extension not found. Please install it with apt, yum or other package manager (preferred), or recompile PHP using --with-xsl.<br><br>';
		}
	
		// PHP PgSQL
		if (!in_array('xsl', $extensions)) {
			$errormessage .= 'PHP <strong>pgsql</strong> extension not found. Please install it with apt, yum or other package manager (preferred), or recompile PHP using --with-pgsql.<br><br>';
		}
		
		// PHP Imagick
		if (!in_array('imagick', $extensions)) {
			$errormessage .= 'PHP <strong>imagick</strong> extension not found. Please install it with apt, yum or other package manager (preferred) or with PECL. <br><br>';
		}
	
		// These are a bit harder to determine. Skip them for now and hope for the best.
		// PostgreSQL
		// ImageMagick 6.5
		// Jasper
		// curl
		
	
		if (!$errormessage && $step == 0) {
			$step = 1;
		}
	
		if (!isset($_POST['submit'])) {$_POST['submit'] = '';}
	
		if ($_POST['submit'] == '<< Back') {
			$step -= 1;
		}
	
		if ($_POST['submit'] == 'Retry >>') {
			$step -= 1;
		}
	
		if ($step == 1) { // Database connection info
	
			// Get our existing info from the config file
			require_once($db_config);
	
			// Prepopulate the info on the page
			$db_dbdriver = $db['default']['dbdriver'];
			$db_hostname = $db['default']['hostname'];
			$db_username = $db['default']['username'];
			$db_password = $db['default']['password'];
			$db_database = $db['default']['database'];
			$db_port = '';
			if (isset($db['default']['port'])) { $db_port = $db['default']['port']; }
	
			if (isset($_POST['submit'])) {
				if ($_POST['submit'] == 'Next >>') {
					// Save whatever data was passed in
					set_config($db_config, "\$db['default']['hostname']", $_POST['database_host']);
					set_config($db_config, "\$db['default']['username']", $_POST['database_username']);
					set_config($db_config, "\$db['default']['password']", $_POST['database_password']);
					set_config($db_config, "\$db['default']['database']", $_POST['database_name']);
					set_config($db_config, "\$db['default']['dbdriver']", $_POST['database_type']);
					set_config($db_config, "\$db['default']['port']",     $_POST['database_port']);
	
					// Now test the database connection
					if ($_POST['database_type'] == 'postgre') {
						$conn = "host=".$_POST['database_host'].
								($_POST['database_port'] ? " port=".$_POST['database_port'] : '').
								" dbname=".$_POST['database_name'].
								" user=".$_POST['database_username'].
								" password=".$_POST['database_password'];
						$conn = pg_connect($conn);
						
						$db_created	= 0;
						if ($conn) {
							$result = @pg_query('select count(*) from account');
							if (!$result) {
								// The account table doesn't exist, so we go ahead and create the database
								$queries = file_get_contents($base_path.'/system/application/sql/macaw-pgsql.sql');
								$result = @pg_query($conn, $queries);
								if (!$result) {
									$errormessage = pg_last_error();
									$success = 0;
								} else {
									$db_created = 1;
									$success = 1;
									$step += 1;
									$done = 1;
								}
							} else {
								$success = 1;
								$step += 1;
								$done = 1;
							}
						} else {
							$errormessage = pg_last_error();
							if (!$errormessage) {
								$errormessage = "Unknown error connecting to database. Is it set up and is it running?";
							}
							$success = 0;
						} // if ($conn)
					} // if ($_POST['database_type'] == 'postgre')
				} // if ($_POST['submit'] == 'Next >>')
			} // if (isset($_POST['submit']))
		} // if ($step == 1)
	
		if ($step == 2 && !$done) { // Database initial setup
	
			if (isset($_POST['submit'])) {
				if ($_POST['submit'] == 'Next >>') {
					$step += 1;
					$_POST['submit'] = null;
				}
			}
	
		}
	
		if ($step == 3 && !$done) {
			// Admin name, password, email
	
			// Get the data from the database
			require_once($db_config);
			require_once($macaw_config);
	
			$conn = null;
	
			if ($db['default']['dbdriver'] == 'postgre') {
				$conn = "host=".$db['default']['hostname'].
					($db['default']['port'] ? " port=".$db['default']['port'] : '').
					" dbname=".$db['default']['database'].
					" user=".$db['default']['username'].
					" password=".$db['default']['password'];
	
				$conn = pg_connect($conn);
				$result = pg_query('select * from account where id = 1');
				$row = pg_fetch_assoc($result);
				// Fill in the fields for the administrator
				$admin_fullname = $row['full_name'];
				$admin_username = $row['username'];
				$admin_email = $config['macaw']['admin_email'];
				$admin_password = $row['password'];
				$organization_name = $config['macaw']['organization_name'];
			}
	
			if (isset($_POST['submit'])) {
				if ($_POST['submit'] == 'Next >>') {
					// Save whatever data was passed in
					set_config($macaw_config, "\$config['macaw']['admin_email']", $_POST['admin_email']);
					set_config($macaw_config, "\$config['macaw']['organization_name']", $_POST['organization_name']);
	
	
					// Set the data in the database
					$result = pg_query_params($conn, 'UPDATE account SET full_name = $1 WHERE id = 1', array($_POST['admin_full_name']));
	
					// Make sure that we get around the whole concept of null values and "variable not set" errors. Sheesh.
					if (!isset($_POST['admin_password'])) {$_POST['admin_password'] = '';}
					if (!isset($_POST['admin_password_c'])) {$_POST['admin_password_c'] = '';}
	
					// Make sure we get a password when we need one
					if (!$admin_password && !$_POST['admin_password']) {
						$errormessage = "You must enter a password and confirmation password.";
					}
					// Continue only if we didn't have an error
					if (!$errormessage) {
						if ($_POST['admin_password'] || $_POST['admin_password_c']) {
							// Make sure the passwords match
							if ($_POST['admin_password'] != $_POST['admin_password_c']) {
								$errormessage = "The passwords you entered do not match.";
							} else {
								// generate the new password hash
								$hasher = new PasswordHash(8, false);
								$pass_hash = $hasher->HashPassword($_POST['admin_password']);
								// Set the data in the database
								$result = pg_query_params($conn, 'UPDATE account SET password = $1 WHERE id = 1', array($pass_hash));
							}
						}
					}
	
					$done = 1;
	
					if (!$errormessage) {
						// Now determine what the next step is and get it displayed
						$step += 1;
						$_POST['submit'] = null;
					}
				}
			}
		}
	
		if ($step == 4 && !$done) {
	
			if (isset($_POST['submit'])) {
				if ($_POST['submit'] == 'Next >>') {
					$step += 1;
					$_POST['submit'] = null;
				}
			}
	
		}
	
		if ($step == 5 && !$done) {
			// Path to base, data and purge directories
			require_once($macaw_config);
			require_once($ci_config);
	
			$base_url = getBaseURL().'/';
			$incoming_path = $base_path."/incoming";
	
			if (isset($_POST['submit'])) {
				$success = true;
				
				if ($_POST['submit'] == 'Next >>') {
					
					// Save Changes
					set_config($ci_config,    "\$config['base_url']",                    $_POST['base_url']);
					set_config($macaw_config, "\$config['macaw']['base_directory']",     $_POST['base_path']);
					set_config($macaw_config, "\$config['macaw']['incoming_directory']", $_POST['incoming_path']);
					set_config($macaw_config, "\$config['macaw']['incoming_directory_remote']", $_POST['incoming_path']);
	
					// Set these in memory because we sort of need them for the next steps.
					$config['base_url']                    = $_POST['base_url'];
					$config['macaw']['base_directory']     = $_POST['base_path'];
					$config['macaw']['incoming_directory'] = $_POST['incoming_path'];
					$config['macaw']['incoming_directory_remote'] = $_POST['incoming_path'];
					$config['macaw']['data_directory']     = $_POST['base_path'].'/books';
					$config['macaw']['logs_directory']     = $_POST['base_path'].'/system/application/logs';
				}
				if ($_POST['submit'] == 'Next >>' || $_POST['submit'] == 'Retry >>') {
					// Verify access to the paths
					array_push($paths, array(
						'name' => 'Base URL',
						'path' => $config['base_url'],
						'success' => 1,
						'message' => 'OK.'
					));
	
	
					if (!file_exists($config['macaw']['base_directory'])) {
						array_push($paths, array(
							'name' => 'Base Directory',
							'path' => $config['macaw']['base_directory'],
							'success' => 0,
							'message' => 'Error! The path could not be found.'
						));
						$success = false;
					} else {
						array_push($paths, array(
							'name' => 'Base Directory',
							'path' => $config['macaw']['base_directory'],
							'success' => 1,
							'message' => 'Success!'
						));
					}
					
					if (!file_exists($config['macaw']['data_directory'])) {
						if (!@mkdir($config['macaw']['data_directory'])) {
							array_push($paths, array(
								'name' => 'Data Directory',
								'path' => $config['macaw']['data_directory'],
								'success' => 0,
								'message' => 'Error! The path could not be created.'
							));
							$success = false;
						} else {
							array_push($paths, array(
								'name' => 'Data Directory',
								'path' => $config['macaw']['data_directory'],
								'success' => 1,
								'message' => 'Success!'
							));					
						}
					} else {
						if (!is_writable($config['macaw']['data_directory'])) {
							array_push($paths, array(
								'name' => 'Data Directory',
								'path' => $config['macaw']['data_directory'],
								'success' => 0,
								'message' => 'Error! Could not write to this path. Please make sure the web server has read/write permissions.'
							));
							$success = false;
						} else {
							array_push($paths, array(
								'name' => 'Data Directory',
								'path' => $config['macaw']['data_directory'],
								'success' => 1,
								'message' => 'Success!'
							));
						}
					}
	
					if (!file_exists($config['macaw']['data_directory'].'/export')) {
						if (!@mkdir($config['macaw']['data_directory'].'/export')) {
							array_push($paths, array(
								'name' => 'Data Export Directory',
								'path' => $config['macaw']['data_directory'].'/export',
								'success' => 0,
								'message' => 'Error! The path could not be created.'
							));
							$success = false;
						} else {
							array_push($paths, array(
								'name' => 'Data Export Directory',
								'path' => $config['macaw']['data_directory'].'/export',
								'success' => 1,
								'message' => 'Success!'
							));					
						}
					} else {
						if (!is_writable($config['macaw']['data_directory'].'/export')) {
							array_push($paths, array(
								'name' => 'Data Export Directory',
								'path' => $config['macaw']['data_directory'].'/export',
								'success' => 0,
								'message' => 'Error! Could not write to this path. Please make sure the web server has read/write permissions.'
							));
							$success = false;
						} else {
							array_push($paths, array(
								'name' => 'Data Export Directory',
								'path' => $config['macaw']['data_directory'].'/export',
								'success' => 1,
								'message' => 'Success!'
							));
						}
					}
	
	
					if (!file_exists($config['macaw']['logs_directory'])) {
						array_push($paths, array(
							'name' => 'Logs Directory',
							'path' => $config['macaw']['logs_directory'],
							'success' => 0,
							'message' => 'Error! The path could not be found.'
						));
						$success = false;
					} else {
						if (!is_writable($config['macaw']['logs_directory'])) {
							array_push($paths, array(
								'name' => 'Logs Directory',
								'path' => $config['macaw']['logs_directory'],
								'success' => 0,
								'message' => 'Error! Could not write to this path. Please make sure the web server has read/write permissions.'
							));
							$success = false;
						} else {
							array_push($paths, array(
								'name' => 'Logs Directory',
								'path' => $config['macaw']['logs_directory'],
								'success' => 1,
								'message' => 'Success!'
							));
						}
					}
	
					if (!file_exists($config['macaw']['incoming_directory'])) {
						if (!@mkdir($config['macaw']['incoming_directory'])) {
							array_push($paths, array(
								'name' => 'Incoming Directory',
								'path' => $config['macaw']['incoming_directory'],
								'success' => 0,
								'message' => 'Error! The path could not be created.'
							));
							$success = false;
						} else {
							array_push($paths, array(
								'name' => 'Incoming Directory',
								'path' => $config['macaw']['incoming_directory'],
								'success' => 1,
								'message' => 'Success!'
							));					
						}
					} else {
						if (!is_writable($config['macaw']['incoming_directory'])) {
							array_push($paths, array(
								'name' => 'Incoming Directory',
								'path' => $config['macaw']['incoming_directory'],
								'success' => 0,
								'message' => 'Error! Could not write to this path. Please make sure the web server has read/write permissions.'
							));
							$success = false;
						} else {
							array_push($paths, array(
								'name' => 'Incoming Directory',
								'path' => $config['macaw']['incoming_directory'],
								'success' => 1,
								'message' => 'Success!'
							));
						}
					}
	
					$done = 1;
	
					if (!$errormessage) {
						// Now determine what the next step is and get it displayed
						$step += 1;
						$_POST['submit'] = null;
					}
				}
			}
		}
	
		if ($step == 6 && !$done) {
	
			if (isset($_POST['submit'])) {
				if ($_POST['submit'] == 'Next >>') {
					$step += 1;
					$_POST['submit'] = null;
				}
			}
	
		}
	
		if ($step == 7 && !$done) {
			require_once($macaw_config);
			require_once($ci_config);
			require_once($db_config);
	
			if ($db['default']['dbdriver'] == 'postgre') {
				$database_driver = 'PostgreSQL';
				$conn = "host=".$db['default']['hostname'].
					($db['default']['port'] ? " port=".$db['default']['port'] : '').
					" dbname=".$db['default']['database'].
					" user=".$db['default']['username'].
					" password=".$db['default']['password'];
	
				$conn = pg_connect($conn);
				$result = pg_query('select * from account where id = 1');
				$row = pg_fetch_assoc($result);
				// Fill in the fields for the administrator
				$admin_fullname = $row['full_name'];
				$admin_username = $row['username'];
				$admin_email = $config['macaw']['admin_email'];
				$admin_password = $row['password'];
				
				$install_file = $config['macaw']['base_directory'].'/install.php';
				rename($install_file, $install_file.'.delete');
			}
		}
	}
	
	function set_config($file, $setting, $value) {
		// Open the file, read into an array
		if (file_exists($file.'.new')) {
			$arrFile = file($file.'.new');
		} else {
			$arrFile = file($file);
		}

		// Open our destination file, appended with ".new"
		$fh = fopen($file.'.new', 'w');

		$found = 0;
		// Read lines from the file, searching for what we want.
		foreach ($arrFile as $l) {
			$l = trim($l);
			// We always skip comments
			if (preg_match('/^#|\s+#/', $l)) {
				fwrite($fh, $l.eol());
			} else {
				// Is our setting somewhere in there?
				$pos = strpos($l, $setting);
				if ($pos === false) {
					// No, so we output whatever line we read (blank lines, nonmatching lines, etc
					fwrite($fh, $l.eol());
				} elseif ($pos < 11) {
					// We found it, so we replace with the new setting
					fwrite($fh, $setting.' = "'.$value.'";'.eol());
					$found = 1;
				} else {
					fwrite($fh, $l.eol());
				}
			}
		}
		// If we didn't find it, we append it to the end
		if (!$found) {
			fwrite($fh, $setting.' = "'.$value.'";'.eol());
		}
		fclose($fh);

		// Rename the new into the old and we are done. yay!
		rename($file.".new", $file);
	}

	function getBaseURL() {
		$pageURL = 'http';
		if (isset($_SERVER["HTTPS"])) {
			if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
		}
		$pageURL .= "://";
		if ($_SERVER["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"];
		}
		$path = $_SERVER['REQUEST_URI'];
		$path = preg_replace('/\/install.php/', '', $path);
		return $pageURL.$path;
	}

	function eol() {
		$os = get_os_type();
		if ($os == 'win') {
			return "\r\n";
		} else {
			return "\n";
		}
	}

	function get_os_type() {
		$ua = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($ua, 'Windows')) {
			return "win";
		} elseif (strpos($ua, 'OS X')) {
			return "osx";
		} else {
			return "nix";
		}
	}
?>

	<style type="text/css">
		#bd {min-height:100px}
		.bd {min-height:300px;}
		.yui-panel {width:100%; visibility:inherit;}
		.step {font-size:120%;font-weight:bold;padding:10px;color:#999999;}
		.active {color:#000000;border:1px solid #0099CC;background-color:#ffffff;}
		.yui-skin-sam .yui-panel .hd {font-size:15px;color:#0099CC;}
		.yui-skin-sam .yui-panel .bd {background-color:#ffffff;}
		.success {font-size:16px;font-weight:bold;color:#009900;margin-bottom:10px}
		.error {border:1px solid #990000;font-weight:bold;color:#CC0000;background-color:#FFCCCC;padding:15px;}
		.warning {border:1px solid #F30;color:#333;background-color:#FC9;padding:15px;margin-bottom:10px;}
		.failure {font-size:16px;font-weight:bold;color:#990000;}
		.small {font-size:100%;}
		h3 {color:#666666;}
		.errormessage {font-size:100%;margin-bottom:10px;}
		.grey {
			color: #999999; font-size: 90%;
		}
	</style>
</head>
<body class="yui-skin-sam">
	<div id="doc3" class="yui-t7">
		<div id="hd" role="banner">
			<img src="images/logo.png" alt="logo.png" width="110" height="110" border="0" align="left" id="logo">
			<div id="title">
				<? if ($version_rev == 'VERSION_GOES_HERE') { ?>
					<h1>Macaw <div style="color:#C60;float:right;">DEVELOPMENT VERSION</div></h1>
					<h2>Metadata Collection and Workflow System</h2>
					<h3>Demo / Development Version</h3>
				<? } else { ?>
					<h1>Macaw</h1>
					<h2>Metadata Collection and Workflow System</h2>
					<h3>Version <? echo($version_rev); ?> / <? echo($version_date); ?></h3>
				<? } ?>
			</div>
		</div>
		<div id="bd" role="main" style="min-height:auto">
			<div class="yui-gd">
				<div class="yui-u first">
					<div class="yui-module yui-overlay yui-panel" style="width: 100%; visibility: inherit;">
						<div class="hd">
							Installation Steps
						</div>
						<div class="bd" style="background-color: #f2f2f2;">
							<div class="step<? if($step == 1) {echo(" active");} ?>">1. Database Connection</div>
							<div class="step<? if($step == 2) {echo(" active");} ?>">2. Database Initialization</div>
							<div class="step<? if($step == 3) {echo(" active");} ?>">3. Administrator Setup</div>
							<div class="step<? if($step == 4) {echo(" active");} ?>">4. Administrator Review</div>
							<div class="step<? if($step == 5) {echo(" active");} ?>">5. File and URL Locations</div>
							<div class="step<? if($step == 6) {echo(" active");} ?>">6. File Locations Review</div>
							<div class="step<? if($step == 7) {echo(" active");} ?>">7. Finished</div>
						</div>
					</div>
				</div>
				<div class="yui-u">
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 0) {echo('style="display: none;"');} ?>>
						<div class="hd">Preliminary Checkup</div>
						<div class="bd">
							<?
							if ($errormessage) {
								echo ('<div class="errormessage">'.$errormessage.'</div>');
							} elseif ($message) {
								echo ('<div class="message">'.$message.'</div>');
							}
							?>
						</div>
					</div>
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 1) {echo('style="display: none;"');} ?>>
						<div class="hd">Step 1: Database Connection</div>
						<div class="bd">
							<?
							if ($errormessage) {
								echo ('<div class="errormessage">'.$errormessage.'</div>');
							} elseif ($message) {
								echo ('<div class="message">'.$message.'</div>');
							}
							?>
							Now tell us about your database server. The database and the database user account need to exist already, we can't create
							them for you.
							<br><br>
<!--
							If you don't have a database server set up, you can choose SQLite and we will create the database for you.
							(SQLite is not yet implemented, please use PostgreSQL.)
 -->
							<form action="install.php" method="post">
								<input type="hidden" name="step" value="1">
								<table border="0" cellspacing="0" cellpadding="2">
									<tr>
										<td>Database Type:</td>
										<td>
											<select name="database_type">
													<option value="postgre" <? if ($db_dbdriver == 'postgre') { echo('selected'); } ?>>PostgreSQL</option>
											</select>
										</td>
									</tr>
									<tr>
										<td>Database Name:</td>
										<td><input type="text" name="database_name" value="<? echo($db_database); ?>" maxlength="64"></td>
									</tr>
									<tr>
										<td>Database Username:</td>
										<td><input type="text" name="database_username" value="<? echo($db_username); ?>" maxlength="64"></td>
									</tr>
									<tr>
										<td>Database Password:</td>
										<td><input type="password" name="database_password" value="<? echo($db_password); ?>" maxlength="64"></td>
									</tr>
									<tr>
										<td>Database Host:</td>
										<td><input type="text" name="database_host" value="<? echo($db_hostname); ?>" maxlength="64"></td>
									</tr>
									<tr>
										<td>Database Port:</td>
										<td><input type="text" name="database_port" value="<? echo($db_port); ?>" maxlength="64"> (optional)</td>
									</tr>
								</table>
								<div style="float:right">
									<input type="submit" name="submit" value="Next &gt;&gt;">
								</div>
								<div class="clear"><!-- --></div>
							</form>
						</div>
					</div>
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 2) {echo('style="display: none;"');} ?>>
						<div class="hd">Step 2: Database Initialization</div>
						<div class="bd">
							<form action="install.php" method="post">
								<input type="hidden" name="step" value="2">
								<div style="margin-bottom: 10px">
									<? if ($success) { ?>
										<p class="success">Success!</p>
										<? if ($db_created) { ?>
											<p>Your database was created successfully.</p>
										<? } else { ?>
											<p>We were able to connect to your database successfully. Your database already exists, so there's nothing
											more that we need to do here.</p>
										<? } ?>
									<?  } else { ?>
										<p class="failure">We had a problem...</p>
										<p>We had some trouble connecting to or creating your database. The exact message is:</p>
										<div class="errormessage">
											<? echo($errormessage) ?>
										</div>
									<? } ?>
									<br>
								</div>
								<div style="float:left">
									<input type="submit" name="submit" value="&lt;&lt; Back">
								</div>
								<? if($success) { ?>
								<div style="float:right">
									<input type="submit" name="submit" value="Next &gt;&gt;">
								</div>
								<? } ?>
								<div class="clear"><!-- --></div>
							</form>
						</div>
					</div>
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 3) {echo('style="display: none;"');} ?>>
						<div class="hd">Step 3: Additional Information and Administrator Setup</div>
						<div class="bd">
							<? if ($errormessage) {
									echo ('<div class="errormessage">'.$errormessage.'</div>');
								} ?>
							<p>Next, let's entering some information about the you and your organization.</p>
							<form action="install.php" method="post">
								<input type="hidden" name="step" value="3">
								<table border="0" cellspacing="0" cellpadding="2">
									<tr>
										<td>Organization Name:</td>
										<td><input type="text" name="organization_name" value="<? echo($organization_name); ?>" maxlength="64"></td>
									</tr>
									<tr>
										<td>Full Name:</td>
										<td><input type="text" name="admin_full_name" value="<? echo($admin_fullname); ?>" maxlength="64"></td>
									</tr>
									<tr>
										<td>Email Address:</td>
										<td><input type="text" name="admin_email" value="<? echo($admin_email); ?>" maxlength="64"></td>
									</tr>
									<tr>
										<td>Username:</td>
										<td><? echo($admin_username); ?></td>
									</tr>
									<tr>
										<td>Password:</td>
										<td><input type="password" name="admin_password" value="" maxlength="64"></td>
									</tr>
									<tr>
										<td>Confirm Password:</td>
										<td><input type="password" name="admin_password_c" value="" maxlength="64"></td>
									</tr>
								</table>
								<div style="float:left">
									<input type="submit" name="submit" value="&lt;&lt; Back">
								</div>
								<? if($success) { ?>
								<div style="float:right">
									<input type="submit" name="submit" value="Next &gt;&gt;">
								</div>
								<? } ?>
								<div class="clear"><!-- --></div>
							</form>
						</div>
					</div>
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 4) {echo('style="display: none;"');} ?>>
						<div class="hd">Step 4: Administrator Review</div>
						<div class="bd">
							<form action="install.php" method="post">
								<input type="hidden" name="step" value="4">
								<div style="margin-bottom: 10px">
									<? if ($success) { ?>
										<p class="success">Success!</p>
										<p>Administrator settings were saved correctly.</p>
									<?  } else { ?>
										<p class="failure">We had a problem...</p>
										<p>We had some trouble saving the administrator settings.</p>
										<div class="errormessage">
											<? echo($errormessage) ?>
										</div>
									<? } ?>
									<br>
								</div>
								<div style="float:left">
									<input type="submit" name="submit" value="&lt;&lt; Back">
								</div>
								<? if($success) { ?>
								<div style="float:right">
									<input type="submit" name="submit" value="Next &gt;&gt;">
								</div>
								<? } ?>
								<div class="clear"><!-- --></div>
							</form>
						</div>
					</div>
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 5) {echo('style="display: none;"');} ?>>
						<div class="hd">Step 5: File and URL Locations</div>
						<div class="bd">
							Please verify the following information.<br><br>
							<form action="install.php" method="post">
								<input type="hidden" name="step" value="5">
								<table border="0" cellspacing="0" cellpadding="2">
									<tr>
										<td valign="top">Base Path:</td>
										<td>
											<input type="text" name="base_path" value="<? echo($base_path); ?>" maxlength="1024" style="width: 450px;">
											<div class="grey">This should already be correct and should not be changed.</div>
										</td>
									</tr>
									<tr>
										<td valign="top">Base URL:</td>
										<td>
											<input type="text" name="base_url" value="<? echo($base_url); ?>" maxlength="1024" style="width: 450px">
											<div class="grey">Must include "http://". This is accurate but it may need to be updated to a fully-qualified domain name.</div>
										</td>
									</tr>
									<tr>
										<td valign="top">Incoming Directory:</td>
										<td>
											<input type="text" name="incoming_path" value="<? echo($incoming_path); ?>" maxlength="1024" style="width: 450px">
											<div class="grey">This is where Macaw will look for new pages for books. Must be an absolute path on the server.</div>
										</td>
									</tr>
								</table>
								<div style="float:left">
									<input type="submit" name="submit" value="&lt;&lt; Back">
								</div>
								<? if($success) { ?>
								<div style="float:right">
									<input type="submit" name="submit" value="Next &gt;&gt;">
								</div>
								<? } ?>
								<div class="clear"><!-- --></div>
							</form>
						</div>
					</div>
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 6) {echo('style="display: none;"');} ?>>
						<div class="hd">Step 6: File Locations Review</div>
						<div class="bd">
							<form action="install.php" method="post">
								<input type="hidden" name="step" value="6">
								<div style="margin-bottom: 10px">
									<? foreach ($paths as $p) { ?>
										<p>
											<strong><? echo($p['name']) ?>:</strong> <? echo($p['path']) ?><br>
											<strong>Status: </strong>
											<span class="<? echo($p['success'] ? 'success' : 'failure')?> small"><? echo($p['message']) ?></span>
										</p>
									<? } ?>
									<br>
								</div>
								<div style="float:left">
									<input type="submit" name="submit" value="&lt;&lt; Back">
								</div>
								<? if($success) { ?>
								<div style="float:right">
									<input type="submit" name="submit" value="Next &gt;&gt;">
								</div>
								<? } else {?>
								<div style="float:right">
									<input type="submit" name="submit" value="Retry &gt;&gt;">
								</div>
								
								<? } ?>
								<div class="clear"><!-- --></div>
							</form>
						</div>
					</div>
					<div class="yui-module yui-overlay yui-panel" <? if ($step != 7) {echo('style="display: none;"');} ?>>
						<div class="hd">Setup Complete!</div>
						<div class="bd">
							<div style="margin-bottom: 10px">
								<div class="success">Macaw is set up and ready to go!</div>

								<h1><a href="<? echo($config['base_url']); ?>"><? echo($config['base_url']); ?></a></h1>

								<p>
									Below is a summary of the settings you made. Other settings may be adjusted in the <strong>/system/application/config/macaw.php</strong> file.
									<blockquote>
										<h3>Administrator Information</h3>
										<blockquote>
											<strong>Full Name:</strong> <? echo($admin_fullname); ?><br>
											<strong>Username:</strong> admin<br>
											<strong>Password:</strong> **********
										</blockquote>

										<h3>Databsase Information</h3>
										<blockquote>
											<strong>Type:</strong> <? echo($database_driver); ?><br>
											<strong>Host:</strong> <? echo($db['default']['hostname']); ?> <br>
											<strong>Port:</strong> <? echo($db['default']['port'] ? $db['default']['port'] : 5432); ?> <br>
											<strong>Database Name:</strong> <? echo($db['default']['database']); ?><br>
											<strong>Username:</strong> <? echo($db['default']['username']); ?><br>
											<strong>Password:</strong> **********
										</blockquote>

										<h3>Paths</h3>
										<blockquote>
											<strong>Base URL:</strong> <? echo($config['base_url']); ?><br>
											<strong>Base Directory:</strong> <? echo($config['macaw']['base_directory']); ?><br>
											<strong>Data Directory:</strong> <? echo($config['macaw']['data_directory']); ?><br>
											<strong>Incoming Directory:</strong> <? echo($config['macaw']['incoming_directory']); ?><br>
											<strong>Logs Directory:</strong> <? echo($config['macaw']['logs_directory']); ?><br>
										</blockquote>
									</blockquote>
								</p>

								<div class="warning">
									<span style="color:#990000;font-weight:bold;">IMPORTANT:</span> The file <strong><? echo($config['macaw']['base_directory']); ?>/install.php</strong> has been deleted for security reasons.<br><br>
									You may also remove write permissions to the configuration directory: <strong><? echo($config['macaw']['base_directory']); ?>/system/application/config</strong>
								</div>

							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>


