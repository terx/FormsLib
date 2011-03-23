<?
	printf("#CFS%s {", @$_SERVER['QUERY_STRING']);
?>
	form {
		font-family: Tahoma, Arial, sans-serif;
		font-size: 10pt;
	}
	#content {
		width: 50%;
	}
	#content div.back {
		align: left;
		float: left;
	}
	#content div.forward {
		align: right;
		float: right;
	}
	div.field {
		border: 1px dashed #aaa;
		padding: 5px;
		margin: 10px;
	/*
		background-image: url(/img/must_have_icon_set/Delete/Delete_16x16.png);
		background-repeat: no-repeat;
		background-position: right top;
	*/
	}
	div.input-text input.right {
		background-color: #cfc;
	}
	div.input-text input.wrong {
		background-color: #fcc;
	}
	div.hint {
		background-color: #eef;
		background-image: url(/img/silk/information.png);
		background-repeat: no-repeat;
		background-position: 5px 5px;
		padding: 5px 5px 5px 28px;
		min-height: 16px;
		border: 1px solid #008;
		margin: 1px;
	}
	div.error {
		color: #f00;
		background-color: #fee;
		background-image: url(/img/silk/error.png);
		background-repeat: no-repeat;
		background-position: 6px 6px;
		padding: 6px 6px 6px 30px;
		min-height: 18px;
		border: 1px solid #800;
		margin: 1px;
		font-weight: bold;
	}
	img.symbol {
		width: 16px;
		height: 16px;
		margin: 1px;
	}
	label {
		padding-left: 3px;
	}
	input.text, textarea {
		border: 1px solid #888;
		background-color: #f9f9f9;
	}
}