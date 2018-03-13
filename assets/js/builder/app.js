'use strict';
var builder = {};

angular.module('builder', ['ui.router', 'ngCookies', 'pascalprecht.translate', 'angularFileUpload', 'ngAnimate', 'builder.projects', 'builder.users', 'builder.elements', 'builder.editors', 'builder.wysiwyg', 'dragAndDrop', 'undoManager', 'builder.styling', 'builder.directives', 'builder.inspector', 'builder.settings'])

.factory('addMethodOverrideHeader', function() {
	return {
		request: function (config) {
			if (['PATCH', 'PUT', 'DELETE'].indexOf(config.method) > -1) {
				config.headers['X-HTTP-Method-Override'] = config.method;
				config.method = 'POST';
			}

			return config;
		}
	};
})

.config(['$stateProvider', '$urlRouterProvider', '$translateProvider', '$httpProvider', function($stateProvider, $urlRouterProvider, $translateProvider, $httpProvider) {
	$httpProvider.interceptors.push('addMethodOverrideHeader');

	if (selectedLocale) {
		$translateProvider.translations(selectedLocale, trans);
		$translateProvider.preferredLanguage(selectedLocale);
	} else {
		$translateProvider.translations('en', trans);
		$translateProvider.preferredLanguage('en');
	}

	$translateProvider.useUrlLoader('trans-messages');

	$translateProvider.useSanitizeValueStrategy('escaped');

	$urlRouterProvider.otherwise("/");

	$stateProvider
		.state('home', {
			url: '/',
			templateUrl: 'views/home.html',
		})
		.state('register', {
			url: '/register',
			templateUrl: 'views/register.html',
		})
		.state('dashboard', {
			url: '/dashboard',
			templateUrl: 'views/dashboard.html',
			controller: 'DashboardController',
		})
		.state('builder', {
			url: '/builder/{name}',
			templateUrl: 'views/builder.html',
			controller: 'BuilderController',
		})
		.state('new', {
			url: '/new',
			templateUrl: 'views/newProject.html',
			controller: 'NewProjectController'
		})
		.state('users', {
			url: '/users',
			templateUrl: 'views/users.html',
			controller: 'UsersController',
		})
}])

.run(['$rootScope', '$state', '$cookieStore', '$http', function($rootScope, $state, $cookieStore, $http) {
	$rootScope.isDemo         = parseInt(isDemo);
	$rootScope.isWebkit       = navigator.userAgent.indexOf('AppleWebKit') > -1;
	$rootScope.isIE           = navigator.userAgent.indexOf('MSIE ') > -1 || navigator.userAgent.indexOf('Trident/') > -1;
	$rootScope.keys           = JSON.parse(keys);
	$rootScope.selectedLocale = selectedLocale;
	$rootScope.baseUrl        = baseUrl;
	$rootScope.registrationEnabled = typeof settings.enable_registration !== 'undefined' ? JSON.parse(settings.enable_registration) : true;

	if ($rootScope.isDemo) {
		$http.get('time-until-reset').success(function(data) {			
			if (data > 0) {
				setTimeout(function() {
					location.replace(location.origin);
				}, parseInt(data));
			}
		});
	}

	$rootScope.userCan = function(permission) {
		if ( ! $rootScope.user || ! $rootScope.user.permissions) return false;

		if ($rootScope.user.permissions.superuser === 1) {
			return true;
		}
			
		return $rootScope.user.permissions[permission] === 1;
	};

	$rootScope.$on('$stateChangeStart', function(e, toState) {

		if ( ! $rootScope.user) {
			$rootScope.user = $cookieStore.get('blUser');
		}

		if (toState.name == 'dashboard' || toState.name == 'builder') {
			if ( ! $rootScope.user) {
				e.preventDefault();
				$state.go('home');
			}
		} else if (toState.name == 'home') {
			if ($rootScope.user) {
				e.preventDefault();
				$state.go('dashboard');
			}
		} else if (toState.name == 'users') {
			if ((! $rootScope.user || ! $rootScope.userCan('users.create')) && ! $rootScope.isDemo) {
				e.preventDefault();
				$state.go('dashboard');
			}
		} else if (toState.name == 'register') {
			if ( ! $rootScope.registrationEnabled) {
				e.preventDefault();
				$state.go('home');
			}
		} else if (toState.name !== 'register' && toState.name !== 'new' && toState.name !== 'users') {
			e.preventDefault();
		}
	})
}])

