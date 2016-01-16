<?php
date_default_timezone_set("America/Los_Angeles");
$date = getdate();

function CallAPI($frDate)
{

	$curl = curl_init();

	// Formats date for api
	$fDate = $frDate['year'].'-'.$frDate['month'].'-'.$frDate['day'].'T'.
		$frDate['hour'].":00:00";
	$tDate = $frDate['year'].'-'.$frDate['month'].'-'.$frDate['day'].'T'.
		$frDate['hour'].":59:59";

    $dates = array('fromdate'=>$fDate, 'todate'=>$tDate);

    $query = http_build_query($dates);

    $url = sprintf("%s?%s", 'https://api.fakewebsite.com/v1/gpsdata', $query);

	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, "username|password");

    curl_setopt($curl, CURLOPT_URL, $url );
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    $result = curl_exec($curl);

    $result2 = json_decode($result, true);

    // Only need the GPS data
    if (!empty($result2['gps-recs'])) {
    	return $result2['gps-recs'];
    } else {
    	return FALSE;
    }
}

// Custom code for doing hourly increments. Hourly largest size we can use 
// without missing data due to row limits. Needed custom code due to api
function IncrementDate($oDate)
{
	$fYear = $oDate['year'];
	$fMonth = $oDate['month'];
	$fDay = $oDate['day'];
	$fHour = $oDate['hour'];
	if ($fHour == '23') {
		$fHour = '00';
		if (
			($fMonth == '01') || ($fMonth == '03') || ($fMonth == '05') ||
			($fMonth == '07') || ($fMonth == '08') || ($fMonth == '10')
		) {
			if ($fDay == '31') {
				$fMonth++;
				if (
					$fMonth < 10 && ($fMonth !== '01' || $fMonth !== '02' ||
					$fMonth !== '03' || $fMonth !== '04' || $fMonth !== '05' ||
					$fMonth !== '06' || $fMonth !== '07' || $fMonth !== '08' ||
					$fMonth !== '09')
				) {
					$fMonth = "0".$fMonth;
				}
				$fDay = '01';
			} else {
				$fDay++;
				if (
					$fDay < 10 && ($fDay !== '01' || $fDay !== '02' ||
					$fDay !== '03' || $fDay !== '04' || $fDay !== '05' ||
					$fDay !== '06' || $fDay !== '07' || $fDay !== '08' ||
					$fDay !== '09')
				) {
					$fDay = "0".$fDay;
				}
			}
		} elseif (
			($fMonth == '04') || ($fMonth == '06') || ($fMonth == '09') ||
			($fMonth == '11')
		) {
			if ($fDay == '30') {
				$fMonth++;
				if (
					$fMonth < 10 && ($fMonth !== '01' || $fMonth !== '02' ||
					$fMonth !== '03' || $fMonth !== '04' || $fMonth !== '05' ||
					$fMonth !== '06' || $fMonth !== '07' || $fMonth !== '08' ||
					$fMonth !== '09')
				) {
					$fMonth = "0".$fMonth;
				}
				$fDay = '01';
			} else {
				$fDay++;
				if (
					$fDay < 10 && ($fDay !== '01' || $fDay !== '02' ||
					$fDay !== '03' || $fDay !== '04' || $fDay !== '05' ||
					$fDay !== '06' || $fDay !== '07' || $fDay !== '08' ||
					$fDay !== '09')
				) {
					$fDay = "0".$fDay;
				}
			}
		} elseif ($fMonth == '02') {
			if ($fDay == '28') {
				$fMonth++;
				if (
					$fMonth < 10 && ($fMonth !== '01' || $fMonth !== '02' ||
					$fMonth !== '03' || $fMonth !== '04' || $fMonth !== '05' ||
					$fMonth !== '06' || $fMonth !== '07' || $fMonth !== '08' ||
					$fMonth !== '09')
				) {
					$fMonth = "0".$fMonth;
				}
				$fDay = '01';
			} else {
				$fDay++;
				if (
					$fDay < 10 && ($fDay !== '01' || $fDay !== '02' ||
					$fDay !== '03' || $fDay !== '04' || $fDay !== '05' ||
					$fDay !== '06' || $fDay !== '07' || $fDay !== '08' ||
					$fDay !== '09')
				) {
					$fDay = "0".$fDay;
				}
			}
		} elseif ($fMonth == '12') {
			if ($fDay == '31') {
				$fMonth = '01';
				$fDay = '01';
				$fYear++;
			} else {
				$fDay++;
				if (
					$fDay < 10 && ($fDay !== '01' || $fDay !== '02' ||
					$fDay !== '03' || $fDay !== '04' || $fDay !== '05' ||
					$fDay !== '06' || $fDay !== '07' || $fDay !== '08' ||
					$fDay !== '09')
				) {
					$fDay = "0".$fDay;
				}
			}
		}
	} else {
		$fHour++;
		if (
			$fHour < 10 && ($fHour !== '01' || $fHour !== '02' ||
			$fHour !== '03' || $fHour !== '04' || $fHour !== '05' ||
			$fHour !== '06' || $fHour !== '07' || $fHour !== '08' ||
			$fHour !== '09')
		) {
			$fHour = "0".$fHour;
		}
	}

	$nDate = array(
		'year' => $fYear,
		'month' => $fMonth,
		'day' => $fDay,
		'hour' => $fHour
	);

	return $nDate;
}

