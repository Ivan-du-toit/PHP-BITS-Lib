<?php
require_once('bits.class.php');
$bits = new BITS();
try
{
	$bits->HandleRequest();
}
catch (E_ExceptionEx $e)
{
 	echo $e->GetMessageEx();
}
?>
