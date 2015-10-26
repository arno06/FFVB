<?php

include_once("class.FFVBProxy.php");

if(!isset($_GET['what']))
{
	output(array("error"=>"Data type missing"));
}
$ffvb = new FFVBProxy();
switch($_GET['what'])
{
	case 'ranking':
		output($ffvb->getRanking());
		break;
	case 'agenda':
		output($ffvb->getAgenda());
		break;
}


function output($pContent)
{
	header("Content-Type:application/json;charset=UTF-8");
	echo json_encode($pContent);
	exit();
}
