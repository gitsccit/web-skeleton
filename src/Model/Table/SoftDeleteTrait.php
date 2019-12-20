<?php
declare(strict_types=1);

namespace Skeleton\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;

trait SoftDeleteTrait
{
    protected $_softDeleteField = 'deleted_at';

    public function getSoftDeleteField()
    {
        return "$this->_alias.$this->_softDeleteField";
    }

    public function callFinder($type, Query $query, array $options = [])
    {
        $query->whereNull($this->getSoftDeleteField());
        return parent::callFinder($type, $query, $options);
    }

    /**
     * Perform the delete operation.
     *
     * Will soft delete the entity provided. Will remove rows from any
     * dependent associations, and clear out join tables for BelongsToMany associations.
     *
     * @param \Cake\DataSource\EntityInterface $entity The entity to delete.
     * @param \ArrayObject $options The options for the delete.
     * @throws \InvalidArgumentException if there are no primary key values of the
     * passed entity
     * @return bool success
     */
    public function _processDelete($entity, $options)
    {
        if ($entity->isNew()) {
            return false;
        }

        $primaryKey = (array)$this->getPrimaryKey();
        if (!$entity->has($primaryKey)) {
            $msg = 'Deleting requires all primary key values.';
            throw new \InvalidArgumentException($msg);
        }

        if ($options['checkRules'] && !$this->checkRules($entity, RulesChecker::DELETE, $options)) {
            return false;
        }

        $event = $this->dispatchEvent('Model.beforeDelete', [
            'entity' => $entity,
            'options' => $options
        ]);

        if ($event->isStopped()) {
            return $event->getResult();
        }

        $this->_associations->cascadeDelete(
            $entity,
            ['_primary' => false] + $options->getArrayCopy()
        );

        $query = $this->query();
        $conditions = (array)$entity->extract($primaryKey);
        $statement = $query->update()
            ->set([$this->getSoftDeleteField() => timestamp()])
            ->where($conditions)
            ->execute();

        $success = $statement->rowCount() > 0;
        if (!$success) {
            return $success;
        }

        $this->dispatchEvent('Model.afterDelete', [
            'entity' => $entity,
            'options' => $options
        ]);

        return $success;
    }

    public function deleteAll($conditions)
    {
        $query = $this->query()
            ->update()
            ->set([$this->getSoftDeleteField() => timestamp()])
            ->where($conditions);
        $statement = $query->execute();
        $statement->closeCursor();

        return $statement->rowCount();
    }

    /**
     * @param EntityInterface $entity
     * @return mixed
     */
    public function hardDelete(EntityInterface $entity)
    {
        return parent::delete($entity);
    }

    /**
     * Hard deletes all records matching the provided conditions.
     * @param array $conditions Conditions to be used, accepts anything Query::where() can take.
     * @param bool $onlySoftDeleted Only deletes the records that were soft deleted.
     * @return int number of affected rows.
     */
    public function hardDeleteAll($conditions, $onlySoftDeleted = true)
    {
        if ($onlySoftDeleted) {
            $conditions[] = $this->getSoftDeleteField() . ' IS NOT NULL';
        }

        return parent::deleteAll($conditions);
    }

    /**
     * Restore a soft deleted entity into an active state.
     * @param EntityInterface $entity Entity to be restored.
     * @return bool true in case of success, false otherwise.
     */
    public function restore(EntityInterface $entity)
    {
        $entity->set($this->_softDeleteField, null);
        return $this->save($entity);
    }
}
