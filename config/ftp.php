<?php defined('SYSPATH') or die('No direct access allowed.');

return array
(
	'default' => array
	(
		/**
		 * The following options are available for FTP:
		 *
		 * string	host		server hostname
		 * string	username	server username
		 * string	password	server password
		 * int		port     	server port
		 * boolean	passive		use passive connections?
		 *
		 */
		'host'		=> 'ftp.example.com',
		'user'		=> 'your-username',
		'password'	=> 'your-password',
		'port'		=> 21,
		'passive'	=> TRUE,
		'ssh'		=> FALSE,
		'timeout'	=> 90,
	),
);