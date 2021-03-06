<?php
define("MAIN","");
require("util.php");

$pages_table = Table::open("cms2-pages");
if(!$pages_table) {
	error_log("cms2-pages table is missing!");
	fatal_error();
}
$pages = $pages_table->getRows();

if($action == "purge") {
	foreach($pages as $page) {
		unlink(cleanPath(ROOT_DIR . $page->out_path));
	}
	redirect("index.php");
}

if($action == "regenerate") {
	foreach($pages as $page) {
		if($page->template_path != "") {
			$template = file_get_contents(cleanPath(ROOT_DIR . $page->template_path));
			$output = template_match($template,"template_replace",$page);
		}
		else {
			$output = template_match($page->body,"template_replace",$page);
		}
		$output = template_match($output,"template_clear",$page);
		
		file_put_contents(ROOT_DIR . $page->out_path,$output);
		chmod(ROOT_DIR . $page->out_path,FILE_PERM);
	}
	redirect("index.php");
}

function hash_files($dirname,$zip) {
	//echo "Entering dir: " . $dirname . "<br>";
	$zip->addEmptyDir(substr($dirname,1));
	$dir = dir(cleanPath(ROOT_DIR . $dirname));
	while(false !== ($entry = $dir->read())) {
		if($entry != "." && $entry != "..") {
			$entry = cleanPath($dirname . "/" . $entry);
			if(is_dir(cleanPath(ROOT_DIR . $entry))) {
				hash_files($entry,$zip);
			}
			else {
				if(filesize(cleanPath(ROOT_DIR. "/" . $entry)) <= 16777216) {
					$time = filemtime(cleanPath(ROOT_DIR . "/" . $entry));
					$hash = md5_file(cleanPath(ROOT_DIR . "/" . $entry));
					//echo $entry . ":" . $time . ":" . $hash . "<br>";
					$zip->addFromString(substr($entry,1),$time . "\n" . $hash);
				}
			}
		}
	}
}

if($action == "hash") {
	ini_set("max_execution_time", 300);
	unlink(cleanPath(ADMIN_DIR . "/tmp/hash.zip"));
	$zip = new ZipArchive();
	$zip->open(cleanPath(ADMIN_DIR . "/tmp/hash.zip"),ZipArchive::CREATE);

	hash_files("/",$zip);

	$zip->close();
	chmod(cleanPath(ADMIN_DIR . "/tmp/hash.zip"),FILE_PERM);

	header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=hash.zip');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize(cleanPath(ADMIN_DIR . "/tmp/hash.zip")));
    readfile(cleanPath(ADMIN_DIR . "/tmp/hash.zip"));
}

function backup_files($dirname,$hzip,$zip,$path) {
	if(cleanPath(ROOT_DIR . $dirname) == cleanPath(ADMIN_DIR . "/tmp/")) {
		return;
	}
	if(cleanPath(ROOT_DIR . $dirname) == cleanPath(ADMIN_DIR . "/session/")) {
		return;
	}
	if(cleanPath(ROOT_DIR . $dirname) == cleanPath(ROOT_DIR . "/.git/")) {
		return;
	}
	if(cleanPath(ROOT_DIR . $dirname) == cleanPath(ROOT_DIR . "/ace-builds/")) {
		return;
	}
	if(cleanPath(ROOT_DIR . $dirname) == cleanPath(ROOT_DIR . "/admin/")) {
		return;
	}
	//echo "Entering dir: " . $dirname . "<br>";
	$zip->addEmptyDir(substr($dirname,1));
	$dir = dir(cleanPath(ROOT_DIR . $dirname));
	while(false !== ($entry = $dir->read())) {
		if($entry != "." && $entry != "..") {
			$entry = cleanPath($dirname . "/" . $entry);
			if(is_dir(cleanPath(ROOT_DIR . $entry))) {
				backup_files($entry,$hzip,$zip,$path);
			}
			else {
				if(filesize(cleanPath(ROOT_DIR. "/" . $entry)) <= 16777216) {
					$rcont = $hzip->getFromName(substr($entry,1));
					if($rcont !== false) {
						$time = filemtime(cleanPath(ROOT_DIR . "/" . $entry));
						$hash = md5_file(cleanPath(ROOT_DIR . "/" . $entry));
						$rconts = explode("\n",$rcont);
						$rtime = $rconts[0];
						$rhash = $rconts[1];
						
						if($hash != $rhash &&
						   $time > $rtime) {
							$rcont = false;
						}
					}
					if($rcont === false) {
						$zip->addFile(cleanPath(ROOT_DIR . "/" . $entry),substr($entry,1));
						
						if(($zip->numFiles % 256) == 0) {
							$zip->close();
							$zip->open($path);
						}
					}
					//echo $entry . ":" . $time . ":" . $hash . ":" . ($rcont ? "TRUE" : "FALSE") . "<br>";
				}
			}
		}
	}
}

