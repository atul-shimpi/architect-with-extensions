angular.module('builder.users', [])

.controller('UsersController', ['$scope', '$rootScope', '$state', '$translate', '$http', 'users', 'usersPaginator', function($scope, $rootScope, $state, $translate, $http, users, usersPaginator) {

	// * - means that permission doesn't have sub-actions like delete, update etc
	$scope.availablePermissions = ['export*', 'publish*', 'themes', 'projects', 'users', 'templates'];
	$scope.permissionSubActions = ['create', 'update', 'delete'];

	//permissions to apply to new accounts
	$scope.defaultPermissions = {};

	$scope.usersPaginator = usersPaginator;

	$scope.filters = {
		query: '',
		status: '',
		sort: 'lastSeen',
		setSortProp: function() {

			//newest first
			if (this.sort == 'newest') {
				this.order = 'created_at';
				this.reverse = true; 
			}

			//oldest first
			else if (this.sort == 'oldest') {
				this.order = 'created_at';
				this.reverse = false;
			}

			//A-Z
			else if (this.sort == 'lastSeen') {
				this.order = 'last_login';
				this.reverse = true;
			}
		},
		order: 'last_seen',
		reverse: true,
	};

	$scope.loading = false;

	$scope.users = [];

	//user that is currently being edited
	$scope.activeUser = {};

	//data for new user creation by admin
	$scope.newUser = {};

	$scope.loginInfo = {};

	if ($scope.isDemo) {
		$scope.loginInfo.email = 'demo@demo.com';
		$scope.loginInfo.password = 'demo';
	}

	$scope.registerInfo = {};

	$scope.errors = {};
	
	$scope.login = function() {
		$scope.loading = true;

		users.login($.extend({}, $scope.loginInfo)).error(function(data) {
			$scope.errors = data;
		}).success(function(user) {
			$('.login-container').addClass('animated fadeOutDown');
						
			setTimeout(function() {
				$rootScope.user = user;
				$state.go('dashboard');
			}, 550);
			
		}).finally(function(data) {
			$scope.loading = false;
		});
	};

	$scope.delete = function(user) {
		alertify.confirm($translate.instant('confirmDeleteUser'), function(confirmed) {
			if (confirmed) {
				$scope.loading = true;

				users.delete(user).success(function(data) {
					for (var i = usersPaginator.sourceItems.length - 1; i >= 0; i--) {
						if (usersPaginator.currentItems[i].id == user.id) {
							usersPaginator.currentItems.splice(i, 1);
							usersPaginator.totalItems -= 1;
						}
					};
				}).error(function(data) {
					alertify.log(data, 'error');
				});
			}
		});
	};

	$scope.createNewUser = function() {
		users.register($scope.newUser).success(function(data) {
			if (typeof data.permissions === 'array') {
				data.permissions = {};
			}
			
			usersPaginator.currentItems.unshift(data);
			$scope.errors = {};
			$scope.newUser = {};
			$('#new-user-modal').modal('hide');
            alertify.log($translate.instant('userCreateSuccess'), 'success');
		}).error(function(data) {
			$scope.errors = data;
		});
	};

	$scope.openPermissionsModal = function(user) {
		if (user.permissions.length === 0) {
			user.permissions = {};
		}

		$scope.activeUser = user;

		$('#permissions-modal').modal('show');
	};

	//save user permissions
	$scope.savePermissions = function() {
		$scope.loading = true;

		users.savePermissions(angular.extend({}, $scope.activeUser)).success(function() {
			$('#permissions-modal').modal('hide');
		}).error(function(data) {
			alertify.log(data, 'error');
		}).finally(function() {
			$scope.loading = false;
		});
	};

	$scope.assignPermissionsToAll = function(permissions) {
		alertify.confirm($translate.instant('assignPermToAllConfirm'), function(confirmed) {
			if (confirmed) {
				$http.post('assign-permissions-to-all', { permissions: JSON.stringify($scope.defaultPermissions) }).success(function(data) {
					$scope.fetchUsers();
					alertify.log(data, 'success');
				}).error(function(data) {
					alertify.log(data, 'error');
				})
			}
		});
	};

	$scope.saveDefaultPermissions = function() {
		$http.post('save-settings', { permissions: JSON.stringify($scope.defaultPermissions) }).success(function() {
			$('#default-permissions-modal').modal('hide');
		}).error(function(data) {
			alertify.log(data, 'error');
		});
	};

	$scope.toggleRegistration = function(value) {
		$http.post('save-settings', { enable_registration: value }).error(function(data) {
			alertify.log(data, 'error');
		});
	};

	$scope.activeUserHasPermission = function(permission) {
		if ( ! $scope.activeUser.permissions) {
			return false;
		}

		if ($scope.activeUser.permissions.superuser) {
			return true;
		}

		return $scope.activeUser.permissions[permission.replace('*', '')];
	};

	$scope.defaultPermissionSet = function(permission) {
		return $scope.defaultPermissions[permission.replace('*', '')];
	};

	$scope.showEmail = function(email) {
		if ($scope.isDemo) {
			return email.replace(/.+?@/, 'hidden_on_demo_site@');
		}

		return email;
	};

	$scope.fetchUsers = function() {
		users.getAll().success(function(data) {
			usersPaginator.start(data);
		}).then(function() {
			$scope.loading = false;
		});
	};

	if ($state.current.name === 'users') {
		$scope.loading = true;

		$scope.fetchUsers();

		if (settings.permissions) {
			$scope.defaultPermissions = JSON.parse(settings.permissions);
		}

		if (typeof settings.enable_registration === 'undefined') {
			$scope.enableRegistration = true;
		} else {
			$scope.enableRegistration = JSON.parse(settings.enable_registration) ? true : false;
		}
	}
}])

