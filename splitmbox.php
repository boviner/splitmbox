<?

/*

  Copyright 2013 Tong-Wing

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.

 */

error_reporting(E_ALL & ~E_NOTICE);

__log( date(DATE_RSS) );
if (PHP_SAPI === 'cli') { 
	__log("Running in CLI mode");
} else {
	__log("Please run from command-line");
	exit;
}

$opt = getOptions();
list ($infile, $infolder, $outfolder, $deleteoriginal) = 
	array($opt["infile"], $opt["infolder"], $opt["outfolder"], $opt["deleteoriginal"]);

// replace backslashes with front slashes
$infile = str_replace("\\", "/", $infile);
$infolder = str_replace("\\", "/", $infolder);
$outfolder = str_replace("\\", "/", $outfolder);


if ($infile && file_exists($infile) && $outfolder) {
	__log("Splitting [$infile] to $outfolder/YYYY");
	splitFile($infile, $outfolder);
} else if ($infolder && $outfolder) {
	$files = enumerateFiles($infolder);
	foreach ($files as $file) {
		// ignore files w/ extensions
		$parts = pathinfo($file);
		if ($parts["extension"]!="") {
		} else {
			__log("Processing [$file]");
			splitFile($file, $outfolder);
		}
	}
} else {
	echo "\nUsage: " . basename(__FILE__) . "\n";
	echo "  --infile <mbox file to split>       - use this option to test on single file\n";
	echo "  --infolder <mbox folder to split>   - use this to run on entire mailbox\n";
	echo "  --outfolder <output location>\n\n";
}

__log("Done");
exit;

/////////////////////////////////////////////////////////////////////////////

function splitFile($infile, $outfolder)
{
	$stat = stat($infile);
	if ($stat["size"]==0) {
		__log("Empty file [$infile]");
		return;
	}

	$fh = fopen($infile, "r");
	$curyear = NULL;
	$outfile = NULL;
	$outfh = NULL;
	$prevline = "";
	
	$parts = explode("/", $infile);
	array_shift($parts);
	$base = join("/", $parts);

	while (!feof($fh)) {
		$line = fgets($fh);

		// detect beginning of a message
		if (
		  !$prevline && 
		  preg_match("/^From .* (Sun|Mon|Tue|Wed|Thu|Fri|Sat) (.*)/", $line, $matches)
		) {
			// extract year - note this is UTC time
			$adate = date_parse($matches[2]);
			if ($adate["year"]!=$curyear) {
				echo "\n";

				// close previous year message
				if ($outfh) fclose($outfh);

				// create new file for current year
				$curyear = $adate["year"];
				$outfile = $outfolder . "/$curyear.sbd/" . $base;

				if (file_exists($outfile)) {
					__log("Appending $outfile");
					$outfh = fopen($outfile, "a");
				} else {
					$folder = dirname($outfile);
					if (!file_exists($folder)) {
						__log("Creating folder [$folder]");
						mkdir($folder, 0770, true);
						// create dummy file 'cos thunderbird requires a subfolder.sbd to 
						// have a file with the same name in the parent folder
						if (substr($folder,-4)==".sbd") {
							$dummy = substr($folder, 0, -4);
							if (!file_exists($dummy)) file_put_contents($dummy,"");
						}
					}
					__log("Creating $outfile");
					$outfh = fopen($outfile, "w");
				}
				if (!$outfh) __err("Can't create output file [$outfile]");
				fputs($outfh, $line);
			} else {
				fputs($outfh, $line);
			}
			echo ".";
		} else {
			// continuation of message
			fputs($outfh, $line);
		}
		$prevline = trim($line);
	}
	echo "\n";

	fclose($fh);
	fclose($outfh);
}

function enumerateFiles($folder)
{
	$files = array();
	$ignorelist = array(".", "..");
	if ($handle = opendir($folder)) {
		while (false !== ($entry = readdir($handle))) {
			if (in_array($entry, $ignorelist)) {
			} elseif (is_dir($folder."/".$entry)) {
				$files = array_merge($files, enumerateFiles($folder."/".$entry));
			} else {
				$files[] = $folder . "/" . $entry;
			}
		}
		closedir($handle);
	}
	return $files;
}

function getOptions()
{
	$shortopt = "";
	$longopt = array(
		"infile:",
		"infolder:",
		"outfolder:",
		"deleteoriginal",
	);

	$opts = getopt($shortopt, $longopt);
	return $opts;
}

function __log($msg) 
{
	echo "[" .time() . "] " . $msg. "\n";
}

function __err($msg) 
{
	__log($msg);
	exit(1);
}

?>
