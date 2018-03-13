angular.module('builder')

.controller('RegisterController', ['$rootScope', '$scope', '$http', '$state', 'users', function($rootScope, $scope, $http, $state, users) {

    $scope.registerInfo = {};
    $scope.errors = {};
    $scope.loading = false;

    $scope.register = function() {
        $scope.loading = true;

        users.register(angular.copy($scope.registerInfo)).error(function(errors) {
            $scope.errors = errors;
        }).success(function(user) {
            $('.login-container').addClass('animated fadeOutDown');

            setTimeout(function() {
                $rootScope.user = user;
                $state.go('dashboard');
            }, 550);

        }).finally(function() {
            $scope.loading = false;
        });
    };
}]);