.factory('bootstrapper', ['$rootScope', '$state', 'project', 'elements', 'keybinds', 'settings', function($rootScope, $state, project, elements, keybinds, settings) {

	var strapper = {

		loaded: false,

		eventsAttached: false,

		start: function() {
			this.initDom();
			this.initProps();
			this.initSidebars();			
			this.initSettings();

			if ( ! this.eventsAttached) {
				$rootScope.$on('builder.dom.loaded', function(e) {
					strapper.initProject();
					strapper.initKeybinds();
					strapper.eventsAttached = true;
				});
			}

			this.loaded = true;
		},

		initDom: function() {		
			$rootScope.frame = $('#iframe');
			$rootScope.frame[0].src = 'about:blank';

			$rootScope.frame.load(function() {
				$rootScope.frameWindow    = $rootScope.frame.get(0).contentWindow;
				$rootScope.frameDoc       = $rootScope.frameWindow.document;
				$rootScope.frameBody      = $($rootScope.frameDoc).find('body');
				$rootScope.frameHead      = $($rootScope.frameDoc).find('head');				
				$rootScope.$broadcast('builder.dom.loaded');
			});
			
			$rootScope.frameOverlay   = $('#frame-overlay');
			$rootScope.hoverBox       = $('#hover-box');
			$rootScope.selectBox      = $('#select-box');
			$rootScope.selectBoxTag   = $rootScope.selectBox.find('.element-tag')[0];
			$rootScope.hoverBoxTag    = $rootScope.hoverBox.find('.element-tag')[0];
			$rootScope.selectBoxActions = document.getElementById('select-box-actions');
			$rootScope.hoverBoxActions = document.getElementById('hover-box-actions');
			$rootScope.textToolbar    = $('#text-toolbar');
			$rootScope.windowWidth    = $(window).width();
			$rootScope.inspectorCont  = $('#inspector');
			$rootScope.contextMenu    = $('#context-menu');
			$rootScope.linker         = $('#linker');
			$rootScope.inspectorWidth = $rootScope.inspectorCont.width();
			$rootScope.elemsContWidth = $("#elements-container").width();
			$rootScope.mainHead       = $('head');		
			$rootScope.body           = $('body');
			$rootScope.viewport       = $('#viewport');
			$rootScope.navbar         = $('nav');
			$rootScope.contextMenuOpen= false;
			$rootScope.activePanel    = false;
			$rootScope.flyoutOpen     = false;
			
			//set the iframe offset so we can calculate nodes positions
			//during drag and drop or sorting correctly		
			$rootScope.frameOffset = {top: 89, left: 234};
			$(document).ready(function() {
				setTimeout(function() {
					$rootScope.frameOffset = $rootScope.frame.offset();
					$rootScope.frameWrapperHeight = $('#frame-wrapper').height();
				}, 1000);	
			});
		},

		initSettings: function() {
			settings.init();
		},

		initProject: function() {
			if ($state.params.name) {
				project.load($state.params.name);
			} else{
				$state.go('dashboard');
			}
		},

		initSidebars: function() {
			elements.init();
		},

		initKeybinds: function() {
			keybinds.init();
		},

		initProps: function() {
			//information about currently user selected DOM node
			$rootScope.selected  = {

				//return selected elements html, prioratize preview html
				html: function(type) {
					if ( ! type || type == 'preview') {
						return this.element.previewHtml || this.element.html;
					} else {
						return this.element.html;
					}
				},

				getStyle: function(prop) {
					if (this.node) {
						return window.getComputedStyle(this.node, null).getPropertyValue(prop);
					}
				}
			};

			//information about node user is currently hovering over
			$rootScope.hover = {};

			//whether or not we're currently in progress of selecting
			//a new active DOM node
			$rootScope.selecting = false;	
		}
	};

	return strapper;
}]);