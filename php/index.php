<?php

	require_once("settings.php");
	require_once("inc/mysql_access.php");
	require_once("inc/mysql_commands.php");
	require_once("inc/common.php");
	require_once("inc/renderer.php");

    $db = array();
    $db["manuals"] = new ManualsData;
    $db["variables"] = new VariablesData;
    $db["groups"] = new GroupsData;
    $db["mgroups"] = new MGroupsData;
    $db["support"] = new SupportData;
    $db["commands"] = new CommandsData;
    $db["options"] = new OptionsData;
    $db["index"] = new IndexData;
    $db["search"] = new SearchHits;

    $sekce = each($_GET);
	$sekce = $sekce['key'];
	if ($sekce=="") { $sekce=$_SERVER["argv"][0]; }
	$maxfilectime = 0;
    if (!$sekce) $sekce = "main-page";

	$renderer = GetRenderer($sekce, $db);
	
	if(stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml"))	
    { 
      	header("Content-Type: application/xhtml+xml; charset=utf-8"); 
        $cthdr = "<meta http-equiv=\"Content-Type\" content=\"application/xhtml+xml; charset=utf-8\" />";
        echo('<?xml version="1.0" encoding="utf-8"?'.'>'."\n");
      	echo('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" 
      	"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'); 
    }
    else
    { 
      	header("Content-Type: text/html; charset=utf-8"); 
        $cthdr = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />";
      	echo ('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
      	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'); 
    }
?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <?=$cthdr?>
  <meta name="keywords" content="<?=META_KEYWORDS?>" />
  <meta name="description" content="<?=META_DESCRIPTION?>" />
  <meta name="author" content="<?=META_AUTHOR?>" />
  <meta name="generator" content="http://ezquake.sourceforge.net/docs/about" />
  <title><?=PROJECTNAME?> Manual: <?php echo(htmlspecialchars($renderer->title)); ?></title>
  <link rel="StyleSheet" type="text/css" href="style.css" title="verze 1" />
  <link rel="Alternate StyleSheet" type="text/css" href="style2.css" media="screen" title="verze 2 cuky" />
  <link rel="Alternate Stylesheet" href="http://www.w3.org/StyleSheets/Core/Modernist" media="screen" type="text/css" title="W3C Modernist" />
</head>
<body>
<h1><a href="./"><?=PROJECTNAME?> Manual</a>: <?php echo(htmlspecialchars($renderer->heading)); ?></h1>


<?php $renderer->RenderContent(); ?>


<p id="last-update">Last update: <?=date("d.m.Y H:i T",$renderer->lastupdate)?><br /><a href="http://ezquake.sourceforge.net/docs/about">ezQDocs</a></p>
<p><a href="http://sourceforge.net/projects/ezquake"><img src="http://sflogo.sourceforge.net/sflogo.php?group_id=117445&amp;type=2" width="125" height="37" alt="SourceForge.net Logo" /></a></p>
</body>
</html>