function insertRows($db, $data) {
	$sql = null;
	try {
		$db->beginTransaction();
		foreach ($data as $row) {
			if (!is_numeric($row['ZipCode'])) {
				$row['ZipCode'] = '00000';
			}
			if (!is_numeric($row['Longitude'])) {
				$row['Longitude'] = '00000';
			}
			if (!is_numeric($row['Latitude'])) {
				$row['Latitude'] = '00000';
			}
			$sql = 'INSERT INTO `survey_schema`.`gpsdata` (`TechName`,
				`UTCTime`, `Country`, `County`, `State`, `City`, `Zip`,
				`Latitude`, `Longitude`) 
			VALUES (?,?,?,?,?,?,?,?,?)';
			$dbCall = $db->prepare($sql);
			$dbCall->execute(
				array(
					$row['UserInfo']['UserName'],$row['UtcTimeTag'],
					$row['Country'],$row['County'],$row['State'],$row['City'],
					$row['ZipCode'],$row['Latitude'],$row['Longitude']
				)
			);
		}

		// commit the transaction
	    $db->commit();
	    echo "New records created successfully";
	}
	catch(PDOException $e) {
	    // roll back the transaction if something failed
	    $db->rollback();
	    var_dump($row);
	    exit( "Error: " . $e->getMessage());
	}

	$db = null;
}


// Credentals for logging in
$servername = "123.456.789.163";
$username = "username";
$password = "password";

// Basic connection script for SQL with text output for success/failure
try {
	$conn = new PDO(
		"mysql:host=$servername;dbname=test_schema", $username, $password
	);
	// set the PDO error mode to exception
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	echo "Connected successfully\r\n"; 
}
catch(PDOException $e) {
	exit("Connection failed: " . $e->getMessage());
}


// Date you want the script to start from
$fromDate = array(
	'year' => '2015',
	'month' => '01',
	'day' => '08',
	'hour' => '17'
);

$apiResult = CallAPI($fromDate);

// Check to make sure we have date to insert
if (!empty($apiResult)) {
	insertRows($conn, $apiResult);
	echo "data logged!\r\n";
	$apiResult = false;
} else {
	echo "No Data! ";
	print_r($fromDate);
	echo "\r\n";
}

// Checks current day in script vs acutal day
$checkDate = strtotime($fromDate['year'].'-'.$fromDate['month'].'-'.
	$fromDate['day'].'T'.$fromDate['hour'].":00:00");
$dayDate = strtotime($date['year'].'-'.$date['mon'].'-'.$date['mday'].
	'T'."00:00:00");


while ($checkDate < $dayDate) {
	// Sanity check, really just helps keep my code clean for me
	if ($checkDate < $dayDate) {
		$apiResult = CallAPI($fromDate);
		if (!empty($apiResult)) {
			insertRows($conn, $apiResult);
			echo "data logged!\r\n";
			$apiResult = false;
		} else {
			var_dump(CallAPI($fromDate));
			echo "No Data! ";
			print_r($fromDate);
			echo "\r\n";
		}
	}
	// Goes to the next day and reformats the checkDate
	$fromDate = IncrementDate($fromDate);
	$checkDate = strtotime($fromDate['year'].'-'.$fromDate['month'].'-'.
		$fromDate['day'].'T'.$fromDate['hour'].":00:00");
}
?>