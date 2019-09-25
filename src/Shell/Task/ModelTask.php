<?php

namespace Skeleton\Shell\Task;

use Cake\Core\Configure;
use Cake\Database\Schema\TableSchema;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Table;

/**
 * Model shell task.
 */
class ModelTask extends \Bake\Shell\Task\ModelTask
{

    protected $_inaccessibleFields = ['created_at', 'modified_at', 'updated_at', 'deleted_at'];
    protected $_hiddenFields = ['id', 'token', 'password', 'passwd', 'deleted_at'];

    /**
     * Generate code for the given model name.
     *
     * @param string $name The model name to generate.
     * @return void
     */
    public function bake($name)
    {
        $table = $this->getTable($name);
        $tableObject = $this->getTableObject($name, $table);
        $data = $this->getTableContext($tableObject, $table, $name);

        if (($theme = $this->param('theme')) && class_exists("\\$theme\Model\Table\\{$name}Table")) {
            $className = $theme . '.' . $name;

            if (!empty($plugin)) {
                $className = $plugin . '.' . $className;
            }

            $themeTableObject = $this->getTableObject($className, $table);

            $associations = $this->getThemeTableAssociations($themeTableObject);
            $diff['associations'] = array_recursive_diff($data['associations'], $associations, 2);
            $this->applyAssociations($tableObject, $diff['associations']);
            $associationInfo = $this->getAssociationInfo($themeTableObject);
            $primaryKey = $this->getThemeTablePrimaryKey($themeTableObject);
            $displayField = $this->getThemeTableDisplayField($themeTableObject);
            $propertySchema = $this->getThemeEntityPropertySchema($themeTableObject);
            $fields = $this->getThemeTableFields($themeTableObject);
            $validation = $this->getThemeTableValidation($themeTableObject, $associations);
            $rulesChecker = $this->getThemeTableRules($themeTableObject, $associations);
            $behaviors = $themeTableObject->behaviors()->getIterator()->getArrayCopy();
            $connection = $this->connection;
            $hidden = $this->getThemeTableHiddenFields($themeTableObject);

            $diff['associationInfo'] = array_recursive_diff($data['associationInfo'], $associationInfo);
            $diff['primaryKey'] = $primaryKey;
            $diff['displayField'] = $displayField;
            $diff['table'] = '';
            $keyDiff = array_diff_improved(array_keys($data['propertySchema']), array_keys($propertySchema));
            $diff['propertySchema'] = array_intersect_key($data['propertySchema'], array_flip($keyDiff));
            $diff['validation'] = array_diff_assoc_improved($data['validation'], $validation);
            $diff['fields'] = array_diff_improved($data['fields'], $fields);
            $diff['rulesChecker'] = array_diff_assoc_improved($data['rulesChecker'], $rulesChecker);
            $diff['behaviors'] = array_recursive_diff($data['behaviors'], $behaviors);
            $diff['connection'] = $connection;
            $diff['hidden'] = array_diff_improved($data['hidden'], $hidden);
            $diff['override'] = true;
            $diff['theme'] = $theme;

            $data = $diff;
        }

        // add traits
        $tableUseStatements = [];
        $tableTraits = [];
        if ($tableObject->hasField('deleted_at')) {
            $tableUseStatements[] = 'Skeleton\\Model\\Table\\SoftDeleteTrait';
            $tableTraits[] = 'SoftDeleteTrait';
        }
        $data = $data + compact('tableUseStatements', 'tableTraits');

        $this->bakeTable($tableObject, $data);
        $this->bakeEntity($tableObject, $data);
        $this->bakeFixture($tableObject->getAlias(), $tableObject->getTable());
        $this->bakeTest($tableObject->getAlias());
    }

    public function getThemeTableAssociations(Table $table)
    {
        $associations = [];
        foreach ($table->associations() as $association) {
            $assoc = [
                'alias' => $association->getClassName() ?? $association->getAlias(),
                'foreignKey' => $association->getForeignKey(),
            ];
            switch (true) {
                case $association instanceof HasMany:
                    $type = 'hasMany';
                    break;
                case $association instanceof BelongsTo:
                    if ($association->getJoinType() === 'INNER') {
                        $assoc['joinType'] = 'INNER';
                    }
                    $type = 'belongsTo';
                    break;
                case $association instanceof BelongsToMany:
                    $assoc += [
                        'targetForeignKey' => $association->getTargetForeignKey(),
                        'joinTable' => $association->junction()->getTable()
                    ];
                    $type = 'belongsToMany';
                    break;
            }
//            if ($className = $association->getClassName()) {
//                $assoc['className'] = $className;
//            }
            $associations[$type][] = $assoc;
        }

        return $associations;
    }

    public function getThemeTableDisplayField($model)
    {
        if (!empty($this->params['display-field'])) {
            return $this->params['display-field'];
        }
        return '';
    }

    public function getThemeTablePrimaryKey($model)
    {
        if (!empty($this->params['primary-key'])) {
            $fields = explode(',', $this->params['primary-key']);

            return array_values(array_filter(array_map('trim', $fields)));
        }
        return [];
    }

