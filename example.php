<?php
	include('ClassFormsLib.php');
	$form = new FormsLib("test");
	
	$form->addFunction('blarg', function($value){
		return preg_match('/narf/', $value);
	});
	
	$step0 = $form->addPage(new i18n(array('de' => 'Willkommen', 'en' => 'Welcome!', 'fr' => 'Bonvoir!')));
	$step1 = $form->addPage("Narf");

	/* TextBox */
	$step0->addField('/textbox', new TextBox(array( 'regexp' => '/^(?:https?:\/\/)?[A-Za-z0-9.-]+(?:\/.*)?$/' )));
	
	/* Password */
	$step0->addField('/password', new Password(array( 'verify' => true )));
	
	/* MultiEdit */
	$step0->addField('/multiedit', new MultiEdit());

	/* TextArea */
	$step0->addField('/textarea', new TextArea());

	/* Label */
	$step0->addField('/label', new Label());
	
	/* Updater */
	$step0->addField('/updater', new Updater());
	
	/* RemoteSelect1 */
	$step0->addField('/remoteselect1', new RemoteSelect1(array()));
	
	/* RemoteRadio */
	$step0->addField('/remoteradio', new RemoteRadio(array()));
	
	/* Spacer */
	$step0->addField('/spacer', new Spacer());
	
	/* CheckBox */
	$step0->addField('/checkbox', new CheckBox());
	
	/* Radio */
	$step0->addField('/radio', new Radio());
	
	/* Select1 */
	$step0->addField('/select1', new Select1());
	
	/* EditBox */
	$step0->addField('/editbox', new EditBox());
	
	$form->setLastPage($step1);
	
	$form->finish();

	$step0->defaultPage($step1);
	
	$step0->getField('/textbox')->show_when(new CondAnd(array('/checkbox' => 'in(abc)')));

	$step0->defaultPage($step0);

	$form->handleRequests();
	
	$form->show();
	
?>
<html>
<head>
<script type="text/javascript" src="prototype.js"></script>
<script type="text/javascript" src="json2.js"></script>
<script type="text/javascript" src="ClassFormsLib.js"></script>
<!--<script src="http://connect.facebook.net/de_DE/all.js"></script>-->
<link rel="stylesheet" type="text/css" href="FormsLib.css" />
</head>
<body>
<div id="header">
<p>Testformular</p>
</div>
<div id="error"></div>
<div id="content">
<?php
	print $form->HTML();
?>
</div>
<script type="text/javascript">
<?php
	print $form->JC();
?>
</script>
</body>
</html>
