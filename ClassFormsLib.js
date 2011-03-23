/******************************************************************************\
 *                                                                            * 
 *  (c) 2011 David Zurborg <david@nemero.com>                                 * 
 *  http://nemero.com/                                                        * 
 *                                                                            * 
 *  Published under BSD-2-Clause-License                                      * 
 *                                                                            * 
\******************************************************************************/

var ClassFormsLibHelper = Class.create({
	"img": function(name) {
		return "/img/silk/"+name+".png";
	},
	
	"info": function(msg) {
		try {
			window.console.log(msg);
		} catch(e) {
		}
	},
	
	"debug": function(msg) {
		this.info(msg);
	},
	
	"inv": function(expr) {
		if (expr) {
			return false;
		} else {
			return true;
		}
	},
	
	"isin": function(e, f) {
		var r = false;
		var a = $w(f);
		this.V(e).each(function(i) {
			var v = $F(i);
			r || a.each(function(t) {
				r = r || (v == t);
			});
		});
		return r;
	},
	
	"symbol": function(element, sym) {
		var e = $(element);
		if (!e) return;
		if (e.retrieve("wait")) return;
		switch (sym) {
			case 'ok':
				e.src = this.img('accept');
				break;
			case 'notok':
				e.src = this.img('exclamation');
				break;
			default:
				e.store("status","none");
				e.hide();
				return;
		}
		e.store("status",sym);
		e.show();
	},
	
	"symbol_wait": function(element, status) {
		var e = $(element);
		if (!e) return;
		if (status) {
			e.store("wait", true);
			e.src = this.img('hourglass');
			e.show();
		} else if (e.retrieve("wait")) {
			e.store("wait",false);
			this.symbol(e,e.retrieve("status"));
		}
	},
	
	"userfunc": function(name, options, value) {
		switch(name) {
			case 'replace':
				return this._replace(eval(options.pattern),options.replacement,value);
				break;
			default:
				throw "unknown userfunc: "+name;
		}
	},
	
	"_replace": function(regexp,replacement, string) {
		return string.replace(regexp, replacement);
	},

	"V": function(e){
		if (e) {
			e = $(e);
		} else {
			e = [];
		}
		try {
			if (e.type) e = [e];
		} catch(i) {}
		return $A(e);
	}
});
var ClassFormsLib = Class.create(ClassFormsLibHelper, {
	
	"initialize": function(name) {
		this.debug(["new ClassFormsLib",name]);
		this.name = name;
		this.pages = $A();
		this.fields  = $H();
		
		this.form = $(name);
		if(!this.form) {
			throw 'No HTML loaded!';
		}
		
		this.F = $H(); // remote Functions results
		this.P = $H(); // Pending functions
		
		this.country = "DE";
		this.locale = "de";
		
		Element.addMethods({
			"once": function(element, eventname, selector, callback){
				if (callback === undefined) {
					callback = selector;
					selector = undefined;
				}
				return $(element).on(eventname, selector, function(event, element){
					event.stop();
					(callback)(event, element);
				});
			}
		});
	},
	
	"register": function(name,field){
		this.debug(["register",name,field]);
		this.fields.set(name,field);
	},
	
	"setDefault": function(hash) {
		$H(hash).each(function(pair){
			field = this.getFieldObj(pair.key);
			if (field !== undefined) {
				field.set(pair.value);
			}
		},this);
	},
	
	"show": function()
	{	
		this.pages.first().show();
		this.form.show();
	},
	
	"addPage": function(page) {
		this.pages.push(page);
	},
	
	"getPage": function(id) {
		try {
			this.pages.each(function(page){
				if (page.id == this)
					throw page;
			}, id);
		} catch (page) {
			return page;
		}
		throw "Page "+id+" not found!";
	},
	
	"getField": function(name) {
		return this.fields.get(name);
	},
	
	"getFieldObj": function(name) {
		try {
			this.pages.each(function(page){
				page.fields.each(function(field){
					if (field.name == this)	throw field;
				}, this);
			}, name);
		} catch (field) {
			return field;
		}
	},
	
	"cfn": function(func, e) {
		e = $(e);
		var v = $F(e);
		
		if (this.F.get(func) && this.F.get(func).get(v)!==undefined) {
			return this.F.get(func).get(v);
		} else if (this.P.get(func)!==undefined) {
			throw {"reason": "pending", "func": func, "status": "waiting"};
		}
		
		var ajax = new Ajax.Request(window.location.pathname, {
			'method': 'GET',
			'parameters': {
				'function': func,
				'value': v
			},
			"onComplete": (function(resp){
				this.P.unset(func);
			}).bind(this),
			"onSuccess": (function(func, v, resp) {
				if (!this.F.get(func))
					this.F.set(func,$H());
				this.F.get(func).set(v, resp.headerJSON.ret);
				var funcs = this.P.get(func);
				this.P.unset(func);
				try {
					funcs.each(function(caller){
						caller(this);
					},this);
				} catch(e) {
					this.info(["remote call error = ",e]);
				}
			}).bind(this, func, v),
			"onException": (function(func, ajax, e) {
				this.info(["response error = ",this,func,ajax,e]);
				this.P.unset(func);
			}).bind(this, func)
			/* TODO: handle failure */
		});
		
		if (ajax.success()){
			this.P.set(func, $A())
			throw {"reason": "pending", "func":func, "status":"new"};
		} else {
			this.info("function cfn("+func+", "+e+"["+e.identify()+"]) : request was NOT successful");
			throw {"reason": "error"};
		}
	},
	
	"pending": function(func, caller) {
		if (!this.P.get(func))
			return false;
		this.P.get(func).push(caller);
		return true;
	},

	"submit": function(){
		this.form.request({
			"onComplete": function(resp){
				$("result").update(resp.responseText);
			}
		});
	}
});
var ClassFormsLibPage = Class.create(ClassFormsLibHelper, {
	"initialize": function(parent, id) {
		this.debug(["new ClassFormsLibPage",parent,id]);
		this.root = parent;
		this.form = this.root.form;
		this.id = id;
		this.page = $(id);
		this.conditions = $A();
		this.img_back = $(id+'_back');
		this.img_forward = $(id+'_forward');
		this.errordiv = $(id+'__pageerror');
		this.fields = $A();
	},
	
	"cfn": function (func, e) {
		return this.root.cfn(func, e);
	},
	
	"addCondition": function(condition, page) {
		//page = this.root.getPage(page);
		this.conditions.push({
			"test": condition.bind(this),
			"nextpage": page
		});
	},
	
	"addField": function(field) {
		this.debug(["add field info",field.name,field.field]);
		this.fields.push(field);
	},
	
	"getField": function(name) {
		try {
			this.fields.each(function(field){
				if (field.name == this)
					throw field;
			}, name);
		} catch (field) {
			return field;
		}
		throw "Field "+name+" not found!";
	},
	
	"show": function() {
		this.fields.each(function(field){
			field.changed();
			if (field.div.visible())
				field.init();
		}, this);
		this.page.show();
	},
	
	"hide": function() {
		this.page.hide();
	},
	
	"next": function() {
		var ok = true;
		var page = null;
		try {
			this.conditions.each(function(condition){
				try {
					ok = condition.test(this);
				} catch (err) {
					if (err.reason == 'pending') {
						this.img_forward.src = 'wait';
						this.root.pending(err.func, this.next.bind(this));
					} else {
						this.info(["MARK 1 no match available because another error accoured:",err,condition]);
					}
					throw null;
				}
				this.img_forward.src = this.img('next');
				if (ok) {
					this.hide();
					page = this.root.getPage(condition.nextpage);
					throw page;
				}
			}, this);
		} catch (page) {
			if (page === null) return;
		}
		this.hide();
		if (page === null)
			page = this.root.getPage(this.defaultpage);
		if (this == page) {
			throw "Same page error!";
		}
		this.hide();
		page.setPrevious(this);
		page.show();
	},
	
	"forward": function() {
		$A(this.page.select("div.field>div.input-editbox>div.edit>a")).each(function(e){
			eval(e.href);
		});
		
		try {
			this.fields.each(function(field){
				var ok = field.isok();
				if (!ok)
					throw field;
			},this);
		} catch (field) {
			if (field.sub("__error")) {
				field.sub("__error").show();
			} else {
				this.errordiv.show();
			}
			return;
		}
		this.errordiv.hide();
		this.next();
		return;
	},
	
	"setPrevious": function(page) {
		this.previous = page;
		this.img_back.src = this.img('previous');
	},
	
	"back": function() {
		if (this.previous) {
			this.hide();
			this.previous.show();
		}
	}
});
var ClassFormsLibField = Class.create(ClassFormsLibHelper, {
	"initialize": function(parent, name, id, condition) {
		this.debug(["new ClassFormsLibField",parent,name,id,condition]);
		this.page = parent;
		this.root = this.page.root;
		this.form = this.root.form;
		this.id = id;
		this.div = $(id+'___div');
		this.name = name;
		this.field = $(this.form[this.name]);
		if (!this.field)
		this.field = $(this.form[this.name+"[]"]);
		this.root.register(this.name,this.field);
		this.condition = condition;
		this.initialized = false;
	},
	
	"cfn": function (func, e) {
		return this.root.cfn(func, e);
	},
	
	"set": function(value) {
		this.value = value;
		if (!this.field) return null;
		this.V(this.field).each(function(field){
			switch(this.type) {
				case 'TextBox':
				case 'TextArea':
				case 'Select1':
					field.setValue(this.value);
					return;
				case 'MultiEdit':
					$A(this.value).reverse().each(function(value){
						this.add(this.id,value);
					},this);
					return;
				case 'CheckBox':
					field.checked = $A(this.value).any(function(value){return(this==value);},field.value);
					return;
				case 'Radio':
				case 'RemoteRadio':
					field.checked = (field.value == this.value);
					return;
				default:
					this.debug(["set field of unknown type", this.type,this.options,field.type,field.name,field.value,$F(field)]);
					throw "error (see above)";
					return;
			}
		},this);
	},
	
	"sub": function(suffix) {
		return $(this.id+'_'+suffix);
	},
	
	"visible": function() {
		if (this.condition) {
			return this.condition(); // (this);
		} else {
			return true;
		}
	},
	
	"isok": function(value) {
		if (!this.div.visible()) return true;
		if (!this.regexp) return true;
		if (!this.field) return false;
		if (!value) value = $F(this.field);
		return this.regexp.test(value);
	},
	
	"changed": function() {
		var ok = true;
		try {
			ok = this.visible();
		} catch (err) {
			if (err.reason == 'pending') {
				this.root.pending(err.func, this.changed.bind(this));
			} else {
				this.info(["MARK 2 no match available because another error accoured:",this.condition,err]);
			}
			return;
		}
		if (ok) {
			this.enable();
			this.init();
		} else {
			this.disable();
		}
	},
	
	"enable": function() {
		if (this.field)
			this.V(this.field).each(function(field){field.enable();});
		this.div.show();
	},
	
	"disable": function() {
		this.div.hide();
		if (this.field)
			this.V(this.field).each(function(field){field.disable();});
	},
	
	"init": function() {
		if (this.initialized) return;
		if (!this.div.visible()) return;
		if (this.update)
			this.div.select("span.btn-reload").first().show();
		this.initHandler();
		this.initialized = true;
	},
	
	"initHandler": function() {}
});
var ClassFormsLibTextBoxField = Class.create(ClassFormsLibField, {
	"isok": function($super) {
		var ok = $super();
		if (ok && this.cond) {
			var func = this.cond.bind(this);
			ok = func();
		}
		this.symbol_wait(this.sub("__symbol"), false);
		this.symbol(this.sub("__symbol"),(ok?"ok":"notok"));
		return ok;
	},
	
	"checkerror": function() {
		var ok;
		try {
			ok = this.isok();
		} catch (err) {
			if (err.reason == 'pending') {
				this.symbol_wait(this.sub("__symbol"), true);
				this.root.pending(err.func, this.checkerror.bind(this));
			}
			return;
		}
		if (ok) {
			this.sub("__error").hide();
		} else {
			this.sub("__error").show();
		}
	},

	"initHandler": function() {
		this.field.on("focus",(function(){
			if (this.options.hint) {
				this.sub("__hint").show();
			}
		}).bind(this));
		
		this.field.on("blur",(function(){
			if (this.options.hint) {
				this.sub("__hint").hide();
			}
			this.checkerror();
		}).bind(this));
	}
});
var ClassFormsLibPasswordField = Class.create(ClassFormsLibField, {
	"isok": function($super) {
		var ok = $super();
		if (ok && this.cond) {
			var func = this.cond.bind(this);
			ok = func();
		}
		this.symbol_wait(this.sub("__symbol"), false);
		this.symbol(this.sub("__symbol"),(ok?"ok":"notok"));
		return ok;
	},
	
	"set": function() {},
	
	"checkerror": function() {
		var ok;
		try {
			ok = this.isok();
		} catch (err) {
			if (err.reason == 'pending') {
				this.symbol_wait(this.sub("__symbol"), true);
				this.root.pending(err.func, this.checkerror.bind(this));
			}
			return;
		}
		if (ok) {
			this.sub("__error").hide();
			this.sub("verify").enable();
		} else {
			this.sub("__error").show();
			this.sub("verify").disable();
		}
	},
	
	"verify": function() {
		if ($F(this.field) == $F(this.sub("verify"))) {
			this.sub("verify__error").hide();
			this.symbol(this.sub("verify__symbol"),"ok");
		} else {
			this.sub("verify__error").show();
			this.symbol(this.sub("verify__symbol"),"notok");
		}
	},

	"initHandler": function() {
		this.field.on("focus",(function(){
			if (this.options.hint) {
				this.sub("__hint").show();
			}
			this.sub("verify").clear();
			this.sub("verify__error").hide();
			this.symbol(this.sub("verify__symbol"),null);
			this.field.clear();
		}).bind(this));
		
		this.field.on("blur",(function(){
			if (this.options.hint) {
				this.sub("__hint").hide();
			}
			this.checkerror();
		}).bind(this));
		
		if (this.options.verify) {
			this.sub("verify").on("focus",(function(event,e){
				e.clear();
			}).bind(this));
			this.sub("verify").on("blur",(function(event,e){
				this.verify();
			}).bind(this));
		}
	}
});
var ClassFormsLibMultiEditField = Class.create(ClassFormsLibField, {
	"add": function(after, value) {
		if (!value) value = "";
		var e = new Element("div");
		e.insert({"top": new Element("input", {
			"type": "text",
			"class": "text",
			"name": this.name,
			"value": value
		})});
		e.insert({"bottom": new Element("a", {
			"href": "javascript:"+this.form.id+".getFieldObj('"+this.name+"').delete('"+e.identify()+"');"
		}).insert({"bottom": new Element("img", {
			"border": "0",
			"class": "symbol",
			"src": this.img("delete")
		})})});
		e.insert({"bottom": new Element("a", {
			"href": "javascript:"+this.form.id+".getFieldObj('"+this.name+"').add('"+e.identify()+"');"
		}).insert({"bottom": new Element("img", {
			"border": "0",
			"class": "symbol",
			"src": this.img("add")
		})})});
		$(after).insert({"after": e});
	},
	
	"delete": function(e) {
		e = $(e);
		if ($A(e.parentNode.select("div input")).size() > 1)
			$(e).remove();
	},
	
	"initHandler": function() {
		this.add(this.id);
	}
});
var ClassFormsLibTextAreaField = Class.create(ClassFormsLibField, {
});
var ClassFormsLibLabelField = Class.create(ClassFormsLibField, {
});
var ClassFormsLibUpdaterField = Class.create(ClassFormsLibField, {
	"update": function() {
		if (this.ajax) {
			return;
		}
		this.ajax = new Ajax.Updater($(this.id), this.options.url, {
			"contentType": "application/json",
			"postBody": JSON.stringify({
				"options": this.options,
				"data": this.form.serialize(true)
			}),
			"onException": (function(resp,err) {
				this.info(["updater error",resp,err,$(this.id)]);
			}).bind(this),
			"onSuccess": (function(resp){	
				this.symbol_wait(this.sub("__symbol"), false);
			}).bind(this),
			"onComplete": (function(resp){
				this.ajax = null;
			}).bind(this)
		});
		if (this.ajax.success()) {
			this.symbol_wait(this.sub("__symbol"), true);
		} else {
			this.ajax = null;
		}
	},
	
	"initHandler": function(){
		this.update();
	}
});
var ClassFormsLibRemoteSelect1Field = Class.create(ClassFormsLibField, {
	"update": function() {
		if (this.ajax) return;
		this.ajax = new Ajax.Request(this.options.url, {
			"parameters": {
				"function": "getl(RemoteSelect1)",
				"data" : this.form.serialize(true)
			},

			"onSuccess": (function(response){
				$H(response.responseJSON.options).each(function(option){
					this.field.insert({
						"bottom": new Element("option",{
							"value": option.key
						}).update(option.value)
					});
				},this);
				this.set($H(response.responseJSON).get('default'));
			}).bind(this),
			
			"onComplete": (function(response){
				this.symbol_wait(this.sub("__symbol"), false);
				this.ajax = null;
			}).bind(this)

		});
		if (this.ajax.success()) {
			this.symbol_wait(this.sub("__symbol"), true);
		} else {
			/* TODO: propagate error */
			this.ajax = null;
		}
	},

	"initHandler": function(){
		this.update();
	}
});



