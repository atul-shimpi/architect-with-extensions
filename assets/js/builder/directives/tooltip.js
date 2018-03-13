'use strict';

angular.module('builder.directives')

.directive('blTooltip', ['$translate', function($translate) {
    return {
        restrict: 'A',
        link: function($scope, el, attrs) {
            el.tooltip({
                placement: attrs.placement || 'bottom',
                delay: 50,
                title: $translate.instant(attrs.blTooltip)
            })
        }
    }
}]);