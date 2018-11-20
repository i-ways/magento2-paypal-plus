/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'underscore'
    ],
    function (ko, _) {
        'use strict';

        var methodList = [];

        return function (value) {
            if (!_.isUndefined(value)) {
                methodList = value;
            }
            return methodList;
        };
    }
);
