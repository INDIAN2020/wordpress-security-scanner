<?php
/*
Author: Adam Lyons

*/

$_filename = __FILE__;
$break = explode('/', $_filename);
$_filename = $break[count($break) - 1]; 
$alarms = array();

$email_encoded = 'YWRhbUBtaXNzaWxlZmlzaC5jb20';
$email = base64_decode(strtr($email_encoded, '-_', '+/'));
$total = 0;

if (defined('STDIN') && isset($argv[1])) {
	$interactive = $argv[1];
} elseif(isset($_GET['prompt'])) { 
	$interactive = $_GET['prompt'];
} else {
	$interactive = '';
}

if($interactive) {
	print "Alerts will cause a pause in script execution, to disable run without any arguments\n\n";
} else {
	print "WARNING: This script is NOT running interactive, only an email report will be generated. To enable run with ./$_filename prompt=1\n\n";
}


$path = getcwd();
print "Getting file list...\n";
$files = recursiveDirList($path);
print "Scanning files...";

foreach ($files as $filename) {
	print ".";
	$total++;
	$line_number = 0;
	#print "processing: $path/$filename\n";
	if($filename !==  $_filename) {
		$handle = fopen("$path/$filename", "r");
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				// process the line read.
				$line_number++;
				$patterns = array("source=base64_decode", "eval.*base64_decode", "POST.*execgate"); 
				$regex = '/(' .implode('|', $patterns) .')/i'; 
				if (preg_match($regex, $line)) {  
					$_line = substr($line, 0, 25);
					print <<<ALERT

####################################################################################################################################
#           ALERT               ALERT                      ALERT                  ALERT                                            #
####################################################################################################################################
$path/$filename
>> $_line

ALERT;
					$alarms["$path/$filename"][$line_number] = $line;

					if($interactive) {
						echo "Disable file by CHMOD? (y/n)\n";
						$_handle = fopen ("php://stdin","r");
						$input = fgets($_handle);
						if(trim($input) == 'y'){
							chmod("$path/$filename", 0000);
						}
						echo "Thank you, continuing...\n";
						fclose($_handle);
					}

				}
			}
			fclose($handle);
		} else {
			// error opening the file.
			print "OPEN FAIL: $path/$filename\n\n";
		} 
	}
}

print "\nScan complete ($total Files)\n\n\n";

	$date = date('l jS \of F Y h:i:s A');
	if($alarms) {
		print "The following alarms occured:\n";
		#print_r($alarms);
		$body = "Alarms detected on $date\n\n" . print_r($alarms, true);
	} else {
		$body = "No alarms detected: $date";
	}

	$to = $email; $subject = "Wordpress Security Scanner ($_filename) Security Report";  
	if (mail($to, $subject, $body)) {   echo("Email successfully sent!\n$body\n");  } else {   echo("Email delivery failed.\n");  }	



function recursiveDirList($dir, $prefix = '') {
	$dir = rtrim($dir, '/');
	$result = array();

	foreach (glob("$dir/*", GLOB_MARK) as $f) {
		if (substr($f, -1) === '/') {
			#print "\n$f";
			$result = array_merge($result, recursiveDirList($f, $prefix . basename($f) . '/'));
		} else {
			$patterns = array("php$", "js$"); 
			$regex = '/(' .implode('|', $patterns) .')/i'; 
			if(preg_match($regex,$f)) {
				if (substr(decoct(fileperms($f)), -3) !== '000') {
					$result[] = $prefix . basename($f);
					#print ".";
				}
			}
		}
	}
	return $result;
}

?>
