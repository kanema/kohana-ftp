Kohana FTP
====================

Kohana FTP is inspired in CodeIgniter FTP Class and permits files to be transfered to a remote server. Remote files can also be moved, renamed, and deleted.
The FTP class also includes a "mirroring" function that permits an entire local directory to be recreated remotely via FTP.

Examples
-------------------------

**Upload**
Uploads a file to your server. You must supply the local path and the remote path, and you can optionally set the mode and permissions. Example:
	FTP::instance()->upload('/local/path/to/myfile.html', '/public_html/myfile.html', 'ascii', 0775);

**Download**
Downloads a file from your server. You must supply the remote path and the local path, and you can optionally set the mode. Example:
	FTP::instance()->download('/public_html/myfile.html', '/local/path/to/myfile.html', 'ascii');

**Rename**
Permits you to rename a file. Supply the source file name/path and the new file name/path.
	FTP::instance()->rename('/public_html/foo/green.html', '/public_html/foo/blue.html'); 

**Move**
Lets you move a file. Supply the source and destination paths:
	FTP::instance()->move('/public_html/joe/blog.html', '/public_html/fred/blog.html'); 

**Delete File**
Lets you delete a file. Supply the source path with the file name.
	FTP::instance()->delete_file('/public_html/joe/blog.html'); 

**Delete Dir**
Lets you delete a directory and everything it contains. Supply the source path to the directory with a trailing slash.
	FTP::instance()->delete_dir('/public_html/path/to/folder/'); 

**List Files**
Permits you to retrieve a list of files on your server returned as an array. You must supply the path to the desired directory.
	$list = FTP::instance()->list_files('/public_html/');
	print_r($list); 

**Mirror**
Recursively reads a local folder and everything it contains (including sub-folders) and creates a mirror via FTP based on it. Whatever the directory structure of the original file path will be recreated on the server. You must supply a source path and a destination path:
	FTP::instance()->mirror('/path/to/myfolder/', '/public_html/myfolder/');

**mkdir**
Lets you create a directory on your server. Supply the path ending in the folder name you wish to create, with a trailing slash. Permissions can be set by passed an octal value in the second parameter (if you are running PHP 5).
	FTP::instance()->mkdir('/public_html/foo/bar/', DIR_WRITE_MODE);

**chmod**
Permits you to set file permissions. Supply the path to the file or folder you wish to alter permissions on:
	FTP::instance()->chmod('/public_html/foo/bar/', DIR_WRITE_MODE);

**file_exists**
Return true if the file exist
	FTP::instance()->file_exists('/public_html/joe/blog.html'); 

**file_size**
Return the file size in bytes
	FTP::instance()->file_size('/public_html/joe/blog.html');

**systype**
Return the system type of server
	FTP::instance()->systype(); 

**timeout**
Set a timeout to request (default is 90 seconds)
	FTP::instance()->timeout(60); 
	

Supported ftp methods
-------------------------

	factory( string $config = "default" )

	instance( string $config = "default" )

	changedir( [ string $path = string(0) "" , bool $supress_debug = bool FALSE ] )

	chmod( string $path , string $perm )

	close()

	delete_dir( string $filepath ) 

	delete_file( string $filepath ) 

	download( string $rempath , string $locpath [, string $mode = string(4) "auto" ] ) 

	file_exists($filepath = '.')
	
	file_size($filepath = '.')
	
	list_files( $path = '.', $details = FALSE )

	mirror( string $locpath , string $rempath )

	mkdir( [ string $path = string(0) "" , $permissions = NULL ] )

	move( string $old_file , string $new_file )

	rename( string $old_file , string $new_file [, bool $move = bool FALSE ] )

	upload( string $locpath , string $rempath [, string $mode = string(4) "auto" , $permissions = NULL ] )
	
Config file `config/ftp.php`
-------------------------

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
