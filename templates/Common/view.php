<?php
/**
 * @var \Skeleton\View\AppView $this
 * @var \Cake\ORM\Entity $entity
 * @var string[] $associations
 * @var string[] $fields
 * @var string $className
 * @var string $displayField
 */
?>
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('Edit ' . \Cake\Utility\Inflector::classify($className)),
                ['action' => 'edit', $entity->id]) ?> </li>
        <li><?= $this->Form->postLink(__('Delete ' . \Cake\Utility\Inflector::classify($className)),
                ['action' => 'delete', $entity->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $entity->id)]) ?> </li>
        <?php $associations = [$className] + $associations; ?>
        <?php foreach ($associations as $association): ?>
            <?php $associationSpaceDelimited = implode(' ', preg_split('/(?=[A-Z])/', $association)); ?>
            <li><?= $this->Html->link(__("List $associationSpaceDelimited"),
                    ['controller' => $association, 'action' => 'index']) ?></li>
            <li><?= $this->Html->link(__("New $associationSpaceDelimited"),
                    ['controller' => $association, 'action' => 'add']) ?></li>
        <?php endforeach; ?>
    </ul>
</nav>
<div class="<?= lcfirst($className) ?> view large-9 medium-8 columns content">
    <h3><?= h($entity->$displayField) ?></h3>
    <table class="vertical-table">
        <?php foreach ($fields as $field): ?>
            <?php $value = $entity->get($field) ?>
            <?php if (!is_array($value)): ?>
                <tr>
                    <th scope="row"><?= __(\Cake\Utility\Inflector::humanize($field)) ?></th>
                    <?= $this->Utils->display($value) ?>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
    <?php foreach ($fields as $field): ?>
        <?php $associatedProperty = $entity->get($field); ?>
        <?php if (is_array($associatedProperty)): ?>
            <div class="related">
                <h4><?= __('Related ' . __(\Cake\Utility\Inflector::humanize($field))) ?></h4>
                <?php if (!empty($associatedProperty)): ?>
                    <table cellpadding="0" cellspacing="0">
                        <?php $associatedProperty = array_map(function ($value) {
                            return $value->toArray();
                        }, $associatedProperty) ?>
                        <tr>
                            <?php foreach (array_keys($associatedProperty[0]) as $key): ?>
                                <th scope="col"><?= __($key) ?></th>
                            <?php endforeach; ?>
                            <th scope="col" class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($associatedProperty as $property): ?>
                            <tr>
                                <?php foreach ($property as $val) {
                                    echo $this->Utils->display($val);
                                } ?>
                                <td class="actions">
                                    <?= $this->Html->link(__('View'),
                                        ['controller' => $field, 'action' => 'view', $property['id']]) ?>
                                    <?= $this->Html->link(__('Edit'),
                                        ['controller' => $field, 'action' => 'edit', $property['id']]) ?>
                                    <?= $this->Form->postLink(__('Delete'),
                                        ['controller' => $field, 'action' => 'delete', $property['id']],
                                        [
                                            'confirm' => __('Are you sure you want to delete # {0}?', $property['id'])
                                        ]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
