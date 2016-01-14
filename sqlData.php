<?php
require_once "Mail.php";
require_once "Mail/mime.php";
date_default_timezone_set("America/Los_Angeles");
$date = getdate();

$sendDate = $date['year'].'-'.$date['mon'].'-'.$date['mday'];
$fileDate = $date['year'].'_'.$date['mon'].'_'.$date['mday'];

$csvName = "../Users/MBednar/Desktop/leaks.csv";
$csvWriteName = "../Users/MBednar/Desktop/reportedSurveys_".$fileDate.".csv";

$connectionInfo = array(
	"UID" => "username",
	"PWD" => "password",
	"Database" => "db1"
);
$connectionInfo2 = array(
	"UID" => "username",
	"PWD" => "password",
	"Database" => "db2"
);
$SQLservername = "108.163.197.163, 2433";

$sqlDB = sqlsrv_connect($SQLservername, $connectionInfo);
$sqlDB2 = sqlsrv_connect($SQLservername, $connectionInfo2);

if( $sqlDB ) {
     echo "Connection to db1 established. \r\n";
}else{
     echo "Connection to db1 could not be established.<br />";
     die( print_r( sqlsrv_errors(), true));
}

if( $sqlDB2 ) {
     echo "Connection to db2 established. \r\n";
}else{
     echo "Connection to db2 could not be established.<br />";
     die( print_r( sqlsrv_errors(), true));
}

function processWalking($results, $fileRow)
{
	global $csvData;
	global $sqlDB;
	global $plat;

	//this is where I'm going to do things
	foreach ($results as $result) {
		$startDate = $result['DateBegan'];
		$endDate = $result['DateStopped'];
		$diff = date_diff($startDate,$endDate);
		$days = $diff->format("%a");
		$currentDate = $result['DateBegan'];
		$formattedDate = date_format($currentDate, 'm/d/y');
		$tempData = array();
		while ($currentDate <= $endDate) {
			$dayDate = $formattedDate.' '."00:00:00";
			$endDay = $formattedDate.' '."23:59:59";

			$querry = "SELECT TOP 1 RecID FROM vPhoneData1
				WHERE (TaskSetName = '1 PING' or TaskSetName =
				 '3 REPORT CGI_OR_H2RL') AND LocalTimetag1 > ? and
				 LocalTimetag1 < ? and cast(FORMDATA as varchar(max)) like
				 '%".$plat."%'";

			$stmt = sqlsrv_prepare($sqlDB, $querry, array(&$dayDate, &$endDay));

			sqlsrv_execute($stmt);

			$fetched = sqlsrv_fetch_array ($stmt);
			if (!empty($fetched)) {
				$formattedDate = date_format($currentDate, 'm/d/y');
				$fileRow['Survey Dt'] = $formattedDate;
				$fileRow['Completed By'] = $result['TechName'];
				$fileRow['Instrument'] = $result['Instrument'];
				$fileRow['Rate'] = $result['RateCode'];
				$fileRow['Survey Method'] = 'WALK';
				$tempData[] = $fileRow;
			}
			$currentDate = date_add(
				$currentDate, date_interval_create_from_date_string('1 days')
			);
			$formattedDate = date_format($currentDate, 'm/d/y');
			$fetched = null;
		}

		if (!empty($tempData)) {
			$daycount = count($tempData);
			if ($result[6] > 0) {
				$dailyTotal = floor($result[6]/($daycount));
				$firstTotal = $dailyTotal+($result[6]%($daycount));
				$isFirst = TRUE;
			} else {
				$dailyTotal = 0;
			}
			foreach ($tempData as $tempRow) {
				if ($isFirst === TRUE) {
					$tempRow['Quantity'] = $firstTotal;
					$isFirst = FALSE;
				} elseif ($dailyTotal > 0) {
					$tempRow['Quantity'] = $dailyTotal;
				} else {
					$tempRow['Quantity'] = 0;
				}
				if ($tempRow['Quantity'] > 0) {
					$csvData[] = $tempRow;
				}
			}
		}
	}
}

$fileData = array();
$file = fopen($csvName, "r");
$headers = TRUE;
while (!feof($file)) {
	if ($headers === TRUE) {
		$headers = FALSE;
		$row = fgetcsv($file);
		$csvHeaders = $row;
	} else {
		$row = fgetcsv($file);
		if (!empty($row[2])) {
			$fileDataRow = array(
				'OP' => $row[0],
				'Plat' => $row[1],
				'Func Loc' => $row[2],
				'Eq' => $row[3],
				'Object Link' => $row[4],
				'Notif' => $row[5],
				'Req Start Dt' => $row[6],
				'Req End Dt' => $row[7],
				'Measuring Point' => $row[8],
				'Survey Type' => $row[9],
				'Quantity' => $row[10],
				'Survey Dt' => $row[11],
				'Completed By' => $row[12],
				'Instrument' => $row[13],
				'Survey Method' => $row[14],
				'Rate' => $row[15],
				'Long Text' => $row[16]
			);

			$fileData[] = $fileDataRow;
		}
	}
}


fclose($file);

$file2 = fopen($csvWriteName, "w");

