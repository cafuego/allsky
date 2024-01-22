#!/usr/bin/php
<?php

// Generic JSON modifying scripts needed because bash doesn't natively handle JSON.
// Output the names and values of each setting.

// --settings-file SETTINGS_FILE
//		Optional name of the settings file.
//		If not specified use the standard file.

// --delimiter D
//		Use "D" as the delimiter between the field name and its value.  Default is "=".

// --capture-only
//		Limit output to only settings used by the capture_* programs.
//		which will have this field in the options file:		"capture" : true
//		Without this option ALL settings/values in the settings file are output.

// --convert
//		Convert the field names to all lower case,
//		boolean values to    true   or   false,
//		and remove any quotes around numbers and booleans.
//		Output a complete settings file.
//		Cannot be used with --capture-only.  Ignores --delimiter.



include_once("functions.php");

$debug = false;
$settings_file = null;
$capture_only = false;
$delimiter = "=";
$convert = false;
$options_file = null;
$options_array = null;

$rest_index;
$longopts = array(
	"settings-file:",
	"options-file:",
	"delimiter:",

	// no arguments:
	"capture-only",
	"convert",
	"debug",
);
$options = getopt("", $longopts, $rest_index);
$ok=true;
foreach ($options as $opt => $val) {
	if ($debug || $opt === "debug") fwrite(STDERR, "===== Argument $opt $val\n");

	if ($opt === "debug") {
		$debug = true;
	} else if ($opt === "settings-file") {
		$settings_file = $val;
		if (! file_exists($settings_file)) {
			echo "ERROR: settings file '$settings_file' not found!\n";
			$ok = false;
		}
	} else if ($opt === "options-file") {
		$options_file = $val;
		if (! file_exists($options_file)) {
			echo "ERROR: options file '$options_file' not found!\n";
			$ok = false;
		}
	} else if ($opt === "capture-only") {
		$capture_only = true;
	} else if ($opt === "convert") {
		$convert = true;
	} else if ($opt === "delimiter") {
		$delimiter = $val;
	} // else: getopt() doesn't return a bad argument
}

if (! $ok || ($convert && $capture_only))
	exit(1);

if ($settings_file === null) {
	// use default
	$settings_file = getSettingsFile();
}
$errorMsg = "ERROR: Unable to process settings file '$settings_file'.";
$settings_array = get_decoded_json_file($settings_file, true, $errorMsg);
if ($settings_array === null) {
	exit(2);
}

if ($options_file === null) {
	// use default
	$options_file = getOptionsFile();
}
if ($capture_only || $convert) {
	$errorMsg = "ERROR: Unable to process options file '$options_file'.";
	$options_array = get_decoded_json_file($options_file, true, $errorMsg);
	if ($options_array === null) {
		exit(3);
	}
}

if ($capture_only) {
	foreach ($options_array as $option) {
		$type = getVariableOrDefault($option, 'type', "");

		// These aren't stored in the settings file.
		if (substr($type, 0, 6) == "header" || $type == "")
			continue;

		if (getVariableOrDefault($option, 'usage', "") == "capture") {
			$name = $option['name'];
			$val = getVariableOrDefault($settings_array, $name, null);
			if ($val !== null) {
				echo "$name$delimiter$val\n";
			}
		}
	}

} else if ($convert) {
	$mode = JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_NUMERIC_CHECK|JSON_PRESERVE_ZERO_FRACTION;

	// We want the output to be the same order as the options file so
	// create an options array indexed by the name where each element contains
	// all the fields of an option (name, type, default, etc.).
	// Keep track of any setting not in the options file,
	// and add it to the end of the new settings file.
// TODO: Do NOT add settings not in the options file.
// The only legit one would be "lastchanged", and since we're converting
// the settings file we want the user to be forced to look at the settings
// before starting Allsky.
	$new_settings_array = Array();
	$new_options = Array();
	foreach ($options_array as $option) {
		$type = getVariableOrDefault($option, 'type', "");
		if (substr($type, 0, 6) === "header") continue;

		$name = $option['name'];
		$new_options[$name] = $option;
// if ($debug) fwrite(STDERR, "Looking at option $name\n");

		$val = getVariableOrDefault($settings_array, $name, null);
		if ($val === null) {
			$val = getVariableOrDefault($option, 'default', "");
		} else {
			// $mode handles quotes around numbers.
			// We just need to convert bools to true and false without quotes.
			if ($type == "boolean") {
				if ($val == 1 || $val == "1" || $val == "true")
					$val = true;
				else
					$val = false;
			}
		}
		$new_settings_array[$name] = $val;
	}

	// Now determine which settings weren't in the options file.
	foreach ($settings_array as $setting => $val) {
		if (getVariableOrDefault($new_settings_array, $setting, null) === null) {
			if ($debug) fwrite(STDERR, "Setting '$setting' not in options file.\n");
/*
			$new_settings_array[$setting] = $val;
*/
		}
	}

	echo json_encode($new_settings_array, $mode);


} else {
	foreach ($settings_array as $name => $val) {
		echo "$name$delimiter$val\n";
	}
}

exit(0);
?>