    public function getThemeEntityPropertySchema(Table $model)
    {
        $properties = [];

        $schema = $model->getSchema();
        $columns = $this->getThemeTableColumns($model);
        foreach ($columns as $column) {
            $columnSchema = $schema->getColumn($column);

            $properties[$column] = [
                'kind' => 'column',
                'type' => $columnSchema['type'],
                'null' => $columnSchema['null'],
            ];
        }

        foreach ($model->associations() as $association) {
            $entityClass = '\\' . ltrim($association->getTarget()->getEntityClass(), '\\');

            if ($entityClass === '\Cake\ORM\Entity') {
                $namespace = Configure::read('App.namespace');

                list($plugin,) = pluginSplit($association->getTarget()->getRegistryAlias());
                if ($plugin !== null) {
                    $namespace = $plugin;
                }
                $namespace = str_replace('/', '\\', trim($namespace, '\\'));

                $entityClass = $this->_entityName($association->getTarget()->getAlias());
                $entityClass = '\\' . $namespace . '\Model\Entity\\' . $entityClass;
            }

            $properties[$association->getProperty()] = [
                'kind' => 'association',
                'association' => $association,
                'type' => $entityClass
            ];
        }

        return $properties;
    }

    public function getThemeEntity(Table $model)
    {
        $entityClass = '\\' . $model->getEntityClass();
        $entity = new $entityClass();

        return $entity;
    }

    public function getThemeTableColumns(Table $table)
    {
        $schema = $table->getSchema();
        $fields = $schema->columns();
        $entity = $this->getThemeEntity($table);

        foreach ($fields as $index => $field) {
            if (!$entity->isAccessible($field) && !in_array($field, $this->_inaccessibleFields)) {
                unset($fields[$index]);
            }
        }

        return $fields;
    }

    public function getThemeTableFields(Table $table)
    {
        $schema = $table->getSchema();
        $fields = $this->getThemeTableColumns($table);

        foreach ($table->associations() as $assoc) {
            $fields[] = $assoc->getProperty();
        }
        $primaryKey = $schema->primaryKey();

        return array_values(array_diff($fields, $primaryKey));
    }

    public function getThemeTableHiddenFields(Table $model)
    {
        $entity = $this->getThemeEntity($model);

        return $entity->getHidden();
    }

    public function getThemeTableValidation(Table $model, array $associations)
    {
        $schema = $model->getSchema();
        $primaryKey = $schema->primaryKey();

        $foreignKeys = [];
        if (isset($associations['belongsTo'])) {
            foreach ($associations['belongsTo'] as $assoc) {
                $foreignKeys[] = $assoc['foreignKey'];
            }
        }

        $validate = [];
        $checkFields = array_merge(array_keys($model->getValidator()->getIterator()->getArrayCopy()),
            $this->_inaccessibleFields);
        foreach ($checkFields as $fieldName) {
            if (in_array($fieldName, $foreignKeys)) {
                continue;
            }
            $validation = [];
            if ($field = $schema->getColumn($fieldName)) {
                $validation = $this->fieldValidation($schema, $fieldName, $field, $primaryKey);
            }
            if (!empty($validation)) {
                $validate[$fieldName] = $validation;
            }
        }

        return $validate;
    }

    public function getThemeTableRules(Table $model, array $associations)
    {
        $schema = $model->getSchema();
        $fields = $this->getThemeTableColumns($model);

        if (empty($fields)) {
            return [];
        }

        $rules = [];
        foreach ($fields as $fieldName) {
            if (in_array($fieldName, ['username', 'email', 'login'])) {
                $rules[$fieldName] = ['name' => 'isUnique'];
            }
        }
        foreach ($schema->constraints() as $name) {
            $constraint = $schema->getConstraint($name);
            if ($constraint['type'] !== TableSchema::CONSTRAINT_UNIQUE) {
                continue;
            }
            if (count($constraint['columns']) > 1) {
                continue;
            }
            $rules[$constraint['columns'][0]] = ['name' => 'isUnique'];
        }

        if (empty($associations['belongsTo'])) {
            return $rules;
        }

        foreach ($associations['belongsTo'] as $assoc) {
            $rules[$assoc['foreignKey']] = ['name' => 'existsIn', 'extra' => $assoc['alias']];
        }

        return $rules;
    }

    public function getFields($table)
    {
        return array_diff_improved(parent::getFields($table), $this->_inaccessibleFields);
    }

    public function getHiddenFields($model)
    {
        if (!empty($this->params['no-hidden'])) {
            return [];
        }
        if (!empty($this->params['hidden'])) {
            $fields = explode(',', $this->params['hidden']);

            return array_values(array_filter(array_map('trim', $fields)));
        }
        $schema = $model->getSchema();
        $columns = $schema->columns();

        $hiddenFields = array_filter($columns, function ($field) {
            return endsWith($field, '_id');
        });
        $hiddenFields = array_merge($hiddenFields, $this->_hiddenFields);

        return array_values(array_intersect($columns, $hiddenFields));
    }

    public function getValidation($model, $associations = [])
    {
        return array_diff_key(parent::getValidation($model, $associations), array_flip($this->_inaccessibleFields));
    }
}