$csvData = array();
for ($i = 0; $i < count($fileData); $i++){
	$dbArray = null;
	$issueDateTime = new DateTime($fileData[$i]['Req Start Dt']);
	$issueDate = date_format($issueDateTime, "Y-m-d H:i:s");

	$op = trim(str_replace(".","",$fileData[$i]['OP']));
	$plat = trim(str_replace(".","",$fileData[$i]['Plat']));
	if (empty($fileData[$i]['Survey Dt'])) {
		if (strpos($fileData[$i]['Survey Type'], "SVC")) {
			$surveyType = trim(
				str_replace("SVC","",$fileData[$i]['Survey Type'])
			);
			$sql2 = "SELECT TOP 20 DateBegan, DateStopped, TechName, Instrument,
			 SurveyMethod, SurveyType, CompletedSvcs, RateCode 
				FROM [reported surveys]
				where DateBegan > ?
				and OpsID = ? and PlatID = ? and SurveyType = ? AND
				 CompletedSvcs > 0
	 			order by id desc";
	 		$stmt = sqlsrv_prepare(
	 			$sqlDB2, $sql2, array(&$issueDate, &$op, &$plat, &$surveyType)
	 		);
	 		sqlsrv_execute($stmt);
	 		while ($row = sqlsrv_fetch_array ($stmt)) {
	 			$dbArray[] = $row;
	 		}
			if (!empty($dbArray)) {
				processWalking($dbArray, $fileData[$i],$csvData);
			} else {
				$csvData[] = $fileData[$i];
			}
		} elseif (strpos($fileData[$i]['Survey Type'], "MAIN")) {
			$surveyType = trim(
				str_replace("MAIN","",$fileData[$i]['Survey Type'])
			);
			$sql2 = "SELECT TOP 20 DateBegan, DateStopped, TechName, Instrument,
			 SurveyMethod, SurveyType, CompletedMain, RateCode 
				FROM [reported surveys]
				where DateBegan > ? 
				and OpsID = ? and PlatID = ? and SurveyType = ? AND
				 CompletedMain > 0
		 		order by id desc";
	 		$stmt = sqlsrv_prepare(
	 			$sqlDB2, $sql2, array(&$issueDate, &$op, &$plat, &$surveyType)
	 		);
	 		sqlsrv_execute($stmt);
			while ($row = sqlsrv_fetch_array ($stmt)) {
	 			$dbArray[] = $row;
	 		}
			if (!empty($dbArray)) {
				processWalking($dbArray, $fileData[$i],$csvData);
			} else {
				$csvData[] = $fileData[$i];
			}
		} elseif ($fileData[$i]['Survey Type'] == 'CAS') {
			$sql2 = "SELECT * FROM [reported surveys] where DateBegan > ?
				and OpsID = ? and PlatID = ? and SurveyType = 'CAS'
		 		order by id desc limit 20";
		 	$stmt = sqlsrv_prepare(
		 		$sqlDB2, $sql2, array(&$issueDate, &$op, &$plat)
		 	);
	 		sqlsrv_execute($stmt);
			while ($row = sqlsrv_fetch_array ($stmt)) {
	 			$dbArray[] = $row;
	 		}
			if (!empty($dbArray)) {
				$dtDate = new DateTime($dbArray[0][7]);
				$formatDate = date_format($dtDate, 'm/d/y');
				$fileData[$i]['Survey Dt'] = $formatDate;
				$fileData[$i]['Completed By'] = $dbArray[0][8];
				$fileData[$i]['Instrument'] = $dbArray[0][9];
				$fileData[$i]['Rate'] = $dbArray[0][14];
				$fileData[$i]['Survey Method'] = 'Y';
				if (
					($fileData[$i+1]['Survey Type'] == 'METHOD') &&
					($fileData[$i+1]['Plat'] == $fileData[$i]['Plat'])
				) {
					$fileData[$i+1]['Survey Dt'] = $formatDate;
					$fileData[$i+1]['Completed By'] = $dbArray[0][8];
					$fileData[$i+1]['Instrument'] = $dbArray[0][9];
					$fileData[$i+1]['Rate'] = $dbArray[0][14];
					$fileData[$i+1]['Survey Method'] = 'WALK';
					$csvData[] = $fileData[$i];
				} else {
					$csvData[] = $fileData[$i];
				}
			} else {
				$csvData[] = $fileData[$i];
			}
		} else {
			$csvData[] = $fileData[$i];
		}
	} else {
		$csvData[] = $fileData[$i];
	}

}

$recipients = 'mbednar@emailaccount.com';

$headers = array(
	'From' => 'PHP Script <mbednar@emailaccount.com>',
	'To' => 'mbednar@emailaccount.com',
	'Subject' => 'Reported Surveys for '.$sendDate
);
$body = "These are the updated reported surveys";

$params = array(
	'host' => 'smtp.office365.com',
	'port' => '587',
	'auth' => TRUE,
	'username' => 'mbednar@emailaccount.com',
	'password' => 'password'
);

if (!empty($csvData)) {
		fputcsv($file2, $csvHeaders);
		foreach ($csvData as $csvRow) {
			fputcsv($file2, $csvRow);
		}
}

$CSVfile = $csvWriteName;

$mime = new Mail_mime();

$mime->setTXTBody($body);
$mime->setHTMLBody($body);
$mime->addAttachment(
	$CSVfile,'application/octet-stream', 'reportedSurveys_'.$fileDate.'.csv',
	 true
);
$body = $mime->get();
$headers = $mime->headers($headers);

$mail_object = new Mail();
$factoryObj = $mail_object->factory('smtp', $params);
$factoryObj->send($recipients, $headers, $body);
?>