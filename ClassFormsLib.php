<?php
	include_once("JSONStore.php");
	
	class FormsLib
	{
		private $JC = '';
		private $HTML = '';
		private $form;
		private $pages = array();
		private $roots = array();
		private $last_page;
		private $hidden = array();
		private $url;
		private static $functions = array();
		private $uniqid;
		private $session;
		private $store;
		private $show = false;
		private $paths = array();
		
		function __construct($form)
		{
			$this->form = $form;
			$this->store = new JSONStore(new DBConnector('[::1]', 'test', '', ''));
		}
		
		function store()
		{
			return $this->store;
		}
		
		function addHidden($name, $value)
		{
			$hidden[$name] = $value;
		}
		
		static function addFunction($name, $caller)
		{
			self::$functions[$name] = $caller;
		}
		
		static function getFunction($name)
		{
			return @self::$functions[$name];
		}
		
		function handleRequests($caller = null)
		{
			if (isset($_COOKIE[$this->form.'_uniqid']) && preg_match('/^[0-9a-f]{32,128}$/', $_COOKIE[$this->form.'_uniqid'])) {
				$this->uniqid = $_COOKIE[$this->form.'_uniqid'];
			}

			if (isset($_COOKIE[$this->form.'_session']) && preg_match('/^[0-9a-f]{32,128}$/', $_COOKIE[$this->form.'_session'])) {
				$this->session = $_COOKIE[$this->form.'_session'];
			}
			
			if (!preg_match('/^[0-9a-f]{32,128}$/', $this->uniqid)) {
				$this->uniqid = hash_hmac('sha256', uniqid(mt_rand(), true), $this->form);
			}

			if (!preg_match('/^[0-9a-f]{32,128}$/', $this->session)) {
				$this->session = hash_hmac('sha256', uniqid(mt_rand(), true), $this->form);
			}
			
			if (!headers_sent()) {
				setcookie($this->form.'_uniqid', $this->uniqid, pow(2,31)-1);
				setcookie($this->form.'_session', $this->uniqid);
			}

			if ($_SERVER['REQUEST_METHOD'] == 'POST' and count($_REQUEST)) {
				print '<pre>';
				print_r($_REQUEST);

				function handlePage($page)
				{
					foreach ($page->getConditions() as $array)
					{
						list($next, $condition) = $array;
						printf("\tPage %s when: %s\n", $next->getIndex(), (is_object($condition)?$condition->phpexpr():$condition));
						if (handleCondition($condition))
							return $next;
					}
					return null;
				}
				
				function handleCondition($condition)
				{
					if (is_object($condition))
						$condition = $condition->phpexpr();
					if (!$condition)
						return true;
					$condition = preg_replace_callback('/#\{([a-zA-Z0-9_-]+)(|\[\])\}/', function($a) {
						return var_export(@$_REQUEST[$a[1]], true);
					}, $condition);
					$code = "\$ok=(($condition)?true:false);";
					$ok = false;
					eval($code);
					return ($ok?true:false);
				}
				
				function isin($ar, $b)
				{
					if (!is_array($ar))
						$ar = array($ar);
					$br = preg_split('/ +/', $b);
					foreach ($ar as $a)
						foreach ($br as $b)
							if ($a == $b)
								return true;
					return false;
				}
				
				function call($function, $value)
				{
					$caller = FormsLib::getFunction($function);
					return ($caller($value) ? true : false);
				}
				
				function fill_data(&$array, $key, $val, $append = true) {
					foreach (preg_split('/_/', $key) as $node) {
						if (!isset ($array[$node]))
							$array[$node] = array();
						$array = &$array[$node];
					}
					if ($append) {
						$array[] = $val;
					} else {
						$array[-1] = $val;
					}
				}

				function unarray(&$array) 
				{
					while (is_array($array)) {
						if (count($array) == 1) {
							if (isset($array[0])) {
								$array = $array[0];
							} else if (isset($array[-1])) {
								$array = $array[-1];
							} else break;
						} else break;
					}
					if (is_array($array)) {
						unset ($array[-1]);
						foreach ($array as &$obj) {
							unarray($obj);
						}
					}
				}
				
				$data = array();
				
				$page = $this->pages[0];
				do {
					printf("\n\n\n\nPage: %s (%s)\n", $page->getIndex(), $page->getLegend());
					foreach ($page->getFields() as $field) {
						printf("\tField: %s (%s)\n", $field->getName(), get_class($field));
						if ($field instanceof FaceBook) {
							foreach ($field->getTargets() as $name) {
								printf("\t\tSubfield: %s\n", $name);
								$name = preg_replace('/\[\]$/', '', $name);
								printf("\t\t\tValue: %s\n", $_REQUEST[$name]);
								fill_data($data, $name, $_REQUEST[$name], false);
							}
						} else {
							$name = preg_replace('/\[\]$/', '', $field->getName());
							if (!isset($_REQUEST[$name]))
								continue;
							$ok = true;
							foreach ($field->getConditions() as $cond) {
								printf("\t\tCondition: %s (%s)\n", (is_object($cond)?$cond->phpexpr():$cond), ($ok?'true':'false'));
							}
							if ($ok) {
								printf("\t\tValue: %s\n", $_REQUEST[$name]);
								fill_data($data, $name, $_REQUEST[$name]);
							}
						}
					}
					printf("\n");
				} while($page = handlePage($page));
				printf("\n");
				
				print_r($data);
				
				unset($data['']['temp']);

				unarray($data);
				
				if (isset($_REQUEST['__FB']))
					$data['FB'] = json_decode($_REQUEST['__FB'], true);
					
				$json = json_encode($data['']);
				
				print_r(json_decode($json, true));
				
				$date = $this->store->added($this->session, sha1($json));
				if (!$date) {
					$this->store->store($json, $this->session);
					if ($caller)
						$caller($this->session, $data);
					$date = strftime('%c');
				}
				
				printf(new i18n(array(
					'de' => 'Formular wurde gespeichert am %s.',
					'en' => 'Form was saved at %s.'
				)), $date, $date);
				
				exit;
			}

			if (@$_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest") {
				$json = array();
				if (isset ($_REQUEST['function']) and isset ($_REQUEST['value'])) {
					$function = $_REQUEST['function'];
					$value = $_REQUEST['value'];
					if ((FormsLib::getFunction($function))) {
						$caller = FormsLib::getFunction($function);
						$json['ret'] = ($caller($value) ? true : false);
					} else {
						$json['err'] = "unknown function '$function'";
					}
				}
				header("X-JSON: ".json_encode($json));
				exit;
			}
			
		}
		
		static function img($name)
		{
			$base = '/img';
			$theme = 'silk';
			$suffix = 'png';
			switch ($name) {
				case 'next':
				case 'next_grey':
				case 'previous':
				case 'previous_grey':
				case 'Stock Index Up':
					$name = preg_replace('/^([^_]+)_?(.*)$/','${1}/${1}_${2}24x24', ucfirst($name));
					$theme = 'must_have';
					break;
				case 'wait':
					$name = 'Synchronize/Synchronize_32x32_anim';
					$suffix = 'gif';
					$theme = 'must_have';
					break;
			}
			return "$base/$theme/$name.$suffix";
		}
		
		static function flag($cc)
		{
			$base = '/img';
			$theme = 'flags';
			$suffix = 'png';
			return "$base/$theme/$cc.$suffix";
		}
		
		function addPage ($page, $root = '/')
		{
			if (!(is_object($page) && $page instanceof Page)) {
				$page = new Page($page, $this->form.'_page'.count($this->pages), $this, $root);
			}
			$this->pages[] = $page;
			end($this->pages);
			$this->roots[key($this->pages)] = $root;
			return $page;
		}
		
		function setLastPage($page)
		{
			$this->last_page = $page;
		}
		
		function finish()
		{
			$this->HTML = sprintf('<form id="%s" method="POST" action="%s" style="display:none">', $this->form, $_SERVER['SCRIPT_NAME']);
			foreach ($this->pages as $i => $page) {
				$page->finish($this->roots[$i], ($page==$this->last_page));
				$this->HTML .= $page->HTML();
			}
			foreach ($this->hidden as $name => $value) {
				$this->HTML .= sprintf('<input type="hidden" name="%s" value="%s" />', $name, $value);
			}
			$this->HTML .= '</form>';
		}
		
		function show()
		{
			$this->show = true;
		}
		
		function HTML()
		{
			return preg_replace('/#\{FORM\}/', $this->form, '<div id="CFS#{FORM}">'.$this->HTML.'</div>');
		}
		
		function JC()
		{

			$JC = sprintf('%s = ((function(){', $this->form);
			foreach ($this->pages as $page) {
				$JC .= sprintf('this.addPage(%s);', $page->JC()).PHP_EOL;
			}
			if ($this->show) {
				$JC .= sprintf('this.show();').PHP_EOL;
			}
			$JC .= sprintf('this.info("READY!");').PHP_EOL;
			$JC .= sprintf('return this;').PHP_EOL;
			$JC .= sprintf('}).bind(new ClassFormsLib(%s)))();', json_encode($this->form)).PHP_EOL;
			return $this->JC.preg_replace('/#\{FORM\}/', $this->form, $JC);
		}
		
		function fillform($data) {
			$this->JC .= sprintf('%s.setDefault(%s);', $this->form, json_encode($data));
		}
		
		function addFBAPI($appid)
		{
			$this->JC .= sprintf('try{FB.init({appId:"%s",status:true,cookie:true});}catch(e){FB=false;}', $appid);
		}
		
		function registerPath($path)
		{
			if ($this->checkPath($path)) {
				return false;
			}
			$this->paths[] = $path;
			return true;
		}
		
		function checkPath($path)
		{
			return in_array(preg_replace('|/+|', '/', $path), $this->paths, true);
		}
	}
	
	class Page
	{
		private $form;
		private $root;
		private $legend;
		private $index;
		private $fields = array();
		private $path_to_id = array();
		private $id_to_path = array();
		private $temp_paths = array();
		
		private $HTML = '';
		private $JC = '';
		
		private $conditions = array();
		
		private $div;

		
		function __construct($legend, $index, $form, $root)
		{
			$this->legend = $legend;
			$this->index = $index;
			$this->div = $this->getIdPrefix('node');
			$this->form = $form;
			$this->root = $root;
		}
		
		function getLegend()
		{
			return $this->legend;
		}
		
		function getIndex()
		{
			return $this->index;
		}
		
		function getFields()
		{
			return $this->fields;
		}
		
		function getConditions()
		{
			return $this->conditions;
		}
		
		protected function getIdPrefix($pathname)
		{
			if (isset ($this->path_to_id[$pathname])) {
				return $this->path_to_id[$pathname];
			}
			$name = preg_replace('/[\[\]]/', '', self::getNamePrefix($pathname));
			$i = 0;
			$id = '';
			do {
				$id = $this->index.'_'.$name.'_'.$i++;
			} while (isset($this->fields[$id]));
			$this->path_to_id[$pathname] = $id;
			$this->id_to_path[$id] = $pathname;
			return $id;
		}
		
		static function getNamePrefix($pathname)
		{
			return preg_replace('/\/+/', '_', $pathname);
		}
		
		protected function getTempPath($root = '')
		{
			$i = 0;
			do {
				$path = $root.'/temp/anon'.$i;
				$i++;
			} while ($this->form->checkPath($path));
			return $path;
		}
		
		function addField($path, Field $field)
		{
			if ($field instanceof FieldGroup) {
				$fields = $field;
				$root = $path;
				foreach ($fields->getFields() as $path => $field) {
					if (is_numeric($path)) {
						$path = null;
					} else {
						$path = $fields->getRoot().$root.$path;
					}
					$this->addField($path, $field);
				}
				return $fields;
			}
			
			if ($path === null) {
				$path = $this->getTempPath($this->root); 
			} else {
				$path = $this->root.$path;
			}
			$path = preg_replace('|/+|', '/', $path);
			if (!$this->form->registerPath($path)) {
				trigger_error('re-registering path: '.$path, E_USER_WARNING);
			}
			$this->fields[$path] = $field;
			return $field;
		}
		
		function getField($path)
		{
			return $this->fields[preg_replace('|/+|', '/', $this->root.$path)];
		}
		
		function nextPage($condition, $nextpage)
		{
			$this->conditions[] = array($nextpage, $condition);
		}
		
		function defaultPage($page)
		{
			//$this->nextPage(null, $page);
			
			foreach ($this->conditions as $array) {
				list($nextpage, $condition) = $array;
				$this->JC .= sprintf('this.addCondition(function(){return %s;},%s);', $condition, json_encode($nextpage->div()));
			}
			$this->JC .= sprintf('this.defaultpage = %s;', json_encode($page->div()));
		}
		
		function finish($root, $last = false)
		{
			$this->HTML = sprintf('<div id="%s" style="display:none;"><fieldset><legend>%s</legend><div id="%s__pageerror" class="error" style="display:none;">%s</div>', $this->div, $this->legend, $this->div, new i18n(array('en' => 'There are problems with some fields.', 'de' => 'Einige Felder sind nicht korrekt ausgefüllt.')));
			foreach ($this->fields as $path => $field) {
				//$path = $root.'/'.$path;
				$this->HTML .= "\n\n<!-- $path -->\n";
				$field->finish($this->getIdPrefix($path), $this::getNamePrefix($path));
				$this->HTML .= $field->HTML();
			}
			$this->HTML .= sprintf('<br /><div class="btn-back"><a href="javascript:#{FORM}.getPage(\'%s\').back();" border=0><img border="0" alt="Back" id="%s_back" src="%s" /></a></div>', $this->div, $this->div, FormsLib::img('previous_grey'));
			
			$this->HTML .= '<div class="btn-forward">';
			if ($last) {
				$this->HTML .= sprintf('<a href="javascript:#{FORM}.submit();" border="0"><img border="0" alt="Submit" id="%s_submit" src="%s" /></a>',$this->div, FormsLib::img('Stock Index Up'));
			} else {
				$this->HTML .= sprintf('<a href="javascript:#{FORM}.getPage(\'%s\').forward();" border="0"><img border="0" alt="Next" id="%s_forward" src="%s" /></a>', $this->div, $this->div, FormsLib::img('next'));
			}
			$this->HTML .= '</div>';
			
			$this->HTML .= '</fieldset></div>';
		}
		
		function div()
		{
			return $this->div;
		}
		
		function HTML()
		{
			return $this->HTML;
		}
		
		function JC()
		{
			$JC = sprintf('((function(){%s', PHP_EOL.$this->JC.PHP_EOL);
			foreach ($this->fields as $field) {
				$JC .= sprintf("\t".'this.addField(%s);'."\n", $field->JC());
			}
			$JC .= sprintf('return this;}).bind(new ClassFormsLibPage(this,%s)))()', json_encode($this->div));
			return $JC;
		}
		
	}
	
	abstract class Field
	{
		private $HTML = '';
		private $JC = '';
		private $cond = null;
		private $HTML_FIN = '';
		private $JC_FIN = '';
		private $name;
		private $div;
		private $idprefix;
		private $conds = array();
	
		function __construct()
		{
		}
		
		function show_when($cond)
		{
			if (!$this->name) {
				trigger_error('cannot apply visibility condition on unfinished field', E_USER_WARNING);
				return null;
			}
			$this->JC_FIN .= $cond->observe();
			$cond->finish();
			$this->cond = "$cond";
			$this->conds[] = $cond->phpexpr();
		}
		
		function finish($idprefix, $nameprefix)
		{
			$this->name = $nameprefix;
			$this->div = $idprefix.'___div';
			$this->idprefix = $idprefix;

			$this->HTML_FIN = sprintf('<div id="#{ID/__div}" class="field"><span class="btn-reload" style="display:none"><a href="javascript:#{FORM}.getFieldObj(\'#{NAME}\').update();"><img border="0" src="%s"></a></span>', FormsLib::img('arrow_rotate_clockwise')).$this->HTML;
			$this->HTML_FIN = preg_replace('/#\{ID\/([A-Za-z0-9_-]+)\}/', $idprefix.'_$1', $this->HTML_FIN);
			$this->HTML_FIN = preg_replace('/#\{ID\}/', $idprefix, $this->HTML_FIN);
			$this->HTML_FIN = preg_replace('/#\{NAME\}/', $nameprefix, $this->HTML_FIN);
			$this->HTML_FIN .= '</div>';
			
			$this->JC_FIN = $this->JC;
			
			$this->JC_FIN = preg_replace('/#\{ID\/([A-Za-z0-9_-]+)\}/', $idprefix.'_$1', $this->JC);
			$this->JC_FIN = preg_replace('/#\{ID\}/', $idprefix, $this->JC_FIN);
			$this->JC_FIN = preg_replace('/#\{NAME\}/', $nameprefix, $this->JC_FIN);
			
		}
		
		function getName()
		{
			if (!$this->name) {
				trigger_error('cannot get name on unfinished field', E_USER_WARNING);
				return null;
			}
			return $this->name;
		}
		
		protected function put($line)
		{
			$this->HTML .= $line;
		}
		
		protected function code($line)
		{
			if ($this->name) {
				$idprefix = preg_replace('/___div$/', '', $this->div);
				$line = preg_replace('/#\{ID\/([A-Za-z0-9_-]+)\}/', $idprefix.'_$1', $line);
				$line = preg_replace('/#\{ID\}/', $idprefix, $line);
				$line = preg_replace('/#\{NAME\}/', $this->name, $line);
				$this->JC_FIN .= $line."\n";
			} else {
				$this->JC .= $line."\n";
			}
		}
		
		function HTML()
		{
			return $this->HTML_FIN;
		}
		
		function JC()
		{
			return sprintf(
				'((function(){%sreturn this;}).bind(new ClassFormsLib%sField(this,%s,%s,%s)))()',
				$this->JC_FIN,
				get_class($this),
				json_encode($this->name),
				json_encode($this->idprefix),
//				($this->cond?sprintf('((function(){return(%s);}).bind(this))', $this->cond):'null')
				($this->cond?sprintf(
					'((function(){this.info("condition check: "+\'%s\');return(%s);}).bind(this))',
					$this->cond,
					$this->cond
				):'null')
			);
		}
		
		function getConditions()
		{
			return $this->conds;
		}
	}
	
	class FieldGroup extends Field
	{
		private $rootpath = '/';
		private $fields = array();
		
		function __construct($fields)
		{
			$this->fields = $fields;
		}
		
		function setRoot($path)
		{
			$this->rootpath = $path;
		}
		
		function getRoot()
		{
			return $this->rootpath;
		}
		
		function getFields()
		{
			return $this->fields;
		}
		
		function show_when($cond)
		{
			foreach ($this->fields as $field) {
				$field->show_when($cond);
			}
		}
	}
	
	class TextBox extends Field
	{
		function __construct($options = array())
		{
			$options = array_merge(array(
				'min' => 40,
				'max' => 256,
				'hint' => null,
				'regexp' => 'null',
				'error' => 'Bitte füllen die dieses Feld korrekt aus.',
				'label' => 'Textfeld'
			), $options);
			$this->code(sprintf('this.type = "TextBox";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->code(sprintf('this.regexp = %s;', $options['regexp']));
			$this->put('<div class="input-text">');
			$this->put(sprintf('<div id="#{ID/__error}" class="error" style="display:none;">%s</div>', $options['error']));
			$this->put(sprintf('<label for="#{ID}">%s:</label><br />', $options['label']));
			$this->put(sprintf('<input class="text" type="text" id="#{ID}" name="#{NAME}" value="" size="%d" maxlength="%d" />&nbsp;<img id="#{ID/__symbol}" style="display:none;" class="symbol" />', $options['min'], $options['max']));
			if ($options['hint']) {
				$this->put(sprintf('<div id="#{ID/__hint}" class="hint" style="display:none;">%s</div>', $options['hint']));
			}
			$this->put('</div>');
		}
		
		function cond($cond)
		{
			$this->code(sprintf("this.cond = (function(){return %s;});", $cond));
		}
	}

	class Password extends Field
	{
		function __construct($options = array())
		{
			$options = array_merge(array(
				'min' => 20,
				'max' => 256,
				'error' => 'Das Passwort ist nicht sicher genug',
				'verify_error' => 'Haha, Dummkopf!',
				'verify' => false,
				'hint' => 'Hint',
				'label' => 'Passwort',
				'regexp' => '/./'
			), $options);
			$this->code(sprintf('this.type = "TextBox";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->code(sprintf('this.regexp = %s;', $options['regexp']));
			$this->put('<div class="input-password">');
			$this->put(sprintf('<div id="#{ID/__error}" class="error" style="display:none;">%s</div>', $options['error']));
			$this->put(sprintf('<label for="#{ID}">%s:</label><br />', $options['label']));
			$this->put(sprintf('<input class="text" type="password" id="#{ID}" name="#{NAME}" value="" size="%d" maxlength="%d" />&nbsp;<img id="#{ID/__symbol}" style="display:none;" class="symbol" />', $options['min'], $options['max']));
			if ($options['hint']) {
				$this->put(sprintf('<div id="#{ID/__hint}" class="hint" style="display:none;">%s</div>', $options['hint']));
			}
			$this->put('</div>');
			if ($options['verify']) {
				$this->put('<br /><div class="input-password-verify">');
				$this->put(sprintf('<div id="#{ID/verify__error}" class="error" style="display:none;">%s</div>', $options['verify_error']));
				$this->put(sprintf('<label for="#{ID/verify}">Verifizieren:</label><br />'));
				$this->put(sprintf('<input class="text" type="password" id="#{ID/verify}" value="" size="%d" maxlength="%d" />&nbsp;<img id="#{ID/verify__symbol}" style="display:none;" class="symbol" />', $options['min'], $options['max']));
				if (@$options['verify_hint']) {
					$this->put(sprintf('<div id="#{ID/verify__hint}" class="hint" style="display:none;">%s</div>', $options['verify_hint']));
				}
				$this->put('</div>');
			}
		}
		
		function cond($cond)
		{
			$this->code(sprintf("this.cond = (function(){return %s;});", $cond));
		}
	}

	class MultiEdit extends Field
	{
		function __construct($options = array())
		{
			$options = array_merge(array(
				'label' => 'Textfelder'
			), $options);
			$this->code(sprintf('this.type = "MultiEdit";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->put(sprintf('<input type="hidden" name="#{NAME}" value=""><div id="#{ID}"><label>%s:</label><br/></div>', $options['label']));
		}
	}
	
	class TextArea extends Field
	{
		function __construct($options = array(), $default = '')
		{
			$options = array_merge(array(
				'rows' => 25,
				'cols' => 80,
				'label' => 'Textfeld'
			), $options);
			$this->code(sprintf('this.type = "TextArea";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->put(sprintf('<div class="input-textarea"><label for="#{ID}">%s:</label><br/>', $options['label']));
			$this->put(sprintf('<textarea id="#{ID}" name="#{NAME}" rows="%d" cols="%d">%s</textarea>', $options['rows'], $options['cols'], $default));
			$this->put('</div>');
		}
	}
	
	class Label extends Field
	{
		function __construct()
		{
			$this->code(sprintf('this.type = "Label";'));
			$this->code(sprintf('this.labels = %s;', json_encode(func_get_args())));
			$labels = func_get_args();
			$this->put('<div class="label">');
			foreach ($labels as $label) {
				$this->put('<p>'.$label.'</p>');
			}
			$this->put('</div>');
		}
	}
	
	class Updater extends Field
	{
		function __construct($options = array())
		{
			$options = array_merge(array(
				'url' => $_SERVER['SCRIPT_NAME']
			), $options);
			$this->code(sprintf('this.type = "Updater";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->put('<img id="#{ID/__symbol}" style="display:none;" class="symbol" />');
			$this->put('<div id="#{ID}" class="updater"></div>');
		}
	}

	class RemoteSelect1 extends Field
	{
		function __construct($options)
		{
			$options = array_merge(array(
				"url" => $_SERVER['SCRIPT_NAME'],
				"label" => "Auswahl"
			), $options);
			$this->code(sprintf('this.type = "RemoteSelect1";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->put(sprintf('<div class="input-select1"><label for="#{ID}">%s:</label><br/><select name="#{NAME}" id="#{ID}">', $options['label']));
			$this->put('</select></div><br /><img id="#{ID/__symbol}" style="display:none;" class="symbol" />');
		}
	}

	class RemoteRadio extends Field
	{
		function __construct($options)
		{
			$options = array_merge(array(
				"data" => array("null" => "null"),
				"url" => $_SERVER["SCRIPT_NAME"],
				"hint" => "Keine Optionen verfügbar"
			), $options);
			$this->code(sprintf('this.type = "RemoteRadio";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->put('<div id="#{ID}"></div><br /><img id="#{ID/__symbol}" style="display:none;" class="symbol" />');
			$this->put(sprintf('<div id="#{ID/hint}" class="alert" style="display:none;">%s</div>', $options['hint']));
		}
	}

	class Spacer extends Field
	{
		function __construct()
		{
			$this->code(sprintf('this.type = "Spacer";'));
			$this->put('<div class="spacer"><hr></div>');
		}
	}
	
	class FaceBook extends Field
	{
		static public $fb_graph_perms = array(
			'/me' => array(
				'id' => '',
				'first_name' => '',
				'last_name' => '',
				'name' => '',
				'link' => '',
				'about' => 'user_about_me',
				'birthday' => 'user_birthday',
				'work' => 'user_work_history',
				'education' => 'user_education_history',
				'email' => 'email',
				'website' => 'user_website',
				'hometown' => 'user_hometown',
				'location' => 'user_location',
				'bio' => 'user_about_me',
				'quotes' => 'user_about_me',
				'gender' => '',
				'interested_in' => 'user_relationship_details',
				'meeting_for' => 'user_relationship_details',
				'relationship_status' => 'user_relationships',
				'religion' => 'user_religion_politics',
				'political' => 'user_religion_politics',
				'verified' => '',
				'significant_other' => 'user_relationship_details',
				'locale' => ''
			)
		);
		
		private $targets = array();
		
		function getTargets()
		{
			return $this->targets;
		}

		function __construct($options)
		{
			$perms = array();
			$me = self::$fb_graph_perms['/me'];
			$this->code(sprintf('this.type = "FaceBook";'));
			$this->code(sprintf('this.permsmap = %s;', json_encode($me)));
			$this->put('<div id="#{ID}"></div>');
			
			$fields = array();
			
			foreach ($options as $array) {
				/* array('field from graph api','aggregate function (or empty string or null)','the target path','a description of the field'); */
				$name = $array[0];
				$func = null;
				$target = Page::getNamePrefix($array[2]);
				if (!$target) {
					$this->put(sprintf('<input type="hidden" name="%s" value="" />', $target));
				}
				$this->targets[] = $target;
				$label = $array[3];
				if (preg_match('/^[\t ]*replace[\t ]*\([\t ]*"(.*)",[\t ]*"(.*)"[\t ]*\)[\t ]*$/', $array[1], $match)) {
					$func = array('replace' => array('pattern' => $match[1], 'replacement' => $match[2]));
				}
				if (preg_match('/^\/me\[([a-z_]+)\]$/', $name, $match)) {
					$name = $match[1];
					if (isset ($me[$name])) {
						$perm = $me[$name];
						if ($perm) {
							$perms = array_merge($perms, preg_split('/,/', $perm));
						}
						$fields[$target] = array(
							'func' => $func,
							'name' => $name,
							'label' => $label
						);
					}
				}
			}
			$this->code(sprintf('this.fields = %s;', json_encode($fields)));
			$this->code(sprintf('this.perms = %s;', json_encode($perms)));
		}
	}

	class CheckBox extends Field
	{
		function __construct($options = array())
		{
			$this->code(sprintf('this.type = "CheckBox";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			foreach ($options as $key => $label) {
				$this->put('<div class="input-checkbox">');
				$this->put(sprintf('<input class="checkbox" type="checkbox" id="#{ID/%s}" name="#{NAME}[]" value="%s" />', $key, $key));
				$this->put(sprintf('<label for="#{ID/%s}">%s</label>', $key, $label));
				$this->put('</div>');
			}
		}
	}
	
	class Radio extends Field
	{
		function __construct($options = array())
		{
			$this->code(sprintf('this.type = "Radio";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->put('<div id="#{ID}">');
			foreach ($options as $key => $label) {
				$this->put('<div class="input-radio">');
				$this->put(sprintf('<input class="radio" type="radio" id="#{ID/%s}" name="#{NAME}" value="%s" />', $key, $key));
				$this->put(sprintf('<label for="#{ID/%s}">%s</label>', $key, $label));
				$this->put('</div>');
			}
			$this->put('</div>');
		}
	}
	
	class Select1 extends Field
	{
		function __construct($options = array(), $data = array())
		{
			$options = array_merge(array(
				"label" => "Auswahl"
			), $options);
			$this->code(sprintf('this.type = "Select1";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->code(sprintf('this.data = %s;', json_encode($data)));
			$this->put(sprintf('<div class="input-select1"><label for="#{ID}">%s:</label><br/><select name="#{NAME}" id="#{ID}">', $options['label']));
			foreach ($data as $value => $label) {
				$this->put(sprintf('<option value="%s">%s</option>', $value, $label));
			}
			$this->put('</select></div>');
		}
	}
	
	class EditBox extends Field
	{
		function __construct($options = array())
		{
			$options = array_merge(array(
				'min' => 40,
				'max' => 256,
				'hint' => null,
				'regexp' => null,
				'error' => 'Bitte füllen die dieses Feld korrekt aus.',
				'label' => 'Textfeld'
			), $options);
			$this->code(sprintf('this.type = "EditBox";'));
			$this->code(sprintf('this.options = %s;', json_encode($options)));
			$this->put('<div class="input-editbox">');
			
			$this->put(sprintf('<input type="hidden" name="#{NAME}" value="" />', $options['min'], $options['max']));
			$this->put(sprintf('<label for="#{ID}">%s:</label><br />', $options['label']));
			
			$this->put(sprintf('<div id="#{ID/__showbox}" class="show">'));
			$this->put(sprintf('<span class="value" id="#{ID/__span}"></span>'));
			$this->put(sprintf('&nbsp;<a href="javascript:#{FORM}.getFieldObj(\'#{NAME}\').edit();" border="0"><img border="0" src="%s"></a>', FormsLib::img('pencil')));
			$this->put(sprintf('</div>'));
			
			$this->put(sprintf('<div id="#{ID/__error}" class="error" style="display:none;">%s</div>', $options['error']));
			
			$this->put(sprintf('<div id="#{ID/__editbox}" class="edit" style="display:none;">'));
			$this->put(sprintf('<input class="text" type="text" id="#{ID}" value="" size="%d" maxlength="%d" />', $options['min'], $options['max']));
			$this->put(sprintf('&nbsp;<a href="javascript:#{FORM}.getFieldObj(\'#{NAME}\').apply();" border="0"><img border="0" src="%s"></a>', FormsLib::img('tick')));
			$this->put(sprintf('&nbsp;<a href="javascript:#{FORM}.getFieldObj(\'#{NAME}\').abort();" border="0"><img border="0" src="%s"></a>', FormsLib::img('cross')));
			$this->put('</div>');
			
			$this->put('</div>');
			if ($options['hint']) {
				$this->put(sprintf('<div id="#{ID/__hint}" class="hint" style="display:none;">%s</div>', $options['hint']));
			}
		}
	}

	class i18n
	{
		private $array = array();
		private $defaultlang = 'de';
		private static $languages = array();
		private static $lang2cc = array(
			'en' => 'gb'
		);
		private static $language_priority = array('de','en','fr');
		
		
		function __construct($array)
		{
			$this->array = $array;
			self::$languages = array_unique(array_merge(self::$languages, array_keys($array)));
		}
		
		function __toString()
		{
			$str = '<span class="localized">';
			foreach ($this->array as $lang => $text) {
				$str .= sprintf('<span lang="%s" style="display:%s;">%s</span>', $lang, ($lang == $this->defaultlang ? '':'none'), $text);
			}
			return $str.'</span>';
		}
		
		static function create_switcher()
		{
			$str = '<script type="text/javascript">';
			$str .= 'function setLang(lang){';
			$str .= '$$("span.localized>span[lang]").each(function(e){e.hide();});';
			$str .= '$$("span.localized>span[lang="+lang+"]").each(function(e){e.show();});';
			foreach (array_merge(array_values(array_intersect(self::$language_priority, self::$languages)), array_values(array_diff(self::$languages, self::$language_priority))) as $lang) {
				$str .= '$$("span.localized").each(function(e){';
					$str .= 'e.select("span[lang]").each(function(f){';
						$str .= 'c=f.visible();';
						$str .= '!c&&$A(f.siblings()).each(function(g){';
							$str .= 'if(g.visible()){';
								$str .= 'c=true;';
							$str .= '}';
						$str .= '});';
						$str .= 'if(!c){';
							$str .= 'f.show();';
						$str .= '}';
					$str .= '});';
				$str .= '});';
			}
			$str .= '}';
			$str .= sprintf('setLang("%s");', 'de');
			$str .= '</script><div class="language-switcher">Select Language:';
			foreach (self::$languages as $lang) {
				$cc = $lang;
				if (isset(self::$lang2cc[$lang])) {
					$cc = self::$lang2cc[$lang];
				}
				$str .= sprintf('&nbsp;<a href="javascript:alert(\'setLang(%s)\');"><img border=0 src="%s"></a>', $lang, FormsLib::flag($cc));
			}
			return $str.'</div>';
		}
		
	}
	
	abstract class Cond
	{
		private $JC = '/* JC */';
		private $JC_FIN = '/* JC_FIN */';
		protected $expressions = array();
		private $names = array();
		protected $phpexpr = array();
		
		function __construct($array = array())
		{
			if (!is_array($array))
				$array = array($array);

			foreach ($array as $field => $function) {
				if (is_object($function)) {
					$this->expressions[] = $function;
					$this->names[] = $function();
					$this->phpexpr[] = $function->phpexpr();
				} elseif (is_array($function)) {
					$this->__construct($function);
				} else {
					$this->add($field, $function);
				}
			}
		}
		
		function add($field, $function) 
		{
			if (is_object($field) and $field instanceof Field) {
				$name = $field->getName();
			} else {
				$name = Page::getNamePrefix($field);
			}
			$this->names[] = $name;
			if (preg_match('/^(!?)\/(.*)\/$/', $function, $match)) {
				$this->expressions[] = sprintf('%s.test($F(this.root.getField("%s")))', $function, $name);
				$this->phpexpr[] = sprintf('%spreg_match(%s,#{%s})', $match[1], var_export('/'.$match[2].'/', true), $name);

			} else if (preg_match('/^eq\((.+)\)$/', $function, $match)) {
				$this->expressions[] = sprintf('$F(this.root.getField("%s"))=="%s"', $name, $match[1]);
				$this->phpexpr[] = sprintf('#{%s}==%s', $name, var_export($match[1], true));

			} else if (preg_match('/^in\((.+)\)$/', $function, $match)) {
				$this->expressions[] = sprintf('this.isin(this.root.getField("%s"),"%s")', $name, $match[1]);
				$this->phpexpr[] = sprintf('isin(#{%s},%s)', $name, var_export($match[1], true));

			} else if (preg_match('/^fn\((.+)\)$/', $function, $match)) {
				if (!(FormsLib::getFunction($match[1])))
					trigger_error('unknown function: '.$match[1], E_USER_WARNING);
				$this->expressions[] = sprintf('this.cfn("%s",this.root.getField("%s"))', $match[1], $name);
				$this->phpexpr[] = sprintf('call(%s,#{%s})', var_export($match[1], true), $name);

			} else {
				trigger_error('unknown expression: "'.$function.'"', E_USER_WARNING);
				array_pop($this->names);
			}
			return $this;
		}
		
		function observe()
		{
			$JC = '';
			foreach ($this->names as $name) {
				if (is_object($name)) {
					$JC .= $name->observe();
				} else {
					$JC .= sprintf(PHP_EOL.'this.info("add events for %s:");this.V(this.root.getField("%s")).each(function(e){this.info(["add event listener",e,this]);e.on((e.type=="hidden"?"value:changed":(e.type=="checkbox"||e.type=="radio"?"click":"change")),this.changed.bind(this));},this);', $name, $name);
				}
			}
			return $JC.PHP_EOL;
		}
		
		function finish()
		{
			$this->JC_FIN = $this->JC;
			foreach ($this->names as $name) {
				if (is_object($name)) {
					$name->finish();
					$this->JC_FIN .= $name->JC();
				}
			}
		}
		
		function JC()
		{
			return $this->JC_FIN;
		}
		
		function __invoke() {
			return $this;
		}
	}
	
	class CondAnd extends Cond
	{
		function __toString()
		{
			$expr = '';
			foreach ($this->expressions as $expression) {
				$expr .= sprintf ('&&(%s)', $expression);
			}
			return substr($expr, 2);
		}
		
		function phpexpr()
		{
			$expr = '';
			foreach ($this->phpexpr as $expression) {
				$expr .= sprintf ('&&(%s)', (is_object($expression)?$expression->phpexpr():$expression));
			}
			return substr($expr, 2);
		}
	}
	
	class CondOr extends Cond
	{
		function __toString()
		{
			$expr = '';
			foreach ($this->expressions as $expression) {
				$expr .= sprintf ('||(%s)', $expression);
			}
			return substr($expr, 2);
		}
		
		function phpexpr()
		{
			$expr = '';
			foreach ($this->phpexpr as $expression) {
				$expr .= sprintf ('||(%s)', (is_object($expression)?$expression->phpexpr():$expression));
			}
			return substr($expr, 2);
		}
	}
	
	class CondNotAnd extends Cond
	{
		function __toString()
		{
			$expr = '';
			foreach ($this->expressions as $expression) {
				$expr .= sprintf ('&&this.inv(%s)', $expression);
			}
			return substr($expr, 2);
		}
		
		function phpexpr()
		{
			$expr = '';
			foreach ($this->phpexpr as $expression) {
				$expr .= sprintf ('&&!(%s)', (is_object($expression)?$expression->phpexpr():$expression));
			}
			return substr($expr, 2);
		}
	}

	class CondNotOr extends Cond
	{
		function __toString()
		{
			$expr = '';
			foreach ($this->expressions as $expression) {
				$expr .= sprintf ('||(%s)', $expression);
			}
			return 'inv('.substr($expr, 2).')';
		}
		
		function phpexpr()
		{
			$expr = '';
			foreach ($this->phpexpr as $expression) {
				$expr .= sprintf ('||(%s)', (is_object($expression)?$expression->phpexpr():$expression));
			}
			return '!('.substr($expr, 2).')';
		}
	}

?>