var ClassFormsLibRemoteRadioField = Class.create(ClassFormsLibField, {
	"add": function(key,value) {
		var input = new Element("input",{
			"type": "radio",
			"class": "radio",
			"name": this.name,
			"value": key
		});
		var label = new Element("label",{
			"for": input.identify()
		}).update(value);
		this.div.insert({
			"bottom": new Element("div", {
				"class": "input-radio"
			}).insert({"top": input, "bottom": label})
		});
	},
	
	"update": function() {
		if (this.ajax) return;
		this.sub("hint").hide();
		this.ajax = new Ajax.Request(this.options.url, {
			"parameters": {
				"function": "getl(RemoteRadio)",
				"data" : this.form.serialize(true)
			},
			"onSucesss": (function(response){
				$H(response.responseJSON.options).each(function(option){
					this.add(option.key, option.value);
				}, this);
			}).bind(this),
			"onComplete": (function(response){
				this.symbol_wait(this.sub("__symbol"), false);
				this.ajax = null;
				if (!this.form[this.name]) {
					this.sub("hint").show();
				}
			}).bind(this)
			/* handle failure and exception */
		});
		if (this.ajax.success()) {
			this.symbol_wait(this.sub("__symbol"), true);
		} else {
			throw new Error(ajax);
			this.ajax = null;
		}
	},
	
	"initHandler": function(){
		$H(this.options.data).each(function(pair){
			if (pair.value !== null)
			this.add(pair.key, pair.value);
		},this);
		this.update();
	}
});
var ClassFormsLibSpacerField = Class.create(ClassFormsLibField, {
});
var ClassFormsLibFaceBookField = Class.create(ClassFormsLibField, {
	"update": function() {
		if (!FB) {
			$(this.id).update('<p class="error">Facebook is not available.</p>');
			return;
		}
		$(this.id).update('<p class="hint">Please wait while checking Facebook status.</p>');
		FB.getLoginStatus((function(response) {
			if (response.session) {
				$(this.id).update('<p class="hint">You are logged in with Facebook.</p>');
				FB.api("/me", (function(response){
					this.response = response;
					var fields = $H(response);
					$H(this.fields).each((function(fields,field){
						var target = field.key;
						var name = field.value.name;
						var label = field.value.label;
						var func = field.value.func;
						var value = fields.get(name);
						if (value) {
							if (func) {
								var funcname = $H(func).keys().first();
								value = this.userfunc(funcname, $H(func).get(funcname), value);
							}
							$(this.id).insert({"bottom": new Element("p").update("<b>"+label+":</b><br/><span>"+value+"</span>")});
							var obj = this.root.getFieldObj(target);
							if (obj) {
								obj.set(value);
							}
						} else {
							this.info(["field not found:",name]);
						}
						//self.field.setValue(%sresponse.%s)
						//$(self.id).insert({"bottom":new Element("p").update("%s: "+$F(self.field))});
					}).curry(fields),this);
				}).bind(this));
			} else {
				$(this.id).insert({
					"bottom": new Element("a", {
						"href": "javascript:"+this.form.id+".getFieldObj('"+this.name+"').login();"
					}).update("Login with Facebook")
				});
			}
		}).bind(this));
	},
	
	"login": function() {
		$(this.id).update('<p class="hint">Please wait...</p>');
		FB.login((function(response) {
			if (response.session) {
				if (response.perms) {
					this.update();
				} else {
					$(this.id).update('<p class="alert">Login successfull, but you did not granted any permissions.</p>');
				}
			} else {
				$(this.id).update('<p class="alert">You are not logged in.</p>');
			}
		}).bind(this), {
			"perms": this.perms
		});
	},
	
	"initHandler": function() {
		this.update();
	}
});
var ClassFormsLibCheckBoxField = Class.create(ClassFormsLibField, {
});
var ClassFormsLibRadioField = Class.create(ClassFormsLibField, {
});
var ClassFormsLibSelect1Field = Class.create(ClassFormsLibField, {
});
var ClassFormsLibEditBoxField = Class.create(ClassFormsLibField, {
	"edit": function() {
		this.sub("__showbox").hide();
		$(this.id).setValue($F(this.field));
		this.sub("__editbox").show();
		$(this.id).activate();
	},
	
	"abort": function() {
		this.sub("__editbox").hide();
		$(this.id).setValue($F(this.field));
		this.sub("__error").hide();
		this.sub("__showbox").show();
	},
	
	"checkerr": function() {
		var e = $(this.id);
		(this.isok($F(e)) == this.sub("__error").visible()) && this.sub("__error").toggle();
		return this.sub("__error").visible();
	},
	
	"apply": function() {
		var e = $(this.id);
		if (this.checkerr()) return;
		this.sub("__editbox").hide();
		this.field.setValue($F(e));
		this.changed();
		this.sub("__span").update($F(this.field));
		this.sub("__showbox").show();
	},
	
	"initHandler": function() {
		$(this.id).on("keydown", (function(event){
			if (event.keyCode == Event.KEY_ESC) this.abort();
			if (event.keyCode == Event.KEY_RETURN) this.apply();
		}).bind(this));
		
		if (this.options.hint) {
			$(this.id).on("focus", (function(event){
				this.sub("__hint").show();
			}).bind(this));
		}
		
		if (this.options.hint || this.options.checkerr) {
			$(this.id).on("blur", (function(event){
				if (this.options.hint) this.sub("__hint").hide();
				if (this.options.regexp) this.checkerr();
			}).bind(this));
		}
	}
});



var ClassFormsLibCondition = Class.create(ClassFormsLibHelper, {
	"initialize": function(condition, nextpage) {
		this.condition = condition;
		this.nextpage = nextpage;
	},
	"test": function(obj) {
		var func = this.condition.bind(obj);
		try {
			return func();
		} catch(err) {
			this.info(["condition test exception",this.condition,obj,err]);
			throw ["condition text exception",err];
		}
	}
});
