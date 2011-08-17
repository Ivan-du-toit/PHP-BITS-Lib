<?php
class E_ExceptionEx extends Exception
{
    function GetVerboseMessage()
    {
        $class_name = get_class($this);
        $message = parent::getMessage();
        $file = parent::getFile();
        $line = parent::getLine();
        $stack_trace = parent::getTraceAsString();
        $embeded = '';
        if ((isset($this->embeded_exception)) and ($this->embeded_exception instanceof AlfaException))
            $embeded = $this->embeded_exception->GetSimpleMessage();
        elseif ((isset($this->embeded_exception)) and ($this->embeded_exception instanceof Exception))
            $embeded = $this->embeded_exception->GetMessage();
        return("An exception of type {$class_name} was raised with message: \"{$message}\" in file {$file} on line {$line}.\n\r
        Stack trace: {$stack_trace} \n\r
        {$embeded}");
    }

    function GetSimpleMessage()
    {
        return(parent::getMessage());
    }

    function GetMessageEx()
    {
        if (DEBUG_MODE)
            return($this->GetVerboseMessage());
        else
            return($this->GetSimpleMessage());
    }

    function __construct($message, $code=400)
    {
        parent::__construct($message, $code);
    }
}

class E_ServerError extends E_ExceptionEx
{
    function __construct($message, $code=503)
    {
        parent::__construct($message, $code);
        //Log This as an error
    }
}

class E_UserError extends E_ExceptionEx
{

}
?>
