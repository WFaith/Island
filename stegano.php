<?php
$_CONST["MinRatio"] = 1;
$_CONST["HideRatioPos"] = 1000;
$_CONST["HideSizePos"] = 1500;

$_CONST["HideStart"] = 150000;

if ($argc < 3) {
	printf("usage: php stegano.php S|R folder|image\n");
	die();
}

switch ($argv[1]) {
	case "S":
		if (!file_exists($argv[2])) {
			printf("%s folder doesn't exists\n", $argv[2]);
			die();
		}
		$Folder2Store = $argv[2];

		// Find .bmp file in current dir
		$images = glob("./*.bmp");
		if (sizeof($images) == 0) {
			printf("No bmp image available in this directory\n");
			die();
		}
		// Zip folder to be stored
		$zip2Store = "./stegano-".time().".zip";
		zipFolder($Folder2Store, $zip2Store);

		$image = $images[mt_rand(0, sizeof($images)-1)];
		printf("Stockage en cours (%s)\n", $image);
		stegBetterHide($image, $zip2Store, str_replace(".bmp", "s.bmp", $image));
		unlink($zip2Store);
		printf("Stockage terminé\n");
		break;
	case "R":
		if (!file_exists($argv[2])) {
			printf("%s image doesn't exists\n", $argv[2]);
			die();
		}
		$image = $argv[2];
	
		printf("Récupération en cours\n");
		stegBetterRetrieve($image, "out.zip");
		printf("Récupération terminée\n");
		break;
	default: 
		printf("Unknown option: %s\n", $argv[1]);
		die();
}

function stegBetterRetrieve($combFilePath, $outputPath) {
	global $_CONST;
	
	if (!($combFD = fopen($combFilePath, "rb")))
		die("Erreur lors de l'ouverture de $combFilePath\n");
	if (!($hideFD = fopen($outputPath, "wb")))
		die("Erreur lors de l'ouverture de $outputPath\n");

	// récup les infos de taille et de pas
	if (fseek($combFD, $_CONST["HideRatioPos"])) printf("Warn: pb fseek $curPos");
	$ratio = fread($combFD, 4);
	printf("Ratio trouve: '%s'\n", $ratio);
	if (fseek($combFD, $_CONST["HideSizePos"])) printf("Warn: pb fseek $curPos");;
	$hideSize = fread($combFD, 10);
	printf("hideSize trouve: '%s'\n", $hideSize);


	// lire le contenu du fichier hide.
	$curPos = $_CONST["HideSizePos"]+10+$ratio;
	for ($i = 0; $i < $hideSize; $i++) {
		if (fseek($combFD, $curPos)) printf("Warn: pb fseek $curPos");
		$str = fread($combFD, 3);
		$char = chr((ord($str[0])%10)*100+(ord($str[1])%10)*10+(ord($str[2])%10));
		
		$curPos += $ratio;
		fprintf($hideFD, "%s", $char);
	}
	
	fclose($hideFD);	
	fclose($combFD);	
}


function stegBetterHide($origFilePath, $hideFilePath, $combFilePath) {
	global $_CONST;
	
	if ($_CONST["HideRatioPos"] > $_CONST["HideSizePos"]) 
		die("Erreur: $_CONST[HideRatioPos] > $_CONST[HideSizePos]\n");
		
	$origSize = filesize($origFilePath);
	$hideSize = filesize($hideFilePath);
	
	if ($origSize < $_CONST["HideSizePos"]) 
		die("Fichier $origFilePath trop petit ($origSize octects)\n");
	$ratio = floor(($origSize-$_CONST["HideSizePos"])/$hideSize);
	if ($ratio < $_CONST["MinRatio"]) 
		die("Le fichier $origFilePath fait moins de $_CONST[MinRatio] fois la taille de $hideFilePath\n");


	printf("Ratio utilise: '%s'\n", $ratio);
	printf("hideSize utilise: '%s'\n", $hideSize);


	// copie de fichier source:
	if (!copy($origFilePath, $combFilePath)) 
		die("Erreur lors de la copie de $origFilePath vers $combFilePath\n");
	
	if (!($hideFD = fopen($hideFilePath, "rb")))
		die("Erreur lors de l'ouverture de $hideFilePath\n");
	if (!($combFD = fopen($combFilePath, "r+b")))
		die("Erreur lors de l'ouverture de $hideFilePath\n");


	// Ecrire les infos de taille et de pas
	if (fseek($combFD, $_CONST["HideRatioPos"])) printf("Warn: pb fseek $curPos");
	fprintf($combFD, "%04d", $ratio);
	if (fseek($combFD, $_CONST["HideSizePos"])) printf("Warn: pb fseek $curPos");;
	fprintf($combFD, "%010d", $hideSize);
	

	// Ecrire le contenu du fichier hide.
	$count = 0;
	$curPos = $_CONST["HideSizePos"]+10+$ratio;
	for ($count = 0; $count < $hideSize; $count++) {
		$char = fread($hideFD, 1);
		//if ($count < 100) printf("'%s'", $char);
		
		if (fseek($combFD, $curPos)) printf("Warn: pb fseek $curPos\n");
		$threeChar = fread($combFD, 3);
		if (fseek($combFD, $curPos)) printf("Warn: pb fseek $curPos\n");
		
		
		//printf("---\n%d|%d|%d\n%d\n", ord($threeChar[0]),ord($threeChar[1]),ord($threeChar[2]),ord($char));
		$c1 = min(240, floor(ord($threeChar[0])/10)*10);
		$c2 = min(240, floor(ord($threeChar[1])/10)*10);
		$c3 = min(240, floor(ord($threeChar[2])/10)*10);   

		//printf("$c1|$c2|$c3\n");
		$c1 = $c1 + floor(ord($char)/100);
		$c2 = $c2 + floor(ord($char)%100/10);
		$c3 = $c3 + ord($char)%10;
		//printf("$c1|$c2|$c3\n");
		$str = chr($c1).chr($c2).chr($c3);
		//printf("---\n");
		//if ($count > 10)
//			die();
		
		$c = fwrite($combFD, $str);
		if ($c != 3) printf("Warn: (iter $count/$hideSize) pb fwrite ".$curPos." - ".$c." octets ecrits\n");
		$curPos += $ratio;
	}
	
	fclose($hideFD);
	fclose($combFD);
	
}