if($action == "backup") {
	ini_set("max_execution_time", 300);
	unlink(cleanPath(ADMIN_DIR . "/tmp/hash.zip"));
	if($_FILES["hash"]["error"] == UPLOAD_ERR_OK) {
		if ($_FILES["hash"]["error"] == UPLOAD_ERR_OK) {
			$tmp_name = $_FILES["hash"]["tmp_name"];
			move_uploaded_file($tmp_name, cleanPath(ADMIN_DIR . "/tmp/hash.zip"));
			chmod(cleanPath(ADMIN_DIR . "/tmp/hash.zip"),FILE_PERM);
		}
	}
	$hzip = new ZipArchive();
	$hzip->open(cleanPath(ADMIN_DIR . "/tmp/hash.zip"),ZipArchive::CREATE);

	unlink(cleanPath(ADMIN_DIR . "/tmp/backup.zip"));
	$zip = new ZipArchive();
	$zip->open(cleanPath(ADMIN_DIR . "/tmp/backup.zip"),ZipArchive::CREATE);

	backup_files("/",$hzip,$zip,cleanPath(ADMIN_DIR . "/tmp/backup.zip"));

	$hzip->close();
	$zip->close();
	chmod(cleanPath(ADMIN_DIR . "/tmp/backup.zip"),FILE_PERM);

	header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=backup.zip');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize(cleanPath(ADMIN_DIR . "/tmp/backup.zip")));
    readfile(cleanPath(ADMIN_DIR . "/tmp/backup.zip"));
}

if($action == "restore") {
	ini_set("max_execution_time", 300);
	unlink(cleanPath(ADMIN_DIR . "/tmp/backup.zip"));
	if($_FILES["backup"]["error"] == UPLOAD_ERR_OK) {
		if ($_FILES["backup"]["error"] == UPLOAD_ERR_OK) {
			$tmp_name = $_FILES["backup"]["tmp_name"];
			move_uploaded_file($tmp_name, cleanPath(ADMIN_DIR . "/tmp/backup.zip"));
			chmod(cleanPath(ADMIN_DIR . "/tmp/backup.zip"),FILE_PERM);
		}
	}

	$zip = new ZipArchive();
	$zip->open(cleanPath(ADMIN_DIR . "/tmp/backup.zip"));

	for($i = 0;$i < $zip->numFiles;$i++) {
		$filename = $zip->getNameIndex($i);
		$filepath = cleanPath(ROOT_DIR . "/" . $zip->getNameIndex($i));
		if(substr($filename,-1) == "/") {
			mkdir($filepath);
			chmod($filepath,DIR_PERM);
		}
		else {
			file_put_contents($filepath,$zip->getFromIndex($i));
			chmod($filepath,FILE_PERM);
		}
	}

	$zip->close();
	redirect("index.php");
}

if($action == "regendoc") {
	$path = cleanPath(ADMIN_DIR . "/doc/");
	$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
	foreach($objects as $name => $object){
		$extension = pathinfo($name,PATHINFO_EXTENSION);
		if($extension == "md") {
			$out_name = substr($name,0,strrpos($name,".")) . ".php";
			$input = file_get_contents($name);
			$Parsedown = new Parsedown();
			$output = $Parsedown->text($input);
			$util_path = cleanPath(ADMIN_DIR . "/util.php");
			$css_path = cleanPath(ADMIN_RDIR . "/css/style.css");
			$out = "<?php require(\"$util_path\");?>" . "\n" .
				   '<html>' .  "\n" .
				   '  <head>' . "\n" .
				   '    <meta charset="utf-8">' . "\n" .
				   '    <title>Documentation</title>' . "\n" .
				   "    <link rel=\"stylesheet\" type=\"text/css\" href=\"$css_path\">" . "\n" .
				   '  </head>' . "\n" .
				   '  <body>' . "\n" .
				   '    <div class="body-container">' . "\n" .
				   $output . "\n" .
				   '    </div>' . "\n" .
				   '  </body>' . "\n" .
				   '</html>' . "\n";
			file_put_contents($out_name,$out);
		}
	}
	redirect("index.php");
}

?>
<html>
	<head>
		<meta charset="utf-8">
		<title>Admin</title>
		<link rel="stylesheet" type="text/css" href="css/style.css">
	</head>
	<body>
		<div class="body-container">
			<h1>Admin</h1>
			<ul class="pill">
				<li class="left">Logged in as <b><?php echo isset($_SESSION["username"]) ? $_SESSION["username"] : "" ?></b>!</li>
				<li class="right"><a class="button" href="login.php?action=logout">Logout</a></li>
			</ul>
			<a href="users.php">User Management</a>
			<hr>
			<a href="files.php">File Explorer</a><br>
			<a href="index.php?action=purge">Purge All Files</a><br>
			<a href="index.php?action=regenerate">Regenerate All Files</a>
			<hr>
			<a href="index.php?action=hash">Generate File Hashes</a><br>
			<form action="index.php?action=backup" method="POST" enctype="multipart/form-data">
				<input name="hash" type="file"/><br />
				<input type="submit" value="Generate Backup"/>
			</form>
			<form action="index.php?action=restore" method="POST" enctype="multipart/form-data">
				<input name="backup" type="file"/><br />
				<input type="submit" value="Restore Backup"/>
			</form>
			<hr>
			<a href="error.php">View Errors</a><br>
			<hr>
			<a href="permissions.php">View Permissions</a><br>
			<hr>
			<a href="doc/index.php">View Documentation</a><br>
			<a href="files.php?path=<?php echo ADMIN_RDIR ?>/doc/">Edit Documentation</a><br>
			<a href="index.php?action=regendoc">Regenerate All Documentation</a><br>
		</div>
	</body>
</html>
