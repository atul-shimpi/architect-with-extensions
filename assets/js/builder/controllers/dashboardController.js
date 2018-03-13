angular.module('builder').controller('DashboardController', ['$scope', '$http', '$translate', 'project', function($scope, $http, $translate, project) {
	$scope.projects = project;
	$scope.requestInProgress = false;

    $scope.selectedProject = false;

    $scope.getProjectUrl = function(project) {
        return $scope.baseUrl+'/projects/'+project.id+'/render';
    };

    $scope.setSelectedProject = function(project) {
        $scope.selectedProject = project;
    };

    $scope.formatTime = function(time) {
        return moment(time).fromNow();
    };

	//publish/unpublish given project in database
	$scope.togglePublished = function(project) {

		if ($scope.requestInProgress) { return false; };

		if (project.published) {
			var p = $http.post('projects/'+project.id+'/publish').success(function() {
            })
		} else {
			var p = $http.post('projects/'+project.id+'/unpublish').success(function() {
			})
		}

		p.finally(function() {
			$scope.requestInProgress = false;
		})
	};

	$scope.deleteProject = function(pr) {
		alertify.confirm($translate.instant('confirmProjectDeletion'), function (e) {
			if (e) {
				project.delete(pr);
			}
		});
	};

	$scope.filters = {
		query: '',
		status: '',
		sort: 'newest',
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
			else if (this.sort == 'a-z') {
				this.order = 'name';
				this.reverse = false;
			}

			//Z-A
			else if (this.sort == 'z-a') {
				this.order = 'name';
				this.reverse = true;
			}
		},
		order: 'created_at',
		reverse: true
	};

	project.getAll().success(function(data) {
	    $scope.selectedProject = data[0];
	});
}])

.directive('blOpenInBuilder', ['$state', '$translate', function($state, $translate) {
    return {
   		restrict: 'A',
      	link: function($scope, el, attrs) {
      		el.on('click', function(e) {

      			if ( ! $scope.userCan('projects.update')) {
      				return alertify.log($translate.instant('noPermProjectUpdate'), 'error');
      			}

                $('#view').addClass('loading');
      			$state.go('builder', { name: attrs.blOpenInBuilder });
      		});
      	}
    }
}]);