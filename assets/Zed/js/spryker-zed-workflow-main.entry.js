'use strict';

require('../scss/main.scss');

require('./modules/definition-validator');
require('./modules/state-machine-builder');
// Loaded after the builder: it announces the initial process name and plugins to the builder on load.
require('./modules/workflow-create-form');
require('./modules/workflow-process-edit-form');
