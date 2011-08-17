<?php
require_once('libs'.DIRECTORY_SEPARATOR.'exception.php');

//TODO: Allow the option not use a db but to use sessions

define('BITS_PROTOCOL_VERSION', '{7df0354d-249b-430f-820d-3d2a9bef4931}');
define('BITS_DEFAULT_TEMP_DIR', 'temp');
define('BITS_DEFAULT_UPLOADED_DIR', 'uploaded');

//BITS header conts
define('BITS_HEADER_SESSION_ID', 'HTTP_BITS_SESSION_ID');
define('BITS_HEADER_PACKET_TYPE', 'HTTP_BITS_PACKET_TYPE');
define('BITS_HEADER_ACK', 'BITS-Packet-Type: Ack');

//DB stuff
define('BITS_DB_TABLE', 'bits_sessions');

class BITS
{
	protected $file_name = '';
    protected $content_pos = 0;
    protected $headers = array();
    protected $rec = array();
    protected $guid = '';

    var $temp_dir = BITS_DEFAULT_TEMP_DIR;
    var $save_dir = BITS_DEFAULT_UPLOADED_DIR;


	function __construct()
    {
		if (isset($_SERVER[BITS_HEADER_SESSION_ID]))
		{
       		$this->LoadSessionFromGUID($_SERVER[BITS_HEADER_SESSION_ID]);
       		$this->file_name = $this->rec['file_name'];
		}
	}

	function __get($prop)
	{
	 	switch($prop)
	 	{
	 	 	case 'file_name': return($this->file_name); break;
	 	 	default : throw new E_ExceptionEx('Invalid property access.');
		}
	}

	/**
	* This allows the user of this libary to override the default save location of the file
	*
	* @param mixed $location
	*/
	/*function SetFileLocation($location)
	{

	}*/

	function HandleRequest()
	{
		//Check the header to see if which request is being preformed
		switch ($_SERVER[BITS_HEADER_PACKET_TYPE])
		{
			case 'ping': $this->Ping(); break;
			case 'Create-Session': $this->CreateSession(); break;
			case 'Fragment': $this->Fragment(); break;
			case 'Close-Session': $this->CloseSession(); break;
			case 'Cancel-Session': $this->CloseSession(); break;
            default: throw new E_BITS_HeaderError('BITS-Packet-Type', "unseported packet '{$_SERVER[BITS_HEADER_PACKET_TYPE]}' type given");
		}
	}

	protected function GetSessionGUID()
	{
		if ($this->guid == '')
		{
			global $AlfaDB;
			$AlfaDB->Query('INSERT INTO '.BITS_DB_TABLE.' SET guid = uuid()');
			$this->rec = $AlfaDB->LoadRow(BITS_DB_TABLE, $AlfaDB->GetLastInsertID());
			$this->guid = $this->rec['guid'];
		}
		return('{'.$this->guid.'}');
	}

	protected function LoadSessionFromGUID($guid)
	{
    	$this->guid = $guid;
    	global $AlfaDB;
    	$this->rec = $AlfaDB->LoadRow(BITS_DB_TABLE, array('guid' => trim($this->guid, '{}')));
    	if (!is_array($this->rec) or empty($this->rec))
    		throw new E_BITS_SessionError();
	}

	protected function SetFileName($file_name)
	{
		$this->file_name = $file_name;
		$this->update();
	}

	protected function update()
	{
		global $AlfaDB;
      	$AlfaDB->Update(array('guid' =>trim($this->guid, '{}')), BITS_DB_TABLE, array('file_name' => $this->file_name));
	}

	protected function GetTempFileName()
	{
	 	return($this->temp_dir.DIRECTORY_SEPARATOR.$this->guid.'.upload');
	}

	protected function Ping()
	{
		//NOTE: This is untested and is not used by bitsadmin
		header(BITS_HEADER_ACK);
	}

	protected function CreateSession()
	{
		if (!isset($_SERVER['REQUEST_METHOD']) or ($_SERVER['REQUEST_METHOD'] != 'BITS_POST'))
			throw new E_BITS_InvalidMethod('BITS_POST');

		if (strpos($_SERVER['HTTP_BITS_SUPPORTED_PROTOCOLS'], BITS_PROTOCOL_VERSION) === false)
			throw new E_BITS_UnsupportedProtocol();


		header(BITS_HEADER_ACK);
		header('BITS-Protocol: '.BITS_PROTOCOL_VERSION);
		header('BITS-Session-Id: '.$this->GetSessionGUID());
		header('Accept-Encoding: Identity');
	}

