angular.module('builder.styling')

.factory('css', ['$rootScope', function($rootScope) {

	//make css path on element reselection for new active DOM node
	$rootScope.$on('element.reselected', function(e) {
		$rootScope.selected.selector = css.formatSelector($rootScope.selected.path.slice(0));
	});

	var css = {

		rules: {},

		sheet: null,

		/**
		 * Parse css into basic js object.
		 * 
		 * @param  string css
		 * @return object
		 */
		cssToObject: function(css) {
			var obj = {};

			if (css) {

				//remove comments
				var stripped = css.replace(/\/\*!(.|\n)+?\*\//, '');

				//split css into selector - rules blocks
	        	var blocks = stripped.split('}\n');
	        	
	            for (var i = blocks.length - 1; i >= 0; i--) {

	                //trim whitespace from sides
	                var block = (blocks[i]).trim();
	     
	                //match selector
	                var sel = block.match(/([^]+){/);

	                if (sel) {
	                	sel = sel[1].trim();
	                	
	                	//assign new object to selector if doesn't exist yet
	                	if ( ! obj[sel]) {
	                		obj[sel] = {};
	                	}
	                	
	                	//split this blocks rules into an array ['padding:10px']
	                    var rules = block.replace(sel, '').replace(/{|}/g, '').trim().split(';');
	                    
	                    for (var ind = 0; ind < rules.length; ind++) {
	                        var rule = rules[ind];

	                        if (rule) {
	                        	
	                        	//split rule into name = value pairs
	                            var split = rule.split(/:(.+)?/);
	                            
	                            //if name and value seem valid assign them to the object
	                            if ((split[0] && split[0].length > 1) && (split[1] && split[1].replace(/\s|;/g, '').length > 0)) {
	                                obj[sel][split[0].trim()] = split[1].trim();
	                            }
	                        }
	                    };
	                }
	            };
	        }
	      
	        return obj;
		},

		/**
		 * Compile custom user css and css generated by the
		 * builder itself into a single formatted string.
		 * 
		 * @return string
		 */
		compile: function() {	
			var css = '',
				obj = $.extend(true, {}, this.rules);
				
			for (var selector in obj) {
				css += selector + ' {\n'
				for (var rule in obj[selector]) {
					if (rule.indexOf('_') !== 0) {
						css += '    ' + rule + ': ' + obj[selector][rule] + ";\n";
					}
				}

				css += "}\n\n";
			}

			var compiled = $rootScope.customCss.html().replace(/\/\*!(.|\n)+?\*\//, '').trim()+"\n\n"+css;
				
			return compiled;
		},

		/**
		 * Replace all relative urls to absolute in given css string.
		 * 
		 * @param  string css
		 * @return string
		 */
		relativeToAbsolute: function(css) {
			if ( ! css) { return ''; };
			return css.replace(/url\((?!http)\.?\.?(.+?)\)/g, 'url('+$rootScope.baseUrl+'$1)');
		},

		/**
		 * Format given selector to a string.
		 * 
		 * @param  mixed selector
		 * @return string
		 */
		formatSelector: function(selector) {
			var hadDynamicClasses = false;
			
			if ( ! selector) {
				selector = $rootScope.selected.selector;
			}
			
			//if we're passed an array of element parents as selector
			//we'll need to parse that to a string first
			if (angular.isArray(selector)) {
				var mapped = $.map(selector, function(obj) {

					//if it's a string just return it
					if (angular.isString(obj)) {
						return obj;

					//if not we'll need to use node class, id or name
					} else {

						//if node has an id return that
						if (obj.node.id) {
							return '#'+obj.node.id;

						//next try to get a class
						} else if (obj.node.className) {
							var c = obj.node.className.split(' ');

							//return the longest class as that one is most likely to be unique
							return '.'+c.reduce(function (a, b) { return a.length > b.length ? a : b; });

						//lastly use node name if nothing else is available
						} else {
							return obj.node.nodeName.toLowerCase();
						}
					}
				});
				
				return mapped.join(' > ');
			}
			
			return selector;
		},

		remove: function(selector, style) {
			var sel  = this.formatSelector(selector),
				style = style.toDashedCase();
			
			if (this.sheet && this.rules && this.rules[sel]) {
				this.rules[sel][style] = null;
				this.rules[sel]._cssObject.style[style] = 'initial';
			}
		},

		/**
		 * Compile css styles object into shorthand string.
		 * 
		 * @param  object o
		 * @return string
		 */
		objectToString: function(o) {
			//compiling border radius
			if (o.topLeft) {
				return o.topLeft+' '+o.topRight+' '+o.bottomRight+' '+o.bottomLeft;

			//compiling everything else
			} else {
				return o.top+' '+o.right+' '+o.bottom+' '+o.left;
			}	
		},

		/**
		 * Load given css into iframe.
		 * 
		 * @param  string cssString
		 * @return {void}
		 */
		loadCss: function(cssString) {

			var editorCss = $rootScope.frameHead.find('#editor-css');

			//clear current css
			editorCss.html('');
			
			//load given css
			editorCss.append("\n"+cssString);
		},

		getValueFor: function(selector, style) {
			var ruleStack = this.rules[selector];

			if (ruleStack && ruleStack[style]) {
				return ruleStack[style];
			}

			return $rootScope.selected.getStyle(style);
		},

		add: function(selector, style, value, oldValue, noEvent) {

			if ( ! this.sheet) {
				this.sheet = $('<style id="inspector-css"></style>').appendTo($rootScope.frameHead)[0].sheet;
			}
			
			if (selector) {
				selector = this.formatSelector(selector);
			} else {
				selector = $rootScope.selected.selector;
			}
			
			//if style is an object then wel'll need to loop
			//trough it and call this method for each property
			if (angular.isObject(style)) {
				for (var prop in style) {
					this.add(selector, prop, style[prop]);
				}
			}
			
			if (angular.isObject(value)) {
				value = this.objectToString(value);
			}

			//apply !important if the element
			if ($rootScope.selected.hasInlineStyles) {
				value = value+' !important';
			}

			//cache the css rules object for this selector
			var ruleStack = this.rules[selector];

			if (ruleStack) {
				//add new value to cached cssRule object, this
				//will instantly reflect new css style in the DOM
			 	ruleStack._cssObject.style[style.toCamelCase()] = value;			 	
				
			 	//store new value in our custom css object
			 	ruleStack[style.toDashedCase()] = value;
			} else {
				//create and cache css rules object for this selector
				var ruleStack = this.rules[selector] = {};

				//add new style to our custom css object
				ruleStack[style.toDashedCase()] = value;

				//insert new style as a rule into iframe styleSheets object
				//so it's reflected in the DOM instantly		
				this.sheet.insertRule(selector+'{'+style+':'+value+';}', 0);
				
				//cache the cssRule object so we can modify it later instead
				//of creating a ton of new rules and eating up the memory
				ruleStack._cssObject = this.sheet.cssRules[0];
			}

			if ( ! noEvent) {
				$rootScope.$broadcast('builder.css.changed');
			}		
		},
	};

	return css;
}]);