# HOWTO: Writing an application integration

## Terminology
 * Adjoining application: The main web application that Curator is being integrated
   with.
 * Application integration: Programming within the adjoining application responsible
   for customizing Curator to the adjoining application, deciding who can run Curator,
   and telling Curator when to apply what updates.
 * Embedded mode: A mode of Curator operation that should be in effect when Curator has
   been integrated with an adjoining application.

## Components of an application integration
### The .phar file
 Curator is released as a single-file .phar archive. You may ship this as part of an
 adjoining application or as part of an extension to an adjoining application. It is
 of vital importance that the filename is _NOT_ `curator.phar`, as this might allow
 Curator to run in Standalone mode, bypassing the authorization controls of the
 adjoining application.
 
 When you `include` the .phar in another script, its return value is a `\Curator\AppManager`
 instance. You can script and control Curator through this interface. 

### The core-dependent logic
 Typically, all of the logic that comprises an application integration is written within
 or as an extension of the adjoining application. It runs on top of the adjoining
 application's core so it can easily utilize relevant configuration, authorization, 
 and other services that the adjoining application offers. However, while Curator is 
 actually performing an update, it is designed to be fully self-sufficient and not dependent
 on the adjoining application or its integration in any way.

 As a result, all aspects of Curator's operation that the application integration wishes
 to influence are communicated from the adjoining application to Curator before an
 update begins, and stored by Curator for reference.
 
 A typical transition from the adjoining application to Curator involves a request where
 1. The adjoining application core or kernel is fully initialized, the user is authorized,
    configuration settings are gathered and user input is processed;
 2. The Curator .phar is `include`ed, and `AppManager::applyIntegrationConfig()` is called
    with direction to create an authenticated Curator session and information about the task
    for Curator to perform.
 3. Curator initializes its own application kernel in order to start a session for the user
    and persist the task and configuration that was passed in. At this time, both applications
    (Curator and adjoining application) are simultaneously loaded and initialized from the
    point of view of the PHP interpreter.
 4. After calling `AppManager::applyIntegrationConfig()`, the adjoining application redirects
    the user to the Direct Access Script.

### The Direct Access Script
 In addition to the bulk of the integration logic found in the adjoining application's extension
 for Curator, a simple but important php file should be provided as part of all application
 integrations that allows incoming http requests to directly run Curator in embedded
 mode. This script should have the following properties:
 * It should be named anything other than `curator.php` or `curator.phar`. (A suggested
   convention would be to name it after the adjoining application, for example
   `drupal_curator.php`.) *The name of this file is important,* because Curator will
   examine the name to determine whether it is in embedded mode. If it is named 
   `curator.php` or `curator.phar`, Curator will select standalone mode rather than 
   embedded mode.
 * It should simply `include` the .phar archive, capture the returned `\Curator\AppManager`,
   and call `run()` on it:
   ```php
   <?php
   $appManager = require __DIR__ . DIRECTORY_SEPARATOR . 'drupal_curator.phar';
   $appManager->run();
   ```
   When started and accessed through this script, the user must already possess an authenticated
   Curator session.
   > There is [a github issue](https://github.com/curator-wik/curator/issues/2) to provide 
     an alternative means to reauthenticate that is not adjoining app dependent, in case 
     an attempted update renders the adjoining app unusable.
 
## More about `\Curator\AppManager` 
 To be documented: main public methods and creating an IntegrationConfig.
 