.directive('blUsersPagination', ['$filter', 'usersPaginator', function($filter, usersPaginator) {

    return {
        restrict: 'A',  
        link: function($scope, el, attrs) {

        	//initiate pagination plugin
        	el.pagination({
		        items: 0,
		        itemsOnPage: usersPaginator.perPage,
		        cssStyle: 'dark-theme',
		        onPageClick: function(num) {
		        	$scope.$apply(function() {
		        		usersPaginator.selectPage(num);
		        	})
		        },
		        onInit: function(a) {
		        	$('.pagi-container > .simple-pagination').on('click', function(e) {
		        		e.preventDefault();
		        	});
		        }
		    });

		    //redraw pagination bar on total items change
		    $scope.$watch('usersPaginator.totalItems', function(value) {
		    	if (value) { el.pagination('updateItems', value) }
		    });

		    $scope.$watch('filters.query', function(value) {
		    	el.pagination('updateItems', $filter('filter')(usersPaginator.sourceItems, value).length);
		    });	 
        }
    }
}])

.factory('usersPaginator', ['$rootScope', function($rootScope) {
	var paginator = {

		/**
		 * All available users.
		 * 
		 * @type array
		 */
		sourceItems: [],

		/**
		 * Users currently being shown.
		 * 
		 * @type array
		 */
		currentItems: [],

		/**
		 * Users to show per page.
		 * 
		 * @type integer
		 */
		perPage: 12,

		/**
		 * Total number of Users.
		 * 
		 * @type integer
		 */
		totalItems: 0,

		/**
		 * Slice items for the given page.
		 * 
		 * @param  integer page
		 * @return void
		 */
		selectPage: function(page) {
			this.currentItems = this.sourceItems.slice(
				(page-1)*this.perPage, (page-1)*this.perPage+this.perPage
			);
		},

		/**
		 * Start the paginator with given items.
		 * 
		 * @param  array items
		 * @return void
		 */
		start: function(items) {
			this.sourceItems  = items;
			this.totalItems   = items.length;
			this.currentItems = items.slice(0, this.perPage);
		}
	};

	return paginator;
}])

.directive('blPermissionsToggler', ['settings', function(settings) {
    return {
   		restrict: 'A',
      	link: function($scope, el, attrs) {
      		var name = attrs.name;

      		var applyToUser = function(value) {
  				if ($scope.activeUser && $scope.activeUser.permissions) {
  					$scope.activeUser.permissions[name] = value ? 1 : 0;
  				}
  			};

  			var applyToDefault = function(value) {
  				$scope.defaultPermissions[name] = value ? 1: 0;
  			};
      		
      		el.toggles({
      			height: 30,
      			width: 70,
      			drag: false,
      			text: { on: 'Yes', off: 'No' },
      			on: attrs.blPermissionsToggler == 'on',
      		});

      		attrs.$observe('blPermissionsToggler', function(value) {
      			if (value == '1' || value == 'true') {
      				el.toggles(true);
      			} else if (value == '0' || value == 'false' || ! value) {
      				el.toggles(false);
      			}            
            });         

      		el.on('toggle', function (e, value) {
      			if (attrs.for == 'user') {
      				applyToUser(value);
      			} else {
      				applyToDefault(value);
      			}
			});
      	}
    };
}])

.factory('users', ['$http', '$cookieStore', '$rootScope', '$state', function($http, $cookieStore, $rootScope, $state) {
	
	var user = {

		getAll: function() {
			return $http.get('users');
		},

		login: function(credentials) {
			return $http.post('users/login', credentials);
		},

		register: function(credentials) {
			return $http.post('users/register', credentials);
		},

		delete: function(user) {
			return $http.delete('users/'+user.id);
		},

		savePermissions: function(user) {
			return $http.post('users/'+user.id+'/modify-permissions', {permissions: user.permissions});
		},

		logout: function() { 
			return $http.post('users/logout').success(function(data) {
				$cookieStore.remove('blUser');
				$rootScope.user = false;

				$state.go('home');
			});
		},

	};

	return user;
}])

.directive('blFormValidator', ['elements', function(elements) {
    return {
   		restrict: 'A',
      	link: function($scope, el) {
      		var form = $('form');

      		$scope.$watch('errors', function(newErrors, oldErrors) {

      			//remove old errors
      			$('.form-error').remove();

      			$.each(newErrors, function(field, message) {

      				//if there's no field name append error as alert before the first input field
      				if (field == '*') {
					    $('#login-page .alert').show().addClass('animated shake').find('.message').text(message);
      				} else {
      					var field = $('[name="'+field+'"]');

      					$('<span class="form-error help-block">'+message+'</span>').appendTo('#login-page').css({
      						top: field.offset().top - 4,
      						left: field.offset().left + field.outerWidth(),
      					}).addClass('animated flipInX');
      				}
      			});
      		});
      	}
    };
}])