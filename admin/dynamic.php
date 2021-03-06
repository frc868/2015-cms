<?php
require("util.php");

$path = cleanPath($_GET['path']);

//Permissions checking
$allowed = true;
foreach($sub_perms as $permission) {
	if(patternMatch($permission["action"],$path,true)) {
		$allowed = $permission["allowed"];
	}
}
if(!$allowed) {
	redirect("permissions.php?action=denied");
}

$pages_table = Table::open("cms2-pages");
if(!$pages_table) {
	error_log("cms2-pages table is missing!");
	fatal_error();
}
$page = $pages_table->getRow($_GET["index"]);

if($action == "save") {
	$page->params = $_POST["params"];
	$page->template_path = $_POST["template_path"];
	$page->body = $_POST["body"];
	date_default_timezone_set("America/Indiana/Indianapolis");
	$page->date = date("D d/n/Y G:i:s");
	$page->write();

	//Templating stuff time
	if($page->template_path != "") {
		$template_path = $page->template_path;
		if($template_path[0] != "/") {
			$template_path = cleanPath(dirname($page->out_path) . "/" . $template_path);
		}

		$template = file_get_contents(cleanPath(ROOT_DIR . $template_path));
		$output = template_match($template,"template_replace",$page);
	}
	else {
		$output = template_match($page->body,"template_replace",$page);
	}
	$output = template_match($output,"template_clear",$page);

	file_put_contents(ROOT_DIR . $path,$output);
	chmod(ROOT_DIR . $path,FILE_PERM);
	
	redirect("dynamic.php?path=" . urlencode($path) . "&index=" . urlencode($page->index));
}

$extension = pathinfo($path,PATHINFO_EXTENSION);

?>
<html>
	<head>
		<meta charset="utf-8">
		<title>Dynamic Editor</title>
		<link rel="stylesheet" type="text/css" href="css/style.css">
	</head>
	<body>
		<div class="body-container">
			<form action="dynamic.php?action=save&path=<?php echo urlencode($path) ?>&index=<?php echo urlencode($page->index) ?>" method="POST">
				<h1><?php echo $path ?></h1>
				<input type="text" name="template_path" placeholder="Template Path" value="<?php echo htmlspecialchars($page->template_path) ?>"><br>
				<textarea name="body" data-editor="<?php echo $extension ?>" placeholder="The body of the file" style="height: 30em; width: 100%;"><?php echo htmlspecialchars($page->body) ?></textarea><br>
				<textarea name="params" placeholder="The parameters of the file" style="height: 30em; width: 100%;"><?php echo htmlspecialchars($page->params) ?></textarea><br>
				Last edited: <?php echo htmlspecialchars($page->date) ?><br>
				<input type="submit" value="Save">
				<a class="button" href="dynamic.php?path=<?php echo urlencode($path) ?>&index=<?php echo urlencode($page->index) ?>">Cancel</a>
				<a class="button" href="files.php?path=<?php echo urlencode(dirname($path)) ?>">Back</a>
			</form>
		</div>
	</body>
	<script src="/ace-builds/src-noconflict/ace.js" type="text/javascript" charset="utf-8"></script>
	<script src="/jquery-1.11.3.js" type="text/javascript" charset="utf-8"></script>
	<script>
	//Ace stuff
	$(function () {
		$('textarea[data-editor]').each(function () {
			var textarea = $(this);
			
			var mode = textarea.data('editor');
			
			var editDiv = $('<div>', {
				position: 'absolute',
				width: textarea.width(),
				height: textarea.height(),
				'class': textarea.attr('class')
			}).insertBefore(textarea);
			
			//textarea.css('visibility', 'hidden');
			textarea.css('display', 'none');
			
			var editor = ace.edit(editDiv[0]);
			//editor.renderer.setShowGutter(false);
			editor.getSession().setValue(textarea.val());
			editor.getSession().setUseWrapMode(true);

			if(mode == "js") mode = "javascript";
			if(mode == "md") mode = "markdown";
			if(mode == "csv") {
				editor.getSession().setUseSoftTabs(false);
				editor.getSession().setUseWrapMode(false);
			}
			editor.getSession().setMode("ace/mode/" + mode);
			//editor.setKeyboardHandler("ace/keyboard/emacs");
			editor.setTheme("ace/theme/chrome");
			
			test = editor;
			// copy back to textarea on form submit
			textarea.closest('form').submit(function () {
				textarea.val(editor.getSession().getValue());
			})
		});
	});
	</script>
</html>
