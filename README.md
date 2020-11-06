# Skeleton plugin for CakePHP

This plugin offers a starting point for a generic web application, features
include CRUD operations, file handling, HTTP request logging, soft-deletion, auto-templating and many more.

## Installation

* Install with composer:
  ```
  composer require gitsccit/web-skeleton
  ```

* Load the plugin:
  ```
  bin/cake plugin load Skeleton
  ```

* Run migrations and seeding: 

  ```
  bin/cake migrations migrate -p Skeleton
  bin/cake migrations seed -p Skeleton
  ```

## Usage

### ApiHandler
The API client of the Application Server, this contains methods to communicate with the App Server.

Add these urls to `app.php` or `app_local.php`.
  ```
  Urls => [
      'apps' => '...', // App server url, this could be dev/test/prod.
      'refreshCallback' => '...',  // The endpoint that App server will call when an update is detected, defaults to '/pages/clear-cache'.
  ],
  ```

### Bake

Use option `-t Skeleton` when baking. E.g.
`bin/cake bake all users -t Skeleton`

### Behaviors

##### CurrentUser
For all incoming save request, this behavior sets `user_id` in request data to `Session::read('Auth.User.id')`.

### Crud
* Make `AppController` extend `\Skeleton\Controller\AppController`.

* All HTTP responses will be parsed based on the `Accept` header in the request, i.e., if `Accept` header 
  is set to `application/json`, the response will be json-serialized.

* Sets a number of convenient view variables for the template to use. See `Template` section below for the list.

### Master/Replica Database Connections
* Configure `default` datasource to connect to the master database, and `replica` to replica database.

* Datasource configuration for plugins are created from master/replica during bootstrap. 
  E.g., `apps` and `apps_replica` datasource configurations will be created for the plugin `Apps`,
  based on `default` and `replica` configurations.

* `DataSource` Event Listener reconfigures `save()` and `delete()` to use `default` connection, 
  and `find()` to use `{*_}replica` connection.

### Enum Options
Use the `EnumTrait` in your ORM table object and use `getEnumOptions()` to read enum fields
from that table.

### Global Helper Functions
`config/functions.php` contains globally available helper functions. E.g.,
* startsWith(‘disease’, ‘dis’) => true
* endsWith(‘disease’, ‘ease’) => true
* timestamp() => ‘2019-10-03 16:00:00’
* is_assoc(\[‘key’ => ‘value’]) => true | is_assoc(\[‘JustValue’]) => false

### Request Sanitation
`RequestSanitationMiddleware` replaces credit card numbers with `X` in all user submitted fields.
You can define a list of valid credit card fields in the middleware.

### Soft Delete

* Create nullable `deleted_at` column of type `timestamp` in the database tables that you wish to implement SoftDelete.
* Use the `SoftDeleteTrait` in your ORM table object. 
  Alternatively, you can `bake` the ORM table: ```bin/cake bake model ModelName -t Skeleton```
* Add these options to the table associations if you wish to cascade soft-deletion.
  ```
  'cascadeCallbacks' => true,
  'dependent' => true
  ```
  
  E.g. If you wish to soft-delete all the articles belonging to a user that is being deleted,
  in  `UsersTable.initialize`, you would have this in your table associations:
  ```
  $this->hasMany('Users', [
      'foreignKey' => 'user_id',
      'cascadeCallbacks' => true,
      'dependent' => true
  ]);
  ```

### Table Filter

Allows all tables to be filtered dynamically by passing query parameters in the url.

* Add `TableFilter` Event listener in `AppController.initialize()`:
   ```
  EventManager::instance()->on(new TableFilter($this));
  ```

* Set `$filterable` on the entity class that you want to filter. E.g.
  ```
  // src/Model/Entity/Article.php
  
  $filterable = [
    'title', // value defaults to ['contain']
    'tag_count' => ['lte'],
    'Tags__name' => ['contains', 'exact'],
  ];
  ```
* Set query params in request url. E.g. 
  * `/pages?title=skeleton` will return page entries where the title contains 'skeleton'. 
  * `/pages?title__exact=skeleton` will return page entries where the title is exactly 'skeleton'. 
  * `/pages?tag_count__lte=5` will return page entries where the tag count is less than or equal to 5. 
  * `/pages?Tags__name=plugins` will return page entries where the tag name contains 'plugins'. 

Available operations are `contains`, `exact`, `gt`, `gte`, `lt`, `lte`, `ne`. 
Default is `contains`, if no operation is specified in the query parameter.

### Templates
When using one of the Crud component methods in your controller to load the response,
the following variables will be available in the view:

* `accessibleFields` The fields assignable by the users. (Only set if action is one of `add` and `edit`) 
* `className` The name of current controller.
* `displayField` The display field of current table object.
* `entity` / `entities` Only set if you are using the fallback templates.
* `title` A user friendly title of the current page.

#### Template Abstraction
* Create template files in `/Template/{FallbackTemplateFolder}`. 
* Set `fallbackTemplatePath` option in your controller:
    ```
    $crud = [
        'fallbackTemplatePath` => {FallbackTemplateFolder}
    ];
    ```
    By default, the fallback template path is set to `/Template/Common`.
* Delete the templates that you wish to be overridden by the fallback templates from
the Cake-designated template folder. 

Example:
* Create `index.php`, `add.php`, `view.php`, `edit.php` in `/Template/Admin`.
* Set `fallbackTemplatePath` option in `UsersController`:
    ```
    $crud = [
        'fallbackTemplatePath` => 'Admin'
    ];
    ```
* Delete `index.php`, `view.php` from `/Template/Users`. 
Now the `index` and `view` methods will fallback to the corresponding templates in `/Admin`,
whereas `add`, `edit` will be using the templates in `/Users`

### View Helper

*To load helpers, add `$this->loadHelper('Skeleton.{helperName}');` in your `AppView`'s `initialize()` method.*

#### UtilsHelper
Utility tools to help you write your template files.

#### PhoneHelper
Parses a phone number into a user friendly format.