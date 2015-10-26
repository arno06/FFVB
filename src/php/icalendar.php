<?php
class ICalendar extends ICalendarItem
{
	public function __construct($pProdID, $pVersion = "1.0")
	{
		$this->name = "VCALENDAR";
		$this->content[] = "PRODID:".$pProdID;
		$this->content[] = "VERSION:".$pVersion;
		$this->content[] = "CALSCALE:GREGORIAN";
		$this->content[] = "METHOD:REQUEST";
	}

	public function addEvent($pName, $pDtStamp, $pDtStart)
	{
		$ve = new VEvent($pName, $pDtStamp, $pDtStart);
		$this->content[] = $ve;
		return $ve;
	}

	public function download($pName)
	{

	}
}

class VEvent extends ICalendarItem
{
	public function __construct($pName, $pDtStamp, $pDtStart)
	{
		$this->name = "VEVENT";
		$this->content[] = "UID:".md5("VEVENT".$pDtStamp."".$pDtStart."".$pName)."@icalendar.com";
		$this->content[] = "DTSTAMP:".date("Ymd\THis\Z", $pDtStamp);
		$this->content[] = "DTSTART:".date("Ymd\THis\Z", $pDtStart);
		$this->content[] = "SUMMARY:".$pName;
	}

	public function setDescription($pText)
	{
		$this->content[] = "DESCRIPTION:".$pText;
		return $this;
	}

	public function setCategories($pText)
	{
		$this->content[] = "CATEGORIES:".$pText;
		return $this;
	}

	public function setDTEnd($pTimeStamp)
	{
		$this->content[] = "DTEND:".date("Ymd\THis\Z", $pTimeStamp);
		return $this;
	}
}

class ICalendarItem
{
	const CRLF = "\r\n";
	protected $name;
	protected $content = array();

	public function render()
	{
		array_unshift($this->content, "BEGIN:".$this->name);
		array_push($this->content, "END:".$this->name);
		foreach($this->content as &$pValue)
		{
			if(!($pValue instanceof ICalendarItem))
				continue;
			$pValue = $pValue->render();
		}
		return implode(self::CRLF, $this->content);
	}
}

define('TARGET', 'CSM EAUBONNE');

$items = file_get_contents('http://www.arnaud-nicolas.fr/projects/Volley/src/php/proxy.ffvb.php?gzip=0');

$data = json_decode($items, true);

$agenda = $data["agenda"];

$ic = new ICalendar("-//arnaud-nicolas.fr/Volley/LIIDF", "1.0");

foreach($agenda as $day)
{
	$name = $day['label'];
	foreach($day['matches'] as $match)
	{
		if($match['home']['name']==TARGET || $match['guest']['name']==TARGET)
		{
			$vs = $match['home']['name']." - ".$match['guest']['name'];
			$d = explode('/', $match['date']);
			$h = explode(':', $match['hour']);
			if(count($h)<2)
				$h = array("12","0");

			$ic->addEvent("Volley : ".$vs,time(), mktime($h[0],$h[1],0,$d[1],$d[0], "20".$d[2]))
					->setDTEnd(mktime($h[0]+2,$h[1],0,$d[1],$d[0], "20".$d[2]))
					->setCategories("Volley")
					->setDescription($name." : ".$vs);
		}
	}
}

header("Content-Type:text/plain;charset=UTF-8");
$content = $ic->render();

$length = strlen($content);
header("content-disposition: attachment; filename=\"Volley_CSM.ics\"");
header('Content-Type: application/force-download');
header('Content-Transfer-Encoding: binary');
header("Content-Length: ".$length);
header("Pragma: no-cache");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
header("Expires: 0");
echo $content;
exit();