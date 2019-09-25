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

### Bake

Use option `-t Skeleton` when baking, e.g.
`bin/cake bake all users -t Skeleton`

### Crud
* Make `AppController` extend `\Skeleton\Controller\AppController`.

* `bake` a new controller and connect the routes for your controller. The component will handle the
  crud actions (index, view, add, edit, delete) for you.
* If you wish to have customized behaviors for your crud actions:
  * Override the actions in your controller.
  * Write a query that retrieve the desired data.
  * Pass the query to `$this->Crud->load($query)`.
* All HTTP responses will be parsed based on the `Accept` header in the request, i.e., if `Accept` header 
  is set to `application/json`, the response will be json-serialized.

### Logging HTTP Requests
All the HTTP requests are automatically logged, you should be able to see 
all the requests in the `logs` database table.

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
  $this->hasMany('Articles', [
      'foreignKey' => 'user_id',
      'cascadeCallbacks' => true,
      'dependent' => true
  ]);
  ```

### Templates
When using one of the Crud component methods in your controller to load the response,
the following variables will be available in the view:

* `accessibleFields` The fields assignable by the users. (Only set if action is one of `add` and `edit`) 
* `associations` The associations of current table. E.g. An article's associations could be \['Authors', 'Tags'].
* `className` The name of current controller.
* `displayField` The display field of current table object.
* `entity` / `entities` Only set if you are using the fallback templates.

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
* Create `index.ctp`, `add.ctp`, `view.ctp`, `edit.ctp`, `delete.ctp` in `/Template/Admin`.
* Set `fallbackTemplatePath` option in `UsersController`:
    ```
    $crud = [
        'fallbackTemplatePath` => 'Admin'
    ];
    ```
* Delete `index.ctp`, `view.ctp` from `/Template/Users`. 
Now the `index` and `view` methods will fallback to the corresponding templates in `/Admin`,
whereas `add`, `edit`, `delete` will be using the templates in `/Users`

#### View Helper
Skeleton provides a useful helper `Utils` to help you write your template files.
To load this helper, add `$this->loadHelper('Skeleton.Utils');` in your `AppView`'s `initialize()` method.
