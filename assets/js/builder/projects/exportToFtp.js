'use strict'

angular.module('builder.projects')

.directive('blOpenPublishModal', ['$http', '$state', 'project', function($http, $state, project) {
    return {
        restrict: 'A',
        link: function($scope, el, attrs) {
        	var modal = $('#publish-modal');

        	el.on('click', function(e) {
        		modal.data('id', attrs.blOpenPublishModal).modal('show');
        	});
        }
    };
}])


.directive('blExportToFtp', ['$http', '$state', 'project', 'localStorage', function($http, $state, project, localStorage) {
    return {
        restrict: 'A',
        link: function($scope, el) {
        	var	loader = el.find('.loader'),
        		error  = el.find('.error'),
                creds  = localStorage.get('publish-credentials');

            //fetch public credentials from local storage if they exist
            if (creds) $scope.publishCredentials = creds;

        	el.find('.publish').on('click', function() {
        		loader.show();

        		if ($state.current.name === 'dashboard') {
        			var id = el.data('id');
        		} else {
        			var id = project.active.id;
        		}

        		$http.post('export/project-ftp/'+id, $scope.publishCredentials).success(function() {
        			error.text('').hide();
        			el.modal('hide');
        			alertify.log('Published project to ftp successfully.', 'success', 2200);
        		}).error(function(data) {
        			error.text(data).show();
        		}).finally(function() {
        			loader.hide();
        		});

                var credentials = $.extend({}, $scope.publishCredentials);
                credentials.password = '';
                localStorage.set('publish-credentials', credentials);
        	});

        	el.find('.close-modal').on('click', function(e) {
        		el.modal('hide');
        	});
        }
    };
}]);

