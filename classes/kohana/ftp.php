<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Database object creation helper methods.
 *
 * @package    Kohana/Ftp
 * @category   Network
 * @author     Eduardo Pacheco
 * @copyright  (c) 2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kohana_Ftp {

	// Config
	public $config = array();
	
	// Connection id
	protected $conn_id = NULL;
	
	// Connection status
	public $connected = FALSE;
	
	// Singleton static instance
	protected static $_instance = array();
	
	/**
	 * Constructor
	 *
	 * Detect if the FTP extension loaded
	 *
	 */
	public function __construct()
	{
		if ( ! extension_loaded('ftp') ) {
			throw new Kohana_Exception("PHP extension FTP is not loaded.");
		}
	}

	/**
	 * Get the singleton instance of Kohana_Ftp.
	 *
	 *     $config = FTP::instance();
	 *
	 * @return  Kohana_FTP
	 */
	public static function instance( $config = "default" )
	{
		if ( ! isset( self::$_instance[$config] ) )
		{
			// Create a new instance
			self::$_instance[$config] = self::factory( $config );
		}

		return self::$_instance[$config];
	}
	
	/**
	 * Get the Kohana_Ftp.
	 *
	 *     $config = FTP::factory();
	 *
	 * @return  Kohana_FTP
	 */
	public static function factory( $config = "default" )
	{
		$ftp = new FTP();
		$file = Kohana::config("ftp");
		if ( isset( $file->$config ) )
		{
			$ftp->config = $file->$config;
		};
		return $ftp;
	}
	
	/**
	 * Magic config
	 *
	 *     FTP::factory()->
	 *			host('ftp://site.com')->
	 *			user('my-user')->
	 * 		password('my-pass')->
	 *			list_files();
	 *
	 */
	public function __call($name, $args = array())
	{
		$pattern = '/^(host|user|password|port|passive)$/i';
		if ( isset($args[0]) && is_array( $args[0] ) )
		{
			foreach ($args[0] as $key => $value)
			{
				if (preg_match($pattern, $key))
				{
					$this->config[$key] = $value;
					return $this;
				};
			};
		};
		if ( preg_match($pattern, $name) )
		{
			$this->config[$name] = $args[0];
			return $this;
		};
		if ( preg_match('/^timeout$/', $name) )
		{
			$this->config['timeout'] = $args[0];
			return $this;
		};
	}

	/**
	 * FTP Connect
	 *
	 * @access	public
	 * @param	array	 the connection values
	 * @return	bool
	 */
	public function connect()
	{
		if ( TRUE === $this->connected )
		{
			return TRUE;
		};
		
		if ( empty( $this->config ) )
		{
			throw new Kohana_Exception('FTP config not set.');
		};
		
		$this->config['port'] = ( isset( $this->config['port'] ) ) ? $this->config['port'] : 21;
		
		if ( ! isset( $this->config['host'] ) )
		{
			throw new Kohana_Exception('FTP host not set.');
		};
		
		$this->config["ssh"] = ( isset( $this->config["ssh"] ) ) ? $this->config["ssh"] : FALSE;
		$this->config["timeout"] = ( isset( $this->config["time"] ) ) ? $this->config["timeout"] : 90;
		
		if ( TRUE === $this->config["ssh"] && FALSE === ($this->conn_id = @ftp_ssl_connect($this->config['host'], $this->config['port'], $this->config["timeout"])))
		{
			throw new Kohana_Exception('FTP unable to ssh connect');
		} else if (FALSE === ($this->conn_id = @ftp_connect($this->config['host'], $this->config['port'], $this->config["timeout"])))
		{
			throw new Kohana_Exception('FTP unable to connect');
		};

		if ( ! $this->_login())
		{
			throw new Kohana_Exception('FTP unable to login');
			return FALSE;
		};

		// Set passive mode if needed
		if ( ! isset( $this->config['passive'] ) || $this->config['passive'] === TRUE )
		{
			ftp_pasv($this->conn_id, TRUE);
		};

		return $this->connected = TRUE;
	}

	/**
	 * FTP Login
	 *
	 * @access	private
	 * @return	bool
	 */
	private function _login()
	{
		$this->config['user'] = ( $this->config['user'] ) ? $this->config['user'] : NULL;
		$this->config['password'] = ( $this->config['password'] ) ? $this->config['password'] : NULL;
		return @ftp_login($this->conn_id, $this->config['user'], $this->config['password']);
	}

	/**
	 * Validates the connection ID
	 *
	 * @access	private
	 * @return	bool
	 */
	private function _is_conn()
	{
		$this->connect();
		if ( ! is_resource($this->conn_id) )
		{
			throw new Kohana_Exception('FTP no connection');
		};
		
		return TRUE;
	}


	/**
	 * Change directory
	 *
	 * The second parameter lets us momentarily turn off debugging so that
	 * this function can be used to test for the existence of a folder
	 * without throwing an error.  There's no FTP equivalent to is_dir()
	 * so we do it by trying to change to a particular directory.
	 * Internally, this parameter is only used by the "mirror" function below.
	 *
	 * @access	public
	 * @param	string
	 * @param	bool
	 * @return	bool
	 */

	public function changedir($path = '', $supress_debug = FALSE)
	{
		if ($path === '' OR ! $this->_is_conn())
		{
			return FALSE;
		};

		$result = @ftp_chdir($this->conn_id, $path);

		if ($result === FALSE && $supress_debug === TRUE)
		{	
			throw new Kohana_Exception('FTP unable to changedir :dir',
				array(':dir' => Kohana::debug_path($path))
			);
		};

		return TRUE;
	}

	/**
	 * Create a directory
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function mkdir($path = '', $permissions = NULL)
	{
		if ($path === '' OR ! $this->_is_conn())
		{
			return FALSE;
		};

		$result = @ftp_mkdir($this->conn_id, $path);

		if ($result === FALSE)
		{
			throw new Kohana_Exception('FTP unable to makdir :dir',
				array(':dir' => Kohana::debug_path($path))
			);
		};

		// Set file permissions if needed
		if ( ! is_null($permissions))
		{
			$this->chmod($path, (int) $permissions);
		};

		return TRUE;
	}

	/**
	 * Upload a file to the server
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function upload($locpath, $rempath, $mode = 'auto', $permissions = NULL)
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		};

		if ( ! file_exists($locpath) )
		{
			throw new Kohana_Exception('FTP no source file');
		};

		// Set the mode if not specified
		if ($mode === 'auto')
		{
			// Get the file extension so we can set the upload type
			$ext = $this->_getext($locpath);
			$mode = $this->_settype($ext);
		};
		
		if ( ftp_alloc( $this->conn_id, filesize($locpath), $result) ) {
			throw new Kohana_Exception('Unable to allocate space on server. Server said: :result',
				array(':result' => $result )
			);
		}

		$mode = ($mode === 'ascii') ? FTP_ASCII : FTP_BINARY;

		$result = @ftp_put($this->conn_id, $rempath, $locpath, $mode);

		if ($result === FALSE)
		{
			throw new Kohana_Exception('FTP unable to upload');
		};

		// Set file permissions if needed
		if ( ! is_null($permissions))
		{
			$this->chmod($rempath, (int) $permissions);
		};

		return TRUE;
	}

	/**
	 * Download a file from a remote server to the local server
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function download($rempath, $locpath, $mode = 'auto')
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		}

		// Set the mode if not specified
		if ($mode === 'auto')
		{
			// Get the file extension so we can set the upload type
			$ext = $this->_getext($rempath);
			$mode = $this->_settype($ext);
		}

		$mode = ($mode === 'ascii') ? FTP_ASCII : FTP_BINARY;

		$result = @ftp_get($this->conn_id, $locpath, $rempath, $mode);

		if ($result === FALSE)
		{
			throw new Kohana_Exception('FTP unable to download');
		}

		return TRUE;
	}

	/**
	 * Rename (or move) a file
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	bool
	 * @return	bool
	 */
	public function rename($old_file, $new_file, $move = FALSE)
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		}

		$result = @ftp_rename($this->conn_id, $old_file, $new_file);

		if ($result === FALSE)
		{
			$msg = ($move === FALSE) ? 'rename' : 'move';

			throw new Kohana_Exception('FTP unale to :msg',
				array(':mover', $mover)
			);
		}

		return TRUE;
	}

	/**
	 * Move a file
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function move($old_file, $new_file)
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		};
		return $this->rename($old_file, $new_file, TRUE);
	}

	/**
	 * Rename (or move) a file
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function delete_file($filepath)
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		}

		$result = @ftp_delete($this->conn_id, $filepath);

		if ($result === FALSE)
		{
			throw new Kohana_Exception('FTP unable to delete');
		}

		return TRUE;
	}

	/**
	 * Delete a folder and recursively delete everything (including sub-folders)
	 * containted within it.
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function delete_dir($filepath)
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		}

		// Add a trailing slash to the file path if needed
		$filepath = preg_replace("/(.+?)\/*$/", "\\1/",  $filepath);

		$list = $this->list_files($filepath);

		if ($list !== FALSE AND count($list) > 0)
		{
			foreach ($list as $item)
			{
				// If we can't delete the item it's probaly a folder so
				// we'll recursively call delete_dir()
				if ( ! @ftp_delete($this->conn_id, $item))
				{
					$this->delete_dir($item);
				}
			}
		}

		$result = @ftp_rmdir($this->conn_id, $filepath);

		if ($result === FALSE)
		{
			throw new Kohana_Exception('FTP unable to delete');
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Set file permissions
	 *
	 * @access	public
	 * @param	string	the file path
	 * @param	string	the permissions
	 * @return	bool
	 */
	public function chmod($path, $perm)
	{
		if ( ! $this->_is_conn() )
		{
			return FALSE;
		};

		// Permissions can only be set when running PHP 5
		if ( ! function_exists('ftp_chmod'))
		{
			throw new Kohana_Exception('FTP unable to chmod');
		};

		$result = @ftp_chmod($this->conn_id, $perm, $path);

		if ($result === FALSE)
		{
			throw new Kohana_Exception('FTP unable to chmod');
		};

		return TRUE;
	}
	
	// :: JaZz phpFramework
	// :: V1.1
	// :: 13/03/2009 15:35:22
	function parse_ftp_rawlist($List, $Win = false)
	{
	  $Output = array();
	  $i = 0;
	  if ($Win) {
		foreach ($List as $Current) {
		  ereg('([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|) +(.+)', $Current, $Split);
		  if (is_array($Split)) {
			if ($Split[3] < 70) {
			  $Split[3] += 2000;
			}
			else {
			  $Split[3] += 1900;
			}
			$Output[$i]['isdir']     = ($Split[7] == '');
			$Output[$i]['size']      = $Split[7];
			$Output[$i]['month']     = $Split[1];
			$Output[$i]['day']       = $Split[2];
			$Output[$i]['time/year'] = $Split[3];
			$Output[$i]['name']      = $Split[8];
			$i++;
		  }
		}
		return !empty($Output) ? $Output : false;
	  }
	  else {
		foreach ($List as $Current) {
		  $Spli = preg_split('[ ]', $Current, 9, PREG_SPLIT_NO_EMPTY);
		  if ($Split[0] != 'total') {
			$Output[$i]['isdir']     = ($Split[0] {0} === 'd');
			$Output[$i]['perms']     = $Split[0];
			$Output[$i]['number']    = $Split[1];
			$Output[$i]['owner']     = $Split[2];
			$Output[$i]['group']     = $Split[3];
			$Output[$i]['size']      = $Split[4];
			$Output[$i]['month']     = $Split[5];
			$Output[$i]['day']       = $Split[6];
			$Output[$i]['time/year'] = $Split[7];
			$Output[$i]['name']      = $Split[8];
			$i++;
		  }
		}
		return !empty($Output) ? $Output : false;
	  }
	}
	
	/**
	 * FTP List files in the specified directory
	 *
	 * @access	public
	 * @return	array
	 */
	public function list_files($path = '.', $details = FALSE)
	{
		if ( ! $this->_is_conn() )
		{
			return FALSE;
		};
		
		if ( TRUE === $details )
		{
			$return = ftp_rawlist($this->conn_id, $path);
			
			if ( ! empty( $return ) )
			{
				$new_return = array();
				var_dump( self::parse_ftp_rawlist( $return ) );
				exit();
				
				$return = $new_return;
			};

			return $return;
		};
		return ftp_nlist($this->conn_id, $path);
	}
	
	/**
	 * Returns the system type identifier of the remote FTP server. 
	 *
	 * @access	public
	 * @return	string	Returns the remote system type, or FALSE on error. 
	 */
	public function systype()
	{
		if ( ! $this->_is_conn() )
		{
			return FALSE;
		};
		return ftp_systype( $this->conn_id );
	}
	
	/**
	 * FTP Size of a specified file
	 *
	 * @access	public
	 * @return	int	Returns the file size on success, or -1 on error
	 */
	public function file_size($filepath = '.', $formated = FALSE)
	{
		if ( ! $this->_is_conn() )
		{
			return FALSE;
		};
		$size = (int) @ftp_size($this->conn_id, $filepath);
		return ( FALSE === $formated ) ? $size : self::_sizeFormat($size) ;
	}
	
	/**
	 * Size Format
	 * @private
	 * @param	int		Size in bytes
	 * @return	string	Returns the formated 
	 */
	private static function _sizeFormat( $size = 0 )
	{
		if( $size < 1024 )
		{
			$return = $size." bytes";
		}
		else if( $size < 1048576 )
		{
			$size = round ( $size / 1024, 1 );
			$return = $size." KB";
		}
		else if( $size < ( 1073741824 ) )
		{
			$size= round( $size / 1048576, 1 );
			$return = $size." MB";
		}
		else
		{
			$size = round( $size / 1073741824, 1 );
			$return = $size." GB";
		};
		return (string) $return;
	}
	
	/**
	 * FTP File exists
	 *
	 * @access	public
	 * @return	bool
	 */
	public function file_exists($filepath = '.')
	{
		if ( ! $this->_is_conn() )
		{
			return FALSE;
		};
		return (bool) ( $this->ftp_size($filepath) !== -1 );
	}
	
	/**
	 * FTP Last modified time of the given file
	 *
	 * @access	public
	 * @return	int	Returns the file size on success, or -1 on error
	 */
	public function filemtime($filepath = '.')
	{
		if ( ! $this->_is_conn() )
		{
			return FALSE;
		};
		return (int) @ftp_mdtm($this->conn_id, $filepath);
	}
	
	/**
	 * FTP Get dir exists
	 *
	 * @access	public
	 * @return	bool
	 */
	public function dir_exists( $path = '.' )
    {
		return (bool) ( ! $this->_is_conn() || ! @ftp_chdir($this->conn_id, $path) );
    }

	/**
	 * Read a directory and recreate it remotely
	 *
	 * This function recursively reads a folder and everything it contains (including
	 * sub-folders) and creates a mirror via FTP based on it.  Whatever the directory structure
	 * of the original file path will be recreated on the server.
	 *
	 * @access	public
	 * @param	string	path to source with trailing slash
	 * @param	string	path to destination - include the base folder with trailing slash
	 * @return	bool
	 */
	public function mirror($locpath, $rempath)
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		};

		// Open the local file path
		if ($fp = @opendir($locpath))
		{
			// Attempt to open the remote file path.
			if ( ! $this->changedir($rempath, TRUE))
			{
				// If it doesn't exist we'll attempt to create the direcotory
				if ( ! $this->mkdir($rempath) OR ! $this->changedir($rempath))
				{
					return FALSE;
				}
			}

			// Recursively read the local directory
			while (FALSE !== ($file = readdir($fp)))
			{
				if (@is_dir($locpath.$file) && substr($file, 0, 1) != '.')
				{
					$this->mirror($locpath.$file."/", $rempath.$file."/");
				}
				elseif (substr($file, 0, 1) != ".")
				{
					// Get the file extension so we can se the upload type
					$ext = $this->_getext($file);
					$mode = $this->_settype($ext);

					$this->upload($locpath.$file, $rempath.$file, $mode);
				}
			}
			return TRUE;
		}

		return FALSE;
	}


	/**
	 * Extract the file extension
	 *
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	private function _getext($filename)
	{
		if (FALSE === strpos($filename, '.'))
		{
			return 'txt';
		};

		$x = explode('.', $filename);
		return (string) end($x);
	}


	/**
	 * Set the upload type
	 *
	 * @access	private
	 * @param	string
	 * @return	string
	 */
	private function _settype($ext)
	{
		$text_types = array(
							'txt',
							'text',
							'php',
							'phps',
							'php4',
							'js',
							'css',
							'htm',
							'html',
							'phtml',
							'shtml',
							'log',
							'xml'
							);

		return (bool) (in_array($ext, $text_types)) ? 'ascii' : 'binary';
	}

	/**
	 * Close the connection
	 *
	 * @access	public
	 * @param	string	path to source
	 * @param	string	path to destination
	 * @return	bool
	 */
	public function close()
	{
		if ( ! $this->_is_conn())
		{
			return FALSE;
		};
		return $this->connected = @ftp_close( $this->conn_id );
	}
	
	/**
	 * Destruct connection
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		$this->close();
	}


}
// END Kohana_FTP