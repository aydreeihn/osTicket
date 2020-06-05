<?php
require_once('../main.inc.php');
if(!defined('INCLUDE_DIR')) die('Fatal Error. Kwaheri!');

// Bootstrap gettext translations. Since no one is yet logged in, use the
// system or browser default
TextDomain::configureForUser();

require_once(INCLUDE_DIR.'class.staff.php');
require_once(INCLUDE_DIR.'class.csrf.php');

$tpl = 'phar://' . INCLUDE_DIR . '/plugins/google_2fa.phar/templates/agent-login.tmpl.php';
$msg = $_SESSION['_staff']['auth']['msg']
    ?: __('Enter the code shown in your Google Authenticator app below.');

Signal::send('google2fa.login', $_POST);

define("OSTSCPINC",TRUE); //Make includes happy!
include_once($tpl);