	protected function Fragment()
	{
		if (!isset($_SERVER['REQUEST_METHOD']) or ($_SERVER['REQUEST_METHOD'] != 'BITS_POST'))
			throw new E_BITS_InvalidMethod('BITS_POST');

		if (!isset($_SERVER['HTTP_CONTENT_RANGE']))
			throw new E_BITS_HeaderError('Content-Range', 'header not found');
		//$this->LoadSessionFromGUID($this->headers['BITS-Session-Id']);
		if (isset($_SERVER['HTTP_Content_Name']) and ($this->file_name == ''))
			$this->SetFileName($_SERVER['HTTP_Content_Name']);
		elseif ($this->file_name == '')
			$this->SetFileName($this->rec['id'].'.upload');
		list($range_start, $range_end) = explode('-', $_SERVER['HTTP_CONTENT_RANGE']);
		$range_start = str_replace('bytes ', '', $range_start);
		list($range_end) = explode('/', $range_end);
		$range_start = ($range_start >= $this->content_pos) ? $range_start : $this->content_pos;
		$fh = fopen($this->GetTempFileName(), 'a');
		if (fseek($fh, $range_start) == -1)
			throw new E_BITS_RangeError();
		if (fwrite($fh, file_get_contents('php://input'), ($range_end - $range_start)+1) === false)
			throw new E_BITS_WriteError();
        $this->content_pos = $range_end;
        header(BITS_HEADER_ACK);
        header('BITS-Received-Content-Range: '.($range_end+1));
        header('BITS-Session-Id: '.$this->guid);


	}

	protected function CloseSession()
	{
		if (!isset($_SERVER['REQUEST_METHOD']) or ($_SERVER['REQUEST_METHOD'] != 'BITS_POST'))
			throw new E_BITS_InvalidMethod('BITS_POST');

		if ($this->guid == '')
			throw new E_BITS_SessionError();
        header(BITS_HEADER_ACK);
		header('BITS-Session-Id: '.$this->guid);
		//Could Later be changed to move if the temp dir is not the same as the save dir
		if (copy($this->GetTempFileName(), $this->save_dir.DIRECTORY_SEPARATOR.$this->file_name))
  			unlink($this->GetTempFileName());
	}

	protected function CancelSession()
	{
		if (!isset($_SERVER['REQUEST_METHOD']) or ($_SERVER['REQUEST_METHOD'] != 'BITS_POST'))
			throw new E_BITS_InvalidMethod('BITS_POST');

		//TODO: Do the clean up like deleting the tempfiles and destroying the session ID
     	header(BITS_HEADER_ACK);
		header('BITS-Session-Id: '.$this->guid);
		unlink($this->GetTempFileName());
	}
}

class E_BITS_ServerError extends E_ServerError
{
	function __construct($msg, $code=300)
    {
        parent::__construct($msg, $code	);
    }
}

class E_BITS_WriteError extends E_BITS_ServerError
{
	function __construct()
    {
        parent::__construct('Could not write contents to file');
    }
}

class E_BITS_HeaderError extends E_UserError
{
	function __construct($header, $error_msg)
    {
        parent::__construct("Header Error: The header '{$header}' has the following error: '{$error_msg}'");
    }
}

class E_BITS_UnsupportedProtocol extends E_UserError
{
	function __construct()
    {
        parent::__construct('Protocol not supported');
    }
}

class E_BITS_RangeError extends E_UserError
{
	function __construct()
    {
        parent::__construct('Range out of bounds');
    }
}

class E_BITS_SessionError extends E_UserError
{
	function __construct()
    {
        parent::__construct('Session guid not found');
    }
}

class E_BITS_InvalidMethod extends E_UserError
{
	function __construct($method)
    {
        parent::__construct("The method : '{$_SERVER['REQUEST_METHOD']}' is not supported for this action, '{$method}' is required.");
    }
}
?>