function stegRetrieve($combFilePath, $hideFilePath) {
	global $_CONST;
	
	if (!($combFD = fopen($combFilePath, "rb")))
		die("Erreur lors de l'ouverture de $combFilePath\n");
	if (!($hideFD = fopen($hideFilePath, "wb")))
		die("Erreur lors de l'ouverture de $hideFilePath\n");

	// récup les infos de taille et de pas
	if (fseek($combFD, $_CONST["HideRatioPos"])) printf("Warn: pb fseek $curPos");
	$ratio = fread($combFD, 4);
	printf("Ratio trouve: '%s'\n", $ratio);
	if (fseek($combFD, $_CONST["HideSizePos"])) printf("Warn: pb fseek $curPos");;
	$hideSize = fread($combFD, 10);
	printf("hideSize trouve: '%s'\n", $hideSize);


	// lire le contenu du fichier hide.
	$curPos = $_CONST["HideSizePos"]+10+$ratio;
	for ($i = 0; $i < $hideSize; $i++) {
		if (fseek($combFD, $curPos)) printf("Warn: pb fseek $curPos");
		$char = fread($combFD, 1);
		$curPos += $ratio;
		fprintf($hideFD, "%s", $char);
	}
	
	fclose($hideFD);	
	fclose($combFD);	
}


function stegHide($origFilePath, $hideFilePath, $combFilePath) {
	global $_CONST;
	
	if ($_CONST["HideRatioPos"] > $_CONST["HideSizePos"]) 
		die("Erreur: $_CONST[HideRatioPos] > $_CONST[HideSizePos]\n");
		
	$origSize = filesize($origFilePath);
	$hideSize = filesize($hideFilePath);
	
	if ($origSize < $_CONST["HideSizePos"]) 
		die("Fichier $origFilePath trop petit ($origSize octects)\n");
	$ratio = floor(($origSize-$_CONST["HideSizePos"])/$hideSize);
	if ($ratio < $_CONST["MinRatio"]) 
		die("Le fichier $origFilePath fait moins de $_CONST[MinRatio] fois la taille de $hideFilePath\n");


	printf("Ratio utilise: '%s'\n", $ratio);
	printf("hideSize utilise: '%s'\n", $hideSize);


	// copie de fichier source:
	if (!copy($origFilePath, $combFilePath)) 
		die("Erreur lors de la copie de $origFilePath vers $combFilePath\n");
	
	if (!($hideFD = fopen($hideFilePath, "rb")))
		die("Erreur lors de l'ouverture de $hideFilePath\n");
	if (!($combFD = fopen($combFilePath, "r+b")))
		die("Erreur lors de l'ouverture de $hideFilePath\n");


	// Ecrire les infos de taille et de pas
	if (fseek($combFD, $_CONST["HideRatioPos"])) printf("Warn: pb fseek $curPos");
	fprintf($combFD, "%04d", $ratio);
	if (fseek($combFD, $_CONST["HideSizePos"])) printf("Warn: pb fseek $curPos");;
	fprintf($combFD, "%010d", $hideSize);
	

	// Ecrire le contenu du fichier hide.
	$count = 0;
	$curPos = $_CONST["HideSizePos"]+10+$ratio;
	for ($count = 0; $count < $hideSize; $count++) {
		$char = fread($hideFD, 1);
		//if ($count < 100) printf("'%s'", $char);
		
		if (fseek($combFD, $curPos)) printf("Warn: pb fseek $curPos\n");
		$c = fwrite($combFD, $char);
		if ($c != 1) printf("Warn: (iter $count/$hideSize) pb fwrite ".$curPos." - ".$c." octets ecrits\n");
		$curPos += $ratio;
	}
	
	fclose($hideFD);
	fclose($combFD);
	
}

function zipFolder($path, $zipfile) {
	// Get real path for our folder
	$rootPath = realpath($path);

	// Initialize archive object
	$zip = new ZipArchive();
	$zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	// Create recursive directory iterator
	/** @var SplFileInfo[] $files */
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($rootPath),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ($files as $name => $file)
	{
		// Skip directories (they would be added automatically)
		if (!$file->isDir())
		{
			// Get real and relative path for current file
			$filePath = $file->getRealPath();
			$relativePath = substr($filePath, strlen($rootPath) + 1);

			// Add current file to archive
			$zip->addFile($filePath, $relativePath);
		}
	}

	// Zip archive will be created only after closing object
	$zip->close();
}

?>
